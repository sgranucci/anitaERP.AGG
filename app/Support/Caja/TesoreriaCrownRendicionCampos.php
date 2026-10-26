<?php

namespace App\Support\Caja;

/**
 * Importes de la rendición que Anita pide después del efectivo y las fichas.
 * Gastronomía y hotel: carga_get3 de a-transfefe (informe X y Z).
 * Medios: carga_get5, la última pantalla de valores (cashless, tarjetas, hotel, QR).
 */
final class TesoreriaCrownRendicionCampos
{
    /**
     * @return array<string, array{titulo: string, ayuda: string, campos: array<string, array{label: string, tipo: string, negativo: bool}>}>
     */
    public static function grupos(): array
    {
        return [
            'gastro' => [
                'titulo' => 'Gastronomía y hotel',
                'ayuda' => 'Informe X, cierre Z e impuestos. Anita los pide solo si la caja se abrió como gastronomía u hotel.',
                'campos' => [
                    'total_x' => ['label' => 'Ventas / X', 'tipo' => 'importe', 'negativo' => false],
                    'invitacion' => ['label' => 'Invitaciones', 'tipo' => 'importe', 'negativo' => false],
                    'total_z' => ['label' => 'Total informe Z', 'tipo' => 'importe', 'negativo' => false],
                    'nro_z' => ['label' => 'Nro. informe Z', 'tipo' => 'texto', 'negativo' => false],
                    'ult_ticket' => ['label' => 'Último ticket del Z', 'tipo' => 'entero', 'negativo' => false],
                    'total_nc' => ['label' => 'Notas de crédito', 'tipo' => 'importe', 'negativo' => false],
                    'iva_facturas' => ['label' => 'IVA facturas', 'tipo' => 'importe', 'negativo' => false],
                    'iva_nc' => ['label' => 'IVA notas de crédito', 'tipo' => 'importe', 'negativo' => false],
                ],
            ],
            'medios' => [
                'titulo' => 'Cashless, tarjetas y hotel',
                'ayuda' => 'Última pantalla de la rendición en Anita: cashless, apuestas, tarjetas, hotel, vouchers y QR.',
                'campos' => [
                    'nro_z_cash' => ['label' => 'Nro. cierre Z cashless', 'tipo' => 'texto', 'negativo' => false],
                    'venta_cash' => ['label' => 'Neto cashless', 'tipo' => 'importe', 'negativo' => true],
                    'vta_apuesta' => ['label' => 'Apuestas deportivas', 'tipo' => 'importe', 'negativo' => false],
                    'venta_online' => ['label' => 'Casino online', 'tipo' => 'importe', 'negativo' => true],
                    'tarjeta_debito' => ['label' => 'Tarjeta de débito', 'tipo' => 'importe', 'negativo' => true],
                    'tarjeta_credito' => ['label' => 'Tarjeta de crédito', 'tipo' => 'importe', 'negativo' => true],
                    'recargo_fin' => ['label' => 'Recargo financiación', 'tipo' => 'importe', 'negativo' => false],
                    'ctacte_hotel' => ['label' => 'Cuenta corriente hotel', 'tipo' => 'importe', 'negativo' => true],
                    'transferencia_hotel' => ['label' => 'Transferencia hotel', 'tipo' => 'importe', 'negativo' => true],
                    'venta_voucher' => ['label' => 'Venta vouchers de juego', 'tipo' => 'importe', 'negativo' => false],
                    'pago_voucher' => ['label' => 'Pago vouchers de juego', 'tipo' => 'importe', 'negativo' => false],
                    'total_qr' => ['label' => 'Total QR', 'tipo' => 'importe', 'negativo' => false],
                ],
            ],
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
}
