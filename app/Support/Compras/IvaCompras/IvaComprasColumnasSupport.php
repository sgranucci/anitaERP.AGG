<?php

declare(strict_types=1);

namespace App\Support\Compras\IvaCompras;

use App\Models\Compras\Columna_Ivacompra;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Facades\Cache;

/**
 * Columnas del listado IVA compras desde la tabla maestro columna_ivacompra
 * (equivalente colivacomp / l-compra.c).
 *
 * Cada concepto_ivacompra apunta a una columna; el reporte acumula
 * comprobante_proveedor_concepto.monto en esa columna (como concmov → coli).
 */
final class IvaComprasColumnasSupport
{
    public const KEY_TOTAL = 'total';

    public const CACHE_KEY = 'iva_compras.columnas_maestro';

    /**
     * @return list<array{key: string, label: string, columna_id: int, numerocolumna: int}>
     */
    public static function columnas(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, static function (): array {
            $filas = Columna_Ivacompra::query()
                ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('numerocolumna'))
                ->orderBy('id')
                ->get(['id', 'numerocolumna', 'nombrecolumna', 'nombre']);

            $out = [];
            foreach ($filas as $col) {
                $id = (int) $col->id;
                $label = trim((string) ($col->nombrecolumna ?: $col->nombre ?: 'Col '.$id));
                $out[] = [
                    'key' => self::keyDesdeId($id),
                    'label' => $label,
                    'columna_id' => $id,
                    'numerocolumna' => (int) ($col->numerocolumna ?? 0),
                ];
            }

            // Total del comprobante (com_monto en l-compra): no es fila de columna_ivacompra.
            $out[] = [
                'key' => self::KEY_TOTAL,
                'label' => 'Total',
                'columna_id' => 0,
                'numerocolumna' => 9999,
            ];

            return $out;
        });
    }

    public static function olvidarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('iva_compras.columna_ids_por_rubro');
    }

    public static function keyDesdeId(int $columnaId): string
    {
        return 'col_'.$columnaId;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::columnas(), 'key');
    }

    /**
     * @return array<string, float>
     */
    public static function montosVacios(): array
    {
        $out = [];
        foreach (self::keys() as $key) {
            $out[$key] = 0.0;
        }

        return $out;
    }

    /**
     * @param  array<string, float>  $acum
     * @param  array<string, float>  $delta
     */
    public static function acumular(array &$acum, array $delta): void
    {
        foreach (self::keys() as $key) {
            $acum[$key] = ($acum[$key] ?? 0.0) + (float) ($delta[$key] ?? 0);
        }
    }

    /**
     * Suma de columnas de IVA liquidado (tipoconcepto I) para conciliación.
     *
     * @param  array<string, float>  $montos
     * @param  list<int>  $columnaIdsIva
     */
    public static function sumaIva(array $montos, array $columnaIdsIva): float
    {
        $suma = 0.0;
        foreach ($columnaIdsIva as $id) {
            $suma += (float) ($montos[self::keyDesdeId((int) $id)] ?? 0);
        }

        return round($suma, 2);
    }

    /**
     * Neto informable: no gravado + gravado + exento + monotributo (sin IVA ni perc.).
     *
     * @param  array<string, float>  $montos
     * @param  list<int>  $columnaIdsNeto
     */
    public static function sumaNeto(array $montos, array $columnaIdsNeto): float
    {
        $suma = 0.0;
        foreach ($columnaIdsNeto as $id) {
            $suma += (float) ($montos[self::keyDesdeId((int) $id)] ?? 0);
        }

        return round($suma, 2);
    }
}
