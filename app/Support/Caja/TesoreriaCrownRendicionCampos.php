<?php

namespace App\Support\Caja;

/**
 * Importes de la rendición que Anita pide después del efectivo y las fichas.
 * Gastronomía y hotel: carga_get3 de a-transfefe (informe X y Z).
 * Medios / valores: tarjetas y QR (gastro compacto); sala completa en otras tareas.
 */
final class TesoreriaCrownRendicionCampos
{
    /**
     * @return array<string, array{titulo: string, ayuda: string, campos: array<string, array{label: string, tipo: string, negativo: bool, solo_historico?: bool}>}>
     */
    public static function grupos(): array
    {
        return [
            'gastro' => [
                'titulo' => 'Gastronomía y hotel',
                'ayuda' => 'X: cierre de mozo gastronomía (POS) o informe X. Z: cierre fiscal. Se completan con «Aplicar a la rendición» o PXSol.',
                'campos' => [
                    'total_x' => ['label' => 'Ventas / X', 'tipo' => 'importe', 'negativo' => false],
                    'invitacion' => ['label' => 'Invitaciones', 'tipo' => 'importe', 'negativo' => false],
                    'total_z' => ['label' => 'Total informe Z', 'tipo' => 'importe', 'negativo' => false],
                    // a-transfefe pedía nro. Z: ya no se carga en pantalla.
                    'nro_z' => ['label' => 'Nro. informe Z', 'tipo' => 'texto', 'negativo' => false, 'solo_historico' => true],
                    'ult_ticket' => ['label' => 'Último ticket del Z', 'tipo' => 'entero', 'negativo' => false],
                    'total_nc' => ['label' => 'Notas de crédito', 'tipo' => 'importe', 'negativo' => false],
                    'fallo_mozo' => ['label' => 'Fallo de mozo', 'tipo' => 'importe', 'negativo' => false],
                ],
            ],
            'medios' => [
                'titulo' => 'Rendición de variables',
                'ayuda' => 'Misma pantalla que Rendición de variables. En sala el recargo sale de la tarjeta de crédito: desde el 8/11/2024 es el crédito menos el crédito dividido 1,05.',
                'campos' => [
                    'nro_z_cash' => ['label' => 'Nro. de cierre Z cashless', 'tipo' => 'texto', 'negativo' => false, 'solo_historico' => true],
                    'pago_cash' => ['label' => 'Monto de pagos Z cashless', 'tipo' => 'importe', 'negativo' => true],
                    'venta_cash' => ['label' => 'Monto de ventas cashless', 'tipo' => 'importe', 'negativo' => true],
                    'pago_parcial' => ['label' => 'Total pagos parcial', 'tipo' => 'importe', 'negativo' => true],
                    'vta_apuesta' => ['label' => 'Venta apuestas deportivas', 'tipo' => 'importe', 'negativo' => false],
                    'pago_apuesta' => ['label' => 'Pago apuestas deportivas', 'tipo' => 'importe', 'negativo' => false],
                    'venta_online' => ['label' => 'Neto casino on-line', 'tipo' => 'importe', 'negativo' => true],
                    'pago_online' => ['label' => 'Pagos de casino on-line', 'tipo' => 'importe', 'negativo' => true, 'solo_historico' => true],
                    'tarjeta_debito' => ['label' => 'Tarjeta de débito', 'tipo' => 'importe', 'negativo' => true],
                    'tarjeta_credito' => ['label' => 'Tarjeta de crédito', 'tipo' => 'importe', 'negativo' => true],
                    'recargo_fin' => ['label' => 'Recargo financiación', 'tipo' => 'importe', 'negativo' => false],
                    'ctacte_hotel' => ['label' => 'Cuenta corriente hotel', 'tipo' => 'importe', 'negativo' => true],
                    'transferencia_hotel' => ['label' => 'Transferencia hotel', 'tipo' => 'importe', 'negativo' => true],
                    'venta_voucher' => ['label' => 'Venta vouchers de juego', 'tipo' => 'importe', 'negativo' => false, 'solo_historico' => true],
                    'pago_voucher' => ['label' => 'Pago vouchers de juego', 'tipo' => 'importe', 'negativo' => false, 'solo_historico' => true],
                    'total_qr' => ['label' => 'Total QR', 'tipo' => 'importe', 'negativo' => false],
                ],
            ],
        ];
    }

