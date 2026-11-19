<?php

namespace App\Services\Compras;

use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\HistorialPreciosArticuloFiltros;
use App\Support\Compras\OrdencompraReporteCriteriosSupport;
use App\Support\Stock\ArticuloPrecioUltimaCompraSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HistorialPreciosArticuloService
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     totales: array{total_articulos: int, total_compras: int, con_variacion: int, sin_variacion: int},
     *     meses: list<string>
     * }
     */
    public function generar(array $filtros): array
    {
        $compras = $this->consultarCompras($filtros);
        $modo = (string) ($filtros['modo'] ?? HistorialPreciosArticuloFiltros::MODO_RESUMEN);
        $meses = HistorialPreciosArticuloFiltros::usaColumnasMes($modo)
            ? HistorialPreciosArticuloFiltros::mesesDelPeriodo($filtros)
            : [];

        $filas = match ($modo) {
            HistorialPreciosArticuloFiltros::MODO_DETALLE => $this->armarDetalle($compras, $filtros),
            HistorialPreciosArticuloFiltros::MODO_MENSUAL => $this->armarMensual($compras, $filtros, $meses),
            HistorialPreciosArticuloFiltros::MODO_MENSUAL_OC => $this->armarMensualOc($compras, $meses),
            default => $this->armarResumen($compras, $filtros),
        };

        if (! empty($filtros['solo_con_variacion'])
            && $modo !== HistorialPreciosArticuloFiltros::MODO_MENSUAL_OC) {
            $filas = array_values(array_filter(
                $filas,
                static function (array $f) use ($modo): bool {
                    if ($modo === HistorialPreciosArticuloFiltros::MODO_MENSUAL) {
                        if (($f['variacion_periodo_pct'] ?? null) !== null
                            && (float) $f['variacion_periodo_pct'] != 0.0) {
                            return true;
                        }
                        foreach ($f['variaciones_pct_mes'] ?? [] as $pct) {
                            if ($pct !== null && (float) $pct != 0.0) {
                                return true;
                            }
                        }

                        return false;
                    }

                    return ($f['variacion_pct'] ?? null) !== null
                        && (float) $f['variacion_pct'] != 0.0;
                },
            ));
        }

        return [
            'filas' => $filas,
            'totales' => $this->totalesDesdeFilas($filas, $modo),
            'meses' => $meses,
        ];
    }

    public function paginarFilas(array $filas, int $perPage, int $page): LengthAwarePaginator
    {
        $page = max(1, $page);
        $perPage = max(10, min(500, $perPage));
        $total = count($filas);
        $offset = ($page - 1) * $perPage;

        return new LengthAwarePaginator(
            array_slice($filas, $offset, $perPage),
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>|null  $empresaQuery
     */
    public function subtituloFiltros(array $filtros, $empresaQuery = null): string
    {
        $partes = [];

        $ids = $filtros['empresa_ids'] ?? [];
        if ($ids !== [] && $empresaQuery !== null) {
            $nombres = collect($empresaQuery)
                ->whereIn('id', $ids)
                ->pluck('nombre')
                ->filter()
                ->values()
                ->all();
            if ($nombres !== []) {
                $txt = 'Empresas: '.implode(', ', $nombres);
                if (count($ids) > 1 && ! empty($filtros['consolidar_empresas'])) {
                    $txt .= ' (consolidado)';
                }
                $partes[] = $txt;
            }
        }

        $partes[] = 'Período: '.HistorialPreciosArticuloFiltros::formatearPeriodoTexto($filtros);
        $partes[] = HistorialPreciosArticuloFiltros::etiquetaModo(
            (string) ($filtros['modo'] ?? HistorialPreciosArticuloFiltros::MODO_RESUMEN),
        );
        $partes[] = HistorialPreciosArticuloFiltros::etiquetaAgrupacion(
            (string) ($filtros['agrupacion'] ?? HistorialPreciosArticuloFiltros::AGRUPACION_ARTICULO),
        );

        if (! empty($filtros['articulo_id'])) {
            $partes[] = 'Artículo ID: '.(int) $filtros['articulo_id'];
        }
        if (($filtros['sku'] ?? '') !== '') {
            $partes[] = 'SKU: '.$filtros['sku'];
        }

        $subProv = OrdencompraReporteCriteriosSupport::subtituloProveedores($filtros);
        if ($subProv !== null) {
            $partes[] = $subProv;
        }

        if (! empty($filtros['tipoarticulo_id'])) {
            $txt = trim((string) (($filtros['tipoarticulo_codigo'] ?? '').' '.($filtros['tipoarticulo_nombre'] ?? '')));
            $partes[] = 'Tipo: '.($txt !== '' ? $txt : 'ID '.$filtros['tipoarticulo_id']);
        }
        if (! empty($filtros['usoarticulo_id'])) {
            $txt = trim((string) ($filtros['usoarticulo_nombre'] ?? ''));
            $partes[] = 'Uso: '.($txt !== '' ? $txt : 'ID '.$filtros['usoarticulo_id']);
        }
        if (! empty($filtros['categoria_id'])) {
            $txt = trim((string) (($filtros['categoria_codigo'] ?? '').' — '.($filtros['categoria_nombre'] ?? '')));
            $partes[] = 'Categoría: '.($txt !== ' — ' ? $txt : 'ID '.$filtros['categoria_id']);
        }
        if (! empty($filtros['subcategoria_id'])) {
            $txt = trim((string) (($filtros['subcategoria_codigo'] ?? '').' — '.($filtros['subcategoria_nombre'] ?? '')));
            $partes[] = 'Subcategoría: '.($txt !== ' — ' ? $txt : 'ID '.$filtros['subcategoria_id']);
        }

        if (! empty($filtros['solo_con_variacion'])) {
            $partes[] = 'Solo con variación de precio';
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, object>
     */
    public function consultarCompras(array $filtros): Collection
    {
        $query = DB::table('recepcion_proveedor_articulo as rpa')
            ->join('recepcion_proveedor as rp', 'rp.id', '=', 'rpa.recepcion_proveedor_id')
            ->join('empresa as e', 'e.id', '=', 'rp.empresa_id')
            ->join('proveedor as p', 'p.id', '=', 'rp.proveedor_id')
            ->join('articulo as a', 'a.id', '=', 'rpa.articulo_id')
            ->leftJoin('moneda as m', 'm.id', '=', 'rpa.moneda_id')
            ->leftJoin('ordencompra as oc', 'oc.id', '=', 'rp.ordencompra_id')
            ->where('rp.estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('rp.tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('rpa.precio', '>', 0)
            ->select([
                'rpa.id as linea_id',
                'rpa.articulo_id',
                'rpa.precio',
                'rpa.moneda_id',
                'rpa.descuento',
                'rpa.cantidad',
                'rp.id as recepcion_id',
                'rp.fecha',
                'rp.numerorecepcion',
                'rp.empresa_id',
                'rp.proveedor_id',
                'rp.ordencompra_id',
                'e.nombre as nombreempresa',
                'p.codigo as codigoproveedor',
                'p.nombre as nombreproveedor',
                'a.sku',
                'a.descripcion as descripcion_articulo',
                'm.abreviatura as moneda_abrev',
                'oc.numeroordencompra',
            ])
            ->orderBy('a.sku')
            ->orderByDesc('rp.fecha')
            ->orderByDesc('rp.id')
            ->orderByDesc('rpa.id');

        if (Schema::hasColumn('recepcion_proveedor', 'deleted_at')) {
            $query->whereNull('rp.deleted_at');
        }

        $this->aplicarFiltros($query, $filtros);

        $compras = $query->get();

        return $this->excluirComCubiertasPorDep($compras);
    }

    /**
     * Anula precios de COM cuya cantidad del artículo quedó cubierta por DEP confirmadas.
     * No agrega líneas DEP: solo saca esas COM de la serie de precios.
     *
     * @param  Collection<int, object>  $compras
     * @return Collection<int, object>
     */
    private function excluirComCubiertasPorDep(Collection $compras): Collection
    {
        if ($compras->isEmpty()) {
            return $compras;
        }

        $cubiertas = ArticuloPrecioUltimaCompraSupport::clavesRecepcionArticuloCubiertasPorDep(
            $compras->pluck('recepcion_id')->all()
        );
        if ($cubiertas === []) {
            return $compras;
        }

        return $compras
            ->reject(static function (object $compra) use ($cubiertas): bool {
                $clave = (int) ($compra->recepcion_id ?? 0).'|'.(int) ($compra->articulo_id ?? 0);

                return isset($cubiertas[$clave]);
            })
            ->values();
    }

    /**
     * @param  Collection<int, object>  $compras
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function armarResumen(Collection $compras, array $filtros): array
    {
        $agrupacion = (string) ($filtros['agrupacion'] ?? HistorialPreciosArticuloFiltros::AGRUPACION_ARTICULO);
        $porClave = [];

        foreach ($compras as $compra) {
            $clave = $this->claveAgrupacion($compra, $agrupacion);
            if (! isset($porClave[$clave])) {
                $porClave[$clave] = [];
            }
            if (count($porClave[$clave]) >= 2) {
                continue;
            }
            $porClave[$clave][] = $compra;
        }

        $filas = [];
        foreach ($porClave as $comprasGrupo) {
            $ultima = $comprasGrupo[0];
            $anterior = $comprasGrupo[1] ?? null;
            $filas[] = $this->filaDesdeUltimaYAnterior($ultima, $anterior, 'resumen');
        }

        usort($filas, static function (array $a, array $b): int {
            $cmpSku = strcmp((string) ($a['sku'] ?? ''), (string) ($b['sku'] ?? ''));
            if ($cmpSku !== 0) {
                return $cmpSku;
            }

            return strcmp((string) ($a['codigoproveedor'] ?? ''), (string) ($b['codigoproveedor'] ?? ''));
        });

        return $filas;
    }

    /**
     * @param  Collection<int, object>  $compras
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function armarDetalle(Collection $compras, array $filtros): array
    {
        $agrupacion = (string) ($filtros['agrupacion'] ?? HistorialPreciosArticuloFiltros::AGRUPACION_ARTICULO);
        $porClave = [];

        foreach ($compras as $compra) {
            $clave = $this->claveAgrupacion($compra, $agrupacion);
            if (! isset($porClave[$clave])) {
                $porClave[$clave] = [];
            }
            $porClave[$clave][] = $compra;
        }

        $filas = [];
        foreach ($porClave as $comprasGrupo) {
            $totalGrupo = count($comprasGrupo);
            for ($i = 0; $i < $totalGrupo; $i++) {
                $actual = $comprasGrupo[$i];
                $anterior = $comprasGrupo[$i + 1] ?? null;
                $filas[] = $this->filaDesdeUltimaYAnterior($actual, $anterior, 'detalle');
            }
        }

        usort($filas, static function (array $a, array $b): int {
            $cmpSku = strcmp((string) ($a['sku'] ?? ''), (string) ($b['sku'] ?? ''));
            if ($cmpSku !== 0) {
                return $cmpSku;
            }
            $cmpProv = strcmp((string) ($a['codigoproveedor'] ?? ''), (string) ($b['codigoproveedor'] ?? ''));
            if ($cmpProv !== 0) {
                return $cmpProv;
            }
            $fechaA = (string) ($a['fecha_ultima'] ?? '');
            $fechaB = (string) ($b['fecha_ultima'] ?? '');

            return strcmp($fechaB, $fechaA);
        });

        return $filas;
    }

    /**
     * Último precio de cada mes del período + variación % vs el mes anterior con precio.
     *
     * @param  Collection<int, object>  $compras
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $meses
     * @return list<array<string, mixed>>
     */
    private function armarMensual(Collection $compras, array $filtros, array $meses): array
    {
        if ($meses === []) {
            return [];
        }

        $agrupacion = (string) ($filtros['agrupacion'] ?? HistorialPreciosArticuloFiltros::AGRUPACION_ARTICULO);
        $mesesSet = array_fill_keys($meses, true);
        /** @var array<string, array{meta: object, precios: array<string, float>, monedas: array<string, string>}> $porClave */
        $porClave = [];

        foreach ($compras as $compra) {
            $fecha = (string) ($compra->fecha ?? '');
            if ($fecha === '') {
                continue;
            }
            try {
                $ym = Carbon::parse($fecha)->format('Y-m');
            } catch (\Throwable) {
                continue;
            }
            if (! isset($mesesSet[$ym])) {
                continue;
            }

            $clave = $this->claveAgrupacion($compra, $agrupacion);
            if (! isset($porClave[$clave])) {
                $porClave[$clave] = [
                    'meta' => $compra,
                    'precios' => [],
                    'monedas' => [],
                ];
            }
            // compras vienen ordenadas fecha DESC: el primero del mes es el último precio.
            if (! isset($porClave[$clave]['precios'][$ym])) {
                $porClave[$clave]['precios'][$ym] = round((float) ($compra->precio ?? 0), 6);
                $porClave[$clave]['monedas'][$ym] = (string) ($compra->moneda_abrev ?? '');
            }
        }

        $filas = [];
        foreach ($porClave as $grupo) {
            $meta = $grupo['meta'];
            $preciosMes = [];
            $variacionesPctMes = [];
            $precioAnterior = null;
            $primerPrecio = null;
            $ultimoPrecio = null;
            $monedaAbrev = '';

            foreach ($meses as $ym) {
                $precio = $grupo['precios'][$ym] ?? null;
                $preciosMes[$ym] = $precio;
                if ($precio === null) {
                    $variacionesPctMes[$ym] = null;
                    continue;
                }
                if ($monedaAbrev === '' && ($grupo['monedas'][$ym] ?? '') !== '') {
                    $monedaAbrev = $grupo['monedas'][$ym];
                }
                if ($primerPrecio === null) {
                    $primerPrecio = $precio;
                }
                if ($precioAnterior !== null && $precioAnterior > 0) {
                    $variacionesPctMes[$ym] = round((($precio - $precioAnterior) / $precioAnterior) * 100, 2);
                } else {
                    $variacionesPctMes[$ym] = null;
                }
                $precioAnterior = $precio;
                $ultimoPrecio = $precio;
            }

            $variacionPeriodo = null;
            if ($primerPrecio !== null && $primerPrecio > 0 && $ultimoPrecio !== null) {
                $variacionPeriodo = round((($ultimoPrecio - $primerPrecio) / $primerPrecio) * 100, 2);
            }

            $filas[] = [
                'tipo_fila' => 'mensual',
                'articulo_id' => (int) ($meta->articulo_id ?? 0),
                'sku' => (string) ($meta->sku ?? ''),
                'descripcion_articulo' => (string) ($meta->descripcion_articulo ?? ''),
                'proveedor_id' => (int) ($meta->proveedor_id ?? 0),
                'codigoproveedor' => (string) ($meta->codigoproveedor ?? ''),
                'nombreproveedor' => (string) ($meta->nombreproveedor ?? ''),
                'moneda_abrev' => $monedaAbrev,
                'empresa_id' => (int) ($meta->empresa_id ?? 0),
                'nombreempresa' => (string) ($meta->nombreempresa ?? ''),
                'precios_mes' => $preciosMes,
                'variaciones_pct_mes' => $variacionesPctMes,
                'variacion_periodo_pct' => $variacionPeriodo,
                'variacion_pct' => $variacionPeriodo,
                'precio_ultimo' => $ultimoPrecio,
                'precio_anterior' => $primerPrecio,
            ];
        }

        usort($filas, static function (array $a, array $b): int {
            $cmpSku = strcmp((string) ($a['sku'] ?? ''), (string) ($b['sku'] ?? ''));
            if ($cmpSku !== 0) {
                return $cmpSku;
            }

            return strcmp((string) ($a['codigoproveedor'] ?? ''), (string) ($b['codigoproveedor'] ?? ''));
        });

        return $filas;
    }

    /**
     * Una fila por compra (COM) con el precio en la columna del mes de esa recepción.
     * Equivale a la solapa «Reporte» del control items histórico (Empresa / Descripción / OC × mes).
     *
     * @param  Collection<int, object>  $compras
     * @param  list<string>  $meses
     * @return list<array<string, mixed>>
     */
    private function armarMensualOc(Collection $compras, array $meses): array
    {
        if ($meses === []) {
            return [];
        }

        $mesesSet = array_fill_keys($meses, true);
        $filas = [];

        // Orden cronológico ascendente para el listado tipo pivot (mes a mes).
        $ordenadas = $compras->sort(static function (object $a, object $b): int {
            $cmpFecha = strcmp((string) ($a->fecha ?? ''), (string) ($b->fecha ?? ''));
            if ($cmpFecha !== 0) {
                return $cmpFecha;
            }
            $cmpSku = strcmp((string) ($a->sku ?? ''), (string) ($b->sku ?? ''));
            if ($cmpSku !== 0) {
                return $cmpSku;
            }

            return ((int) ($a->recepcion_id ?? 0)) <=> ((int) ($b->recepcion_id ?? 0));
        })->values();

        foreach ($ordenadas as $compra) {
            $fecha = (string) ($compra->fecha ?? '');
            if ($fecha === '') {
                continue;
            }
            try {
                $ym = Carbon::parse($fecha)->format('Y-m');
            } catch (\Throwable) {
                continue;
            }
            if (! isset($mesesSet[$ym])) {
                continue;
            }

            $preciosMes = [];
            foreach ($meses as $mes) {
                $preciosMes[$mes] = $mes === $ym
                    ? round((float) ($compra->precio ?? 0), 6)
                    : null;
            }

            $filas[] = [
                'tipo_fila' => 'mensual_oc',
                'articulo_id' => (int) ($compra->articulo_id ?? 0),
                'sku' => (string) ($compra->sku ?? ''),
                'descripcion_articulo' => (string) ($compra->descripcion_articulo ?? ''),
                'proveedor_id' => (int) ($compra->proveedor_id ?? 0),
                'codigoproveedor' => (string) ($compra->codigoproveedor ?? ''),
                'nombreproveedor' => (string) ($compra->nombreproveedor ?? ''),
                'moneda_abrev' => (string) ($compra->moneda_abrev ?? ''),
                'empresa_id' => (int) ($compra->empresa_id ?? 0),
                'nombreempresa' => (string) ($compra->nombreempresa ?? ''),
                'recepcion_id' => (int) ($compra->recepcion_id ?? 0),
                'numerorecepcion' => (string) ($compra->numerorecepcion ?? ''),
                'ordencompra_id' => isset($compra->ordencompra_id) ? (int) $compra->ordencompra_id : null,
                'numeroordencompra' => (string) ($compra->numeroordencompra ?? ''),
                'fecha_ultima' => $fecha,
                'precios_mes' => $preciosMes,
                'variaciones_pct_mes' => [],
                'variacion_periodo_pct' => null,
                'variacion_pct' => null,
                'precio_ultimo' => round((float) ($compra->precio ?? 0), 6),
                'precio_anterior' => null,
            ];
        }

        return $filas;
    }

    /**
     * @return array<string, mixed>
     */
    private function filaDesdeUltimaYAnterior(object $ultima, ?object $anterior, string $tipoFila): array
    {
        $precioUltimo = round((float) ($ultima->precio ?? 0), 6);
        $precioAnterior = $anterior !== null ? round((float) ($anterior->precio ?? 0), 6) : null;
        $variacionPct = null;
        $variacionAbs = null;

        if ($precioAnterior !== null && $precioAnterior > 0) {
            $variacionAbs = round($precioUltimo - $precioAnterior, 6);
            $variacionPct = round(($variacionAbs / $precioAnterior) * 100, 2);
        }

        return [
            'tipo_fila' => $tipoFila,
            'articulo_id' => (int) ($ultima->articulo_id ?? 0),
            'sku' => (string) ($ultima->sku ?? ''),
            'descripcion_articulo' => (string) ($ultima->descripcion_articulo ?? ''),
            'proveedor_id' => (int) ($ultima->proveedor_id ?? 0),
            'codigoproveedor' => (string) ($ultima->codigoproveedor ?? ''),
            'nombreproveedor' => (string) ($ultima->nombreproveedor ?? ''),
            'precio_ultimo' => $precioUltimo,
            'precio_anterior' => $precioAnterior,
            'variacion_abs' => $variacionAbs,
            'variacion_pct' => $variacionPct,
            'fecha_ultima' => (string) ($ultima->fecha ?? ''),
            'fecha_anterior' => $anterior !== null ? (string) ($anterior->fecha ?? '') : null,
            'moneda_id' => isset($ultima->moneda_id) ? (int) $ultima->moneda_id : null,
            'moneda_abrev' => (string) ($ultima->moneda_abrev ?? ''),
            'recepcion_id' => (int) ($ultima->recepcion_id ?? 0),
            'numerorecepcion' => (string) ($ultima->numerorecepcion ?? ''),
            'ordencompra_id' => isset($ultima->ordencompra_id) ? (int) $ultima->ordencompra_id : null,
            'numeroordencompra' => (string) ($ultima->numeroordencompra ?? ''),
            'empresa_id' => (int) ($ultima->empresa_id ?? 0),
            'nombreempresa' => (string) ($ultima->nombreempresa ?? ''),
            'cantidad' => round((float) ($ultima->cantidad ?? 0), 6),
            'descuento' => round((float) ($ultima->descuento ?? 0), 4),
        ];
    }

    private function claveAgrupacion(object $compra, string $agrupacion): string
    {
        $articuloId = (int) ($compra->articulo_id ?? 0);
        if ($agrupacion === HistorialPreciosArticuloFiltros::AGRUPACION_ARTICULO_PROVEEDOR) {
            return $articuloId.'|'.(int) ($compra->proveedor_id ?? 0);
        }

        return (string) $articuloId;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{total_articulos: int, total_compras: int, con_variacion: int, sin_variacion: int}
     */
    private function totalesDesdeFilas(array $filas, string $modo): array
    {
        $articulos = [];
        $conVariacion = 0;
        $sinVariacion = 0;

        foreach ($filas as $fila) {
            $articulos[(int) ($fila['articulo_id'] ?? 0)] = true;
            if (($fila['variacion_pct'] ?? null) !== null && (float) $fila['variacion_pct'] != 0.0) {
                $conVariacion++;
            } else {
                $sinVariacion++;
            }
        }

        return [
            'total_articulos' => count(array_filter(array_keys($articulos), static fn ($id) => (int) $id > 0)),
            'total_compras' => count($filas),
            'con_variacion' => $conVariacion,
            'sin_variacion' => $sinVariacion,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros(Builder $query, array $filtros): void
    {
        $empresaIds = array_values(array_filter(
            array_map('intval', $filtros['empresa_ids'] ?? []),
            static fn (int $id) => $id > 0,
        ));
        if ($empresaIds !== []) {
            $query->whereIn('rp.empresa_id', $empresaIds);
        } else {
            $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
            if ($asignadas !== []) {
                $query->whereIn('rp.empresa_id', $asignadas);
            }
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('rp.fecha', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('rp.fecha', '<=', $filtros['fecha_hasta']);
        }

        if (! empty($filtros['articulo_id'])) {
            $query->where('rpa.articulo_id', (int) $filtros['articulo_id']);
        }

        $sku = trim((string) ($filtros['sku'] ?? ''));
        if ($sku !== '') {
            $query->where('a.sku', 'like', '%'.$sku.'%');
        }

        OrdencompraReporteCriteriosSupport::aplicarFiltroProveedoresCodigo(
            $query,
            (string) ($filtros['proveedores'] ?? ''),
            'p.codigo',
        );

        if (! empty($filtros['tipoarticulo_id'])) {
            $query->where('a.tipoarticulo_id', (int) $filtros['tipoarticulo_id']);
        }
        if (! empty($filtros['usoarticulo_id'])) {
            $query->where('a.usoarticulo_id', (int) $filtros['usoarticulo_id']);
        }
        if (! empty($filtros['categoria_id'])) {
            $query->where('a.categoria_id', (int) $filtros['categoria_id']);
        }
        if (! empty($filtros['subcategoria_id'])) {
            $query->where('a.subcategoria_id', (int) $filtros['subcategoria_id']);
        }
    }
}
