<?php

namespace App\Support\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\CuentaEstacionamiento;
use App\Models\Caja\Estacionamiento\CuentaEstacionamientoLinea;

/**
 * Arma el payload de ítems para FacturacionService sin artículos de stock:
 * articulo_id = 0 y descripción desde ítem de estacionamiento.
 */
final class EstacionamientoFacturaPayloadSupport
{
    public const PREFIJO_DETALLE_ITEM = '[EST-ITEM:';

    /**
     * @return array{0:list<int>,1:list<float>,2:list<float>,3:list<string>}
     */
    public static function arraysDesdeCuenta(CuentaEstacionamiento $cuenta): array
    {
        $cuenta->loadMissing(['lineas.itemEstacionamiento']);

        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descripciones = [];

        foreach ($cuenta->lineas->sortBy('numero_linea') as $linea) {
            $articuloIds[] = 0;
            $cantidades[] = (float) $linea->cantidad;
            $precios[] = (float) $linea->precio_unitario;
            $descripciones[] = self::descripcionLineaFactura($linea);
        }

        return [$articuloIds, $cantidades, $precios, $descripciones];
    }

    public static function descripcionLineaFactura(CuentaEstacionamientoLinea $linea): string
    {
        $itemId = (int) ($linea->item_estacionamiento_id ?? 0);
        $nombre = trim((string) ($linea->descripcion ?? ''));
        if ($nombre === '') {
            $nombre = trim((string) ($linea->itemEstacionamiento?->nombre ?? ''));
        }
        if ($nombre === '') {
            $nombre = 'Ítem estacionamiento';
        }

        if ($itemId > 0) {
            return self::PREFIJO_DETALLE_ITEM.$itemId.'] '.$nombre;
        }

        return $nombre;
    }

    /**
     * @return list<int>
     */
    public static function impuestoIdsParaLineas(int $cantidadLineas): array
    {
        if ($cantidadLineas <= 0) {
            return [];
        }

        $impuestoId = (int) config('estacionamiento.impuesto_exento_id', 1);

        return array_fill(0, $cantidadLineas, $impuestoId);
    }

    public static function resolverItemEstacionamientoIdDesdeDetalle(?string $detalle): ?int
    {
        $texto = trim((string) $detalle);
        if ($texto === '') {
            return null;
        }

        if (preg_match('/^\[EST-ITEM:(\d+)\]/', $texto, $m)) {
            return (int) $m[1];
        }

        $itemId = \App\Models\Caja\Estacionamiento\ItemEstacionamiento::query()
            ->where('nombre', $texto)
            ->value('id');

        return $itemId !== null ? (int) $itemId : null;
    }

    public static function etiquetaItemDesdeDetalle(?string $detalle): string
    {
        $texto = trim((string) $detalle);
        if ($texto === '') {
            return '—';
        }

        if (preg_match('/^\[EST-ITEM:\d+\]\s*(.+)$/u', $texto, $m)) {
            return trim($m[1]) !== '' ? trim($m[1]) : '—';
        }

        return $texto;
    }
}
