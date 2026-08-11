<?php

namespace App\Support\Stock;

use App\Models\Contable\Centrocosto;
use App\Models\Stock\Depmae;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Models\Stock\Transferencia_Mercaderia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Alcance del listado y acceso a movimientos de stock / transferencias.
 *
 * Por defecto (sin listar-todos-movimientos-de-stock): solo registros cargados por usuarios
 * del mismo centro de costo que el usuario logueado. Además pueden aplicar depósitos y tipos
 * de transacción autorizados en la ficha del usuario (transferencias: solo depósito de origen).
 *
 * Excepción Surmar (El Bierzo): si el listado filtra empresa Surmar, no se aplica el filtro
 * por centro de costo (el histórico Anita quedó con usuario Administrador de otro CC).
 */
final class MovimientoStockVisibilidadSupport
{
    public const PERMISO_VER_TODOS = 'listar-todos-movimientos-de-stock';

    public static function puedeVerTodos(): bool
    {
        return can(self::PERMISO_VER_TODOS, false);
    }

    /**
     * Histórico Anita Surmar se grabó con usuario Administrador (otro CC).
     * En listados/acceso de empresa Surmar no aplicar filtro por centro de costo del cargador.
     */
    public static function omitirFiltroCentrocostoEmpresaSurmar(?int $empresaId): bool
    {
        return SurmarSupport::esEmpresaSurmar($empresaId);
    }

    /**
     * Movimiento con líneas en depósito de empresa Surmar (El Bierzo).
     */
    public static function movimientoPerteneceEmpresaSurmar(int $movimientoId): bool
    {
        if ($movimientoId <= 0 || ! SurmarSupport::esEmpresaSurmar(SurmarSupport::EMPRESA_ID)) {
            return false;
        }

        return DB::table('articulo_movimiento as am')
            ->join('depmae', 'depmae.id', '=', 'am.deposito_id')
            ->where('am.movimientostock_id', $movimientoId)
            ->whereNull('am.deleted_at')
            ->where('depmae.empresa_id', SurmarSupport::EMPRESA_ID)
            ->exists();
    }

    /** Consulta / PDF COM de transferencias (mail aviso, pantalla transferencias, pendientes). */
    public static function puedeConsultarTransferencia(): bool
    {
        return can('listar-movimientos-de-stock', false)
            || can('crear-transferencia-mercaderia', false)
            || can('aprobar-transferencia-mercaderia', false)
            || can('listar-transferencias-pendientes', false);
    }

    public static function abortSiNoPuedeConsultarTransferencia(): void
    {
        if (! self::puedeConsultarTransferencia()) {
            abort(403, 'No tiene permisos para consultar transferencias de mercadería.');
        }
    }

