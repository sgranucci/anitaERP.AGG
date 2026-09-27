<?php

declare(strict_types=1);

namespace App\Support\Stock;

use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoQbeSupport;

/**
 * Catálogo del workbench de artículos estándar (grilla, QBE, cortes).
 */
final class ArticuloListadoColumnas
{
    public const RECURSO = 'stock.articulo';

    /**
     * @var array<string, array{label: string, default: bool, type: string, source: string, attr: string, group: string}>
     */
    public const COLUMNAS = [
        'sku' => ['label' => 'SKU', 'default' => true, 'type' => 'texto', 'source' => 'articulo.sku', 'attr' => 'codigoarticulo', 'group' => 'identificacion'],
        'codigobarra' => ['label' => 'Cód. barra', 'default' => true, 'type' => 'texto', 'source' => 'articulo.codigobarra', 'attr' => 'codigobarra', 'group' => 'identificacion'],
        'descripcion' => ['label' => 'Descripción', 'default' => true, 'type' => 'texto', 'source' => 'articulo.descripcion', 'attr' => 'descripcion', 'group' => 'identificacion'],
        'unidadmedida' => ['label' => 'Unidad de medida', 'default' => true, 'type' => 'texto', 'source' => 'unidadmedida.nombre', 'attr' => 'nombreunidadmedida', 'group' => 'clasificacion'],
        'categoria' => ['label' => 'Categoría', 'default' => true, 'type' => 'texto', 'source' => 'categoria.nombre', 'attr' => 'nombrecategoria', 'group' => 'clasificacion'],
        'tipoarticulo' => ['label' => 'Tipo de artículo', 'default' => true, 'type' => 'texto', 'source' => 'tipoarticulo.nombre', 'attr' => 'nombretipoarticulo', 'group' => 'clasificacion'],
        'usoarticulo' => ['label' => 'Uso', 'default' => true, 'type' => 'texto', 'source' => 'usoarticulo.nombre', 'attr' => 'nombreusoarticulo', 'group' => 'clasificacion'],
        'canal' => ['label' => 'Canal', 'default' => true, 'type' => 'texto', 'source' => '', 'attr' => 'canal', 'group' => 'clasificacion'],
        'empresa' => ['label' => 'Empresa', 'default' => true, 'type' => 'texto', 'source' => 'empresa.nombre', 'attr' => 'nombreempresa', 'group' => 'clasificacion'],
        'numeroparte' => ['label' => 'Nro. parte', 'default' => true, 'type' => 'texto', 'source' => 'articulo.numeroparte', 'attr' => 'numeroparte', 'group' => 'partes'],
        'ubicacionparte' => ['label' => 'Ubic. parte', 'default' => true, 'type' => 'texto', 'source' => 'articulo.ubicacionparte', 'attr' => 'ubicacionparte', 'group' => 'partes'],
        'saldo' => ['label' => 'Saldo dep.', 'default' => true, 'type' => 'texto', 'source' => '', 'attr' => 'saldo', 'group' => 'stock'],
        'nofactura' => ['label' => 'Facturable', 'default' => true, 'type' => 'texto', 'source' => 'articulo.nofactura', 'attr' => 'nofactura', 'group' => 'clasificacion'],
        'estado' => ['label' => 'Estado', 'default' => true, 'type' => 'texto', 'source' => 'articulo.estado', 'attr' => 'estado', 'group' => 'clasificacion'],
        'fecha_alta' => ['label' => 'Fecha de alta', 'default' => false, 'type' => 'fecha', 'source' => 'articulo.created_at', 'attr' => 'created_at', 'group' => 'fechas'],
        'fecha_modificacion' => ['label' => 'Fecha de modificación', 'default' => false, 'type' => 'fecha', 'source' => 'articulo.updated_at', 'attr' => 'updated_at', 'group' => 'fechas'],
    ];

    /** @return array<string, array{label: string, default: bool, type: string, source: string, attr: string, group: string}> */
    public static function catalogoActivo(): array
    {
        $cols = self::COLUMNAS;
        if (! ArticuloListadoFiltros::filtroCanalActivo()) {
            unset($cols['canal']);
        }
        if (! ArticuloListadoFiltros::filtroEmpresaActivo()) {
            unset($cols['empresa']);
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
            'sku' => self::primero($data, 'codigoarticulo', 'sku'),
            'unidadmedida' => self::primero($data, 'nombreunidadmedida', 'unidadmedida'),
            'categoria' => self::primero($data, 'nombrecategoria', 'categoria'),
            'tipoarticulo' => self::primero($data, 'nombretipoarticulo', 'tipoarticulo'),
            'usoarticulo' => self::primero($data, 'nombreusoarticulo', 'usoarticulo'),
            'empresa' => ($nombre = self::primero($data, 'nombreempresa', 'empresa')) !== '' ? $nombre : 'Todas',
            'canal' => self::nombresCanal($data),
            'nofactura' => match ((string) ($data->nofactura ?? '')) {
                '0' => 'Facturable',
                '1' => 'No facturable',
                default => self::primero($data, 'nofactura'),
            },
            'estado' => self::textoEstado($data),
            'fecha_alta' => ListadoQbeSupport::formatearFecha(self::primero($data, 'fecha_alta', 'created_at')),
            'fecha_modificacion' => ListadoQbeSupport::formatearFecha(self::primero($data, 'fecha_modificacion', 'updated_at')),
            'saldo' => '',
            default => (string) ($data->{$key} ?? ''),
        };
    }

    /**
     * @return array{select: list<string>, groupBy: list<string>}|null
     */
    public static function sqlAgrupacion(string $key): ?array
    {
        if (in_array($key, ['canal', 'saldo'], true)) {
            return null;
        }

        $ordenable = ArticuloListadoFiltros::camposOrdenables()[$key] ?? null;
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

    private static function primero(object $data, string ...$keys): string
    {
        foreach ($keys as $key) {
            $valor = trim((string) ($data->{$key} ?? ''));
            if ($valor !== '') {
                return $valor;
            }
        }

        return '';
    }

    private static function nombresCanal(object $data): string
    {
        if (method_exists($data, 'relationLoaded') && $data->relationLoaded('canales')) {
            return $data->canales->pluck('nombre')->filter()->implode(', ');
        }

        return (string) ($data->canal ?? '');
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
