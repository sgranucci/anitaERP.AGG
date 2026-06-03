<?php

namespace App\Support\Ventas\Gastronomia;

/**
 * Cuadro de cierre: Anita facturado + Waitry pendiente / impago + efectivo no facturable.
 */
final class CierreJornadaProcesoGrillaSupport
{
    /**
     * @param  list<array<string, mixed>>  $movimientos  Movimientos ya enriquecidos (clasificar)
     * @param  array{qr:float,mp:float,efectivo:float,otros:float,total:float}  $anitaJornada
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   grilla: array<string, float>,
     *   total_facturacion: float,
     *   total_pendiente_facturar: float,
     *   total_impago_waitry: float,
     *   total_cuadro: float
     * }
     */
    public static function armar(array $movimientos, array $anitaJornada): array
    {
        $anitaJornada = self::normalizarFilaMedios($anitaJornada);

        $waitryPagoSinFact = self::filaVacia('Waitry pagado sin facturar (a facturar)', 'waitry_pago');
        $waitryImpago = self::filaVacia('Waitry impago (referencia)', 'waitry_impago');
        $waitryCash = self::filaVacia('Efectivo Waitry — no se factura', 'waitry_cash');

        foreach ($movimientos as $mov) {
            if (! empty($mov['discrepancia_gap'])) {
                continue;
            }
            if (! empty($mov['facturada_erp'])) {
                continue;
            }

            $total = round((float) ($mov['total'] ?? 0), 2);
            if ($total <= 0.0001) {
                continue;
            }

            $waitryTipo = $mov['waitry_tipo_pago'] ?? null;
            if (CierreJornadaProcesoMedioSupport::esWaitryCash($waitryTipo)) {
                $waitryCash['efectivo'] = round($waitryCash['efectivo'] + $total, 2);
                continue;
            }

            $col = self::columnaDesdeClave((string) ($mov['medio_waitry_clave'] ?? CierreJornadaProcesoMedioSupport::CLAVE_OTRO));
            if (self::cobradaEnWaitry($mov)) {
                $waitryPagoSinFact[$col] = round($waitryPagoSinFact[$col] + $total, 2);
            } else {
                $waitryImpago[$col] = round($waitryImpago[$col] + $total, 2);
            }
        }

        $waitryPagoSinFact = self::cerrarFila($waitryPagoSinFact);
        $waitryImpago = self::cerrarFila($waitryImpago);
        $waitryCash = self::cerrarFila($waitryCash);

        $totalPendiente = $waitryPagoSinFact['total'];
        $totalImpago = $waitryImpago['total'];
        $totalFacturacion = $anitaJornada['total'];
        $totalCuadro = round($totalFacturacion + $totalPendiente + $totalImpago, 2);

        $grilla = [
            'anita_qr' => $anitaJornada['qr'],
            'anita_mp' => $anitaJornada['mp'],
            'anita_efectivo' => $anitaJornada['efectivo'],
            'anita_otros' => $anitaJornada['otros'],
            'waitry_pago_qr' => $waitryPagoSinFact['qr'],
            'waitry_pago_mp' => $waitryPagoSinFact['mp'],
            'waitry_pago_efectivo' => $waitryPagoSinFact['efectivo'],
            'waitry_pago_otros' => $waitryPagoSinFact['otros'],
            'waitry_impago_qr' => $waitryImpago['qr'],
            'waitry_impago_mp' => $waitryImpago['mp'],
            'waitry_impago_efectivo' => $waitryImpago['efectivo'],
            'waitry_impago_otros' => $waitryImpago['otros'],
            'waitry_cash_no_facturar' => $waitryCash['efectivo'],
            // Claves legacy (redistribución / compat.)
            'qr_facturado_anita' => $anitaJornada['qr'],
            'mp_facturado_anita' => $anitaJornada['mp'],
            'efectivo_facturado_anita' => $anitaJornada['efectivo'],
            'qr_sin_facturar' => $waitryPagoSinFact['qr'],
            'cobrado_waitry_sin_facturar' => $totalPendiente,
            'efectivo_waitry' => $waitryCash['efectivo'],
            'total_anita_jornada' => $totalFacturacion,
            'total_pendiente_facturar' => $totalPendiente,
            'total_impago_waitry' => $totalImpago,
            'total_cuadro' => $totalCuadro,
        ];

        return [
            'filas' => [
                $anitaJornada,
                $waitryPagoSinFact,
                $waitryImpago,
                $waitryCash,
            ],
            'grilla' => $grilla,
            'total_facturacion' => $totalFacturacion,
            'total_pendiente_facturar' => $totalPendiente,
            'total_impago_waitry' => $totalImpago,
            'total_cuadro' => $totalCuadro,
        ];
    }

    /**
     * @return array{qr:float,mp:float,efectivo:float,otros:float,total:float,etiqueta:string,tipo:string}
     */
    public static function filaVacia(string $etiqueta, string $tipo): array
    {
        return [
            'etiqueta' => $etiqueta,
            'tipo' => $tipo,
            'qr' => 0.0,
            'mp' => 0.0,
            'efectivo' => 0.0,
            'otros' => 0.0,
            'total' => 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private static function cerrarFila(array $fila): array
    {
        foreach (['qr', 'mp', 'efectivo', 'otros'] as $k) {
            $fila[$k] = round((float) ($fila[$k] ?? 0), 2);
        }
        $fila['total'] = round($fila['qr'] + $fila['mp'] + $fila['efectivo'] + $fila['otros'], 2);

        return $fila;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private static function normalizarFilaMedios(array $fila): array
    {
        $base = self::filaVacia('Facturado Anita (jornada)', 'anita_jornada');
        foreach (['qr', 'mp', 'efectivo', 'otros'] as $k) {
            if (isset($fila[$k])) {
                $base[$k] = (float) $fila[$k];
            }
        }
        if (isset($fila['etiqueta'])) {
            $base['etiqueta'] = (string) $fila['etiqueta'];
        }

        $base = self::cerrarFila($base);
        if (array_key_exists('total', $fila)) {
            $base['total'] = round((float) $fila['total'], 2);
        }

        return $base;
    }

    private static function columnaDesdeClave(string $clave): string
    {
        return match ($clave) {
            CierreJornadaProcesoMedioSupport::CLAVE_QR => 'qr',
            CierreJornadaProcesoMedioSupport::CLAVE_MP => 'mp',
            CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO => 'efectivo',
            default => 'otros',
        };
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private static function cobradaEnWaitry(array $mov): bool
    {
        if (! empty($mov['waitry_cobro_totem'])) {
            return true;
        }
        if (($mov['paid_waitry'] ?? null) === true) {
            return true;
        }

        return (float) ($mov['monto_cobro_waitry'] ?? 0) > 0.0001;
    }
}