    public static function centrocostoFiltroUsuario(): ?int
    {
        if (self::puedeVerTodos()) {
            return null;
        }

        $id = (int) (Auth::user()->centrocosto_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /** @return list<int>|null */
    public static function depositoIdsRestringidos(): ?array
    {
        return UsuarioDepositoAutorizado::idsRestringidos();
    }

    /** @return list<int>|null */
    public static function tipotransaccionStockIdsRestringidos(): ?array
    {
        return UsuarioTipotransaccionStockAutorizado::idsRestringidos();
    }

    public static function tieneRestriccionPorCentrocosto(): bool
    {
        return self::centrocostoFiltroUsuario() !== null;
    }

    public static function tieneRestriccionPorDeposito(): bool
    {
        return UsuarioDepositoAutorizado::tieneRestriccion();
    }

    public static function tieneRestriccionPorTipotransaccionStock(): bool
    {
        return UsuarioTipotransaccionStockAutorizado::tieneRestriccion();
    }

    public static function tieneAlgunaRestriccion(): bool
    {
        return self::tieneRestriccionPorCentrocosto()
            || self::tieneRestriccionPorDeposito()
            || self::tieneRestriccionPorTipotransaccionStock();
    }

    public static function etiquetaAlcanceActivo(?int $empresaIdFiltro = null): ?string
    {
        $partes = [];

        $omitirCc = self::omitirFiltroCentrocostoEmpresaSurmar($empresaIdFiltro);
        $centrocosto = $omitirCc ? null : self::etiquetaCentrocostoActivo();
        if ($centrocosto !== null) {
            $partes[] = 'Centro de costo: '.$centrocosto;
        }

        $depositos = self::etiquetaDepositosActivo();
        if ($depositos !== null) {
            $partes[] = 'Depósitos: '.$depositos;
        }

        $tipos = self::etiquetaTipotransaccionesStockActivo();
        if ($tipos !== null) {
            $partes[] = 'Tipos trans.: '.$tipos;
        }

        return $partes === [] ? null : implode(' · ', $partes);
    }

    public static function etiquetaCentrocostoActivo(): ?string
    {
        $centrocostoId = self::centrocostoFiltroUsuario();
        if ($centrocostoId === null) {
            return null;
        }

        $centrocosto = Centrocosto::query()->find($centrocostoId);
        if ($centrocosto === null) {
            return null;
        }

        return trim($centrocosto->codigo.' — '.$centrocosto->nombre);
    }

    public static function etiquetaDepositosActivo(): ?string
    {
        $depositoIds = self::depositoIdsRestringidos();
        if (! is_array($depositoIds) || $depositoIds === []) {
            return null;
        }

        $nombres = Depmae::query()
            ->whereIn('id', $depositoIds)
            ->orderBy('nombre')
            ->pluck('nombre')
            ->map(fn ($nombre) => trim((string) $nombre))
            ->filter(fn (string $nombre) => $nombre !== '')
            ->values()
            ->all();

        return $nombres === [] ? null : implode(', ', $nombres);
    }

    public static function etiquetaTipotransaccionesStockActivo(): ?string
    {
        $tipoIds = self::tipotransaccionStockIdsRestringidos();
        if (! is_array($tipoIds) || $tipoIds === []) {
            return null;
        }

        $nombres = Tipotransaccion_Stock::query()
            ->whereIn('id', $tipoIds)
            ->orderBy('nombre')
            ->pluck('nombre')
            ->map(fn ($nombre) => trim((string) $nombre))
            ->filter(fn (string $nombre) => $nombre !== '')
            ->values()
            ->all();

        return $nombres === [] ? null : implode(', ', $nombres);
    }

    /**
     * @param  QueryBuilder  $query
     */
    public static function aplicarFiltroTipotransaccionesMovimientoQuery(QueryBuilder $query, string $aliasMov = 'ms'): void
    {
        $tipoIds = self::tipotransaccionStockIdsRestringidos();
        if (! is_array($tipoIds) || $tipoIds === []) {
            return;
        }

        $query->whereIn("{$aliasMov}.tipotransaccion_stock_id", $tipoIds);
    }

    /**
     * @param  QueryBuilder  $query
     */
    public static function aplicarFiltroTipotransaccionesTransferenciaQuery(QueryBuilder $query, string $aliasTm = 'tm'): void
    {
        $tipoIds = self::tipotransaccionStockIdsRestringidos();
        if (! is_array($tipoIds) || $tipoIds === []) {
            return;
        }

        $query->whereIn("{$aliasTm}.tipotransaccion_stock_id", $tipoIds);
    }

    /**
     * @param  QueryBuilder  $query
     */
    public static function aplicarFiltroCentrocostoMovimientoQuery(QueryBuilder $query, string $aliasMov = 'ms'): void
    {
        $centrocostoId = self::centrocostoFiltroUsuario();
        if ($centrocostoId === null) {
            return;
        }

        $query->whereIn("{$aliasMov}.usuario_id", self::subqueryUsuarioIdsCentrocosto($centrocostoId));
    }

    /**
     * @param  QueryBuilder  $query
     */
    public static function aplicarFiltroCentrocostoTransferenciaQuery(QueryBuilder $query, string $aliasTm = 'tm'): void
    {
        $centrocostoId = self::centrocostoFiltroUsuario();
        if ($centrocostoId === null) {
            return;
        }

        $query->whereIn("{$aliasTm}.usuario_origen_id", self::subqueryUsuarioIdsCentrocosto($centrocostoId));
    }

    /**
     * @return \Closure(\Illuminate\Database\Query\Builder): void
     */
    private static function subqueryUsuarioIdsCentrocosto(int $centrocostoId): \Closure
    {
        return function ($sub) use ($centrocostoId) {
            $sub->from('usuario')
                ->where('centrocosto_id', $centrocostoId)
                ->select('id');
        };
    }

    /**
     * @param  QueryBuilder  $query
     */
    public static function aplicarFiltroDepositosMovimientoQuery(QueryBuilder $query, string $columnaDeposito): void
    {
        $depositoIds = self::depositoIdsRestringidos();
        if (! is_array($depositoIds) || $depositoIds === []) {
            return;
        }

        $query->whereIn($columnaDeposito, $depositoIds);
    }

    /**
     * Transferencias visibles solo si el depósito de origen está autorizado (quien envía).
     * No filtrar por destino: una recepción en un depósito del usuario no implica que la haya cargado.
     *
     * @param  QueryBuilder  $query
     */
    public static function aplicarFiltroDepositosTransferenciaQuery(QueryBuilder $query, string $aliasTm = 'tm'): void
    {
        $depositoIds = self::depositoIdsRestringidos();
        if (! is_array($depositoIds) || $depositoIds === []) {
            return;
        }

        $query->whereIn("{$aliasTm}.deposito_origen_id", $depositoIds);
    }

    /**
     * @param  Builder<\App\Models\Stock\MovimientoStock>  $query
     */
    private static function aplicarFiltroCentrocostoMovimientoEloquent(Builder $query): void
    {
        $centrocostoId = self::centrocostoFiltroUsuario();
        if ($centrocostoId === null) {
            return;
        }

        $query->whereIn('movimientostock.usuario_id', self::subqueryUsuarioIdsCentrocosto($centrocostoId));
    }

    /**
     * @param  Builder<\App\Models\Stock\Transferencia_Mercaderia>  $query
     */
    private static function aplicarFiltroCentrocostoTransferenciaEloquent(Builder $query): void
    {
        $centrocostoId = self::centrocostoFiltroUsuario();
        if ($centrocostoId === null) {
            return;
        }

        $query->whereIn('transferencia_mercaderia.usuario_origen_id', self::subqueryUsuarioIdsCentrocosto($centrocostoId));
    }

    /**
     * @param  Builder<\App\Models\Stock\MovimientoStock>  $query
     */
    private static function aplicarFiltroDepositosMovimientoEloquent(Builder $query): void
    {
        $depositoIds = self::depositoIdsRestringidos();
        if (! is_array($depositoIds) || $depositoIds === []) {
            return;
        }

        $query->whereHas('articulos_movimiento', function ($q) use ($depositoIds) {
            $q->whereIn('deposito_id', $depositoIds);
        });
    }

    /**
     * @param  Builder<\App\Models\Stock\MovimientoStock>  $query
     */
    private static function aplicarFiltroTipotransaccionesMovimientoEloquent(Builder $query): void
    {
        $tipoIds = self::tipotransaccionStockIdsRestringidos();
        if (! is_array($tipoIds) || $tipoIds === []) {
            return;
        }

        $query->whereIn('movimientostock.tipotransaccion_stock_id', $tipoIds);
    }

    /**
     * @param  Builder<\App\Models\Stock\Transferencia_Mercaderia>  $query
     */
    private static function aplicarFiltroTipotransaccionesTransferenciaEloquent(Builder $query): void
    {
        $tipoIds = self::tipotransaccionStockIdsRestringidos();
        if (! is_array($tipoIds) || $tipoIds === []) {
            return;
        }

        $query->whereIn('transferencia_mercaderia.tipotransaccion_stock_id', $tipoIds);
    }

    /**
     * @param  Builder<\App\Models\Stock\Transferencia_Mercaderia>  $query
     */
    private static function aplicarFiltroDepositosTransferenciaEloquent(Builder $query): void
    {
        $depositoIds = self::depositoIdsRestringidos();
        if (! is_array($depositoIds) || $depositoIds === []) {
            return;
        }

        $query->whereIn('transferencia_mercaderia.deposito_origen_id', $depositoIds);
    }

    public static function movimientoAccesiblePorId(int $movimientoId): bool
    {
        if ($movimientoId <= 0) {
            return false;
        }

        if (self::puedeVerTodos() && ! self::tieneRestriccionPorDeposito() && ! self::tieneRestriccionPorTipotransaccionStock()) {
            return MovimientoStock::query()->whereKey($movimientoId)->exists();
        }

        $query = MovimientoStock::query()->where('movimientostock.id', $movimientoId);
        if (! self::movimientoPerteneceEmpresaSurmar($movimientoId)) {
            self::aplicarFiltroCentrocostoMovimientoEloquent($query);
        }
        self::aplicarFiltroDepositosMovimientoEloquent($query);
        self::aplicarFiltroTipotransaccionesMovimientoEloquent($query);

        return $query->exists();
    }

    public static function movimientoAccesible(MovimientoStock $movimiento): bool
    {
        return self::movimientoAccesiblePorId((int) $movimiento->id);
    }

    public static function transferenciaAccesiblePorId(int $transferenciaId): bool
    {
        if ($transferenciaId <= 0) {
            return false;
        }

        if (self::puedeVerTodos() && ! self::tieneRestriccionPorDeposito() && ! self::tieneRestriccionPorTipotransaccionStock()) {
            return Transferencia_Mercaderia::query()->whereKey($transferenciaId)->exists();
        }

        $query = Transferencia_Mercaderia::query()->where('transferencia_mercaderia.id', $transferenciaId);
        self::aplicarFiltroCentrocostoTransferenciaEloquent($query);
        self::aplicarFiltroDepositosTransferenciaEloquent($query);
        self::aplicarFiltroTipotransaccionesTransferenciaEloquent($query);

        return $query->exists();
    }

    public static function abortSiNoAccesibleMovimiento(int $movimientoId): void
    {
        if (! self::movimientoAccesiblePorId($movimientoId)) {
            abort(404);
        }
    }

    public static function abortSiNoAccesibleTransferencia(int $transferenciaId): void
    {
        if (! self::transferenciaAccesiblePorId($transferenciaId)) {
            abort(404);
        }
    }
}
