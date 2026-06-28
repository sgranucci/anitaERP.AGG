<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Caja\Usocuentacaja;
use Illuminate\Support\Facades\Schema;

/**
 * Uso de cuenta de caja «Gastronomía» (POS, jornada, rendición vending, etc.).
 */
final class GastronomiaUsoCuentacajaSupport
{
    public static function resolverId(): ?int
    {
        $configured = config('gastronomia.usocuentacaja_id');
        if ($configured !== null && $configured !== '') {
            return (int) $configured;
        }

        if (! Schema::hasTable('usocuentacaja')) {
            return null;
        }

        $id = Usocuentacaja::query()->where('nombre', 'Gastronomia')->value('id');

        return $id ? (int) $id : null;
    }
}
