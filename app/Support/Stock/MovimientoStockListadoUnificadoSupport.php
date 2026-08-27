<?php

namespace App\Support\Stock;

use App\Models\Stock\Depmae;
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
        if (! MovimientoStockListadoFiltros::tieneCriteriosInteligentes($filtros)) {
            return $this->listarPorClaves($filtros, $paginar);
        }

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
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('transferencia_mercaderia as t1')
                    ->whereColumn('t1.movimientostock_salida_id', 'ms.id');
            })
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('transferencia_mercaderia as t2')
                    ->whereColumn('t2.movimientostock_entrada_id', 'ms.id');
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
            ->leftJoin('usuario as u_orig', 'u_orig.id', '=', 'tm.usuario_origen_id')
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
                // No seleccionar texto de bien_uso acá: utf8mb4_unicode_ci vs spanish_ci rompe el UNION (1271).
                DB::raw('NULL as bien_uso_origen_etiqueta'),
                DB::raw('NULL as bien_uso_destino_etiqueta'),
                DB::raw('NULL as marca_nombre'),
                'empresa.nombre as nombreempresa',
                'tm.empresa_id as empresa_id_listado',
                DB::raw('COALESCE(tm.deposito_origen_id, tm.deposito_destino_id, 0) as deposito_id_listado'),
                'u_orig.nombre as usuario_nombre',
            ]);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($movQuery, 'depmae.empresa_id');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($tmQuery, 'tm.empresa_id');

        $empresaFiltro = (int) ($filtros['empresa_id'] ?? 0);
        $omitirCcSurmar = MovimientoStockVisibilidadSupport::omitirFiltroCentrocostoEmpresaSurmar(
            $empresaFiltro > 0 ? $empresaFiltro : null
        );

        if (! $omitirCcSurmar) {
            MovimientoStockVisibilidadSupport::aplicarFiltroCentrocostoMovimientoQuery($movQuery);
            MovimientoStockVisibilidadSupport::aplicarFiltroCentrocostoTransferenciaQuery($tmQuery);
        }

        MovimientoStockVisibilidadSupport::aplicarFiltroDepositosMovimientoQuery($movQuery, 'am_agg.deposito_id');
        MovimientoStockVisibilidadSupport::aplicarFiltroDepositosTransferenciaQuery($tmQuery);
        MovimientoStockVisibilidadSupport::aplicarFiltroTipotransaccionesMovimientoQuery($movQuery);
        MovimientoStockVisibilidadSupport::aplicarFiltroTipotransaccionesTransferenciaQuery($tmQuery);
        $this->aplicarFiltrosInteligentes($movQuery, $filtros, true);
        $this->aplicarFiltrosInteligentes($tmQuery, $filtros, false);

        if ($empresaFiltro > 0 && $this->empresaRepository->empresaIdPermitida($empresaFiltro)) {
            $movQuery->where('depmae.empresa_id', $empresaFiltro);
            $tmQuery->where('tm.empresa_id', $empresaFiltro);
        }

        $depositoFiltro = (int) ($filtros['deposito_id'] ?? 0);
        if ($depositoFiltro > 0 && UsuarioDepositoAutorizado::depositoAutorizado($depositoFiltro)) {
            $movQuery->where('am_agg.deposito_id', $depositoFiltro);
            $tmQuery->where('tm.deposito_origen_id', $depositoFiltro);
        }

        return $movQuery->unionAll($tmQuery);
    }

    /**
     * Listado sin búsqueda de texto: no agrega articulo_movimiento completo.
     * Cuenta cada pata por separado y solo hidrata la página.
     *
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, MovimientoStockListadoFila>|Collection<int, MovimientoStockListadoFila>
     */
    private function listarPorClaves(array $filtros, bool $paginar): LengthAwarePaginator|Collection
    {
        $mov = $this->queryMovimientosClaves($filtros);
        $tm = $this->queryTransferenciasClaves($filtros);

        $ordenado = static function (QueryBuilder $union): QueryBuilder {
            return DB::query()
                ->fromSub($union, 'listado_ms')
                ->orderByDesc('operacion_stock_id')
                ->orderByDesc('fecha')
                ->orderByDesc('pk_id');
        };

        if ($paginar) {
            $page = PaginatorImpl::resolveCurrentPage();
            $perPage = 15;
            $total = (int) (clone $mov)->count()
                + (int) (clone $tm)->count();
            $claves = $ordenado($mov->unionAll($tm))
                ->forPage($page, $perPage)
                ->get();

            return new PaginatorImpl(
                $this->hidratarDesdeClaves($claves),
                $total,
                $perPage,
                $page,
                ['path' => PaginatorImpl::resolveCurrentPath()]
            );
        }

        return $this->hidratarDesdeClaves($ordenado($mov->unionAll($tm))->get());
    }

    /**
     * Columnas del UNION (por posición): fila_tipo, pk_id, operacion_stock_id, fecha.
     *
     * @param  array<string, mixed>  $filtros
     */
    private function queryMovimientosClaves(array $filtros): QueryBuilder
    {
        $depositoIds = $this->depositoIdsAlcance($filtros);

        $query = DB::table('movimientostock as ms')
            ->selectRaw("'movimiento' as fila_tipo")
            ->selectRaw('ms.id as pk_id')
            ->selectRaw('ms.id as operacion_stock_id')
            ->selectRaw('ms.fecha as fecha')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('transferencia_mercaderia as t1')
                    ->whereColumn('t1.movimientostock_salida_id', 'ms.id');
            })
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('transferencia_mercaderia as t2')
                    ->whereColumn('t2.movimientostock_entrada_id', 'ms.id');
            });

        if ($depositoIds === []) {
            $query->whereRaw('0 = 1');
        } else {
            $query->whereIn('ms.id', function ($sub) use ($depositoIds) {
                $sub->from('articulo_movimiento as am_f')
                    ->select('am_f.movimientostock_id')
                    ->whereIn('am_f.deposito_id', $depositoIds)
                    ->whereNotNull('am_f.movimientostock_id');
            });
        }

        $empresaFiltro = (int) ($filtros['empresa_id'] ?? 0);
        $omitirCcSurmar = MovimientoStockVisibilidadSupport::omitirFiltroCentrocostoEmpresaSurmar(
            $empresaFiltro > 0 ? $empresaFiltro : null
        );
        if (! $omitirCcSurmar) {
            MovimientoStockVisibilidadSupport::aplicarFiltroCentrocostoMovimientoQuery($query);
        }
        MovimientoStockVisibilidadSupport::aplicarFiltroTipotransaccionesMovimientoQuery($query);

        return $query;
    }

    /**
     * Mismo orden de columnas que queryMovimientosClaves (UNION ALL).
     *
     * @param  array<string, mixed>  $filtros
     */
    private function queryTransferenciasClaves(array $filtros): QueryBuilder
    {
        $query = DB::table('transferencia_mercaderia as tm')
            ->selectRaw("'transferencia' as fila_tipo")
            ->selectRaw('tm.id as pk_id')
            ->selectRaw(
                'GREATEST(COALESCE(tm.movimientostock_salida_id, 0), COALESCE(tm.movimientostock_entrada_id, 0)) as operacion_stock_id'
            )
            ->selectRaw('tm.fecha as fecha');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'tm.empresa_id');

        $empresaFiltro = (int) ($filtros['empresa_id'] ?? 0);
        $omitirCcSurmar = MovimientoStockVisibilidadSupport::omitirFiltroCentrocostoEmpresaSurmar(
            $empresaFiltro > 0 ? $empresaFiltro : null
        );
        if (! $omitirCcSurmar) {
            MovimientoStockVisibilidadSupport::aplicarFiltroCentrocostoTransferenciaQuery($query);
        }
        MovimientoStockVisibilidadSupport::aplicarFiltroDepositosTransferenciaQuery($query);
        MovimientoStockVisibilidadSupport::aplicarFiltroTipotransaccionesTransferenciaQuery($query);

        if ($empresaFiltro > 0 && $this->empresaRepository->empresaIdPermitida($empresaFiltro)) {
            $query->where('tm.empresa_id', $empresaFiltro);
        }

        $depositoFiltro = (int) ($filtros['deposito_id'] ?? 0);
        if ($depositoFiltro > 0 && UsuarioDepositoAutorizado::depositoAutorizado($depositoFiltro)) {
            $query->where('tm.deposito_origen_id', $depositoFiltro);
        }

        return $query;
    }

    /**
     * Depósitos del alcance (empresa + autorización). Vacío = no hay nada que listar.
     *
     * @param  array<string, mixed>  $filtros
     * @return list<int>
     */
    private function depositoIdsAlcance(array $filtros): array
    {
        $query = DB::table('depmae')->select('id');

        $empresaFiltro = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaFiltro > 0 && $this->empresaRepository->empresaIdPermitida($empresaFiltro)) {
            $query->where('empresa_id', $empresaFiltro);
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
        }

        $depositoFiltro = (int) ($filtros['deposito_id'] ?? 0);
        if ($depositoFiltro > 0 && UsuarioDepositoAutorizado::depositoAutorizado($depositoFiltro)) {
            $query->where('id', $depositoFiltro);
        }

        $restringidos = MovimientoStockVisibilidadSupport::depositoIdsRestringidos();
        if (is_array($restringidos) && $restringidos !== []) {
            $query->whereIn('id', $restringidos);
        }

        return array_values(array_map(
            static fn ($id): int => (int) $id,
            $query->pluck('id')->all()
        ));
    }

    /**
     * @param  Collection<int, object>|array<int, object>  $claves
     * @return Collection<int, MovimientoStockListadoFila>
     */
    private function hidratarDesdeClaves($claves): Collection
    {
        $claves = collect($claves);
        if ($claves->isEmpty()) {
            return collect();
        }

        $movIds = $claves->where('fila_tipo', 'movimiento')->pluck('pk_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $tmIds = $claves->where('fila_tipo', 'transferencia')->pluck('pk_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $aggMov = [];
        if ($movIds !== []) {
            foreach (DB::table('articulo_movimiento')
                ->select('movimientostock_id')
                ->selectRaw('MIN(deposito_id) as deposito_id')
                ->selectRaw('MIN(lote) as lote')
                ->selectRaw('SUM(ABS(cantidad)) as total_cantidad')
                ->selectRaw('COUNT(*) as items_count')
                ->whereIn('movimientostock_id', $movIds)
                ->groupBy('movimientostock_id')
                ->get() as $row) {
                $aggMov[(int) $row->movimientostock_id] = $row;
            }
        }

        $aggTm = [];
        if ($tmIds !== []) {
            foreach (DB::table('transferencia_mercaderia_articulo')
                ->select('transferencia_mercaderia_id')
                ->selectRaw('SUM(ABS(cantidad_destino)) as total_cantidad')
                ->selectRaw('COUNT(*) as items_count')
                ->whereIn('transferencia_mercaderia_id', $tmIds)
                ->groupBy('transferencia_mercaderia_id')
                ->get() as $row) {
                $aggTm[(int) $row->transferencia_mercaderia_id] = $row;
            }
        }

        $depositos = collect();
        $depIds = collect($aggMov)->pluck('deposito_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($depIds !== []) {
            $depositos = Depmae::query()
                ->with('empresas:id,nombre')
                ->whereIn('id', $depIds)
                ->get(['id', 'codigo', 'nombre', 'empresa_id'])
                ->keyBy('id');
        }

        $usuarios = collect();
        $movimientos = $movIds === []
            ? collect()
            : MovimientoStock::query()
                ->with(['tipotransaccion_stock:id,nombre', 'mventas:id,nombre'])
                ->whereIn('id', $movIds)
                ->get()
                ->keyBy('id');
        $usuarioIds = $movimientos->pluck('usuario_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($usuarioIds !== []) {
            $usuarios = DB::table('usuario')->whereIn('id', $usuarioIds)->pluck('nombre', 'id');
        }

        $transferencias = $tmIds === []
            ? collect()
            : Transferencia_Mercaderia::query()
                ->with([
                    'tipotransaccion_stock:id,nombre,abreviatura',
                    'depositoOrigen:id,codigo,nombre',
                    'depositoDestino:id,codigo,nombre',
                    'bienUsoOrigen:'.implode(',', TransferenciaBienUsoSupport::BIEN_USO_RELATION_COLUMNS),
                    'bienUsoDestino:'.implode(',', TransferenciaBienUsoSupport::BIEN_USO_RELATION_COLUMNS),
                    'usuarioOrigen:id,nombre',
                    'empresas:id,nombre',
                ])
                ->whereIn('id', $tmIds)
                ->get()
                ->keyBy('id');

        $filasRaw = $claves->map(function ($clave) use ($aggMov, $aggTm, $depositos, $usuarios, $movimientos, $transferencias) {
            $raw = new \stdClass();
            $raw->fila_tipo = (string) ($clave->fila_tipo ?? 'movimiento');
            $raw->pk_id = (int) ($clave->pk_id ?? 0);
            $raw->fecha = $clave->fecha ?? null;
            $raw->bien_uso_origen_etiqueta = null;
            $raw->bien_uso_destino_etiqueta = null;

            if ($raw->fila_tipo === 'transferencia') {
                $tm = $transferencias->get($raw->pk_id);
                $agg = $aggTm[$raw->pk_id] ?? null;
                $raw->transferencia_id = $raw->pk_id;
                $raw->movimientostock_id = $tm
                    ? ((int) ($tm->movimientostock_salida_id ?? 0) ?: (int) ($tm->movimientostock_entrada_id ?? 0))
                    : null;
                $raw->mov_salida_id = $tm?->movimientostock_salida_id;
                $raw->mov_entrada_id = $tm?->movimientostock_entrada_id;
                $raw->codigo_listado = (string) ($tm?->codigo ?? '');
                $raw->tipo_nombre = (string) (optional($tm?->tipotransaccion_stock)->nombre ?? '');
                $raw->leyenda_listado = (string) ($tm?->observacion ?? '');
                $raw->lote_listado = (string) ($tm?->lote ?? '');
                $raw->total_cantidad = (float) ($agg->total_cantidad ?? 0);
                $raw->items_count = (int) ($agg->items_count ?? 0);
                $raw->estado_movimiento = null;
                $raw->estado_transferencia = $tm?->estado;
                $raw->deposito_codigo = null;
                $raw->deposito_nombre = null;
                $raw->deposito_id_listado = (int) ($tm?->deposito_origen_id ?? $tm?->deposito_destino_id ?? 0);
                $raw->deposito_origen_codigo = optional($tm?->depositoOrigen)->codigo;
                $raw->deposito_origen_nombre = optional($tm?->depositoOrigen)->nombre;
                $raw->deposito_origen_id = $tm?->deposito_origen_id;
                $raw->deposito_destino_codigo = optional($tm?->depositoDestino)->codigo;
                $raw->deposito_destino_nombre = optional($tm?->depositoDestino)->nombre;
                $raw->deposito_destino_id = $tm?->deposito_destino_id;
                $raw->marca_nombre = null;
                $raw->nombreempresa = (string) (optional($tm?->empresas)->nombre ?? '');
                $raw->usuario_nombre = (string) (optional($tm?->usuarioOrigen)->nombre ?? '');

                return $raw;
            }

            $mov = $movimientos->get($raw->pk_id);
            $agg = $aggMov[$raw->pk_id] ?? null;
            $depId = (int) ($agg->deposito_id ?? 0);
            $dep = $depId > 0 ? $depositos->get($depId) : null;
            $raw->transferencia_id = null;
            $raw->movimientostock_id = $raw->pk_id;
            $raw->mov_salida_id = null;
            $raw->mov_entrada_id = null;
            $raw->codigo_listado = (string) ($mov?->codigo ?? '');
            $raw->tipo_nombre = (string) (optional($mov?->tipotransaccion_stock)->nombre ?? '');
            $raw->leyenda_listado = (string) ($mov?->leyenda ?? '');
            $raw->lote_listado = (string) ($agg->lote ?? '');
            $raw->total_cantidad = (float) ($agg->total_cantidad ?? 0);
            $raw->items_count = (int) ($agg->items_count ?? 0);
            $raw->estado_movimiento = $mov?->estado;
            $raw->estado_transferencia = null;
            $raw->deposito_codigo = $dep?->codigo;
            $raw->deposito_nombre = $dep?->nombre;
            $raw->deposito_id_listado = $depId;
            $raw->deposito_origen_codigo = null;
            $raw->deposito_origen_nombre = null;
            $raw->deposito_origen_id = null;
            $raw->deposito_destino_codigo = null;
            $raw->deposito_destino_nombre = null;
            $raw->deposito_destino_id = null;
            $raw->marca_nombre = optional($mov?->mventas)->nombre;
            $raw->nombreempresa = (string) (optional($dep?->empresas)->nombre ?? '');
            $raw->usuario_nombre = (string) ($usuarios[(int) ($mov?->usuario_id ?? 0)] ?? '');

            return $raw;
        });

        return $this->hidratarFilas($filasRaw, $movimientos, $transferencias);
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
     * @param  Collection<int, MovimientoStock>|null  $movimientos
     * @param  Collection<int, Transferencia_Mercaderia>|null  $transferencias
     * @return Collection<int, MovimientoStockListadoFila>
     */
    private function hidratarFilas($filasRaw, ?Collection $movimientos = null, ?Collection $transferencias = null): Collection
    {
        $filasRaw = collect($filasRaw);
        if ($filasRaw->isEmpty()) {
            return collect();
        }

        if ($movimientos === null) {
            $movIds = $filasRaw->pluck('movimientostock_id')->filter()->unique()->values()->all();
            $movimientos = $movIds === []
                ? collect()
                : MovimientoStock::query()
                    ->with(['tipotransaccion_stock:id,nombre', 'mventas:id,nombre'])
                    ->whereIn('id', $movIds)
                    ->get()
                    ->keyBy('id');
        }

        if ($transferencias === null) {
            $tmIds = $filasRaw->where('fila_tipo', 'transferencia')->pluck('transferencia_id')->filter()->unique()->values()->all();
            $transferencias = $tmIds === []
                ? collect()
                : Transferencia_Mercaderia::query()
                    ->with([
                        'tipotransaccion_stock:id,nombre,abreviatura',
                        'depositoOrigen:id,codigo,nombre',
                        'depositoDestino:id,codigo,nombre',
                        'bienUsoOrigen:'.implode(',', TransferenciaBienUsoSupport::BIEN_USO_RELATION_COLUMNS),
                        'bienUsoDestino:'.implode(',', TransferenciaBienUsoSupport::BIEN_USO_RELATION_COLUMNS),
                        'usuarioOrigen:id,nombre',
                        'empresas:id,nombre',
                    ])
                    ->whereIn('id', $tmIds)
                    ->get()
                    ->keyBy('id');
        }

        $filas = $filasRaw->map(function ($raw) use ($movimientos, $transferencias) {
            return MovimientoStockListadoFila::desdeRaw($raw, $movimientos, $transferencias);
        });

        return MovimientoStockListadoCostoSupport::enriquecer($filas);
    }
}
