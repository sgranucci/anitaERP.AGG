<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\TiendanubePedido;
use App\Models\Ventas\TiendanubePedidoVenta;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Decide si el PDF de factura incluye la hoja remito (Ferli / El Bierzo).
 *
 * Ferli local y Tienda Nube: solo factura (sin hoja remito).
 * Ferli mayorista: hoja remito solo si la venta tiene remito asociado.
 * El Bierzo: mantiene el comportamiento histórico (hoja remito salvo omitir explícito).
 */
final class FacturaPdfHojaRemitoSupport
{
    /**
     * @param  object{id?: int|null, numeroremito?: int|string|null, remito_id?: int|string|null}  $venta
     */
    public static function mostrarParaVenta(
        object $venta,
        bool $omitirFlag,
        bool $soloRemito,
        bool $esElBierzo,
        bool $esFerli,
    ): bool {
        return self::mostrar(
            $venta,
            $omitirFlag,
            $soloRemito,
            $esElBierzo,
            $esFerli,
            self::esCanalSinHojaRemito((int) ($venta->id ?? 0)),
        );
    }

    /**
     * Decisión pura (sin BD). `$omitirPorCanal` = Facturación Local / Tienda Nube.
     *
     * @param  object{numeroremito?: int|string|null, remito_id?: int|string|null}  $venta
     */
    public static function mostrar(
        object $venta,
        bool $omitirFlag,
        bool $soloRemito,
        bool $esElBierzo,
        bool $esFerli,
        bool $omitirPorCanal = false,
    ): bool {
        if ($soloRemito) {
            return true;
        }
        if ($omitirFlag || $omitirPorCanal) {
            return false;
        }
        if ($esElBierzo) {
            return true;
        }
        if (! $esFerli) {
            return false;
        }

        return self::tieneRemitoAsociado($venta);
    }

    /**
     * Local POS y Tienda Nube Ferli: el PDF de factura no lleva hoja remito.
     */
    public static function esCanalSinHojaRemito(int $ventaId): bool
    {
        if ($ventaId <= 0 || ! EntornoEmpresaSupport::esFerli()) {
            return false;
        }

        if (FacturacionLocalEmision::query()
            ->where(function ($q) use ($ventaId) {
                $q->where('venta_id', $ventaId)
                    ->orWhere('venta_nc_id', $ventaId);
            })
            ->exists()) {
            return true;
        }

        if (TiendanubePedidoVenta::query()->where('venta_id', $ventaId)->exists()) {
            return true;
        }

        return TiendanubePedido::query()->where('venta_id', $ventaId)->exists();
    }

    /**
     * @param  object{numeroremito?: int|string|null, remito_id?: int|string|null}  $venta
     */
    public static function tieneRemitoAsociado(object $venta): bool
    {
        return ((int) ($venta->numeroremito ?? 0) > 0)
            || ((int) ($venta->remito_id ?? 0) > 0);
    }
}
