<?php

namespace App\Support\Stock;

use App\Models\Stock\Depmae;
use App\Repositories\Stock\Tipotransaccion_StockRepository;
use Illuminate\Support\Facades\Cache;

final class MovimientoStockPreferenciasUsuario
{
    public const CACHE_TIPO_TRANSACCION = 'movimientostock-tipotransaccion';

    public const CACHE_DEPOSITO = 'movimientostock-deposito';

    public static function persistirTipoTransaccion(?int $tipoStockId): void
    {
        if ($tipoStockId === null || $tipoStockId <= 0) {
            return;
        }

        Cache::forever(generaKey(self::CACHE_TIPO_TRANSACCION), $tipoStockId);
    }

    public static function resolverTipoTransaccionDefaultId(): ?int
    {
        $cached = cache()->get(generaKey(self::CACHE_TIPO_TRANSACCION));
        if ($cached === null || $cached === '') {
            return null;
        }

        $resolved = app(Tipotransaccion_StockRepository::class)->resolveIdFromLegacy((int) $cached);

        if ($resolved <= 0 || ! UsuarioTipotransaccionStockAutorizado::tipotransaccionAutorizada($resolved)) {
            return null;
        }

        return $resolved;
    }

    public static function persistirDeposito(?int $depositoId): void
    {
        if ($depositoId === null || $depositoId <= 0) {
            return;
        }

        if (! UsuarioDepositoAutorizado::depositoAutorizado($depositoId)) {
            return;
        }

        Cache::forever(generaKey(self::CACHE_DEPOSITO), $depositoId);
    }

    public static function resolverDepositoDefaultId(): ?int
    {
        $cached = cache()->get(generaKey(self::CACHE_DEPOSITO));
        if ($cached === null || $cached === '') {
            return null;
        }

        $depositoId = (int) $cached;
        if ($depositoId <= 0 || ! UsuarioDepositoAutorizado::depositoAutorizado($depositoId)) {
            return null;
        }

        if (! Depmae::query()->whereKey($depositoId)->exists()) {
            return null;
        }

        return $depositoId;
    }

    /**
     * Persiste tipo y depósito usados en el último movimiento guardado.
     *
     * @param  array<string, mixed>  $data
     */
    public static function persistirDesdeDatos(array $data): void
    {
        $tipoStockId = (int) ($data['tipotransaccion_stock_id'] ?? $data['tipotransaccion_id'] ?? 0);
        self::persistirTipoTransaccion($tipoStockId > 0 ? $tipoStockId : null);

        $depositoId = (int) ($data['deposito_id'] ?? 0);
        if ($depositoId <= 0) {
            $depositoId = (int) ($data['deposito_salida_id'] ?? 0);
        }
        self::persistirDeposito($depositoId > 0 ? $depositoId : null);
    }
}
