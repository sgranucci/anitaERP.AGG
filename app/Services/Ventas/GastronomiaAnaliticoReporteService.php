<?php

namespace App\Services\Ventas;

use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Queries\Ventas\GastronomiaAnaliticoReporteQuery;
use App\Support\Ventas\Gastronomia\GastronomiaInformeGerenteCostoListaSupport;
use App\Support\Ventas\GastronomiaAnaliticoPrecioCeroSupport;
use App\Support\Ventas\GastronomiaAnaliticoReporteFiltros;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

final class GastronomiaAnaliticoReporteService
{
    public function __construct(
        private readonly GastronomiaAnaliticoReporteQuery $query,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas: LengthAwarePaginator<int, object>|Collection<int, object>,
     *   totales: array<string, float|int>,
     *   periodo_texto: string,
     *   lista_costo: string
     * }
     */
    public function generar(array $filtros, bool $paginar = true, int $perPage = 25): array
    {
        $filasRaw = $this->query->listado($filtros, $paginar, $perPage);
        $totales = $this->query->totales($filtros);

        $fechaCostoDefault = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($fechaCostoDefault === '') {
            $fechaCostoDefault = now()->toDateString();
        }

        $listasDefault = GastronomiaInformeGerenteCostoListaSupport::listasDesdeFechaJornada($fechaCostoDefault);
        $listaCostoCodigo = (string) ($listasDefault['lista_actual'] ?? '');
        $listaCostoId = $this->resolverListaprecioId($listaCostoCodigo);

        $cacheCosto = [];
        $cacheListaMes = [];
        $itemsBase = $filasRaw instanceof LengthAwarePaginator
            ? collect($filasRaw->items())
            : $filasRaw;
        $this->precargarCacheCostos(
            $itemsBase,
            $cacheCosto,
            $cacheListaMes,
            $fechaCostoDefault,
        );

        $mapear = function (object $row) use (&$cacheCosto, &$cacheListaMes, $listaCostoId, $fechaCostoDefault): object {
            return $this->enriquecerFila($row, $cacheCosto, $cacheListaMes, $listaCostoId, $fechaCostoDefault);
        };

        if ($filasRaw instanceof LengthAwarePaginator) {
            $items = $itemsBase->map($mapear)->values();
            GastronomiaAnaliticoPrecioCeroSupport::enriquecer($items);
            if (GastronomiaAnaliticoReporteFiltros::debeSepararPorEmpresa($filtros)) {
                $items = $this->insertarHeadersEmpresa($items, true);
            }
            $filasRaw->setCollection($items);
            $filas = $filasRaw;
        } else {
            $filas = $itemsBase->map($mapear)->values();
            GastronomiaAnaliticoPrecioCeroSupport::enriquecer($filas);
            if (GastronomiaAnaliticoReporteFiltros::debeSepararPorEmpresa($filtros)) {
                $filas = $this->insertarHeadersEmpresa($filas, false);
            }
        }

        $costoTotal = 0.0;
        foreach ($filas instanceof LengthAwarePaginator ? $filas->getCollection() : $filas as $f) {
            $costoTotal += (float) ($f->costo ?? 0);
        }
        // Para export/pagina: el total de costo del filtro completo se recalcula solo en export sin paginar.
        if (! $paginar) {
            $totales['costo_total'] = round($costoTotal, 2);
        } else {
            $totales['costo_total_pagina'] = round($costoTotal, 2);
        }

        return [
            'filas' => $filas,
            'totales' => $totales,
            'periodo_texto' => GastronomiaAnaliticoReporteFiltros::formatearPeriodoTexto($filtros),
            'lista_costo' => $listaCostoCodigo,
        ];
    }

