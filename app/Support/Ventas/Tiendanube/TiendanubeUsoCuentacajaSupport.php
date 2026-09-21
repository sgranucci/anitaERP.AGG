<?php

declare(strict_types=1);

namespace App\Support\Ventas\Tiendanube;

use App\Models\Caja\Usocuentacaja;
use Illuminate\Support\Facades\Schema;

/**
 * Uso de cuenta de caja «TIENDA NUBE» para facturación e-commerce Ferli.
 */
final class TiendanubeUsoCuentacajaSupport
{
    public const NOMBRE_DEFAULT = 'TIENDA NUBE';

    public static function nombre(?string $storeId = null): string
    {
        if (class_exists(TiendanubeConfiguracionSupport::class)
            && TiendanubeConfiguracionSupport::tablasListas()) {
            $desdeDb = trim(TiendanubeConfiguracionSupport::usocuentacajaNombre($storeId));
            if ($desdeDb !== '') {
                return $desdeDb;
            }
        }

        return trim((string) config('tiendanube.usocuentacaja_nombre', self::NOMBRE_DEFAULT))
            ?: self::NOMBRE_DEFAULT;
    }

    public static function resolverId(): ?int
    {
        if (! Schema::hasTable('usocuentacaja')) {
            return null;
        }

        $id = Usocuentacaja::query()->where('nombre', self::nombre())->value('id');

        return $id ? (int) $id : null;
    }

    public static function asegurarId(): int
    {
        $id = self::resolverId();
        if ($id !== null && $id > 0) {
            return $id;
        }

        $uso = Usocuentacaja::query()->firstOrCreate(
            ['nombre' => self::nombre()],
            ['nombre' => self::nombre()]
        );

        return (int) $uso->id;
    }
}
