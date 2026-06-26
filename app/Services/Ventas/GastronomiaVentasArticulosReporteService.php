<?php

namespace App\Services\Ventas;

use App\Models\Stock\Listaprecio;
use App\Queries\Ventas\GastronomiaVentasArticulosReporteQuery;
use App\Services\Stock\PrecioService;
use App\Support\Ventas\Gastronomia\GastronomiaInformeGerenteCostoListaSupport;
use App\Support\Ventas\GastronomiaVentasArticulosReporteFiltros;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;

final class GastronomiaVentasArticulosReporteService
{
    public function __construct(
        private readonly GastronomiaVentasArticulosReporteQuery $query,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas:list<array<string,mixed>>,
     *   totales:array<string,float>,
     *   listas_costo:array<string,mixed>,
     *   listaprecio_venta_id:int|null,
     *   listaprecio_venta_codigo:string,
     *   periodo_texto:string
     * }
     */
    public function generar(array $filtros): array
    {
        $filasRaw = $this->query->filasAgregadas($filtros);

        $fechaCosto = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($fechaCosto === '') {
            $fechaCosto = now()->toDateString();
        }

        $listas = GastronomiaInformeGerenteCostoListaSupport::listasDesdeFechaJornada($fechaCosto);
        $listaCostoId = $this->resolverListaprecioId((string) $listas['lista_actual']);
        $listaVentaId = $this->resolverListaprecioVentaId();
        $listaVentaCodigo = $this->resolverListaprecioCodigo($listaVentaId);

        $cacheCosto = [];
        $cacheVenta = [];
        $filas = [];

        $totales = [
            'cant_total' => 0.0,
            'cant_externa' => 0.0,
            'importe_externa' => 0.0,
            'cant_invitacion' => 0.0,
            'cant_staff' => 0.0,
            'venta_interna_costo' => 0.0,
            'venta_interna_precio_vta' => 0.0,
            'venta_externa_costo' => 0.0,
        ];

        foreach ($filasRaw as $fila) {
            $articuloId = (int) $fila->articulo_id;
            $costoUnit = $this->resolverPrecioUnitario(
                $articuloId,
                $listaCostoId,
                $fechaCosto,
                $cacheCosto,
            );
            $precioVenta = $this->resolverPrecioUnitario(
                $articuloId,
                $listaVentaId,
                $fechaCosto,
                $cacheVenta,
            );

            $cantInterna = round((float) $fila->cant_invitacion + (float) $fila->cant_staff, 4);
            $ventaInternaCosto = round($cantInterna * $costoUnit, 2);
            $ventaInternaPrecioVta = round($cantInterna * $precioVenta, 2);
            $ventaExternaCosto = round((float) $fila->cant_externa * $costoUnit, 2);

            $row = [
                'articulo_id' => $articuloId,
                'sku' => $fila->sku,
                'descripcion' => $fila->descripcion,
                'precio_venta' => $precioVenta,
                'costo_unitario' => $costoUnit,
                'cant_total' => (float) $fila->cant_total,
                'cant_externa' => (float) $fila->cant_externa,
                'importe_externa' => (float) $fila->importe_externa,
                'cant_invitacion' => (float) $fila->cant_invitacion,
                'cant_staff' => (float) $fila->cant_staff,
                'venta_interna_costo' => $ventaInternaCosto,
                'venta_interna_precio_vta' => $ventaInternaPrecioVta,
                'venta_externa_costo' => $ventaExternaCosto,
            ];

            $filas[] = $row;

            $totales['cant_total'] += $row['cant_total'];
            $totales['cant_externa'] += $row['cant_externa'];
            $totales['importe_externa'] += $row['importe_externa'];
            $totales['cant_invitacion'] += $row['cant_invitacion'];
            $totales['cant_staff'] += $row['cant_staff'];
            $totales['venta_interna_costo'] += $ventaInternaCosto;
            $totales['venta_interna_precio_vta'] += $ventaInternaPrecioVta;
            $totales['venta_externa_costo'] += $ventaExternaCosto;
        }

        foreach ($totales as $k => $v) {
            $totales[$k] = round($v, in_array($k, ['cant_total', 'cant_externa', 'cant_invitacion', 'cant_staff'], true) ? 4 : 2);
        }

        return [
            'filas' => $filas,
            'totales' => $totales,
            'listas_costo' => $listas,
            'listaprecio_venta_id' => $listaVentaId,
            'listaprecio_venta_codigo' => $listaVentaCodigo,
            'periodo_texto' => GastronomiaVentasArticulosReporteFiltros::formatearPeriodoTexto($filtros),
        ];
    }

    /**
     * @param  list<mixed>  $items
     */
    public function paginarFilas(array $items, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $total = count($items);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($items, $offset, $perPage);

        return new PaginatorImpl(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => PaginatorImpl::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
    }

    /**
     * @param  array<string, float>  $cache
     */
    private function resolverPrecioUnitario(
        int $articuloId,
        ?int $listaprecioId,
        string $fechaReferencia,
        array &$cache,
    ): float {
        if ($articuloId <= 0 || $listaprecioId === null || $fechaReferencia === '') {
            return 0.0;
        }

        $key = $articuloId.'|'.$listaprecioId.'|'.$fechaReferencia;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $precios = PrecioService::asignaPrecioPorLista($articuloId, $listaprecioId, $fechaReferencia);
        $cache[$key] = $precios !== []
            ? round((float) (end($precios)['precio'] ?? 0), 2)
            : 0.0;

        return $cache[$key];
    }

    private function resolverListaprecioId(string $codigoLista): ?int
    {
        $codigoLista = trim($codigoLista);
        if ($codigoLista === '') {
            return null;
        }

        $id = Listaprecio::query()->where('codigo', $codigoLista)->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function resolverListaprecioVentaId(): ?int
    {
        $configId = (int) config('gastronomia.ventas_articulos_listaprecio_venta_id', 0);
        if ($configId > 0) {
            return $configId;
        }

        $defaultId = (int) config('precio.listaprecio_default_id', 1);

        return $defaultId > 0 ? $defaultId : null;
    }

    private function resolverListaprecioCodigo(?int $listaprecioId): string
    {
        if ($listaprecioId === null || $listaprecioId <= 0) {
            return '1';
        }

        $codigo = Listaprecio::query()->whereKey($listaprecioId)->value('codigo');

        return $codigo !== null && trim((string) $codigo) !== ''
            ? trim((string) $codigo)
            : (string) $listaprecioId;
    }
}
