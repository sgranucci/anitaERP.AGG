<?php

namespace App\Support\Stock;

use App\Models\Contable\Centrocosto;
use App\Models\Stock\Depmae;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Transferencia_Mercaderia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;

/**
 * Alcance del listado y acceso a movimientos de stock / transferencias por centro de costo
 * y depósitos autorizados del usuario.
 */
final class MovimientoStockVisibilidadSupport
{
    public const PERMISO_VER_TODOS = 'listar-todos-movimientos-de-stock';

    public static function puedeVerTodos(): bool
    {
        return can(self::PERMISO_VER_TODOS, false);
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

    public static function tieneRestriccionPorCentrocosto(): bool
    {
        return self::centrocostoFiltroUsuario() !== null;
    }

    public static function tieneRestriccionPorDeposito(): bool
    {
        return UsuarioDepositoAutorizado::tieneRestriccion();
    }

    public static function tieneAlgunaRestriccion(): bool
    {
        return self::tieneRestriccionPorCentrocosto() || self::tieneRestriccionPorDeposito();
    }

    public static function etiquetaAlcanceActivo(): ?string
    {
        $partes = [];

        $centrocosto = self::etiquetaCentrocostoActivo();
        if ($centrocosto !== null) {
            $partes[] = 'Centro de costo: '.$centrocosto;
        }

        $depositos = self::etiquetaDepositosActivo();
        if ($depositos !== null) {
            $partes[] = 'Depósitos: '.$depositos;
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

    /**
     * @param  QueryBuilder  $query
     */
    public static function aplicarFiltroCentrocostoMovimientoQuery(QueryBuilder $query, string $aliasMov = 'ms'): void
    {
        $centrocostoId = self::centrocostoFiltroUsuario();
        if ($centrocostoId === null) {
            return;
        }

        $query->where(function ($q) use ($centrocostoId, $aliasMov) {
            $q->where("{$aliasMov}.centrocosto_destino_id", $centrocostoId)
                ->orWhere(function ($q2) use ($centrocostoId, $aliasMov) {
                    $q2->whereNull("{$aliasMov}.centrocosto_destino_id")
                        ->whereIn("{$aliasMov}.usuario_id", function ($sub) use ($centrocostoId) {
                            $sub->from('usuario')
                                ->where('centrocosto_id', $centrocostoId)
                                ->select('id');
                        });
                });
        });
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

        $query->where(function ($q) use ($centrocostoId, $aliasTm) {
            $q->where("{$aliasTm}.centrocosto_destino_id", $centrocostoId)
                ->orWhere(function ($q2) use ($centrocostoId, $aliasTm) {
                    $q2->whereNull("{$aliasTm}.centrocosto_destino_id")
                        ->whereIn("{$aliasTm}.usuario_origen_id", function ($sub) use ($centrocostoId) {
                            $sub->from('usuario')
                                ->where('centrocosto_id', $centrocostoId)
                                ->select('id');
                        });
                });
        });
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
     * @param  QueryBuilder  $query
     */
    public static function aplicarFiltroDepositosTransferenciaQuery(QueryBuilder $query, string $aliasTm = 'tm'): void
    {
        $depositoIds = self::depositoIdsRestringidos();
        if (! is_array($depositoIds) || $depositoIds === []) {
            return;
        }

        $query->where(function ($q) use ($depositoIds, $aliasTm) {
            $q->whereIn("{$aliasTm}.deposito_origen_id", $depositoIds)
                ->orWhereIn("{$aliasTm}.deposito_destino_id", $depositoIds);
        });
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

        $query->where(function ($q) use ($centrocostoId) {
            $q->where('movimientostock.centrocosto_destino_id', $centrocostoId)
                ->orWhere(function ($q2) use ($centrocostoId) {
                    $q2->whereNull('movimientostock.centrocosto_destino_id')
                        ->whereIn('movimientostock.usuario_id', function ($sub) use ($centrocostoId) {
                            $sub->from('usuario')
                                ->where('centrocosto_id', $centrocostoId)
                                ->select('id');
                        });
                });
        });
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

        $query->where(function ($q) use ($centrocostoId) {
            $q->where('transferencia_mercaderia.centrocosto_destino_id', $centrocostoId)
                ->orWhere(function ($q2) use ($centrocostoId) {
                    $q2->whereNull('transferencia_mercaderia.centrocosto_destino_id')
                        ->whereIn('transferencia_mercaderia.usuario_origen_id', function ($sub) use ($centrocostoId) {
                            $sub->from('usuario')
                                ->where('centrocosto_id', $centrocostoId)
                                ->select('id');
                        });
                });
        });
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
     * @param  Builder<\App\Models\Stock\Transferencia_Mercaderia>  $query
     */
    private static function aplicarFiltroDepositosTransferenciaEloquent(Builder $query): void
    {
        $depositoIds = self::depositoIdsRestringidos();
        if (! is_array($depositoIds) || $depositoIds === []) {
            return;
        }

        $query->where(function ($q) use ($depositoIds) {
            $q->whereIn('transferencia_mercaderia.deposito_origen_id', $depositoIds)
                ->orWhereIn('transferencia_mercaderia.deposito_destino_id', $depositoIds);
        });
    }

    public static function movimientoAccesiblePorId(int $movimientoId): bool
    {
        if ($movimientoId <= 0) {
            return false;
        }

        if (self::puedeVerTodos() && ! self::tieneRestriccionPorDeposito()) {
            return MovimientoStock::query()->whereKey($movimientoId)->exists();
        }

        $query = MovimientoStock::query()->where('movimientostock.id', $movimientoId);
        self::aplicarFiltroCentrocostoMovimientoEloquent($query);
        self::aplicarFiltroDepositosMovimientoEloquent($query);

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

        if (self::puedeVerTodos() && ! self::tieneRestriccionPorDeposito()) {
            return Transferencia_Mercaderia::query()->whereKey($transferenciaId)->exists();
        }

        $query = Transferencia_Mercaderia::query()->where('transferencia_mercaderia.id', $transferenciaId);
        self::aplicarFiltroCentrocostoTransferenciaEloquent($query);
        self::aplicarFiltroDepositosTransferenciaEloquent($query);

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
