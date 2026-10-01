<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista de precios Ferli: una fila por artículo y una columna por lista pedida.
 */
class PrecioListaFerliConsulta
{
    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, codigo: string, nombre: string, encabezado: string}>
     */
    public static function resolverListas(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $filas = Listaprecio::query()
            ->whereIn('id', $ids)
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('id');

        $out = [];
        foreach ($ids as $id) {
            $fila = $filas->get($id);
            if ($fila === null) {
                continue;
            }
            $codigo = trim((string) $fila->codigo);
            $nombre = trim((string) $fila->nombre);
            $encabezado = 'LISTA '.$codigo;
            if ($nombre !== '') {
                $encabezado .= ' - '.$nombre;
            }
            $out[] = [
                'id' => (int) $fila->id,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'encabezado' => $encabezado,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<array{id: int, codigo: string, nombre: string, encabezado: string}>  $listas
     */
    public static function paginar(array $filtros, array $listas, int $porPagina = 25): LengthAwarePaginator
    {
        PrecioListaFerliFiltros::validar($filtros);

        $pagina = self::queryArticulos($filtros)
            ->paginate($porPagina)
            ->withQueryString();

        $ids = $pagina->getCollection()->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $precios = self::preciosDe($ids, $listas, (string) $filtros['fecha_vigencia']);
        $pagina->setCollection($pagina->getCollection()->map(
            static fn ($articulo) => self::armarFila($articulo, $listas, $precios)
        ));

        return $pagina;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<array{id: int, codigo: string, nombre: string, encabezado: string}>  $listas
     * @return list<object>
     */
    public static function filas(array $filtros, array $listas): array
    {
        PrecioListaFerliFiltros::validar($filtros);

        $ids = self::queryArticulos($filtros)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        if ($ids === []) {
            return [];
        }

        $filas = [];
        foreach (array_chunk($ids, 400) as $lote) {
            $articulos = Articulo::query()
                ->whereIn('id', $lote)
                ->get(['id', 'sku', 'descripcion', 'detalle'])
                ->keyBy('id');
            $precios = self::preciosDe($lote, $listas, (string) $filtros['fecha_vigencia']);
            foreach ($lote as $id) {
                $articulo = $articulos->get($id);
                if ($articulo === null) {
                    continue;
                }
                $filas[] = self::armarFila($articulo, $listas, $precios);
            }
        }

        return $filas;
    }

    public static function textoPrecio(?float $precio): string
    {
        if ($precio === null) {
            return '';
        }

        $decimales = abs($precio - round($precio)) < 0.001 ? 0 : 2;

        return number_format($precio, $decimales, ',', '.');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Articulo>
     */
    private static function queryArticulos(array $filtros): Builder
    {
        $listaIds = array_values(array_map('intval', $filtros['listaprecio_ids'] ?? []));
        $fecha = (string) $filtros['fecha_vigencia'];

        $query = Articulo::query()
            ->select(['articulo.id', 'articulo.sku', 'articulo.descripcion', 'articulo.detalle'])
            ->whereIn('articulo.mventa_id', $filtros['mventa_ids'])
            ->where('articulo.nofactura', (string) $filtros['facturable']);

        self::aplicarCanal($query, (string) ($filtros['canal'] ?? ''));
        ArticuloEstadoCanalSupport::aplicarFiltroEstadoListado(
            $query,
            (string) ($filtros['estado'] ?? ''),
            (string) ($filtros['canal'] ?? '')
        );

        $query->whereExists(function ($sub) use ($listaIds, $fecha) {
            $sub->selectRaw('1')
                ->from('precio')
                ->whereColumn('precio.articulo_id', 'articulo.id')
                ->whereIn('precio.listaprecio_id', $listaIds)
                ->where('precio.fechavigencia', '<=', $fecha);
        });

        return $query
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('articulo.sku'))
            ->orderBy('articulo.sku')
            ->orderBy('articulo.id');
    }

    /**
     * Misma regla que el listado de artículos: fábrica, local o sin fila en articulo_canal.
     *
     * @param  Builder<Articulo>  $query
     */
    private static function aplicarCanal(Builder $query, string $canal): void
    {
        $canal = strtoupper(trim($canal));
        if ($canal === '' || $canal === 'TODOS') {
            return;
        }

        if ($canal === 'SIN') {
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('articulo_canal')
                    ->whereColumn('articulo_canal.articulo_id', 'articulo.id');
            });

            return;
        }

        $query->whereExists(function ($sub) use ($canal) {
            $sub->selectRaw('1')
                ->from('articulo_canal')
                ->join('canal', 'canal.id', '=', 'articulo_canal.canal_id')
                ->whereColumn('articulo_canal.articulo_id', 'articulo.id')
                ->where('canal.codigo', $canal)
                ->where('canal.activo', true);
        });
    }

    /**
     * @param  list<int>  $articuloIds
     * @param  list<array{id: int, codigo: string, nombre: string, encabezado: string}>  $listas
     * @return array<int, array<int, float>>
     */
    private static function preciosDe(array $articuloIds, array $listas, string $fecha): array
    {
        $mapa = [];
        foreach ($listas as $lista) {
            $vigentes = PrecioListaVigenteSupport::vigentesPorArticulos($articuloIds, (int) $lista['id'], $fecha);
            foreach ($vigentes as $articuloId => $dato) {
                $mapa[(int) $articuloId][(int) $lista['id']] = (float) $dato['precio'];
            }
        }

        return $mapa;
    }

    /**
     * @param  list<array{id: int, codigo: string, nombre: string, encabezado: string}>  $listas
     * @param  array<int, array<int, float>>  $preciosPorArticulo
     */
    private static function armarFila(object $articulo, array $listas, array $preciosPorArticulo): object
    {
        $id = (int) $articulo->id;
        $descripcion = trim((string) ($articulo->descripcion ?? ''));
        if ($descripcion === '') {
            $descripcion = trim((string) ($articulo->detalle ?? ''));
        }

        $precios = [];
        foreach ($listas as $lista) {
            $precios[(int) $lista['id']] = $preciosPorArticulo[$id][(int) $lista['id']] ?? null;
        }

        return (object) [
            'id' => $id,
            'sku' => trim((string) $articulo->sku),
            'descripcion' => $descripcion,
            'precios' => $precios,
        ];
    }
}
