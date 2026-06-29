<?php

namespace App\Support\Stock;

use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Listado unificado: movimientos sueltos + transferencias (una fila con egreso/ingreso enlazados).
 */
final class MovimientoStockListadoUnificadoSupport
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, MovimientoStockListadoFila>|Collection<int, MovimientoStockListadoFila>
     */
    public function listar(array $filtros, bool $paginar = false): LengthAwarePaginator|Collection
    {
        $union = $this->queryUnion($filtros);

        $query = DB::query()
            ->fromSub($union, 'listado_ms')
            ->orderByDesc('operacion_stock_id')
            ->orderByDesc('fecha')
            ->orderByDesc('pk_id');

        if ($paginar) {
            $page = PaginatorImpl::resolveCurrentPage();
            $perPage = 15;
            $total = (clone $query)->count();
            $filasRaw = (clone $query)
                ->forPage($page, $perPage)
                ->get();

            $items = $this->hidratarFilas($filasRaw);

            return new PaginatorImpl(
                $items,
                $total,
                $perPage,
                $page,
                ['path' => PaginatorImpl::resolveCurrentPath()]
            );
        }

        return $this->hidratarFilas($query->get());
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryUnion(array $filtros): QueryBuilder
    {
        $amAgg = DB::table('articulo_movimiento as am')
            ->select('am.movimientostock_id')
            ->selectRaw('MIN(am.deposito_id) as deposito_id')
            ->selectRaw('MIN(am.lote) as lote')
            ->selectRaw('SUM(ABS(am.cantidad)) as total_cantidad')
            ->selectRaw('COUNT(*) as items_count')
            ->whereNull('am.deleted_at')
            ->whereNotNull('am.movimientostock_id')
            ->groupBy('am.movimientostock_id');

        $tmAgg = DB::table('transferencia_mercaderia_articulo as tma')
            ->select('tma.transferencia_mercaderia_id')
            ->selectRaw('SUM(ABS(tma.cantidad_destino)) as total_cantidad')
            ->selectRaw('COUNT(*) as items_count')
            ->groupBy('tma.transferencia_mercaderia_id');

        $movQuery = DB::table('movimientostock as ms')
            ->joinSub($amAgg, 'am_agg', fn ($j) => $j->on('am_agg.movimientostock_id', '=', 'ms.id'))
            ->leftJoin('depmae', 'depmae.id', '=', 'am_agg.deposito_id')
            ->join('empresa', 'empresa.id', '=', 'depmae.empresa_id')
            ->join('tipotransaccion_stock as tts', 'tts.id', '=', 'ms.tipotransaccion_stock_id')
            ->leftJoin('mventa', 'mventa.id', '=', 'ms.mventa_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'ms.usuario_id')
            ->whereNull('ms.deleted_at')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('transferencia_mercaderia as tm_legs')
                    ->whereNull('tm_legs.deleted_at')
                    ->where(function ($w) {
                        $w->whereColumn('tm_legs.movimientostock_salida_id', 'ms.id')
                            ->orWhereColumn('tm_legs.movimientostock_entrada_id', 'ms.id');
                    });
            })
            ->select([
                DB::raw("'movimiento' as fila_tipo"),
                'ms.id as pk_id',
                'ms.id as operacion_stock_id',
                'ms.fecha',
                'ms.id as movimientostock_id',
                DB::raw('NULL as transferencia_id'),
                DB::raw('NULL as mov_salida_id'),
                DB::raw('NULL as mov_entrada_id'),
                'ms.codigo as codigo_listado',
                'tts.nombre as tipo_nombre',
                'ms.leyenda as leyenda_listado',
                'am_agg.lote as lote_listado',
                'am_agg.total_cantidad',
                'am_agg.items_count',
                'ms.estado as estado_movimiento',
                DB::raw('NULL as estado_transferencia'),
                'depmae.codigo as deposito_codigo',
                'depmae.nombre as deposito_nombre',
                DB::raw('NULL as deposito_origen_codigo'),
                DB::raw('NULL as deposito_origen_nombre'),
                DB::raw('NULL as deposito_destino_codigo'),
                DB::raw('NULL as deposito_destino_nombre'),
                DB::raw('NULL as deposito_origen_id'),
                DB::raw('NULL as deposito_destino_id'),
                DB::raw('NULL as bien_uso_origen_etiqueta'),
                DB::raw('NULL as bien_uso_destino_etiqueta'),
                'mventa.nombre as marca_nombre',
                'empresa.nombre as nombreempresa',
                DB::raw('COALESCE(depmae.empresa_id, 0) as empresa_id_listado'),
                'am_agg.deposito_id as deposito_id_listado',
                'usuario.nombre as usuario_nombre',
            ]);

        $tmQuery = DB::table('transferencia_mercaderia as tm')
            ->leftJoinSub($tmAgg, 'tma_agg', fn ($j) => $j->on('tma_agg.transferencia_mercaderia_id', '=', 'tm.id'))
            ->join('tipotransaccion_stock as tts', 'tts.id', '=', 'tm.tipotransaccion_stock_id')
            ->join('empresa', 'empresa.id', '=', 'tm.empresa_id')
            ->leftJoin('depmae as dep_o', 'dep_o.id', '=', 'tm.deposito_origen_id')
            ->leftJoin('depmae as dep_d', 'dep_d.id', '=', 'tm.deposito_destino_id')
            ->leftJoin('bien_uso as bu_o', 'bu_o.id', '=', 'tm.bien_uso_origen_id')
            ->leftJoin('bien_uso as bu_d', 'bu_d.id', '=', 'tm.bien_uso_destino_id')
            ->leftJoin('usuario as u_orig', 'u_orig.id', '=', 'tm.usuario_origen_id')
            ->whereNull('tm.deleted_at')
            ->select([
                DB::raw("'transferencia' as fila_tipo"),
                'tm.id as pk_id',
                DB::raw('GREATEST(COALESCE(tm.movimientostock_salida_id, 0), COALESCE(tm.movimientostock_entrada_id, 0)) as operacion_stock_id'),
                'tm.fecha',
                DB::raw('COALESCE(tm.movimientostock_salida_id, tm.movimientostock_entrada_id) as movimientostock_id'),
                'tm.id as transferencia_id',
                'tm.movimientostock_salida_id as mov_salida_id',
                'tm.movimientostock_entrada_id as mov_entrada_id',
                'tm.codigo as codigo_listado',
                'tts.nombre as tipo_nombre',
                'tm.observacion as leyenda_listado',
                'tm.lote as lote_listado',
                DB::raw('COALESCE(tma_agg.total_cantidad, 0) as total_cantidad'),
                DB::raw('COALESCE(tma_agg.items_count, 0) as items_count'),
                DB::raw('NULL as estado_movimiento'),
                'tm.estado as estado_transferencia',
                DB::raw('NULL as deposito_codigo'),
                DB::raw('NULL as deposito_nombre'),
                'dep_o.codigo as deposito_origen_codigo',
                'dep_o.nombre as deposito_origen_nombre',
                'dep_d.codigo as deposito_destino_codigo',
                'dep_d.nombre as deposito_destino_nombre',
                'tm.deposito_origen_id as deposito_origen_id',
                'tm.deposito_destino_id as deposito_destino_id',
                DB::raw("TRIM(CONCAT(COALESCE(bu_o.codigo_inventario, ''), ' ', COALESCE(bu_o.hostname, ''))) as bien_uso_origen_etiqueta"),
                DB::raw("TRIM(CONCAT(COALESCE(bu_d.codigo_inventario, ''), ' ', COALESCE(bu_d.hostname, ''))) as bien_uso_destino_etiqueta"),
                DB::raw('NULL as marca_nombre'),
                'empresa.nombre as nombreempresa',
                'tm.empresa_id as empresa_id_listado',
                DB::raw('COALESCE(tm.deposito_origen_id, tm.deposito_destino_id, 0) as deposito_id_listado'),
                'u_orig.nombre as usuario_nombre',
            ]);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($movQuery, 'depmae.empresa_id');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($tmQuery, 'tm.empresa_id');
        MovimientoStockVisibilidadSupport::aplicarFiltroCentrocostoMovimientoQuery($movQuery);
        MovimientoStockVisibilidadSupport::aplicarFiltroCentrocostoTransferenciaQuery($tmQuery);
        MovimientoStockVisibilidadSupport::aplicarFiltroDepositosMovimientoQuery($movQuery, 'am_agg.deposito_id');
        MovimientoStockVisibilidadSupport::aplicarFiltroDepositosTransferenciaQuery($tmQuery);
        MovimientoStockVisibilidadSupport::aplicarFiltroTipotransaccionesMovimientoQuery($movQuery);
        MovimientoStockVisibilidadSupport::aplicarFiltroTipotransaccionesTransferenciaQuery($tmQuery);
        $this->aplicarFiltrosInteligentes($movQuery, $filtros, true);
        $this->aplicarFiltrosInteligentes($tmQuery, $filtros, false);

        $empresaFiltro = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaFiltro > 0 && $this->empresaRepository->empresaIdPermitida($empresaFiltro)) {
            $movQuery->where('depmae.empresa_id', $empresaFiltro);
            $tmQuery->where('tm.empresa_id', $empresaFiltro);
        }

        $depositoFiltro = (int) ($filtros['deposito_id'] ?? 0);
        if ($depositoFiltro > 0 && UsuarioDepositoAutorizado::depositoAutorizado($depositoFiltro)) {
            $movQuery->where('am_agg.deposito_id', $depositoFiltro);
            $tmQuery->where(function ($q) use ($depositoFiltro) {
                $q->where('tm.deposito_origen_id', $depositoFiltro)
                    ->orWhere('tm.deposito_destino_id', $depositoFiltro);
            });
        }

        return $movQuery->unionAll($tmQuery);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosInteligentes(QueryBuilder $query, array $filtros, bool $esMovimiento): void
    {
        if (! MovimientoStockListadoFiltros::tieneCriteriosInteligentes($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = (string) ($filtros['operador'] ?? 'contiene');
        $modo = (string) ($filtros['modo'] ?? MovimientoStockListadoFiltros::MODO_TODOS);

        if ($operador === 'vacio') {
            $columnaCodigo = $esMovimiento ? 'ms.codigo' : 'tm.codigo';
            $query->where(function ($q) use ($columnaCodigo) {
                $q->whereNull($columnaCodigo)->orWhere($columnaCodigo, '');
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        if ($modo === MovimientoStockListadoFiltros::MODO_CAMPO) {
            $this->aplicarFiltroCampo($query, $filtros, $esMovimiento);

            return;
        }

        $like = '%'.MovimientoStockListadoFiltros::escapeLikePublic($valor).'%';

        $query->where(function ($q) use ($valor, $like, $esMovimiento) {
            if (filter_var($valor, FILTER_VALIDATE_INT) !== false) {
                $id = (int) $valor;
                if ($esMovimiento) {
                    $q->orWhere('ms.id', $id);
                } else {
                    $q->orWhere('tm.id', $id)
                        ->orWhere('tm.movimientostock_salida_id', $id)
                        ->orWhere('tm.movimientostock_entrada_id', $id);
                }
            }

            if ($esMovimiento) {
                $q->orWhere('ms.codigo', 'like', $like)
                    ->orWhere('tts.nombre', 'like', $like)
                    ->orWhere('ms.leyenda', 'like', $like)
                    ->orWhere('am_agg.lote', 'like', $like)
                    ->orWhere('empresa.nombre', 'like', $like)
                    ->orWhere('usuario.nombre', 'like', $like)
                    ->orWhere('depmae.nombre', 'like', $like)
                    ->orWhere('depmae.codigo', 'like', $like)
                    ->orWhere('mventa.nombre', 'like', $like)
                    ->orWhere('ms.estado', 'like', $like);
            } else {
                $q->orWhere('tm.codigo', 'like', $like)
                    ->orWhere('tts.nombre', 'like', $like)
                    ->orWhere('tm.observacion', 'like', $like)
                    ->orWhere('tm.lote', 'like', $like)
                    ->orWhere('empresa.nombre', 'like', $like)
                    ->orWhere('u_orig.nombre', 'like', $like)
                    ->orWhere('dep_o.nombre', 'like', $like)
                    ->orWhere('dep_o.codigo', 'like', $like)
                    ->orWhere('dep_d.nombre', 'like', $like)
                    ->orWhere('dep_d.codigo', 'like', $like)
                    ->orWhere('tm.estado', 'like', $like);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltroCampo(QueryBuilder $query, array $filtros, bool $esMovimiento): void
    {
        $campo = (string) ($filtros['campo'] ?? 'codigo');
        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = (string) ($filtros['operador'] ?? 'contiene');
        $like = '%'.MovimientoStockListadoFiltros::escapeLikePublic($valor).'%';

        $mapMov = [
            'id' => 'ms.id',
            'fecha' => 'ms.fecha',
            'codigo' => 'ms.codigo',
            'tipo' => 'tts.nombre',
            'marca' => 'mventa.nombre',
            'lote' => 'am_agg.lote',
            'deposito' => 'depmae.codigo',
            'empresa' => 'empresa.nombre',
            'leyenda' => 'ms.leyenda',
            'estado' => 'ms.estado',
            'usuario' => 'usuario.nombre',
        ];

        $mapTm = [
            'id' => 'tm.id',
            'fecha' => 'tm.fecha',
            'codigo' => 'tm.codigo',
            'tipo' => 'tts.nombre',
            'marca' => DB::raw('NULL'),
            'lote' => 'tm.lote',
            'deposito' => 'dep_o.codigo',
            'empresa' => 'empresa.nombre',
            'leyenda' => 'tm.observacion',
            'estado' => 'tm.estado',
            'usuario' => 'u_orig.nombre',
        ];

        if ($esMovimiento && ! isset($mapMov[$campo])) {
            return;
        }
        if (! $esMovimiento && ! isset($mapTm[$campo])) {
            return;
        }

        if ($campo === 'id') {
            if (filter_var($valor, FILTER_VALIDATE_INT) !== false) {
                $columna = $esMovimiento ? 'ms.id' : 'tm.id';
                $query->where($columna, '=', (int) $valor);
            }

            return;
        }

        if ($campo === 'marca' && ! $esMovimiento) {
            $query->whereRaw('1 = 0');

            return;
        }

        $columna = $esMovimiento ? $mapMov[$campo] : $mapTm[$campo];

        if ($operador === 'igual') {
            $query->where($columna, '=', $valor);
        } elseif ($operador === 'distinto') {
            $query->where($columna, '!=', $valor);
        } elseif ($operador === 'empieza') {
            $query->where($columna, 'like', MovimientoStockListadoFiltros::escapeLikePublic($valor).'%');
        } elseif ($operador === 'termina') {
            $query->where($columna, 'like', '%'.MovimientoStockListadoFiltros::escapeLikePublic($valor));
        } else {
            $query->where($columna, 'like', $like);
        }
    }

    /**
     * @param  Collection<int, object>|array<int, object>  $filasRaw
     * @return Collection<int, MovimientoStockListadoFila>
     */
    private function hidratarFilas($filasRaw): Collection
    {
        $filasRaw = collect($filasRaw);
        if ($filasRaw->isEmpty()) {
            return collect();
        }

        $movIds = $filasRaw->pluck('movimientostock_id')->filter()->unique()->values()->all();
        $tmIds = $filasRaw->where('fila_tipo', 'transferencia')->pluck('transferencia_id')->filter()->unique()->values()->all();

        $movimientos = $movIds === []
            ? collect()
            : MovimientoStock::query()
                ->with(['tipotransaccion_stock:id,nombre', 'mventas:id,nombre'])
                ->whereIn('id', $movIds)
                ->get()
                ->keyBy('id');

        $transferencias = $tmIds === []
            ? collect()
            : Transferencia_Mercaderia::query()
                ->with([
                    'tipotransaccion_stock:id,nombre,abreviatura',
                    'depositoOrigen:id,codigo,nombre',
                    'depositoDestino:id,codigo,nombre',
                    'bienUsoOrigen:id,codigo_inventario,hostname,modelo',
                    'bienUsoDestino:id,codigo_inventario,hostname,modelo',
                    'usuarioOrigen:id,nombre',
                    'empresas:id,nombre',
                ])
                ->whereIn('id', $tmIds)
                ->get()
                ->keyBy('id');

        return $filasRaw->map(function ($raw) use ($movimientos, $transferencias) {
            return MovimientoStockListadoFila::desdeRaw($raw, $movimientos, $transferencias);
        });
    }
}
