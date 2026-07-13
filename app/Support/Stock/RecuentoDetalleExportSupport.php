<?php

namespace App\Support\Stock;

use App\Models\Stock\Recuento_Item;
use Illuminate\Support\Collection;

/**
 * Ordena y totaliza líneas de recuento por tipo de artículo (para PDF/Excel).
 */
final class RecuentoDetalleExportSupport
{
    public const TIPO_SIN_ASIGNAR = 'Sin tipo';

    /**
     * @param  iterable<int, Recuento_Item>  $items
     * @return array{
     *     grupos: list<array{
     *         tipo_id: int|null,
     *         tipo_nombre: string,
     *         items: list<array{
     *             item: Recuento_Item,
     *             diferencia: float,
     *             costo_uc: float|null,
     *             valor_contado: float|null,
     *             valor_dif: float|null
     *         }>,
     *         subtotal_valor_contado: float,
     *         subtotal_valor_dif: float,
     *         cantidad_lineas: int
     *     }>,
     *     total_valor_contado: float,
     *     total_valor_dif: float,
     *     cantidad_lineas: int
     * }
     */
    public static function agruparPorTipoArticulo(iterable $items): array
    {
        $ordenados = Collection::make($items)
            ->sortBy([
                static fn (Recuento_Item $item): string => mb_strtolower(self::nombreTipo($item)),
                static fn (Recuento_Item $item): string => (string) (optional($item->articulos)->sku ?? ''),
                static fn (Recuento_Item $item): int => (int) $item->id,
            ])
            ->values();

        $gruposIndexados = [];
        $totalValorContado = 0.0;
        $totalValorDif = 0.0;

        foreach ($ordenados as $item) {
            $tipoId = self::tipoId($item);
            $tipoNombre = self::nombreTipo($item);
            $clave = $tipoId !== null ? 'id:'.$tipoId : 'nombre:'.$tipoNombre;

            if (! isset($gruposIndexados[$clave])) {
                $gruposIndexados[$clave] = [
                    'tipo_id' => $tipoId,
                    'tipo_nombre' => $tipoNombre,
                    'items' => [],
                    'subtotal_valor_contado' => 0.0,
                    'subtotal_valor_dif' => 0.0,
                    'cantidad_lineas' => 0,
                ];
            }

            $linea = self::enriquecerLinea($item);
            $gruposIndexados[$clave]['items'][] = $linea;
            $gruposIndexados[$clave]['cantidad_lineas']++;

            if ($linea['valor_contado'] !== null) {
                $gruposIndexados[$clave]['subtotal_valor_contado'] += $linea['valor_contado'];
                $totalValorContado += $linea['valor_contado'];
            }
            if ($linea['valor_dif'] !== null) {
                $gruposIndexados[$clave]['subtotal_valor_dif'] += $linea['valor_dif'];
                $totalValorDif += $linea['valor_dif'];
            }
        }

        return [
            'grupos' => array_values($gruposIndexados),
            'total_valor_contado' => $totalValorContado,
            'total_valor_dif' => $totalValorDif,
            'cantidad_lineas' => $ordenados->count(),
        ];
    }

    /**
     * @return array{
     *     item: Recuento_Item,
     *     diferencia: float,
     *     costo_uc: float|null,
     *     valor_contado: float|null,
     *     valor_dif: float|null
     * }
     */
    private static function enriquecerLinea(Recuento_Item $item): array
    {
        $dif = (float) $item->diferencia();
        $costoUc = $item->precio_ultima_compra ?? null;
        $contado = (float) $item->cantidad_contada;
        $valorContado = ($costoUc !== null) ? $contado * (float) $costoUc : null;
        $valorDif = ($costoUc !== null && abs($dif) > 1e-9) ? $dif * (float) $costoUc : null;

        return [
            'item' => $item,
            'diferencia' => $dif,
            'costo_uc' => $costoUc !== null ? (float) $costoUc : null,
            'valor_contado' => $valorContado,
            'valor_dif' => $valorDif,
        ];
    }

    private static function tipoId(Recuento_Item $item): ?int
    {
        $id = (int) (optional($item->articulos)->tipoarticulo_id ?? 0);

        return $id > 0 ? $id : null;
    }

    private static function nombreTipo(Recuento_Item $item): string
    {
        $nombre = trim((string) (optional($item->articulos?->tipoarticulos)->nombre ?? ''));

        return $nombre !== '' ? $nombre : self::TIPO_SIN_ASIGNAR;
    }
}
