<?php

declare(strict_types=1);

namespace App\Support\Caja\Flash;

/**
 * Fórmulas de drop/win de slots y ruletas del Flash.
 *
 * Slots: en lugar de ventas tickets (slots+caja) e impuesto venta, entra
 * «Venta de fichas (slots)» de la rendición de máquinas turno C
 * (fallback: VentaTickets de sesión Wigos M+T+N). Se resta impuesto drop.
 * Ruletas: solo bill; no entran ventas tickets de ruleta.
 */
final class FlashCajaDropWinFormulaSupport
{
    public const SLOT_D = 'BillSlots + VentaFichas(rendición C) + MontoNetoQR − ImpuestoDrop';

    public const SLOT_R = 'BillSlots + VentaFichas(rendición C) + MontoNetoQR − PagosSlots − PagosCaja − PagosManuales(M+T+N) − ImpuestoDrop';

    public const RUL_D = 'BillRul';

    public const RUL_R = 'BillRul − PagosRuletas';

    public const SLOT_D_LARGA = 'BillSlots(bruto) + VentaFichas(rendición C) + MontoNetoQR − ImpDrop (turno C)';

    public const SLOT_R_LARGA = 'BillSlots(bruto) + VentaFichas(rendición C) + MontoNetoQR − PagosSlots − PagosCaja − PagosManuales(M+T+N) − ImpDrop (turno C)';

    public const RUL_D_LARGA = 'BillRul(bruto)';

    public const RUL_R_LARGA = 'BillRul(bruto) − PagosRuletas';

    public static function slotD(
        float $billSlots,
        float $ventaFicha,
        float $montoNetoQr,
        float $impuestoDrop,
    ): float {
        return round($billSlots + $ventaFicha + $montoNetoQr - $impuestoDrop, 2);
    }

    public static function slotR(
        float $billSlots,
        float $ventaFicha,
        float $montoNetoQr,
        float $pagosSlots,
        float $pagosCaja,
        float $pagosManuales,
        float $impuestoDrop,
    ): float {
        return round(
            $billSlots + $ventaFicha + $montoNetoQr - $pagosSlots - $pagosCaja - $pagosManuales - $impuestoDrop,
            2
        );
    }

    public static function rulD(float $billRul): float
    {
        return round($billRul, 2);
    }

    public static function rulR(float $billRul, float $pagosRuletas): float
    {
        return round($billRul - $pagosRuletas, 2);
    }

    /**
     * Preferencia: venta_ficha del turno C (Anita/ERP). Si no hay C, sesión Wigos.
     *
     * @param  array{origen?: string, venta_ficha?: float}  $rendicion
     */
    public static function resolverVentaFicha(array $rendicion, float $ventaFichaWigos): float
    {
        $origen = (string) ($rendicion['origen'] ?? 'ninguno');
        if ($origen !== '' && $origen !== 'ninguno') {
            return round((float) ($rendicion['venta_ficha'] ?? 0), 2);
        }

        return round($ventaFichaWigos, 2);
    }
}
