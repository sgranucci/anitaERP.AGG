<?php

namespace App\Support\Compras;

use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\IngresoEgresoSolicitudpagoSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Index de órdenes de pago: `pagoproveedor` + OPP de Ingresos/Egresos (solo tipo OPP).
 */
final class PagoproveedorListadoUnificadoSupport
{
    private const PER_PAGE = 10;

    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, PagoproveedorListadoFila>|Collection<int, PagoproveedorListadoFila>
     */
    public function listar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        // Sin cuentas_caja en el UNION: la subconsulta correlacionada por fila
        // hace timeout del index con ~24k OP + 100k movimientos de caja.
        $union = $this->queryUnion($filtros);

        $query = DB::query()
            ->fromSub($union, 'listado_op')
            ->orderByDesc('fecha')
            // Número de OP (no pk_id): IE y pagoproveedor mezclan IDs distintos.
            ->orderByRaw("CAST(NULLIF(TRIM(numerotransaccion), '') AS UNSIGNED) DESC")
            ->orderByDesc('pk_id');

        if ($paginar) {
            $page = PaginatorImpl::resolveCurrentPage();
            $total = (clone $query)->count();
            $filasRaw = (clone $query)
                ->forPage($page, self::PER_PAGE)
                ->get();

            $filas = $filasRaw
                ->map(fn ($row) => PagoproveedorListadoFila::desdeUnionRow($row))
                ->values();

            return new PaginatorImpl(
                $this->hidratarCuentasCaja($filas),
                $total,
                self::PER_PAGE,
                $page,
                ['path' => PaginatorImpl::resolveCurrentPath()]
            );
        }

        $filas = $query->get()
            ->map(fn ($row) => PagoproveedorListadoFila::desdeUnionRow($row))
            ->values();

