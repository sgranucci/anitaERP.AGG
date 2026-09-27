<?php

declare(strict_types=1);

namespace App\Support\Stock;

use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoQbeSupport;

/**
 * Catálogo del workbench del index Ferli (products.index).
 * Distinto de ArticuloListadoColumnas (index estándar).
 */
final class ArticuloFerliListadoColumnas
{
    public const RECURSO = 'stock.producto_ferli';

    /**
     * @var array<string, array{label: string, default: bool, type: string, source: string, attr: string, group: string}>
     */
    public const COLUMNAS = [
        'sku' => ['label' => 'Código', 'default' => true, 'type' => 'texto', 'source' => 'articulo.sku', 'attr' => 'stkm_articulo', 'group' => 'identificacion'],
        'descripcion' => ['label' => 'Descripción', 'default' => true, 'type' => 'texto', 'source' => 'articulo.descripcion', 'attr' => 'stkm_desc', 'group' => 'identificacion'],
        'categoria' => ['label' => 'Categoría', 'default' => true, 'type' => 'texto', 'source' => 'categoria.nombre', 'attr' => 'stkm_agrupacion', 'group' => 'clasificacion'],
        'marca' => ['label' => 'Marca', 'default' => true, 'type' => 'texto', 'source' => 'mventa.nombre', 'attr' => 'stkm_marca', 'group' => 'clasificacion'],
        'linea' => ['label' => 'Línea', 'default' => true, 'type' => 'texto', 'source' => 'linea.nombre', 'attr' => 'stkm_linea', 'group' => 'clasificacion'],
        'canal' => ['label' => 'Canal', 'default' => true, 'type' => 'texto', 'source' => '', 'attr' => 'canal', 'group' => 'clasificacion'],
        'nofactura' => ['label' => 'Facturable', 'default' => true, 'type' => 'texto', 'source' => 'articulo.nofactura', 'attr' => 'nofactura', 'group' => 'clasificacion'],
        'estado' => ['label' => 'Estado', 'default' => true, 'type' => 'texto', 'source' => 'articulo.estado', 'attr' => 'estado', 'group' => 'clasificacion'],
        'fecha_alta' => ['label' => 'Fecha de alta', 'default' => false, 'type' => 'fecha', 'source' => 'articulo.created_at', 'attr' => 'created_at', 'group' => 'fechas'],
        'fecha_modificacion' => ['label' => 'Fecha de modificación', 'default' => false, 'type' => 'fecha', 'source' => 'articulo.updated_at', 'attr' => 'updated_at', 'group' => 'fechas'],
    ];

    /** @return array<string, array{label: string, default: bool, type: string, source: string, attr: string, group: string}> */
    public static function catalogoActivo(): array
    {
        $cols = self::COLUMNAS;
        if (! ArticuloEstadoCanalSupport::uiFerliActiva()) {
            unset($cols['canal']);
        }

        return $cols;
    }

    /** @return list<string> */
    public static function defaultsVisibles(): array
    {
        $keys = [];
        foreach (self::catalogoActivo() as $key => $meta) {
            if ($meta['default']) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function normalizarVisibles(array $keys): array
    {
        $catalogo = self::catalogoActivo();
        $out = [];
        foreach ($keys as $key) {
            if (isset($catalogo[$key]) && ! in_array($key, $out, true)) {
                $out[] = $key;
            }
        }

        return $out !== [] ? $out : self::defaultsVisibles();
    }

    public static function valorCelda(object $data, string $key): string
    {
        return match ($key) {
            'sku' => (string) ($data->stkm_articulo ?? $data->sku ?? ''),
            'descripcion' => (string) ($data->stkm_desc ?? $data->descripcion ?? ''),
            'categoria' => (string) ($data->stkm_agrupacion ?? ''),
            'marca' => (string) ($data->stkm_marca ?? ''),
            'linea' => (string) ($data->stkm_linea ?? ''),
            'canal' => self::textoCanal($data),
            'nofactura' => ArticuloNofacturaSupport::etiqueta($data->nofactura ?? null),
            'estado' => self::textoEstado($data),
            'fecha_alta' => ListadoQbeSupport::formatearFecha((string) ($data->created_at ?? '')),
            'fecha_modificacion' => ListadoQbeSupport::formatearFecha((string) ($data->updated_at ?? '')),
            default => (string) ($data->{$key} ?? ''),
        };
    }

    /**
     * @return array{select: list<string>, groupBy: list<string>}|null
     */
    public static function sqlAgrupacion(string $key): ?array
    {
        if ($key === 'canal') {
            return null;
        }

        $ordenable = ArticuloFerliListadoFiltros::camposOrdenables()[$key] ?? null;
        if ($ordenable !== null) {
            return ListadoCortesSupport::sqlAgrupacionDefault($key, $ordenable);
        }

        $meta = self::catalogoActivo()[$key] ?? null;
        if ($meta === null || ($meta['source'] ?? '') === '') {
            return null;
        }

        return ListadoCortesSupport::sqlAgrupacionDefault($key, [
            'column' => $meta['source'],
            'attr' => $key,
            'type' => $meta['type'],
        ]);
    }

    private static function textoCanal(object $data): string
    {
        if (! method_exists($data, 'relationLoaded') || ! $data->relationLoaded('canales')) {
            return '';
        }
        $codigos = $data->canales->pluck('codigo')->map(static fn ($c) => strtoupper((string) $c))->all();
        $partes = [];
        if (in_array('FABRICA', $codigos, true)) {
            $partes[] = 'Fábrica';
        }
        if (in_array('LOCAL', $codigos, true)) {
            $partes[] = 'Local';
        }

        return implode(' ', $partes);
    }

    private static function textoEstado(object $data): string
    {
        if (ArticuloEstadoCanalSupport::uiFerliActiva()) {
            $ef = strtoupper((string) ($data->estado_fabrica ?? $data->estado ?? ''));
            $el = strtoupper((string) ($data->estado_local ?? $data->estado ?? ''));

            return 'Fab '.($ef === 'ACTIVO' ? 'A' : 'I').' / Loc '.($el === 'ACTIVO' ? 'A' : 'I');
        }

        return (string) ($data->estado ?? '');
    }
}
