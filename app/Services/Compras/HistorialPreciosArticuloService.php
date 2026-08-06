<?php

namespace App\Services\Compras;

use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Compras\HistorialPreciosArticuloFiltros;
use App\Support\Compras\OrdencompraReporteCriteriosSupport;
use App\Support\Stock\RecepcionProveedorEstados;
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
     *     totales: array{total_articulos: int, total_compras: int, con_variacion: int, sin_variacion: int}
     * }
     */
    public function generar(array $filtros): array
    {
        $compras = $this->consultarCompras($filtros);
        $modo = (string) ($filtros['modo'] ?? HistorialPreciosArticuloFiltros::MODO_RESUMEN);

        $filas = $modo === HistorialPreciosArticuloFiltros::MODO_DETALLE
            ? $this->armarDetalle($compras, $filtros)
            : $this->armarResumen($compras, $filtros);

        if (! empty($filtros['solo_con_variacion'])) {
            $filas = array_values(array_filter(
                $filas,
                static fn (array $f) => ($f['variacion_pct'] ?? null) !== null
                    && (float) $f['variacion_pct'] != 0.0,
            ));
        }

        return [
            'filas' => $filas,
            'totales' => $this->totalesDesdeFilas($filas, $modo),
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

        return $query->get();
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
            'total_compras' => $modo === HistorialPreciosArticuloFiltros::MODO_DETALLE
                ? count($filas)
                : count($filas),
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
    }
}
