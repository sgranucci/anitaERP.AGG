<?php

namespace App\Support\Ventas;

use App\Models\Stock\Listaprecio;

/**
 * Lista de precios CMV provisorio del listado canjes marketing (código Anita, no id interno).
 */
final class CanjeMarketingListadoListaprecioCmvSupport
{
    private static ?int $listaprecioIdCache = null;

    private static ?string $etiquetaCache = null;

    public static function codigoConfigurado(): int
    {
        return max(1, (int) config('gastronomia.canje_marketing_listado_listaprecio_cmv_codigo', 50));
    }

    public static function resolverListaprecioId(): ?int
    {
        if (self::$listaprecioIdCache !== null) {
            return self::$listaprecioIdCache > 0 ? self::$listaprecioIdCache : null;
        }

        $codigo = self::codigoConfigurado();
        self::$listaprecioIdCache = (int) (Listaprecio::query()
            ->where('codigo', $codigo)
            ->value('id') ?? 0);

        return self::$listaprecioIdCache > 0 ? self::$listaprecioIdCache : null;
    }

    public static function etiquetaLista(): string
    {
        if (self::$etiquetaCache !== null) {
            return self::$etiquetaCache;
        }

        $codigo = self::codigoConfigurado();
        $nombre = trim((string) (Listaprecio::query()
            ->where('codigo', $codigo)
            ->value('nombre') ?? ''));

        self::$etiquetaCache = $nombre !== ''
            ? 'cód. '.$codigo.' — '.$nombre
            : 'cód. '.$codigo;

        return self::$etiquetaCache;
    }
}
