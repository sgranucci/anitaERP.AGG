<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use Illuminate\Support\Collection;

/**
 * Detalle de factura Facturación Local: cobranzas e ítems desde venta_emision.
 */
final class FacturacionLocalVentaDetalleSupport
{
    /**
     * @return Collection<int, \App\Models\Caja\Cobranza>
     */
    public static function cobranzasDeVenta(Venta $venta): Collection
    {
        return GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
    }

    /**
     * @param  Collection<int, \App\Models\Caja\Cobranza>  $cobranzas
     * @return array<int, list<object{cuentacaja_id?:int, codigo?:string, nombre?:string, cuenta?:string, monto?:float}>>
     */
    public static function mediosPagoPorCobranza(Collection $cobranzas): array
    {
        return GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
    }

    /**
     * Ítems facturados desde venta_emision (artículos Local).
     *
     * @return Collection<int, object{
     *   venta_emision_id:int,
     *   articulo_id:int,
     *   codigo:string,
     *   detalle:string,
     *   cantidad:float,
     *   precio:float,
     *   descuento:float
     * }>
     */
    public static function itemsFacturadosParaDetalle(Venta $venta): Collection
    {
        $venta->loadMissing(['venta_emisiones.articulos']);

        return $venta->venta_emisiones
            ->sortBy('numeroitem')
            ->values()
            ->map(function (Venta_Emision $em) {
                $articulo = $em->articulos;
                $sku = trim((string) ($articulo->sku ?? ''));
                $detalle = trim((string) ($em->detalle ?? ''));
                if ($detalle === '' && $articulo) {
                    $detalle = trim((string) ($articulo->descripcion ?? ''));
                }

                return (object) [
                    'venta_emision_id' => (int) $em->id,
                    'articulo_id' => (int) ($em->articulo_id ?? 0),
                    'codigo' => $sku !== '' ? $sku : (string) ($em->articulo_id ?? ''),
                    'detalle' => $detalle !== '' ? $detalle : 'Ítem',
                    'cantidad' => (float) ($em->cantidad ?? 0),
                    'precio' => (float) ($em->precio ?? 0),
                    'descuento' => (float) ($em->descuento ?? 0),
                ];
            });
    }
}
