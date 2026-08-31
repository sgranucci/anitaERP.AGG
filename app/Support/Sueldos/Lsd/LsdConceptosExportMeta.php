<?php

namespace App\Support\Sueldos\Lsd;

use Illuminate\Support\Facades\Cache;

class LsdConceptosExportMeta
{
    public const CACHE_AT = 'lsd.conceptos_exportados_at';

    public const CACHE_CANTIDAD = 'lsd.conceptos_exportados_cantidad';

    public static function marcar(int $cantidad): void
    {
        Cache::forever(self::CACHE_AT, now()->toDateTimeString());
        Cache::forever(self::CACHE_CANTIDAD, $cantidad);
    }

    public static function exportadoAt(): ?string
    {
        $v = Cache::get(self::CACHE_AT);

        return $v ? (string) $v : null;
    }

    public static function cantidad(): int
    {
        return (int) Cache::get(self::CACHE_CANTIDAD, 0);
    }
}