    /**
     * Valores compactos para gastronomía/hotel: solo tarjetas, recargo, QR y (hotel) cta.cte.
     *
     * @return array{titulo: string, ayuda: string, campos: array<string, array{label: string, tipo: string, negativo: bool}>}
     */
    public static function valoresCompactosGastroHotel(?string $tipoTarea): array
    {
        $campos = [
            'tarjeta_credito' => ['label' => 'Tarjeta de crédito', 'tipo' => 'importe', 'negativo' => true],
            'tarjeta_debito' => ['label' => 'Tarjeta de débito', 'tipo' => 'importe', 'negativo' => true],
            'recargo_fin' => ['label' => 'Recargo financiación', 'tipo' => 'importe', 'negativo' => false],
            'total_qr' => ['label' => 'Total QR', 'tipo' => 'importe', 'negativo' => false],
        ];
        if ($tipoTarea === 'H') {
            $campos['ctacte_hotel'] = ['label' => 'Cuenta corriente hotel', 'tipo' => 'importe', 'negativo' => true];
            $campos['transferencia_hotel'] = ['label' => 'Transferencia hotel', 'tipo' => 'importe', 'negativo' => true];
        }

        return [
            'titulo' => 'Valores',
            'ayuda' => 'Tarjetas y QR. El resto de variables de sala no se carga en gastronomía/hotel.',
            'campos' => $campos,
        ];
    }

    /**
     * @return array<string, array{label: string, tipo: string, negativo: bool}>
     */
    public static function campos(): array
    {
        $out = [];
        foreach (self::grupos() as $grupo) {
            foreach ($grupo['campos'] as $clave => $meta) {
                $out[$clave] = $meta;
            }
        }

        return $out;
    }

    public static function esCajaGastroHotel(?string $tipoTarea): bool
    {
        return in_array($tipoTarea, ['G', 'H'], true);
    }

    /**
     * @return array<string, array{titulo: string, ayuda: string, campos: array<string, array{label: string, tipo: string, negativo: bool}>}>
     */
    public static function gruposParaTarea(?string $tipoTarea): array
    {
        $grupos = self::grupos();
        if (! self::esCajaGastroHotel($tipoTarea)) {
            unset($grupos['gastro']);
        } else {
            $grupos['medios'] = self::valoresCompactosGastroHotel($tipoTarea);
        }
        foreach ($grupos as $clave => $grupo) {
            $grupos[$clave]['campos'] = array_filter(
                $grupo['campos'],
                static fn (array $meta): bool => empty($meta['solo_historico'])
            );
        }

        return $grupos;
    }

    /**
     * @return array<string, array{label: string, tipo: string, negativo: bool}>
     */
    public static function camposDe(?string $tipoTarea): array
    {
        $out = [];
        foreach (self::gruposParaTarea($tipoTarea) as $grupo) {
            foreach ($grupo['campos'] as $clave => $meta) {
                $out[$clave] = $meta;
            }
        }

        return $out;
    }

    /**
     * Hay algún importe/texto de medios cargado (sirve para pedir firmas sin billetes).
     */
    public static function tieneMediosCargados(object $comprobante, ?string $tipoTarea): bool
    {
        foreach (self::camposDe($tipoTarea) as $clave => $meta) {
            $valor = $comprobante->{$clave} ?? null;
            if ($meta['tipo'] === 'texto') {
                if (trim((string) $valor) !== '') {
                    return true;
                }
                continue;
            }
            if ($valor !== null && $valor !== '' && (float) $valor != 0.0) {
                return true;
            }
        }

        return false;
    }
}
