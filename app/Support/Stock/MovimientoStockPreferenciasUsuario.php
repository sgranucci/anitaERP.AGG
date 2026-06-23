<?php

namespace App\Support\Stock;

use App\Repositories\Stock\Tipotransaccion_StockRepository;
use Illuminate\Support\Facades\Cache;

final class MovimientoStockPreferenciasUsuario
{
    public const CACHE_TIPO_TRANSACCION = 'movimientostock-tipotransaccion';

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

        return $resolved > 0 ? $resolved : null;
    }
}
