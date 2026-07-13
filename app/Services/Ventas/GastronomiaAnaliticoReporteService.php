<?php

namespace App\Services\Ventas;

use App\Models\Stock\Listaprecio;
use App\Queries\Ventas\GastronomiaAnaliticoReporteQuery;
use App\Services\Stock\PrecioService;
use App\Support\Ventas\Gastronomia\GastronomiaInformeGerenteCostoListaSupport;
use App\Support\Ventas\GastronomiaAnaliticoReporteFiltros;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

        $mapear = function (object $row) use (&$cacheCosto, &$cacheListaMes, $listaCostoId, $fechaCostoDefault): object {
            return $this->enriquecerFila($row, $cacheCosto, $cacheListaMes, $listaCostoId, $fechaCostoDefault);
        };

        if ($filasRaw instanceof LengthAwarePaginator) {
            $items = collect($filasRaw->items())->map($mapear)->values();
            $filasRaw->setCollection($items);
            $filas = $filasRaw;
        } else {
            $filas = $filasRaw->map($mapear)->values();
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

        return $row;
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
