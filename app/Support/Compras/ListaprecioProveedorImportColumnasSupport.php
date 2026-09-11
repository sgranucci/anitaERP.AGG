<?php

namespace App\Support\Compras;

use App\Imports\Stock\PrecioImportLecturaCruda;
use App\Support\Stock\PrecioImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Encabezados del Excel de listas de precio de proveedores.
 * Reutiliza la detección flexible de precios de venta y suma alias de compras (MATERIAL, dto, cód. proveedor).
 */
final class ListaprecioProveedorImportColumnasSupport
{
    public const COL_SKU_DEFAULT = 'sku';

    public const COL_DESCRIPCION_DEFAULT = 'descripcion';

    public const COL_PRECIO_DEFAULT = 'precio';

    public const COL_DESCUENTO_DEFAULT = 'descuento';

    public const COL_CODIGO_PROVEEDOR_DEFAULT = 'codigo_proveedor';

    public const MAX_FILAS_BUSQUEDA_ENCABEZADO = 15;

    /** @var list<string> */
    public const ALIAS_SKU = [
        'sku',
        'plu',
        'codigo',
        'codigo_articulo',
        'codigo_plu',
        'articulo',
        'art',
        'item',
        'referencia',
        'material',
        'codigo_material',
        'nro_articulo',
        'cod_articulo',
        'cod_art',
    ];

    /** @var list<string> */
    public const ALIAS_DESCRIPCION = [
        'descripcion',
        'detalle',
        'nombre',
        'nombre_producto',
        'nombre_del_producto',
        'producto',
        'articulo_descripcion',
        'denominacion',
        'descripcion_producto',
    ];

    /** @var list<string> */
    public const ALIAS_PRECIO = [
        'precio',
        'importe',
        'valor',
        'precio_venta',
        'precio_lista',
        'precio_unitario',
        'unitario',
        'pvp',
        'costo',
        'lista',
        'p_lista',
        'precio_neto',
        'neto',
        'us_kg',
        'usd_kg',
        'uss_kg',
        'uskg',
        'usdkg',
        'usd',
        'uss',
        'dolar',
        'dolares',
        'precio_kg',
        'precio_usd',
        'p_unitario',
        'punitario',
        'price',
        'unit_price',
        'cotizacion',
    ];

    /** @var list<string> */
    public const ALIAS_DESCUENTO = [
        'descuento',
        'dto',
        'dcto',
        'desc',
        'porc_descuento',
        'porcentaje_descuento',
        'porcentaje',
        'dto_porc',
        'descuento_pct',
    ];

    /** @var list<string> */
    public const ALIAS_CODIGO_PROVEEDOR = [
        'codigo_proveedor',
        'codigo_articulo_proveedor',
        'codigo_art_proveedor',
        'cod_proveedor',
        'cod_art_proveedor',
        'codigo_del_proveedor',
        'nro_proveedor',
        'sku_proveedor',
        'material_proveedor',
    ];

    /**
     * @param  array<int, mixed>  $encabezados
     * @return array{indice: int, titulo: string, clave_normalizada: string}|null
     */
    public static function resolverColumna(array $encabezados, string $nombreConfigurado, string $default, array $alias): ?array
    {
        return PrecioImportColumnasSupport::resolverColumnaEnEncabezados(
            $encabezados,
            $nombreConfigurado,
            $default,
            $alias
        );
    }

    /**
     * Precio de listas de proveedor: además de "precio/importe", reconoce U$S/KG, USD/kg, $/kg, etc.
     *
     * @param  array<int, mixed>  $encabezados
     * @return array{indice: int, titulo: string, clave_normalizada: string}|null
     */
    public static function resolverColumnaPrecio(array $encabezados, string $nombreConfigurado): ?array
    {
        $info = self::resolverColumna($encabezados, $nombreConfigurado, self::COL_PRECIO_DEFAULT, self::ALIAS_PRECIO);
        if ($info !== null) {
            return $info;
        }

        $mejor = null;
        $mejorPuntaje = 0;
        foreach ($encabezados as $indice => $celda) {
            $titulo = trim((string) $celda);
            if ($titulo === '') {
                continue;
            }
            $puntaje = self::puntajeEncabezadoPrecio($titulo);
            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejor = [
                    'indice' => (int) $indice,
                    'titulo' => $titulo,
                    'clave_normalizada' => PrecioImportColumnasSupport::normalizarNombreColumna($titulo),
                ];
            }
        }

        return $mejorPuntaje >= 60 ? $mejor : null;
    }

    /**
     * Encabezados típicos de cotización de proveedores (U$S/KG, USD/kg, $/kg).
     */
    public static function puntajeEncabezadoPrecio(string $titulo): int
    {
        $titulo = trim($titulo);
        if ($titulo === '') {
            return 0;
        }

        $norm = PrecioImportColumnasSupport::normalizarNombreColumna($titulo);
        $sinSimbolo = trim(preg_replace('/_+/', '_', str_replace(['$', '€'], '', $norm)) ?? $norm, '_');

        if (in_array($sinSimbolo, ['us_kg', 'usd_kg', 'uss_kg', 'uskg', 'usdkg', 'usd', 'uss'], true)
            || in_array($norm, ['u$s_kg', 'u$s', 'usd_kg', 'us_kg'], true)) {
            return 100;
        }

        if (preg_match('/u\s*\$\s*s\s*\/?\s*kg/i', $titulo)
            || preg_match('/usd\s*\/?\s*kg/i', $titulo)
            || preg_match('/\$\s*\/\s*kg/i', $titulo)) {
            return 100;
        }

        if (preg_match('/u\s*\$\s*s/i', $titulo) || preg_match('/\busd\b/i', $titulo)) {
            return 90;
        }

        if (preg_match('/\/\s*kg/i', $titulo) && preg_match('/\$|usd|uss|dolar/i', $titulo)) {
            return 90;
        }

        if (str_contains($norm, 'precio') || str_contains($sinSimbolo, 'precio')) {
            return 85;
        }

        if (str_contains($norm, 'importe') || str_contains($norm, 'unitario')) {
            return 80;
        }

        return 0;
    }

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

        return 1;
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

        $terminos = array_merge(self::ALIAS_SKU, self::ALIAS_PRECIO, self::ALIAS_DESCUENTO, self::ALIAS_CODIGO_PROVEEDOR);
        foreach ($celdas as $celda) {
            foreach ($terminos as $termino) {
                if ($celda === PrecioImportColumnasSupport::normalizarNombreColumna($termino)) {
                    return true;
                }
            }
        }

        foreach ($fila as $valor) {
            if (self::puntajeEncabezadoPrecio((string) $valor) >= 60) {
                return true;
            }
        }

        return false;
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

    /**
     * @param  array{indice: int, titulo: string, clave_normalizada: string}|null  $info
     * @return array{configurado: string, encontrada: bool, titulo: ?string, requerida: bool}
     */
    public static function presentarColumna(string $configurado, ?array $info, bool $requerida = true): array
    {
        return [
            'configurado' => $configurado,
            'encontrada' => $info !== null,
            'titulo' => $info['titulo'] ?? null,
            'requerida' => $requerida,
        ];
    }
}
