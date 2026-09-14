<?php

declare(strict_types=1);

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Caja\Usocuentacaja;
use Illuminate\Support\Facades\Schema;

/**
 * Uso de cuenta de caja «Local» (POS Facturación Local / ABM locales).
 */
final class FacturacionLocalUsoCuentacajaSupport
{
    public static function nombre(): string
    {
        return trim((string) config('facturacion_local.usocuentacaja_nombre', 'Local')) ?: 'Local';
    }

    public static function resolverId(): ?int
    {
        $configured = config('facturacion_local.usocuentacaja_id');
        if ($configured !== null && $configured !== '' && (int) $configured > 0) {
            return (int) $configured;
        }

        if (! Schema::hasTable('usocuentacaja')) {
            return null;
        }

        $id = Usocuentacaja::query()->where('nombre', self::nombre())->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * Asegura el uso en BD y lo devuelve.
     */
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
