<?php

namespace App\Queries\Ventas;

use App\Services\Stock\PrecioService;
use App\Support\Stock\ArticuloUsoInsumoSupport;
use App\Support\Ventas\CanjeMarketingListadoFiltros;
use App\Support\Ventas\CanjeMarketingListadoListaprecioCmvSupport;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CanjeMarketingListadoQuery
{
    private static function cantidadExpr(): string
    {
        return GastronomiaVentaComprobanteSignoSupport::sqlCantidadLineaVenta();
    }

    /**
     * @return LengthAwarePaginator<int, object>|Collection<int, object>
     */
    public function listado(array $filtros, bool $paginar = true, int $perPage = 50): LengthAwarePaginator|Collection
    {
        $query = $this->queryBase($filtros)
            ->orderByDesc('cme.fechacanje')
            ->orderByDesc('cme.id')
            ->orderBy('ve.id');

        if ($paginar) {
            $paginator = $query->paginate(max(10, min(200, $perPage)));
            $items = collect($paginator->items())
                ->map(fn ($row) => $this->enriquecerFila($row));
            $paginator->setCollection($this->aplicarPreciosCmv($items)->values());

            return $paginator;
        }

        $filas = $query->get()->map(fn ($row) => $this->enriquecerFila($row));

        return $this->aplicarPreciosCmv($filas);
    }

    /**
     * @return array{
     *   cantidad_filas: int,
     *   cantidad_total: float,
     *   cmv_total: float,
     *   precio_venta_total: float
     * }
     */
    public function totales(array $filtros): array
    {
        $filas = $this->listado($filtros, false);

        $cantidad = 0.;
        $cmv = 0.;
        $precio = 0.;
        foreach ($filas as $f) {
            $cant = abs((float) ($f->cantidad ?? 0));
            $cantidad += $cant;
            $cmv += $cant * (float) ($f->cmv ?? 0);
            $precio += $cant * (float) ($f->precio_venta ?? 0);
        }

        return [
            'cantidad_filas' => $filas->count(),
            'cantidad_total' => round($cantidad, 4),
            'cmv_total' => round($cmv, 2),
            'precio_venta_total' => round($precio, 2),
        ];
    }

    private function queryBase(array $filtros): Builder
    {
        $query = DB::table('canje_marketing_entrega_gastronomia as cme')
            ->join('venta as v', 'v.id', '=', 'cme.venta_id')
            ->join('venta_emision as ve', 've.venta_id', '=', 'v.id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->leftJoin('cliente_vip_gastronomia as cv', 'cv.id', '=', 'cme.cliente_vip_gastronomia_id')
            ->leftJoin('mozo_gastronomia as m', 'm.id', '=', 'cme.mozo_gastronomia_id')
            ->leftJoin('configuracion_puntoventa_gastronomia as cfg', function ($join) {
                $join->on('cfg.empresa_id', '=', 'cme.empresa_id')
                    ->whereRaw('BINARY cfg.identificador_pc = BINARY cme.identificador_pc');
            })
            ->leftJoin('ubicaciones_gastronomia as ug', 'ug.id', '=', 'cfg.ubicacion_id')
            ->leftJoin('empresa as e', 'e.id', '=', 'cme.empresa_id')
            ->whereNull('v.deleted_at');

        $this->aplicarExclusionInsumos($query);
        $this->aplicarFiltrosEstructurales($query, $filtros);

        return $query->select([
            'cme.id as canje_id',
            'cme.fechacanje',
            'cme.venta_id',
            'cme.cliente_vip_gastronomia_id',
            'cme.mozo_gastronomia_id',
            'cme.empresa_id',
            'cme.nombre_vip',
            'cme.apellido_vip',
            'cme.nrodocumento_vip',
            've.id as venta_emision_id',
            've.articulo_id',
            've.precio as precio_venta',
            'a.sku',
            'a.descripcion as producto',
            'cv.numeroid as numeroid_vip',
            'cv.nickname',
            'm.nombre as mozo_nombre',
            'm.codigo as mozo_codigo',
            'ug.id as ubicacion_id',
            'ug.nombre as sala',
            'e.nombre as nombreempresa',
            'v.codigo as venta_codigo',
            'v.fechajornada',
        ])->selectRaw(self::cantidadExpr().' as cantidad');
    }

    private function aplicarExclusionInsumos(Builder $query): void
    {
        $query->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('usoarticulo as ua_ins')
                ->whereColumn('ua_ins.id', 'a.usoarticulo_id')
                ->whereRaw(
                    'UPPER(TRIM(ua_ins.nombre)) = ?',
                    [ArticuloUsoInsumoSupport::NOMBRE_USO_INSUMO],
                );
        });
    }

    private function aplicarFiltrosEstructurales(Builder $query, array $filtros): void
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('cme.empresa_id', $empresaId);
        }

        [$desde, $hasta] = CanjeMarketingListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        if ($desde !== '') {
            $query->whereDate('cme.fechacanje', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate('cme.fechacanje', '<=', $hasta);
        }

        $ubicacionIds = array_values(array_filter(array_map('intval', $filtros['ubicacion_ids'] ?? []), fn (int $id) => $id > 0));
        if ($ubicacionIds !== []) {
            $query->whereIn('ug.id', $ubicacionIds);
        }
    }

    private function enriquecerFila(object $row): object
    {
        $fecha = $row->fechacanje ?? null;
        $row->fechacanje_ymd = $fecha ? (string) $fecha : '';
        $row->fechacanje_fmt = $fecha
            ? \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y')
            : '—';
        $row->cantidad = round(abs((float) ($row->cantidad ?? 0)), 4);
        $row->precio_venta = round((float) ($row->precio_venta ?? 0), 2);
        $row->cmv = null;
        $row->mozo_etiqueta = trim((string) ($row->mozo_nombre ?? ''));
        $row->sala = trim((string) ($row->sala ?? ''));

        return $row;
    }

    /**
     * @param  Collection<int, object>  $filas
     * @return Collection<int, object>
     */
    private function aplicarPreciosCmv(Collection $filas): Collection
    {
        $listaId = CanjeMarketingListadoListaprecioCmvSupport::resolverListaprecioId();
        if ($listaId === null) {
            return $filas->map(function (object $row) {
                $row->cmv = 0.;

                return $row;
            });
        }

        $cache = [];

        return $filas->map(function (object $row) use (&$cache, $listaId) {
            $articuloId = (int) ($row->articulo_id ?? 0);
            $fecha = trim((string) ($row->fechacanje_ymd ?? ''));
            if ($articuloId <= 0 || $fecha === '') {
                $row->cmv = 0.;

                return $row;
            }

            $key = $articuloId.'|'.$fecha;
            if (! array_key_exists($key, $cache)) {
                $precios = PrecioService::asignaPrecioPorLista($articuloId, $listaId, $fecha);
                $cache[$key] = $precios !== [] ? round((float) (end($precios)['precio'] ?? 0), 2) : 0.;
            }
            $row->cmv = $cache[$key];

            return $row;
        });
    }
}
