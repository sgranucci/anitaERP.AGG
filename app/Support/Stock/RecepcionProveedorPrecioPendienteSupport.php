<?php

namespace App\Support\Stock;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Models\Stock\Recepcion_Proveedor_Articulo;

/**
 * Circuito recepción borrador con precio distinto a OC sin permiso de modificar precio.
 */
final class RecepcionProveedorPrecioPendienteSupport
{
    public static function puedeModificarPrecioEnRecepcion(): bool
    {
        return can('modificar-precio-recepcion-proveedor', false);
    }

    public static function puedeModificarPrecioEnOrdencompra(): bool
    {
        return can('modificar-precio-ordencompra', false);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function normalizarItemsSegunPermiso(array $items, bool $puedeModificarPrecio): array
    {
        if ($puedeModificarPrecio) {
            return $items;
        }

        $normalizados = [];
        foreach ($items as $item) {
            $precioOc = (float) ($item['precio_ordencompra'] ?? $item['precio'] ?? 0);
            $precioEnviado = (float) ($item['precio'] ?? 0);
            $precioSolicitado = isset($item['precio_solicitado']) && $item['precio_solicitado'] !== ''
                ? (float) $item['precio_solicitado']
                : null;

            if ($precioSolicitado === null && $precioOc > 0 && abs($precioEnviado - $precioOc) >= 0.0001) {
                $precioSolicitado = $precioEnviado;
            }

            $item['precio'] = $precioOc > 0 ? $precioOc : $precioEnviado;
            $item['precio_solicitado'] = $precioSolicitado;

            $tieneSolicitud = $precioSolicitado !== null
                && $precioOc > 0
                && abs($precioSolicitado - $precioOc) >= 0.0001;

            $item['fl_precio_diferencia'] = $tieneSolicitud;
            if ($tieneSolicitud && trim((string) ($item['comentario_precio'] ?? '')) === '') {
                throw new \RuntimeException(
                    'Indique el motivo de la diferencia de precio respecto a la OC (línea con precio solicitado distinto).'
                );
            }

            $normalizados[] = $item;
        }

        return $normalizados;
    }

    /**
     * @param  iterable<int, Recepcion_Proveedor_Articulo|object>  $lineas
     */
    public static function recepcionTienePrecioSolicitadoPendiente(iterable $lineas): bool
    {
        foreach ($lineas as $linea) {
            if (self::lineaTienePrecioSolicitadoPendiente($linea)) {
                return true;
            }
        }

        return false;
    }

    public static function lineaTienePrecioSolicitadoPendiente(object $linea): bool
    {
        if ((string) ($linea->tipo_linea ?? RecepcionProveedorDiferenciaSupport::TIPO_OC) === RecepcionProveedorDiferenciaSupport::TIPO_EXTRA) {
            return false;
        }

        $precioOc = (float) ($linea->precio_ordencompra ?? 0);
        $precioSolicitado = $linea->precio_solicitado !== null
            ? (float) $linea->precio_solicitado
            : null;

        if ($precioSolicitado === null) {
            return false;
        }

        return $precioOc > 0 && abs($precioSolicitado - $precioOc) >= 0.0001;
    }

    public static function ocCoincideConPreciosSolicitados(Ordencompra $oc, Recepcion_Proveedor $recepcion): bool
    {
        $oc->loadMissing('ordencompra_articulos');
        $recepcion->loadMissing('recepcion_proveedor_articulos');

        $descuentoCabeceraOc = (float) ($oc->descuento ?? 0);
        $preciosOc = $oc->ordencompra_articulos->mapWithKeys(
            static fn ($art) => [(int) $art->id => RecepcionProveedorConversionSupport::precioUnitarioNetoDesdeLineaOc(
                (float) $art->precio,
                (float) ($art->descuento ?? 0),
                $descuentoCabeceraOc,
            )]
        );

        $haySolicitud = false;
        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            if (! self::lineaTienePrecioSolicitadoPendiente($linea)) {
                continue;
            }

            $haySolicitud = true;
            $ocArtId = (int) ($linea->ordencompra_articulo_id ?? 0);
            if ($ocArtId <= 0) {
                return false;
            }

            $precioOcActual = (float) ($preciosOc->get($ocArtId) ?? 0);
            $precioSolicitado = (float) $linea->precio_solicitado;

            if (abs($precioOcActual - $precioSolicitado) >= 0.0001) {
                return false;
            }
        }

        return $haySolicitud;
    }

    /** Sincroniza precio de recepción con OC ya actualizada y limpia solicitud. */
    public static function aplicarPreciosOcALineasRecepcion(Recepcion_Proveedor $recepcion, Ordencompra $oc): void
    {
        $oc->loadMissing('ordencompra_articulos');
        $descuentoCabeceraOc = (float) ($oc->descuento ?? 0);
        $preciosOc = $oc->ordencompra_articulos->mapWithKeys(
            static fn ($art) => [(int) $art->id => RecepcionProveedorConversionSupport::precioUnitarioNetoDesdeLineaOc(
                (float) $art->precio,
                (float) ($art->descuento ?? 0),
                $descuentoCabeceraOc,
            )]
        );

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $ocArtId = (int) ($linea->ordencompra_articulo_id ?? 0);
            if ($ocArtId <= 0) {
                continue;
            }

            $precioOc = (float) ($preciosOc->get($ocArtId) ?? 0);
            if ($precioOc <= 0) {
                continue;
            }

            $linea->update([
                'precio' => $precioOc,
                'precio_ordencompra' => $precioOc,
                'precio_solicitado' => null,
                'descuento' => 0,
                'fl_precio_diferencia' => false,
            ]);
        }
    }

    public static function resumenPreciosSolicitados(Recepcion_Proveedor $recepcion): string
    {
        $recepcion->loadMissing('recepcion_proveedor_articulos.articulos');
        $lineas = [];
        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            if (! self::lineaTienePrecioSolicitadoPendiente($linea)) {
                continue;
            }
            $sku = optional($linea->articulos)->sku ?? (string) $linea->articulo_id;
            $lineas[] = sprintf(
                '%s: OC %.4f → solicitado %.4f',
                $sku,
                (float) $linea->precio_ordencompra,
                (float) $linea->precio_solicitado
            );
        }

        return $lineas !== [] ? implode("\n", $lineas) : '—';
    }
}
