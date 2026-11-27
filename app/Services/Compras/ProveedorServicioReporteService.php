<?php

namespace App\Services\Compras;

use App\Support\Compras\OrdencompraReporteCriteriosSupport;
use App\Support\Compras\RequisicionReporteCriteriosSupport;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Proveedores con clientes de servicios cargados (proveedor_servicio).
 * Un renglón por cliente / medidor.
 */
class ProveedorServicioReporteService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<array<string, mixed>>, totales: array{renglones: int, proveedores: int}}
     */
    public function generar(array $filtros): array
    {
        $filas = $this->consulta($filtros)
            ->get()
            ->map(fn ($row) => $this->filaDesdeRegistro($row))
            ->all();

        return [
            'filas' => $filas,
            'totales' => $this->totales($filtros),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function paginar(array $filtros, int $perPage, int $page): LengthAwarePaginator
    {
        $perPage = max(10, min(500, $perPage));
        $paginador = $this->consulta($filtros)->paginate($perPage, ['*'], 'page', max(1, $page));
        $paginador->setCollection(
            $paginador->getCollection()->map(fn ($row) => $this->filaDesdeRegistro($row))
        );

        return $paginador;
    }

    /**
     * Totales del filtro completo (no solo la página visible).
     *
     * @param  array<string, mixed>  $filtros
     * @return array{renglones: int, proveedores: int}
     */
    public function totales(array $filtros): array
    {
        $row = $this->base($filtros)
            ->selectRaw('COUNT(ps.id) as renglones, COUNT(DISTINCT p.id) as proveedores')
            ->first();

        return [
            'renglones' => (int) ($row->renglones ?? 0),
            'proveedores' => (int) ($row->proveedores ?? 0),
        ];
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
                $partes[] = 'Empresas: '.implode(', ', $nombres);
            }
        }

        $prov = OrdencompraReporteCriteriosSupport::subtituloProveedores($filtros);
        if ($prov !== null) {
            $partes[] = $prov;
        }
        if (($filtros['estado'] ?? '') !== '') {
            $partes[] = 'Estado: '.$filtros['estado'];
        }
        if (($filtros['cliente'] ?? '') !== '') {
            $partes[] = 'Cliente / medidor: '.$filtros['cliente'];
        }
        if (($filtros['detalle'] ?? '') !== '') {
            $partes[] = 'Detalle: '.$filtros['detalle'];
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function consulta(array $filtros): Builder
    {
        return $this->base($filtros)
            ->select([
                'ps.id as servicio_id',
                'p.id as proveedor_id',
                'p.codigo as codigoproveedor',
                'p.nombre as proveedor_nombre',
                'p.nroinscripcion as cuit',
                'p.estado as proveedor_estado',
                't.nombre as tiposervicio',
                DB::raw(SqlDialectSupport::coalesce('e.nombre', 'pe.nombre').' as nombreempresa'),
                'ps.cliente',
                'ps.detalle',
            ])
            ->orderBy('p.nombre')
            ->orderBy('p.id')
            ->orderBy('ps.cliente')
            ->orderBy('ps.id');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function base(array $filtros): Builder
    {
        $query = DB::table('proveedor_servicio as ps')
            ->join('proveedor as p', 'p.id', '=', 'ps.proveedor_id')
            ->leftJoin('empresa as e', 'e.id', '=', 'ps.empresa_id')
            ->leftJoin('empresa as pe', 'pe.id', '=', 'p.empresa_id')
            ->leftJoin('tiposervicio_proveedor as t', 't.id', '=', 'p.tiposervicio_proveedor_id')
            ->whereNull('p.deleted_at');

        // Empresa efectiva: la del servicio; si no tiene, la del proveedor. Sin ninguna = vale para todas.
        $empresaIds = array_map('intval', $filtros['empresa_ids'] ?? []);
        if ($empresaIds !== []) {
            $efectiva = SqlDialectSupport::coalesce('ps.empresa_id', 'p.empresa_id');
            $query->where(function ($q) use ($empresaIds, $efectiva) {
                $placeholders = implode(',', array_fill(0, count($empresaIds), '?'));
                $q->whereRaw($efectiva.' IN ('.$placeholders.')', $empresaIds)
                    ->orWhereRaw($efectiva.' IS NULL');
            });
        }

        $proveedores = trim((string) ($filtros['proveedores'] ?? ''));
        if ($proveedores !== '' && RequisicionReporteCriteriosSupport::parseListaCodigos($proveedores) !== []) {
            OrdencompraReporteCriteriosSupport::aplicarFiltroProveedoresCodigo($query, $proveedores, 'p.codigo');
        }

        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $query->where('p.estado', $estado);
        }

        foreach (['cliente' => 'ps.cliente', 'detalle' => 'ps.detalle'] as $clave => $columna) {
            $texto = trim((string) ($filtros[$clave] ?? ''));
            if ($texto !== '') {
                $query->whereRaw(
                    SqlDialectSupport::lower($columna).' LIKE ?',
                    ['%'.mb_strtolower($texto).'%']
                );
            }
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function filaDesdeRegistro(object $row): array
    {
        return [
            'servicio_id' => (int) $row->servicio_id,
            'proveedor_id' => (int) $row->proveedor_id,
            'codigoproveedor' => (string) ($row->codigoproveedor ?? ''),
            'proveedor_nombre' => (string) ($row->proveedor_nombre ?? ''),
            'cuit' => (string) ($row->cuit ?? ''),
            'proveedor_estado' => (string) ($row->proveedor_estado ?? ''),
            'tiposervicio' => (string) ($row->tiposervicio ?? ''),
            'nombreempresa' => (string) ($row->nombreempresa ?? ''),
            'cliente' => (string) ($row->cliente ?? ''),
            'detalle' => (string) ($row->detalle ?? ''),
        ];
    }
}
