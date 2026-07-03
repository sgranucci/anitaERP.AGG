<?php

namespace App\Queries\Ventas;

use App\Support\Stock\ArticuloUsoInsumoSupport;
use App\Support\Ventas\GastronomiaDescuentoReporteFiltros;
use App\Support\Ventas\GastronomiaVentaComprobanteSignoSupport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class GastronomiaDescuentoReporteQuery
{
    private static function cantidadExpr(): string
    {
        return GastronomiaVentaComprobanteSignoSupport::sqlCantidadLineaVenta();
    }

    private static function importeExpr(): string
    {
        return GastronomiaVentaComprobanteSignoSupport::sqlImporteLineaVenta();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<object{
     *   descuento_id:int,
     *   descuento_codigo:string,
     *   descuento_nombre:string,
     *   cliente_interno_id:int,
     *   cliente_codigo:string,
     *   cliente_nombre:string,
     *   articulo_id:int,
     *   sku:string,
     *   descripcion:string,
     *   unidades:float,
     *   total_venta:float
     * }>
     */
    public function filasAgregadas(array $filtros): array
    {
        $listarTodos = ! empty($filtros['listar_todos']);
        $agruparPor = (string) ($filtros['agrupar_por'] ?? GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO);
        $codigos = $agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO
            ? ($filtros['codigos_descuento_resueltos'] ?? [])
            : [];
        $codigosFiltroCliente = $agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE
            ? ($filtros['codigos_descuento_cliente_resueltos'] ?? [])
            : [];
        $clienteIds = $filtros['clientes_descuento_ids'] ?? [];

        if (! $listarTodos) {
            if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE && $clienteIds === []) {
                return [];
            }
            if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO && $codigos === []) {
                return [];
            }
        }

        $query = $this->queryBaseLineas($filtros);
        $this->aplicarFiltrosSeleccion($query, $listarTodos, $agruparPor, $codigos, $codigosFiltroCliente, $clienteIds);

        $rows = $query
            ->select([
                'dg.id as descuento_id',
                'dg.codigo as descuento_codigo',
                'dg.nombre as descuento_nombre',
                'cg.cliente_interno_descuento_id as cliente_interno_id',
                'cli.codigo as cliente_codigo',
                'cli.nombre as cliente_nombre',
                've.articulo_id',
                'a.sku',
                'a.descripcion',
            ])
            ->selectRaw('SUM('.self::cantidadExpr().') as unidades')
            ->selectRaw('SUM('.self::importeExpr().') as total_venta')
            ->groupBy(
                'dg.id',
                'dg.codigo',
                'dg.nombre',
                'cg.cliente_interno_descuento_id',
                'cli.codigo',
                'cli.nombre',
                've.articulo_id',
                'a.sku',
                'a.descripcion',
            )
            ->orderBy('dg.codigo')
            ->orderBy('cli.codigo')
            ->orderBy('a.sku')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $unidades = round(abs((float) ($row->unidades ?? 0)), 4);
            if ($unidades <= 0.0001) {
                continue;
            }

            $clienteId = (int) ($row->cliente_interno_id ?? 0);

            $out[] = (object) [
                'descuento_id' => (int) $row->descuento_id,
                'descuento_codigo' => trim((string) $row->descuento_codigo),
                'descuento_nombre' => trim((string) $row->descuento_nombre),
                'cliente_interno_id' => $clienteId,
                'cliente_codigo' => $clienteId > 0 ? trim((string) ($row->cliente_codigo ?? '')) : '',
                'cliente_nombre' => $clienteId > 0 ? trim((string) ($row->cliente_nombre ?? '')) : 'Sin cliente interno',
                'articulo_id' => (int) $row->articulo_id,
                'sku' => trim((string) $row->sku),
                'descripcion' => trim((string) $row->descripcion),
                'unidades' => $unidades,
                'total_venta' => round(abs((float) ($row->total_venta ?? 0)), 2),
            ];
        }

        return $out;
    }

    /**
     * Facturas (ventas) que componen un bloque/total del reporte.
     *
     * @param  array<string, mixed>  $filtros
     * @return list<object{
     *   venta_id:int,
     *   fechajornada:string,
     *   codigo:string,
     *   numerocomprobante:int,
     *   tipo_comprobante:string,
     *   total_venta:float
     * }>
     */
    public function ventasPorClaveBloque(array $filtros, string $clave): array
    {
        $clave = trim($clave);
        if ($clave === '' || ! $this->aplicarFiltroClaveBloque($clave, $filtros)) {
            return [];
        }

        $query = $this->queryBaseLineas($filtros);
        $this->aplicarFiltroClaveEnQuery($query, $clave);

        $agruparPor = (string) ($filtros['agrupar_por'] ?? GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO);
        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE) {
            $codigosFiltroCliente = $filtros['codigos_descuento_cliente_resueltos'] ?? [];
            if (is_array($codigosFiltroCliente) && $codigosFiltroCliente !== []) {
                $query->whereIn('dg.codigo', $codigosFiltroCliente);
            }
        }

        $rows = $query
            ->select([
                'v.id as venta_id',
                'v.fechajornada',
                'v.codigo',
                'v.numerocomprobante',
                'tt.abreviatura as tipo_comprobante',
            ])
            ->selectRaw('SUM('.self::importeExpr().') as total_venta')
            ->groupBy('v.id', 'v.fechajornada', 'v.codigo', 'v.numerocomprobante', 'tt.abreviatura')
            ->orderByDesc('v.fechajornada')
            ->orderByDesc('v.id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $total = round(abs((float) ($row->total_venta ?? 0)), 2);
            if ($total <= 0.0001) {
                continue;
            }

            $out[] = (object) [
                'venta_id' => (int) $row->venta_id,
                'fechajornada' => (string) ($row->fechajornada ?? ''),
                'codigo' => trim((string) ($row->codigo ?? '')),
                'numerocomprobante' => (int) ($row->numerocomprobante ?? 0),
                'tipo_comprobante' => trim((string) ($row->tipo_comprobante ?? '')),
                'total_venta' => $total,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryBaseLineas(array $filtros): Builder
    {
        $query = DB::table('venta_emision as ve')
            ->join('venta as v', 'v.id', '=', 've.venta_id')
            ->join('venta_gastronomia_emision as vge', 'vge.venta_id', '=', 'v.id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'v.tipotransaccion_id')
            ->join('puntoventa as pv', 'pv.id', '=', 'v.puntoventa_id')
            ->join('cuenta_gastronomia as cg', 'cg.id', '=', 'vge.cuenta_gastronomia_id')
            ->join('descuento_gastronomia as dg', 'dg.id', '=', 'cg.descuento_gastronomia_id')
            ->join('articulo as a', 'a.id', '=', 've.articulo_id')
            ->leftJoin('cliente as cli', 'cli.id', '=', 'cg.cliente_interno_descuento_id')
            ->whereNull('v.deleted_at')
            ->whereNull('vge.venta_factura_origen_id')
            ->whereNotNull('cg.descuento_gastronomia_id');

        $this->aplicarExclusionInsumos($query);
        $this->aplicarExclusionOpcionalesFormula($query);
        $this->aplicarFiltrosEstructurales($query, $filtros);

        if (($filtros['agrupar_por'] ?? '') === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE) {
            $query->where('cg.cliente_interno_descuento_id', '>', 0);
        }

        return $query;
    }

    /**
     * Valida clave y aplica filtros de alcance coherentes con el bloque.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltroClaveBloque(string $clave, array $filtros): bool
    {
        if (str_starts_with($clave, 'd_')) {
            $codigo = substr($clave, 2);
            if ($codigo === '') {
                return false;
            }
            if (($filtros['agrupar_por'] ?? '') !== GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO) {
                return false;
            }

            return true;
        }

        if (str_starts_with($clave, 'c_')) {
            $clienteId = (int) substr($clave, 2);
            if ($clienteId <= 0) {
                return false;
            }
            if (($filtros['agrupar_por'] ?? '') !== GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE) {
                return false;
            }

            return true;
        }

        return false;
    }

    private function aplicarFiltroClaveEnQuery(Builder $query, string $clave): void
    {
        if (str_starts_with($clave, 'd_')) {
            $query->where('dg.codigo', substr($clave, 2));

            return;
        }

        if (str_starts_with($clave, 'c_')) {
            $query->where('cg.cliente_interno_descuento_id', (int) substr($clave, 2));
        }
    }

    /**
     * @param  list<string>  $codigos
     * @param  list<string>  $codigosFiltroCliente
     * @param  list<int>  $clienteIds
     */
    private function aplicarFiltrosSeleccion(
        Builder $query,
        bool $listarTodos,
        string $agruparPor,
        array $codigos,
        array $codigosFiltroCliente,
        array $clienteIds,
    ): void {
        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE) {
            if (! $listarTodos && $clienteIds !== []) {
                $query->whereIn('cg.cliente_interno_descuento_id', $clienteIds);
            }
            if ($codigosFiltroCliente !== []) {
                $query->whereIn('dg.codigo', $codigosFiltroCliente);
            }

            return;
        }

        if (! $listarTodos && $codigos !== []) {
            $query->whereIn('dg.codigo', $codigos);
        }
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

    /**
     * Excluye renglones $0 de opcionales de fórmula agregados al emitir (no están en la cuenta).
     * Import Anita no graba cuenta_gastronomia_linea: en ese caso se incluyen todas las venta_emision.
     */
    private function aplicarExclusionOpcionalesFormula(Builder $query): void
    {
        $query->where(function (Builder $outer): void {
            $outer->whereNotExists(function ($sub): void {
                $sub->select(DB::raw(1))
                    ->from('cuenta_gastronomia_linea as cgl_any')
                    ->whereColumn('cgl_any.cuenta_gastronomia_id', 'cg.id');
            })->orWhereExists(function ($sub): void {
                $sub->select(DB::raw(1))
                    ->from('cuenta_gastronomia_linea as cgl')
                    ->whereColumn('cgl.cuenta_gastronomia_id', 'cg.id')
                    ->whereColumn('cgl.articulo_id', 've.articulo_id');
            });
        });
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosEstructurales(Builder $query, array $filtros): void
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('pv.empresa_id', $empresaId);
        }

        [$desde, $hasta] = GastronomiaDescuentoReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        if ($desde !== '') {
            $query->whereDate('v.fechajornada', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate('v.fechajornada', '<=', $hasta);
        }
    }
}
