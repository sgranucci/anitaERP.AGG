<?php

namespace App\Support\Stock;

use App\Imports\Stock\PrecioImportLecturaCruda;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Encabezados y celdas del Excel de importación de recuento.
 */
final class RecuentoImportColumnasSupport
{
    public const COL_SKU_DEFAULT = 'sku';

    public const COL_CANTIDAD_DEFAULT = 'cantidad_contada';

    public const COL_DETALLE_DEFAULT = 'detalle';

    public const COL_COLOR_DEFAULT = 'color';

    public const COL_TALLE_DEFAULT = 'talle';

    public const MAX_FILAS_BUSQUEDA_ENCABEZADO = 15;

    /** @var list<string> */
    public const ALIAS_SKU = [
        'sku',
        'plu',
        'codigo',
        'codigo_articulo',
        'articulo',
        'art',
        'item',
        'referencia',
    ];

    /** @var list<string> */
    public const ALIAS_CANTIDAD = [
        'cantidad_contada',
        'cantidad',
        'contado',
        'conteo',
        'qty',
        'cantidad_fisica',
        'stock_contado',
        'unidades',
    ];

    /** @var list<string> */
    public const ALIAS_DETALLE = [
        'detalle',
        'descripcion',
        'nombre',
        'producto',
        'denominacion',
    ];

    /** @var list<string> */
    public const ALIAS_COLOR = [
        'color',
        'colour',
        'col',
    ];

    /** @var list<string> */
    public const ALIAS_TALLE = [
        'talle',
        'talla',
        'size',
        'talle_id',
    ];

    /** @var list<string> */
    private const VALORES_VACIOS_COLOR_TALLE = [
        '',
        '-',
        '—',
        '–',
        '−',
        'n/a',
        'na',
        's/d',
        'sd',
        'sin',
        'ninguno',
        'ninguna',
    ];

    /**
     * @param  UploadedFile|string  $archivo
     */
    public static function detectarFilaEncabezado(
        UploadedFile|string $archivo,
        ?int $filaIndicada = null,
        int $hojaIndice = 0
    ): int {
        if ($filaIndicada !== null && $filaIndicada >= 1 && $filaIndicada <= 50) {
            return $filaIndicada;
        }

        $hoja = Excel::toArray(new PrecioImportLecturaCruda(), $archivo)[$hojaIndice] ?? [];
        $limite = min(self::MAX_FILAS_BUSQUEDA_ENCABEZADO, count($hoja));

        for ($i = 0; $i < $limite; $i++) {
            $fila = $hoja[$i] ?? [];
            if (is_array($fila) && self::pareceFilaEncabezado($fila)) {
                return $i + 1;
            }
        }

        return PrecioImportColumnasSupport::detectarFilaEncabezado($archivo, null, $hojaIndice);
    }

    /**
     * @param  array<int, mixed>  $fila
     */
    public static function pareceFilaEncabezado(array $fila): bool
    {
        if (PrecioImportColumnasSupport::pareceFilaEncabezado($fila)) {
            return true;
        }

        $celdas = array_values(array_filter(array_map(
            static fn ($v) => PrecioImportColumnasSupport::normalizarNombreColumna((string) $v),
            $fila
        ), static fn ($v) => $v !== ''));

        if ($celdas === []) {
            return false;
        }

        $terminos = array_merge(self::ALIAS_SKU, self::ALIAS_CANTIDAD);
        foreach ($celdas as $celda) {
            foreach ($terminos as $termino) {
                if ($celda === PrecioImportColumnasSupport::normalizarNombreColumna($termino)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $encabezados
     * @param  list<string>  $alias
     * @return array{indice: int, titulo: string, clave_normalizada: string}|null
     */
    public static function resolverColumna(
        array $encabezados,
        string $nombreConfigurado,
        string $default,
        array $alias
    ): ?array {
        return PrecioImportColumnasSupport::resolverColumnaEnEncabezados(
            $encabezados,
            $nombreConfigurado,
            $default,
            $alias
        );
    }

    public static function normalizarSkuCelda(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        if (is_int($valor)) {
            return (string) $valor;
        }

        if (is_float($valor)) {
            if (floor($valor) == $valor) {
                return (string) (int) $valor;
            }

            return rtrim(rtrim(sprintf('%.6F', $valor), '0'), '.');
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }

        if (is_numeric($texto) && str_contains($texto, 'E')) {
            $numero = (float) $texto;
            if (floor($numero) == $numero) {
                return (string) (int) $numero;
            }
        }

        return $texto;
    }

    public static function normalizarCantidad(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $texto = preg_replace('/[^\d,.-]/', '', (string) $valor);
        if ($texto === '' || $texto === null) {
            return null;
        }

        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            if (strrpos($texto, ',') > strrpos($texto, '.')) {
                $texto = str_replace('.', '', $texto);
                $texto = str_replace(',', '.', $texto);
            } else {
                $texto = str_replace(',', '', $texto);
            }
        } else {
            $texto = str_replace(',', '.', $texto);
        }

        return is_numeric($texto) ? (float) $texto : null;
    }

    public static function esValorVacioColorTalle(mixed $valor): bool
    {
        $texto = mb_strtolower(trim((string) $valor));

        return in_array($texto, self::VALORES_VACIOS_COLOR_TALLE, true);
    }

    /**
     * @return list<string>
     */
    public static function candidatosSku(string $sku): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            return [];
        }

        $candidatos = [$sku];
        $sinCeros = ltrim($sku, '0');
        if ($sinCeros !== '' && $sinCeros !== $sku) {
            $candidatos[] = $sinCeros;
        }

        if (ctype_digit($sinCeros !== '' ? $sinCeros : $sku)) {
            $base = $sinCeros !== '' ? $sinCeros : $sku;
            foreach ([13, 12, 8] as $largo) {
                $padded = str_pad($base, $largo, '0', STR_PAD_LEFT);
                if (! in_array($padded, $candidatos, true)) {
                    $candidatos[] = $padded;
                }
            }
        }

        return array_values(array_unique($candidatos));
    }

    /**
     * @param  array<int, mixed>  $fila
     */
    public static function filaVacia(array $fila): bool
    {
        foreach ($fila as $valor) {
            if (trim((string) $valor) !== '') {
                return false;
            }
        }

        return true;
    }
}
