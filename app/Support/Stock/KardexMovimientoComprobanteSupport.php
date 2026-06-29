<?php

namespace App\Support\Stock;

final class KardexMovimientoComprobanteSupport
{
    private static ?bool $puedeAbrirFactura = null;

    private static ?bool $puedeAbrirMovimientoStock = null;

    public static function puedeAbrirFactura(): bool
    {
        return self::$puedeAbrirFactura ??= (
            can('editar-factura', false) || can('listar-factura', false)
        );
    }

    public static function puedeAbrirMovimientoStock(): bool
    {
        return self::$puedeAbrirMovimientoStock ??= (
            can('listar-movimientos-de-stock', false)
            || can('editar-movimientos-de-stock', false)
        );
    }

    /**
     * @return array<string, string>
     */
    public static function queryConsultaAbm(): array
    {
        return [
            'origen' => 'modal_consulta',
            'vista' => 'consulta',
        ];
    }

    public static function urlFactura(int $ventaId): ?string
    {
        if ($ventaId <= 0 || ! self::puedeAbrirFactura()) {
            return null;
        }

        return route('editar_factura', array_merge(['id' => $ventaId], self::queryConsultaAbm()));
    }

    public static function urlMovimientoStock(int $movimientostockId): ?string
    {
        if ($movimientostockId <= 0 || ! self::puedeAbrirMovimientoStock()) {
            return null;
        }

        return route('editar_movimientostock', array_merge(['id' => $movimientostockId], self::queryConsultaAbm()));
    }

    public static function enriquecerFila(object $row): object
    {
        $row->url_factura = self::urlFactura((int) ($row->venta_id ?? 0));
        $row->url_movimientostock = self::urlMovimientoStock((int) ($row->movimientostock_id ?? 0));

        return $row;
    }

    public static function resetCachePermisos(): void
    {
        self::$puedeAbrirFactura = null;
        self::$puedeAbrirMovimientoStock = null;
    }
}
