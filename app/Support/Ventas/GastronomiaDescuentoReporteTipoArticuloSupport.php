<?php

namespace App\Support\Ventas;

/**
 * Ordena y totaliza líneas del reporte de descuentos por tipo de artículo.
 */
final class GastronomiaDescuentoReporteTipoArticuloSupport
{
    public const TIPO_SIN_ASIGNAR = 'Sin tipo';

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{
     *     grupos: list<array{
     *         tipo_id: int|null,
     *         tipo_nombre: string,
     *         filas: list<array<string, mixed>>,
     *         subtotal_unidades: float,
     *         subtotal_costo_total: float,
     *         subtotal_total_venta: float,
     *         cantidad_lineas: int
     *     }>,
     *     filas: list<array<string, mixed>>,
     *     cantidad_lineas: int
     * }
     */
    public static function agruparFilas(array $filas): array
    {
        usort($filas, static function (array $a, array $b): int {
            $ta = mb_strtolower(self::nombreTipo($a));
            $tb = mb_strtolower(self::nombreTipo($b));
            if ($ta !== $tb) {
                return strcmp($ta, $tb);
            }

            $sku = strcmp((string) ($a['sku'] ?? ''), (string) ($b['sku'] ?? ''));
            if ($sku !== 0) {
                return $sku;
            }

            return ((int) ($a['articulo_id'] ?? 0)) <=> ((int) ($b['articulo_id'] ?? 0));
        });

        $gruposIndexados = [];
        foreach ($filas as $fila) {
            $tipoId = self::tipoId($fila);
            $tipoNombre = self::nombreTipo($fila);
            $clave = $tipoId !== null ? 'id:'.$tipoId : 'nombre:'.$tipoNombre;

            if (! isset($gruposIndexados[$clave])) {
                $gruposIndexados[$clave] = [
                    'tipo_id' => $tipoId,
                    'tipo_nombre' => $tipoNombre,
                    'filas' => [],
                    'subtotal_unidades' => 0.0,
                    'subtotal_costo_total' => 0.0,
                    'subtotal_total_venta' => 0.0,
                    'cantidad_lineas' => 0,
                ];
            }

            $fila['tipoarticulo_id'] = $tipoId;
            $fila['tipoarticulo_nombre'] = $tipoNombre;
            $gruposIndexados[$clave]['filas'][] = $fila;
            $gruposIndexados[$clave]['cantidad_lineas']++;
            $gruposIndexados[$clave]['subtotal_unidades'] = round(
                $gruposIndexados[$clave]['subtotal_unidades'] + (float) ($fila['unidades'] ?? 0),
                4,
            );
            $gruposIndexados[$clave]['subtotal_costo_total'] = round(
                $gruposIndexados[$clave]['subtotal_costo_total'] + (float) ($fila['costo_total'] ?? 0),
                2,
            );
            $gruposIndexados[$clave]['subtotal_total_venta'] = round(
                $gruposIndexados[$clave]['subtotal_total_venta'] + (float) ($fila['total_venta'] ?? 0),
                2,
            );
        }

        $grupos = array_values($gruposIndexados);
        $filasOrdenadas = [];
        foreach ($grupos as $grupo) {
            foreach ($grupo['filas'] as $fila) {
                $filasOrdenadas[] = $fila;
            }
        }

        return [
            'grupos' => $grupos,
            'filas' => $filasOrdenadas,
            'cantidad_lineas' => count($filasOrdenadas),
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function tipoId(array $fila): ?int
    {
        $id = (int) ($fila['tipoarticulo_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function nombreTipo(array $fila): string
    {
        $nombre = trim((string) ($fila['tipoarticulo_nombre'] ?? ''));

        return $nombre !== '' ? $nombre : self::TIPO_SIN_ASIGNAR;
    }
}
