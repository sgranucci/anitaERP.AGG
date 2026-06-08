<?php

namespace App\Support\Ventas\Gastronomia;

use App\Support\Ventas\Waitry\WaitryOrdenEstadoSupport;

/**
 * Cuadro de cierre: Anita facturado + Waitry pendiente / impago + efectivo no facturable.
 */
final class CierreJornadaProcesoGrillaSupport
{
    /**
     * @param  list<array<string, mixed>>  $movimientos  Movimientos ya enriquecidos (clasificar)
     * @param  array<string, mixed>  $totalesAnita  Salida de CierreJornadaFacturadoAnitaSupport
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   columnas_medios: list<array{id:string,cuentacaja_id:int,codigo:string,nombre:string,etiqueta:string}>,
     *   grilla: array<string, float>,
     *   total_facturacion: float,
     *   total_pendiente_facturar: float,
     *   total_impago_waitry: float,
     *   total_cuadro: float
     * }
     */
    public static function armar(array $movimientos, array $totalesAnita, int $empresaId = 0): array
    {
        $anitaJornada = self::normalizarFilaMedios(
            is_array($totalesAnita['anita_jornada'] ?? null)
                ? $totalesAnita['anita_jornada']
                : $totalesAnita,
        );
        $anitaTotem = self::normalizarFilaMedios(
            is_array($totalesAnita['anita_totem'] ?? null)
                ? $totalesAnita['anita_totem']
                : self::filaVacia(
                    'Facturado Anita — cobro TOTEM (medio real Waitry)',
                    'anita_totem',
                ),
        );
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

            if (WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotalLinea($mov)) {
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

            $planificados = $mov['medios_pago_planificados'] ?? null;
            if (is_array($planificados) && $planificados !== []) {
                foreach ($planificados as $parte) {
                    $montoParte = CierreJornadaProcesoRedistribucionSupport::pesos((float) ($parte['monto'] ?? 0));
                    if ($montoParte <= 0.0001) {
                        continue;
                    }
                    $colPlan = self::columnaDesdeClave((string) ($parte['clave'] ?? ''));
                    if (self::cobradaEnWaitry($mov)) {
                        $waitryPagoSinFact[$colPlan] = CierreJornadaProcesoRedistribucionSupport::pesos(
                            $waitryPagoSinFact[$colPlan] + $montoParte,
                        );
                    } else {
                        $waitryImpago[$colPlan] = CierreJornadaProcesoRedistribucionSupport::pesos(
                            $waitryImpago[$colPlan] + $montoParte,
                        );
                    }
                }
                continue;
            }

            $col = self::columnaDesdeClave((string) ($mov['medio_waitry_clave'] ?? CierreJornadaProcesoMedioSupport::CLAVE_OTRO));
            if (self::cobradaEnWaitry($mov)) {
                $waitryPagoSinFact[$col] = round($waitryPagoSinFact[$col] + $total, 2);
            } else {
                $waitryImpago[$col] = round($waitryImpago[$col] + $total, 2);
            }
        }

        $anitaJornada = self::cerrarFila($anitaJornada);

        $waitryPagoSinFact = self::cerrarFila($waitryPagoSinFact);
        $waitryImpago = self::cerrarFila($waitryImpago);
        $waitryCash = self::cerrarFila($waitryCash);

        $totalPendiente = $waitryPagoSinFact['total'];
        $totalImpago = $waitryImpago['total'];
        $totalFacturacion = round((float) ($totalesAnita['total'] ?? $anitaJornada['total'] ?? 0), 2);
        $totalAnitaJornadaCuadro = round((float) ($anitaJornada['total'] ?? 0), 2);
        $totalAnitaTotemCuadro = round((float) ($anitaTotem['total'] ?? 0), 2);
        $totalAnitaSinWaitry = round((float) ($totalesAnita['anita_sin_waitry']['total'] ?? $totalesAnita['total_sin_waitry'] ?? 0), 2);
        $totalNotasCredito = round((float) ($totalesAnita['total_notas_credito'] ?? 0), 2);
        $totalCuadro = round($totalFacturacion + $totalPendiente + $totalImpago, 2);

        $grilla = [
            'anita_qr' => $anitaJornada['qr'],
            'anita_mp' => $anitaJornada['mp'],
            'anita_efectivo' => $anitaJornada['efectivo'],
            'anita_otros' => $anitaJornada['otros'],
            'anita_totem_qr' => $anitaTotem['qr'],
            'anita_totem_mp' => $anitaTotem['mp'],
            'anita_totem_efectivo' => $anitaTotem['efectivo'],
            'anita_totem_otros' => $anitaTotem['otros'],
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
            'total_anita_jornada' => $totalAnitaJornadaCuadro,
            'total_anita_totem_cuadro' => $totalAnitaTotemCuadro,
            'total_anita_sin_waitry' => $totalAnitaSinWaitry,
            'total_notas_credito' => $totalNotasCredito,
            'total_pendiente_facturar' => $totalPendiente,
            'total_impago_waitry' => $totalImpago,
            'total_cuadro' => $totalCuadro,
        ];

        $filas = [
            $anitaJornada,
            $anitaTotem,
            $waitryPagoSinFact,
            $waitryImpago,
            $waitryCash,
        ];
        $enriquecido = CierreJornadaCuadroColumnasSupport::enriquecerFilas($filas, $empresaId);

        return [
            'filas' => $enriquecido['filas'],
            'columnas_medios' => $enriquecido['columnas'],
            'grilla' => $grilla,
            'total_facturacion' => $totalFacturacion,
            'total_anita_jornada_cuadro' => $totalAnitaJornadaCuadro,
            'total_anita_totem_cuadro' => $totalAnitaTotemCuadro,
            'total_anita_sin_waitry' => $totalAnitaSinWaitry,
            'total_notas_credito' => $totalNotasCredito,
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
            'diferencia_caja' => 0.0,
            'total' => 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private static function cerrarFila(array $fila): array
    {
        foreach (['qr', 'mp', 'efectivo', 'otros', 'diferencia_caja'] as $k) {
            $fila[$k] = round((float) ($fila[$k] ?? 0), 2);
        }
        $fila['total'] = round(
            $fila['qr'] + $fila['mp'] + $fila['efectivo'] + $fila['otros'] + $fila['diferencia_caja'],
            2,
        );

        return $fila;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private static function normalizarFilaMedios(array $fila): array
    {
        $base = self::filaVacia('Facturado Anita (jornada)', 'anita_jornada');
        foreach (['qr', 'mp', 'efectivo', 'otros', 'diferencia_caja'] as $k) {
            if (isset($fila[$k])) {
                $base[$k] = (float) $fila[$k];
            }
        }
        if (isset($fila['etiqueta'])) {
            $base['etiqueta'] = (string) $fila['etiqueta'];
        }
        if (isset($fila['tipo'])) {
            $base['tipo'] = (string) $fila['tipo'];
        }
        if (isset($fila['por_cuenta']) && is_array($fila['por_cuenta'])) {
            $base['por_cuenta'] = $fila['por_cuenta'];
        }

        return self::cerrarFila($base);
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