    /**
     * Paginación en memoria desde snapshot cacheado.
     *
     * @param  Collection<int, object>|array<int, object>  $filas
     */
    public function paginarFilas(Collection|array $filas, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $coleccion = $filas instanceof Collection ? $filas->values() : collect($filas)->values();
        $perPage = max(10, min(200, $perPage));
        $page = max(1, $page);
        $total = $coleccion->count();
        $items = $coleccion->slice(($page - 1) * $perPage, $perPage)->values();

        return new PaginatorImpl(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
    }

    /**
     * Carga en una sola query los precios de lista necesarios y arma el cache
     * articulo|lista|fecha (misma semántica que PrecioService::asignaPrecioPorLista).
     *
     * @param  Collection<int, object>  $filas
     * @param  array<string, float>  $cacheCosto
     * @param  array<string, int|null>  $cacheListaMes
     */
    private function precargarCacheCostos(
        Collection $filas,
        array &$cacheCosto,
        array &$cacheListaMes,
        string $fechaCostoDefault,
    ): void {
        if ($filas->isEmpty()) {
            return;
        }

        /** @var array<int, true> $articuloIds */
        $articuloIds = [];
        /** @var array<string, array{articulo_id: int, lista_id: int, fecha: string}> $claves */
        $claves = [];

        foreach ($filas as $row) {
            $articuloId = (int) ($row->articulo_id ?? 0);
            if ($articuloId <= 0) {
                continue;
            }

            $fechaJornada = $this->ymd($row->fechajornada ?? null);
            $fechaCosto = $fechaJornada !== '' ? $fechaJornada : $fechaCostoDefault;
            if ($fechaCosto === '') {
                continue;
            }

            try {
                $mesKey = Carbon::parse($fechaCosto)->format('Y-m');
            } catch (\Throwable) {
                continue;
            }
            if (! array_key_exists($mesKey, $cacheListaMes)) {
                $listas = GastronomiaInformeGerenteCostoListaSupport::listasDesdeFechaJornada($fechaCosto);
                $cacheListaMes[$mesKey] = $this->resolverListaprecioId((string) $listas['lista_actual']);
            }
            $listaId = $cacheListaMes[$mesKey];
            if ($listaId === null) {
                continue;
            }

            $articuloIds[$articuloId] = true;
            $key = $articuloId.'|'.$listaId.'|'.$fechaCosto;
            $claves[$key] = [
                'articulo_id' => $articuloId,
                'lista_id' => $listaId,
                'fecha' => $fechaCosto,
            ];
        }

        if ($articuloIds === [] || $claves === []) {
            return;
        }

        $listaIds = array_values(array_unique(array_filter(array_column($claves, 'lista_id'))));
        if ($listaIds === []) {
            return;
        }

        /** @var array<string, list<array{fecha: string, precio: float}>> $porArticuloLista */
        $porArticuloLista = [];
        Precio::query()
            ->select(['articulo_id', 'listaprecio_id', 'fechavigencia', 'precio'])
            ->whereIn('articulo_id', array_keys($articuloIds))
            ->whereIn('listaprecio_id', $listaIds)
            ->orderBy('fechavigencia')
            ->get()
            ->each(function ($precio) use (&$porArticuloLista): void {
                $grupo = ((int) $precio->articulo_id).'|'.((int) $precio->listaprecio_id);
                $porArticuloLista[$grupo][] = [
                    'fecha' => $this->ymd($precio->fechavigencia),
                    'precio' => (float) $precio->precio,
                ];
            });

        foreach ($claves as $key => $meta) {
            $grupo = $meta['articulo_id'].'|'.$meta['lista_id'];
            $vigencias = $porArticuloLista[$grupo] ?? [];
            $precioRet = 0.0;
            foreach ($vigencias as $vigencia) {
                if ($vigencia['fecha'] !== '' && $vigencia['fecha'] <= $meta['fecha']) {
                    $precioRet = $vigencia['precio'];
                }
            }
            $cacheCosto[$key] = round($precioRet, 2);
        }
    }

    /**
     * @param  array<string, float>  $cacheCosto
     * @param  array<string, int|null>  $cacheListaMes
     */
    private function enriquecerFila(
        object $row,
        array &$cacheCosto,
        array &$cacheListaMes,
        ?int $listaCostoIdDefault,
        string $fechaCostoDefault,
    ): object {
        $fechaJornada = $this->ymd($row->fechajornada ?? null);
        $fechaReal = $this->ymd($row->fecha_real ?? null);
        $horaFuente = $row->venta_created_at ?? $row->fecha_real ?? null;

        $anio = '';
        $mes = '';
        $dia = '';
        $hora = '';
        if ($fechaJornada !== '') {
            try {
                $dj = Carbon::parse($fechaJornada);
                $anio = $dj->format('Y');
                $mes = $dj->format('m');
                $dia = $dj->format('d');
            } catch (\Throwable) {
                // keep empty
            }
        }
        if ($horaFuente) {
            try {
                $hora = Carbon::parse($horaFuente)->format('H:i');
            } catch (\Throwable) {
                $hora = '';
            }
        }

        $cantidad = round((float) ($row->cantidad ?? 0), 4);
        $precio = round((float) ($row->precio_unitario ?? 0), 2);
        $total = round((float) ($row->total ?? 0), 2);

        $listaId = $listaCostoIdDefault;
        $fechaCosto = $fechaJornada !== '' ? $fechaJornada : $fechaCostoDefault;
        if ($fechaCosto !== '') {
            $mesKey = Carbon::parse($fechaCosto)->format('Y-m');
            if (! array_key_exists($mesKey, $cacheListaMes)) {
                $listas = GastronomiaInformeGerenteCostoListaSupport::listasDesdeFechaJornada($fechaCosto);
                $cacheListaMes[$mesKey] = $this->resolverListaprecioId((string) $listas['lista_actual']);
            }
            $listaId = $cacheListaMes[$mesKey];
        }

        $costoUnit = $this->resolverPrecioUnitario(
            (int) ($row->articulo_id ?? 0),
            $listaId,
            $fechaCosto,
            $cacheCosto,
        );
        $costo = round(abs($cantidad) * $costoUnit * ($cantidad < 0 ? -1 : 1), 2);

        $row->fecha_jornada = $fechaJornada;
        $row->fecha_jornada_fmt = $this->fmtFecha($fechaJornada);
        $row->fecha_real = $fechaReal;
        $row->fecha_real_fmt = $this->fmtFecha($fechaReal);
        $row->cantidad = $cantidad;
        $row->precio_unitario = $precio;
        $row->total = $total;
        $row->costo_unitario = $costoUnit;
        $row->costo = $costo;
        $row->tipo_venta = trim((string) ($row->tipo_venta ?? 'venta'));
        $row->tipo_descuento = trim((string) ($row->tipo_descuento ?? ''));
        $row->observacion_precio = '';
        $row->categoria_articulo = trim((string) ($row->categoria_articulo ?? ''));
        $row->cliente = trim((string) ($row->cliente ?? ''));
        $row->sala = trim((string) ($row->sala ?? ''));
        $row->nombre_mozo = trim((string) ($row->nombre_mozo ?? ''));
        $row->legajo_mozo = trim((string) ($row->legajo_mozo ?? ''));
        $row->anio = $anio;
        $row->mes = $mes;
        $row->dia = $dia;
        $row->hora = $hora;
        $row->numero_comprobante_fmt = $this->fmtNumeroComprobante($row);
        $row->tipo_fila = 'detalle';
        $row->nombreempresa = trim((string) ($row->nombreempresa ?? $row->sala ?? ''));

        return $row;
    }

    /**
     * Inserta filas header_empresa al cambiar de empresa (modo no consolidado).
     *
     * @param  Collection<int, object>  $filas
     * @return Collection<int, object>
     */
    private function insertarHeadersEmpresa(Collection $filas, bool $repetirHeaderAlInicioPagina): Collection
    {
        if ($filas->isEmpty()) {
            return $filas;
        }

        $out = collect();
        $empresaActual = null;

        foreach ($filas as $idx => $row) {
            $empresaId = (int) ($row->empresa_id ?? 0);
            $nombre = trim((string) ($row->nombreempresa ?? $row->sala ?? ''));
            if ($nombre === '') {
                $nombre = $empresaId > 0 ? (string) $empresaId : '—';
            }

            $cambiar = $empresaId !== $empresaActual;
            if ($cambiar || ($repetirHeaderAlInicioPagina && $idx === 0)) {
                $out->push((object) [
                    'tipo_fila' => 'header_empresa',
                    'empresa_id' => $empresaId,
                    'nombreempresa' => $nombre,
                    'sala' => $nombre,
                ]);
                $empresaActual = $empresaId;
            }

            $out->push($row);
        }

        return $out->values();
    }

    private function fmtNumeroComprobante(object $row): string
    {
        $nro = trim((string) ($row->numero_comprobante ?? ''));
        $pv = trim((string) ($row->punto_venta ?? ''));
        $tipo = trim((string) ($row->tipo_comprobante ?? ''));
        if ($nro === '') {
            return trim((string) ($row->venta_codigo ?? ''));
        }

        $parts = array_filter([$tipo, $pv !== '' ? $pv : null, $nro], static fn ($v) => $v !== null && $v !== '');

        return implode(' ', $parts);
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

        // Fallback puntual si no se precargó (no debería ocurrir tras precargarCacheCostos).
        $cache[$key] = 0.0;

        return 0.0;
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

    private function ymd(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }
        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    private function fmtFecha(string $ymd): string
    {
        if ($ymd === '') {
            return '—';
        }
        try {
            return Carbon::parse($ymd)->format('d/m/Y');
        } catch (\Throwable) {
            return $ymd;
        }
    }
}
