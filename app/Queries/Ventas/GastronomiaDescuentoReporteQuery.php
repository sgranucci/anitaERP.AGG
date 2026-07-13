<?php

namespace App\Queries\Ventas;

use App\Support\Stock\ArticuloUsoDescartableSupport;
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
     * @return list<object>
     */
    public function filasAgregadas(array $filtros): array
    {
        $listarTodos = ! empty($filtros['listar_todos']);
        $agruparPor = (string) ($filtros['agrupar_por'] ?? GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO);
        $codigos = $agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO
            ? ($filtros['codigos_descuento_resueltos'] ?? [])
            : [];
        $codigosFiltroSecundario = GastronomiaDescuentoReporteFiltros::usaFiltroCodigosDescuentoSecundario($filtros)
            ? ($filtros['codigos_descuento_cliente_resueltos'] ?? [])
            : [];
        $clienteIds = $filtros['clientes_descuento_ids'] ?? [];
        $mozoIds = $filtros['mozos_descuento_ids'] ?? [];
        $vipIds = $filtros['vips_descuento_ids'] ?? [];

        if (! $listarTodos) {
            if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE && $clienteIds === []) {
                return [];
            }
            if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_MOZO
                && GastronomiaDescuentoReporteFiltros::mozoRangoSinCoincidencias($filtros)) {
                return [];
            }
            if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_VIP
                && GastronomiaDescuentoReporteFiltros::vipRangoSinCoincidencias($filtros)) {
                return [];
            }
            if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO && $codigos === []) {
                return [];
            }
        }

        $query = $this->queryBaseLineas($filtros);
        $this->aplicarFiltrosSeleccion(
            $query,
            $filtros,
            $listarTodos,
            $agruparPor,
            $codigos,
            $codigosFiltroSecundario,
            $clienteIds,
            $mozoIds,
            $vipIds,
        );

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_MOZO) {
            $query->leftJoin('mozo_gastronomia as mg', 'mg.id', '=', 'cg.mozo_gastronomia_id');
        }

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_VIP) {
            $query->leftJoin('cliente_vip_gastronomia as cvg', 'cvg.id', '=', 'cg.cliente_vip_gastronomia_id');
        }

        $this->aplicarSelectGroupByOrden($query, $agruparPor);

        $rows = $query->get();

        $out = [];
        foreach ($rows as $row) {
            $unidades = round(abs((float) ($row->unidades ?? 0)), 4);
            if ($unidades <= 0.0001) {
                continue;
            }

            $out[] = $this->mapearFilaAgregada($row, $agruparPor);
        }

        return $out;
    }

    /**
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

        if (GastronomiaDescuentoReporteFiltros::usaFiltroCodigosDescuentoSecundario($filtros)) {
            $codigosFiltroSecundario = $filtros['codigos_descuento_cliente_resueltos'] ?? [];
            if (is_array($codigosFiltroSecundario) && $codigosFiltroSecundario !== []) {
                $query->whereIn('dg.codigo', $codigosFiltroSecundario);
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
            ->leftJoin('tipoarticulo as ta', 'ta.id', '=', 'a.tipoarticulo_id')
            ->leftJoin('cliente as cli', 'cli.id', '=', 'cg.cliente_interno_descuento_id')
            ->whereNull('v.deleted_at')
            ->whereNull('vge.venta_factura_origen_id')
            ->whereNotNull('cg.descuento_gastronomia_id');

        $this->aplicarExclusionInsumosYDescartables($query);
        $this->aplicarExclusionOpcionalesFormula($query);
        $this->aplicarFiltrosEstructurales($query, $filtros);

        if (($filtros['agrupar_por'] ?? '') === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE) {
            $query->where('cg.cliente_interno_descuento_id', '>', 0);
        }

        if (($filtros['agrupar_por'] ?? '') === GastronomiaDescuentoReporteFiltros::AGRUPAR_MOZO) {
            $query->where('cg.mozo_gastronomia_id', '>', 0);
        }

        if (($filtros['agrupar_por'] ?? '') === GastronomiaDescuentoReporteFiltros::AGRUPAR_VIP) {
            $query->where('cg.cliente_vip_gastronomia_id', '>', 0);
        }

        return $query;
    }

    private function aplicarSelectGroupByOrden(Builder $query, string $agruparPor): void
    {
        $camposArticulo = [
            've.articulo_id',
            'a.sku',
            'a.descripcion',
            'a.tipoarticulo_id',
            'ta.nombre as tipoarticulo_nombre',
        ];
        $groupArticulo = [
            've.articulo_id',
            'a.sku',
            'a.descripcion',
            'a.tipoarticulo_id',
            'ta.nombre',
        ];

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_VIP) {
            $query
                ->select(array_merge([
                    'cg.cliente_vip_gastronomia_id as vip_id',
                    'cvg.numeroid as vip_codigo',
                    'cvg.apellido as vip_apellido',
                    'cvg.nombre as vip_nombre',
                ], $camposArticulo))
                ->selectRaw('SUM('.self::cantidadExpr().') as unidades')
                ->selectRaw('SUM('.self::importeExpr().') as total_venta')
                ->groupBy(array_merge([
                    'cg.cliente_vip_gastronomia_id',
                    'cvg.numeroid',
                    'cvg.apellido',
                    'cvg.nombre',
                ], $groupArticulo))
                ->orderBy('cvg.numeroid')
                ->orderBy('ta.nombre')
                ->orderBy('a.sku');

            return;
        }

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_MOZO) {
            $query
                ->select(array_merge([
                    'cg.mozo_gastronomia_id as mozo_id',
                    'mg.codigo as mozo_codigo',
                    'mg.nombre as mozo_nombre',
                ], $camposArticulo))
                ->selectRaw('SUM('.self::cantidadExpr().') as unidades')
                ->selectRaw('SUM('.self::importeExpr().') as total_venta')
                ->groupBy(array_merge([
                    'cg.mozo_gastronomia_id',
                    'mg.codigo',
                    'mg.nombre',
                ], $groupArticulo))
                ->orderBy('mg.codigo')
                ->orderBy('ta.nombre')
                ->orderBy('a.sku');

            return;
        }

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE) {
            $query
                ->select(array_merge([
                    'cg.cliente_interno_descuento_id as cliente_interno_id',
                    'cli.codigo as cliente_codigo',
                    'cli.nombre as cliente_nombre',
                ], $camposArticulo))
                ->selectRaw('SUM('.self::cantidadExpr().') as unidades')
                ->selectRaw('SUM('.self::importeExpr().') as total_venta')
                ->groupBy(array_merge([
                    'cg.cliente_interno_descuento_id',
                    'cli.codigo',
                    'cli.nombre',
                ], $groupArticulo))
                ->orderBy('cli.codigo')
                ->orderBy('ta.nombre')
                ->orderBy('a.sku');

            return;
        }

        $query
            ->select(array_merge([
                'dg.id as descuento_id',
                'dg.codigo as descuento_codigo',
                'dg.nombre as descuento_nombre',
            ], $camposArticulo))
            ->selectRaw('SUM('.self::cantidadExpr().') as unidades')
            ->selectRaw('SUM('.self::importeExpr().') as total_venta')
            ->groupBy(array_merge([
                'dg.id',
                'dg.codigo',
                'dg.nombre',
            ], $groupArticulo))
            ->orderBy('dg.codigo')
            ->orderBy('ta.nombre')
            ->orderBy('a.sku');
    }

    private function mapearFilaAgregada(object $row, string $agruparPor): object
    {
        $tipoId = (int) ($row->tipoarticulo_id ?? 0);
        $camposArticulo = [
            'articulo_id' => (int) $row->articulo_id,
            'sku' => trim((string) $row->sku),
            'descripcion' => trim((string) $row->descripcion),
            'tipoarticulo_id' => $tipoId > 0 ? $tipoId : null,
            'tipoarticulo_nombre' => trim((string) ($row->tipoarticulo_nombre ?? '')),
            'unidades' => round(abs((float) ($row->unidades ?? 0)), 4),
            'total_venta' => round(abs((float) ($row->total_venta ?? 0)), 2),
        ];

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_VIP) {
            $vipId = (int) ($row->vip_id ?? 0);
            $nombreVip = trim(trim((string) ($row->vip_apellido ?? '')).' '.trim((string) ($row->vip_nombre ?? '')));

            return (object) array_merge([
                'vip_id' => $vipId,
                'vip_codigo' => $vipId > 0 ? trim((string) ($row->vip_codigo ?? '')) : '',
                'vip_nombre' => $vipId > 0 ? ($nombreVip !== '' ? $nombreVip : 'Cliente VIP '.$vipId) : 'Sin cliente VIP',
            ], $camposArticulo);
        }

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_MOZO) {
            $mozoId = (int) ($row->mozo_id ?? 0);

            return (object) array_merge([
                'mozo_id' => $mozoId,
                'mozo_codigo' => $mozoId > 0 ? trim((string) ($row->mozo_codigo ?? '')) : '',
                'mozo_nombre' => $mozoId > 0 ? trim((string) ($row->mozo_nombre ?? '')) : 'Sin mozo',
            ], $camposArticulo);
        }

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE) {
            $clienteId = (int) ($row->cliente_interno_id ?? 0);

            return (object) array_merge([
                'cliente_interno_id' => $clienteId,
                'cliente_codigo' => $clienteId > 0 ? trim((string) ($row->cliente_codigo ?? '')) : '',
                'cliente_nombre' => $clienteId > 0 ? trim((string) ($row->cliente_nombre ?? '')) : 'Sin cliente interno',
            ], $camposArticulo);
        }

        return (object) array_merge([
            'descuento_id' => (int) $row->descuento_id,
            'descuento_codigo' => trim((string) $row->descuento_codigo),
            'descuento_nombre' => trim((string) $row->descuento_nombre),
        ], $camposArticulo);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltroClaveBloque(string $clave, array $filtros): bool
    {
        if (str_starts_with($clave, 'd_')) {
            $codigo = substr($clave, 2);
            if ($codigo === '') {
                return false;
            }

            return ($filtros['agrupar_por'] ?? '') === GastronomiaDescuentoReporteFiltros::AGRUPAR_CODIGO;
        }

        if (str_starts_with($clave, 'c_')) {
            $clienteId = (int) substr($clave, 2);
            if ($clienteId <= 0) {
                return false;
            }

            return ($filtros['agrupar_por'] ?? '') === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE;
        }

        if (str_starts_with($clave, 'm_')) {
            $mozoId = (int) substr($clave, 2);
            if ($mozoId <= 0) {
                return false;
            }

            return ($filtros['agrupar_por'] ?? '') === GastronomiaDescuentoReporteFiltros::AGRUPAR_MOZO;
        }

        if (str_starts_with($clave, 'v_')) {
            $vipId = (int) substr($clave, 2);
            if ($vipId <= 0) {
                return false;
            }

            return ($filtros['agrupar_por'] ?? '') === GastronomiaDescuentoReporteFiltros::AGRUPAR_VIP;
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

            return;
        }

        if (str_starts_with($clave, 'm_')) {
            $query->where('cg.mozo_gastronomia_id', (int) substr($clave, 2));

            return;
        }

        if (str_starts_with($clave, 'v_')) {
            $query->where('cg.cliente_vip_gastronomia_id', (int) substr($clave, 2));
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $codigos
     * @param  list<string>  $codigosFiltroSecundario
     * @param  list<int>  $clienteIds
     * @param  list<int>  $mozoIds
     * @param  list<int>  $vipIds
     */
    private function aplicarFiltrosSeleccion(
        Builder $query,
        array $filtros,
        bool $listarTodos,
        string $agruparPor,
        array $codigos,
        array $codigosFiltroSecundario,
        array $clienteIds,
        array $mozoIds,
        array $vipIds = [],
    ): void {
        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_VIP) {
            if (! $listarTodos
                && ! GastronomiaDescuentoReporteFiltros::vipAlcanceImplicitoTodos($filtros)
                && $vipIds !== []) {
                $query->whereIn('cg.cliente_vip_gastronomia_id', $vipIds);
            }
            if ($codigosFiltroSecundario !== []) {
                $query->whereIn('dg.codigo', $codigosFiltroSecundario);
            }

            return;
        }

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_CLIENTE) {
            if (! $listarTodos && $clienteIds !== []) {
                $query->whereIn('cg.cliente_interno_descuento_id', $clienteIds);
            }
            if ($codigosFiltroSecundario !== []) {
                $query->whereIn('dg.codigo', $codigosFiltroSecundario);
            }

            return;
        }

        if ($agruparPor === GastronomiaDescuentoReporteFiltros::AGRUPAR_MOZO) {
            if (! $listarTodos
                && ! GastronomiaDescuentoReporteFiltros::mozoAlcanceImplicitoTodos($filtros)
                && $mozoIds !== []) {
                $query->whereIn('cg.mozo_gastronomia_id', $mozoIds);
            }
            if ($codigosFiltroSecundario !== []) {
                $query->whereIn('dg.codigo', $codigosFiltroSecundario);
            }

            return;
        }

        if (! $listarTodos && $codigos !== []) {
            $query->whereIn('dg.codigo', $codigos);
        }
    }

    private function aplicarExclusionInsumosYDescartables(Builder $query): void
    {
        $query->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('usoarticulo as ua_excl')
                ->whereColumn('ua_excl.id', 'a.usoarticulo_id')
                ->whereRaw(
                    'UPPER(TRIM(ua_excl.nombre)) IN (?, ?)',
                    [
                        ArticuloUsoInsumoSupport::NOMBRE_USO_INSUMO,
                        ArticuloUsoDescartableSupport::NOMBRE_USO_DESCARTABLES,
                    ],
                );
        });
    }

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