        return $this->hidratarCuentasCaja($filas);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryUnion(array $filtros): QueryBuilder
    {
        $pp = $this->queryPagoproveedor($filtros);
        $ie = $this->queryIeOpp($filtros);

        return $pp->unionAll($ie);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryPagoproveedor(array $filtros): QueryBuilder
    {
        $query = DB::table('pagoproveedor as pp')
            ->leftJoin('empresa', 'empresa.id', '=', 'pp.empresa_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'pp.proveedor_id')
            ->leftJoin('moneda', 'moneda.id', '=', 'pp.moneda_id')
            ->select([
                DB::raw("'".PagoproveedorListadoFila::ORIGEN_PAGOPROVEEDOR."' as origen"),
                'pp.id as pk_id',
                'pp.fecha',
                'pp.tipocomprobante',
                'pp.letra',
                'pp.sucursal',
                'pp.numerotransaccion',
                'pp.empresa_id',
                'empresa.nombre as nombreempresa',
                'pp.proveedor_id',
                'proveedor.nombre as nombreproveedor',
                'pp.monto',
                'pp.moneda_id',
                'moneda.abreviatura as moneda_abrev',
                'pp.estado',
                'pp.detalle',
                DB::raw('NULL as solicitudpago_id'),
                DB::raw("'' as cuentas_caja"),
            ]);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'pp.empresa_id');
        $this->aplicarFiltrosComunes($query, $filtros, [
            'id' => 'pp.id',
            'numero' => 'pp.numerotransaccion',
            'proveedor' => 'proveedor.nombre',
            'empresa' => 'empresa.nombre',
            'estado' => 'pp.estado',
            'fecha' => 'pp.fecha',
            'detalle' => 'pp.detalle',
            'empresa_id' => 'pp.empresa_id',
        ]);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryIeOpp(array $filtros): QueryBuilder
    {
        $tipoOppId = IngresoEgresoSolicitudpagoSupport::tipotransaccionCajaIdPorConfig();
        $tipoOpaId = IngresoEgresoSolicitudpagoSupport::tipotransaccionCajaIdPorAbreviaturaPublica('OPA');
        $tipoIds = array_values(array_unique(array_filter([
            $tipoOppId > 0 ? $tipoOppId : null,
            $tipoOpaId > 0 ? $tipoOpaId : null,
        ])));

        $montoSub = DB::table('caja_movimiento_cuentacaja as cmc')
            ->select('cmc.caja_movimiento_id')
            ->selectRaw(
                'COALESCE(SUM(ABS(cmc.monto * CASE WHEN COALESCE(cmc.moneda_id, 1) > 1 THEN COALESCE(cmc.cotizacion, 1) ELSE 1 END)), 0) as monto_mn'
            )
            ->groupBy('cmc.caja_movimiento_id');

        $monedaLocalAbrev = addcslashes(
            (string) (DB::table('moneda')->where('id', 1)->value('abreviatura') ?: 'PES'),
            "\\'"
        );

        $query = DB::table('caja_movimiento as cm')
            ->leftJoin('empresa', 'empresa.id', '=', 'cm.empresa_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'cm.proveedor_id')
            ->leftJoin('tipotransaccion_caja as ttc', 'ttc.id', '=', 'cm.tipotransaccion_caja_id')
            ->leftJoinSub($montoSub, 'monto_agg', function ($join) {
                $join->on('monto_agg.caja_movimiento_id', '=', 'cm.id');
            })
            ->whereNull('cm.pagoproveedor_id')
            ->select([
                DB::raw("'".PagoproveedorListadoFila::ORIGEN_IE_OPP."' as origen"),
                'cm.id as pk_id',
                'cm.fecha',
                DB::raw("COALESCE(NULLIF(TRIM(ttc.abreviatura), ''), 'OPP') as tipocomprobante"),
                DB::raw("'' as letra"),
                DB::raw('COALESCE(empresa.codigo, cm.empresa_id, 0) as sucursal'),
                'cm.numerotransaccion',
                'cm.empresa_id',
                'empresa.nombre as nombreempresa',
                'cm.proveedor_id',
                'proveedor.nombre as nombreproveedor',
                DB::raw('COALESCE(monto_agg.monto_mn, 0) as monto'),
                DB::raw('1 as moneda_id'),
                DB::raw("'{$monedaLocalAbrev}' as moneda_abrev"),
                DB::raw("CASE WHEN cm.caja_movimiento_revertido_por_id IS NOT NULL THEN 'REVERTIDA' ELSE 'CONFIRMADA' END as estado"),
                'cm.detalle',
                'cm.solicitudpago_id',
                DB::raw("'' as cuentas_caja"),
            ]);

        if ($tipoIds !== []) {
            $query->whereIn('cm.tipotransaccion_caja_id', $tipoIds);
        } else {
            $query->whereIn(DB::raw('UPPER(TRIM(ttc.abreviatura))'), ['OPP', 'OPA'])
                ->whereNull('ttc.deleted_at');
        }

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'cm.empresa_id');
        $this->aplicarFiltrosComunes($query, $filtros, [
            'id' => 'cm.id',
            'numero' => 'cm.numerotransaccion',
            'proveedor' => 'proveedor.nombre',
            'empresa' => 'empresa.nombre',
            'estado' => "CASE WHEN cm.caja_movimiento_revertido_por_id IS NOT NULL THEN 'REVERTIDA' ELSE 'CONFIRMADA' END",
            'fecha' => 'cm.fecha',
            'detalle' => 'cm.detalle',
            'empresa_id' => 'cm.empresa_id',
        ], true);

        return $query;
    }

    /**
     * Carga cuentas de caja solo para las filas visibles (página / export acotado).
     *
     * @param  Collection<int, PagoproveedorListadoFila>  $filas
     * @return Collection<int, PagoproveedorListadoFila>
     */
    private function hidratarCuentasCaja(Collection $filas): Collection
    {
        if ($filas->isEmpty()) {
            return $filas;
        }

        $ppIds = $filas
            ->filter(static fn (PagoproveedorListadoFila $f) => ! $f->esIeOpp())
            ->map(static fn (PagoproveedorListadoFila $f) => $f->id)
            ->unique()
            ->values()
            ->all();

        $ieIds = $filas
            ->filter(static fn (PagoproveedorListadoFila $f) => $f->esIeOpp())
            ->map(static fn (PagoproveedorListadoFila $f) => $f->id)
            ->unique()
            ->values()
            ->all();

        $cuentasPp = $this->fusionarCuentasCajaYCheques(
            $this->mapCuentasCajaPagoproveedor($ppIds),
            $this->mapCuentasChequePagoproveedor($ppIds)
        );
        $cuentasIe = $this->fusionarCuentasCajaYCheques(
            $this->mapCuentasCajaIeOpp($ieIds),
            $this->mapCuentasChequeIeOpp($ieIds)
        );

        return $filas->map(function (PagoproveedorListadoFila $fila) use ($cuentasPp, $cuentasIe) {
            $texto = $fila->esIeOpp()
                ? (string) ($cuentasIe[$fila->id] ?? '')
                : (string) ($cuentasPp[$fila->id] ?? '');

            if ($texto === '' || $texto === $fila->cuentasCaja) {
                return $fila;
            }

            return $fila->conCuentasCaja($texto);
        })->values();
    }

    /**
     * @param  list<int>  $pagoproveedorIds
     * @return array<int, array<int, string>>
     */
    private function mapCuentasCajaPagoproveedor(array $pagoproveedorIds): array
    {
        if ($pagoproveedorIds === []) {
            return [];
        }

        $porFk = $this->mapCuentasPorCuentacajaId(
            DB::table('caja_movimiento as cmx')
                ->join('caja_movimiento_cuentacaja as cmc', 'cmc.caja_movimiento_id', '=', 'cmx.id')
                ->join('cuentacaja as cc', 'cc.id', '=', 'cmc.cuentacaja_id')
                ->whereIn('cmx.pagoproveedor_id', $pagoproveedorIds),
            'cmx.pagoproveedor_id'
        );

        $sinFk = array_values(array_diff($pagoproveedorIds, array_map('intval', array_keys($porFk))));
        if ($sinFk === []) {
            return $porFk;
        }

        $porCajaMov = $this->mapCuentasPorCuentacajaId(
            DB::table('pagoproveedor as pp')
                ->join('caja_movimiento as cmx', 'cmx.id', '=', 'pp.caja_movimiento_id')
                ->join('caja_movimiento_cuentacaja as cmc', 'cmc.caja_movimiento_id', '=', 'cmx.id')
                ->join('cuentacaja as cc', 'cc.id', '=', 'cmc.cuentacaja_id')
                ->whereIn('pp.id', $sinFk)
                ->whereNotNull('pp.caja_movimiento_id'),
            'pp.id'
        );

        return $porFk + $porCajaMov;
    }

    /**
     * @param  list<int>  $pagoproveedorIds
     * @return array<int, array<int, string>>
     */
    private function mapCuentasChequePagoproveedor(array $pagoproveedorIds): array
    {
        if ($pagoproveedorIds === []) {
            return [];
        }

        $porFk = $this->mapCuentasChequePorId(
            DB::table('cheque as ch')
                ->join('cuentacaja as cc', 'cc.id', '=', 'ch.cuentacaja_id')
                ->whereIn('ch.pagoproveedor_id', $pagoproveedorIds)
                ->where('ch.origen', 'E'),
            'ch.pagoproveedor_id'
        );

        $porCajaMov = $this->mapCuentasChequePorId(
            DB::table('pagoproveedor as pp')
                ->join('cheque as ch', 'ch.caja_movimiento_id', '=', 'pp.caja_movimiento_id')
                ->join('cuentacaja as cc', 'cc.id', '=', 'ch.cuentacaja_id')
                ->whereIn('pp.id', $pagoproveedorIds)
                ->whereNotNull('pp.caja_movimiento_id')
                ->where('ch.origen', 'E'),
            'pp.id'
        );

        return $this->combinarMapasPorCuenta($porFk, $porCajaMov);
    }

    /**
     * @param  list<int>  $cajaMovimientoIds
     * @return array<int, array<int, string>>
     */
    private function mapCuentasCajaIeOpp(array $cajaMovimientoIds): array
    {
        if ($cajaMovimientoIds === []) {
            return [];
        }

        return $this->mapCuentasPorCuentacajaId(
            DB::table('caja_movimiento_cuentacaja as cmc')
                ->join('cuentacaja as cc', 'cc.id', '=', 'cmc.cuentacaja_id')
                ->whereIn('cmc.caja_movimiento_id', $cajaMovimientoIds),
            'cmc.caja_movimiento_id'
        );
    }

    /**
     * @param  list<int>  $cajaMovimientoIds
     * @return array<int, array<int, string>>
     */
    private function mapCuentasChequeIeOpp(array $cajaMovimientoIds): array
    {
        if ($cajaMovimientoIds === []) {
            return [];
        }

        return $this->mapCuentasChequePorId(
            DB::table('cheque as ch')
                ->join('cuentacaja as cc', 'cc.id', '=', 'ch.cuentacaja_id')
                ->whereIn('ch.caja_movimiento_id', $cajaMovimientoIds)
                ->where('ch.origen', 'E'),
            'ch.caja_movimiento_id'
        );
    }

    /**
     * @param  array<int, array<int, string>>  $a
     * @param  array<int, array<int, string>>  $b
     * @return array<int, array<int, string>>
     */
    private function combinarMapasPorCuenta(array $a, array $b): array
    {
        $out = $a;
        foreach ($b as $id => $porCuenta) {
            foreach ($porCuenta as $cuentaId => $etiqueta) {
                $out[(int) $id][$cuentaId] = $etiqueta;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array<int, string>>  $caja
     * @param  array<int, array<int, string>>  $cheques
     * @return array<int, string>
     */
    private function fusionarCuentasCajaYCheques(array $caja, array $cheques): array
    {
        $ids = array_unique(array_merge(array_keys($caja), array_keys($cheques)));
        $out = [];
        foreach ($ids as $id) {
            $porCuenta = ($caja[$id] ?? []) + [];
            foreach ($cheques[$id] ?? [] as $cuentaId => $etiquetaChp) {
                $porCuenta[$cuentaId] = $etiquetaChp;
            }
            $etiquetas = array_values(array_filter(
                array_map('trim', $porCuenta),
                static fn (string $item): bool => $item !== ''
            ));
            if ($etiquetas === []) {
                continue;
            }
            $out[(int) $id] = implode(' | ', $etiquetas);
        }

        return $out;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function mapCuentasPorCuentacajaId(QueryBuilder $query, string $idCol): array
    {
        $etiqueta = $this->sqlEtiquetaCuentaCaja();
        $filas = $query
            ->groupBy($idCol, 'cc.id', 'cc.codigo', 'cc.nombre')
            ->selectRaw("{$idCol} as id")
            ->selectRaw('cc.id as cuentacaja_id')
            ->selectRaw("{$etiqueta} as etiqueta")
            ->get();

        return $this->indexarEtiquetasPorCuenta($filas);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function mapCuentasChequePorId(QueryBuilder $query, string $idCol): array
    {
        $etiqueta = $this->sqlEtiquetaCuentaCaja();
        $filas = $query
            ->groupBy($idCol, 'cc.id', 'cc.codigo', 'cc.nombre')
            ->selectRaw("{$idCol} as id")
            ->selectRaw('cc.id as cuentacaja_id')
            ->selectRaw("CONCAT({$etiqueta}, ' · CHP ', GROUP_CONCAT(DISTINCT TRIM(ch.numerocheque) ORDER BY ch.numerocheque SEPARATOR ', ')) as etiqueta")
            ->get();

        return $this->indexarEtiquetasPorCuenta($filas);
    }

    /**
     * @param  Collection<int, object>  $filas
     * @return array<int, array<int, string>>
     */
    private function indexarEtiquetasPorCuenta(Collection $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila->id ?? 0);
            $cuentaId = (int) ($fila->cuentacaja_id ?? 0);
            $etiqueta = trim((string) ($fila->etiqueta ?? ''));
            if ($id <= 0 || $cuentaId <= 0 || $etiqueta === '') {
                continue;
            }
            $out[$id][$cuentaId] = $etiqueta;
        }

        return $out;
    }

    private function sqlEtiquetaCuentaCaja(): string
    {
        return "TRIM(CONCAT(
            COALESCE(cc.codigo, ''),
            CASE WHEN COALESCE(cc.codigo, '') = '' THEN '' ELSE ' — ' END,
            COALESCE(cc.nombre, '')
        ))";
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, string>  $cols
     */
    private function aplicarFiltrosComunes(QueryBuilder $query, array $filtros, array $cols, bool $estadoEsExpresion = false): void
    {
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $query->where($cols['empresa_id'], (int) $filtros['empresa_id']);
        }

        if (! PagoproveedorListadoFiltros::tieneCriteriosTexto($filtros)
            && ! PagoproveedorListadoFiltros::tieneCriteriosAplicados($filtros)
        ) {
            return;
        }

        if (! PagoproveedorListadoFiltros::tieneCriteriosTexto($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $modo = $filtros['modo'] ?? PagoproveedorListadoFiltros::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === PagoproveedorListadoFiltros::MODO_CAMPO) {
            $campo = (string) ($filtros['campo'] ?? 'proveedor');
            $this->aplicarCampo($query, $campo, $operador, $valor, (string) ($filtros['valor_hasta'] ?? ''), $cols, $estadoEsExpresion);

            return;
        }

        if ($operador === 'vacio') {
            $query->where(function ($q) use ($cols) {
                foreach ([$cols['numero'], $cols['detalle'], $cols['proveedor']] as $col) {
                    $q->where(function ($w) use ($col) {
                        $w->whereNull($col)->orWhere($col, '');
                    });
                }
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = $this->patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $id, $cols, $estadoEsExpresion) {
            if ($id !== false) {
                $q->orWhere($cols['id'], (int) $id);
            }
            foreach (['numero', 'detalle', 'proveedor', 'empresa'] as $key) {
                $q->orWhere($cols[$key], 'like', $like);
            }
            if ($estadoEsExpresion) {
                $q->orWhereRaw($cols['estado'].' like ?', [$like]);
            } else {
                $q->orWhere($cols['estado'], 'like', $like);
            }
            // Búsqueda también por etiqueta OPP + número (valor completo)
            if ($valor !== '') {
                $q->orWhere($cols['numero'], 'like', $like);
            }
        });
    }

    /**
     * @param  array<string, string>  $cols
     */
    private function aplicarCampo(
        QueryBuilder $query,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta,
        array $cols,
        bool $estadoEsExpresion
    ): void {
        $map = [
            'id' => $cols['id'],
            'numero' => $cols['numero'],
            'numerotransaccion' => $cols['numero'],
            'proveedor' => $cols['proveedor'],
            'empresa' => $cols['empresa'],
            'estado' => $cols['estado'],
            'fecha' => $cols['fecha'],
            'detalle' => $cols['detalle'],
        ];
        $column = $map[$campoKey] ?? $cols['proveedor'];
        $type = PagoproveedorListadoFiltros::CAMPOS[$campoKey]['type'] ?? 'texto';

        if ($type === 'entero') {
            $id = filter_var($valor, FILTER_VALIDATE_INT);
            if ($id === false) {
                return;
            }
            $id = (int) $id;
            match ($operador) {
                'mayor' => $query->where($column, '>', $id),
                'menor' => $query->where($column, '<', $id),
                default => $query->where($column, '=', $id),
            };

            return;
        }

        if ($type === 'fecha') {
            $desde = trim($valor);
            $hasta = trim($valorHasta);
            if ($desde === '' && $hasta === '') {
                return;
            }
            if ($desde !== '' && $hasta !== '') {
                $query->whereDate($column, '>=', $desde)->whereDate($column, '<=', $hasta);

                return;
            }
            if ($desde !== '') {
                $op = match ($operador) {
                    'menor' => '<=',
                    'mayor' => '>=',
                    default => '=',
                };
                $query->whereDate($column, $op, $desde);
            }

            return;
        }

        if ($operador === 'vacio') {
            if ($estadoEsExpresion && $campoKey === 'estado') {
                return;
            }
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        $like = $this->patronLike($operador, $valor);
        if ($operador === 'igual') {
            if ($estadoEsExpresion && $campoKey === 'estado') {
                $query->whereRaw($column.' = ?', [$valor]);
            } else {
                $query->where($column, '=', $valor);
            }

            return;
        }
        if ($operador === 'distinto') {
            if ($estadoEsExpresion && $campoKey === 'estado') {
                $query->whereRaw($column.' != ?', [$valor]);
            } else {
                $query->where($column, '!=', $valor);
            }

            return;
        }

        if ($estadoEsExpresion && $campoKey === 'estado') {
            $query->whereRaw($column.' like ?', [$like]);

            return;
        }

        if ($operador === 'contiene') {
            $query->where($column, 'like', $like);

            return;
        }

        $query->where($column, 'like', $like);
    }

    private function patronLike(string $operador, string $valor): string
    {
        $v = addcslashes($valor, '%_\\');

        return match ($operador) {
            'empieza' => $v.'%',
            'termina' => '%'.$v,
            'igual' => $v,
            default => '%'.$v.'%',
        };
    }
}
