<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\Log;

/**
 * Recepciones y devoluciones de proveedor: la única escritura Anita que queda es contab.ctamov.
 * recepmae, recepmov, aplicped, stkmov, stkmae, pendmovp, recpunica y numerador no se replican.
 */
final class RecepcionProveedorAnitaSalidaSupport
{
    public static function soloCtamov(): bool
    {
        return filter_var(
            config('recepcion_proveedor.anita.solo_escritura_ctamov', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function logOmitida(string $operacion, ?int $recepcionId = null): void
    {
        Log::info('recepcion_proveedor.anita.escritura_omitida', [
            'operacion' => $operacion,
            'recepcion_id' => $recepcionId,
            'motivo' => 'solo ctamov',
        ]);
    }
}
