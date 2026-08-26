<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Repositories\Contable\AsientoRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Informix no comparte la TX MySQL: si create() ya grabó ctamov y el cierre aborta,
 * quedan asientos huérfanos (bingo Rebisco 20/8 y 22/8; estacionamiento Rebisco 20/8).
 * Registrar la compensación ANTES de guardarAnita, para que un fallo a mitad de
 * ctamov también dispare el borrado al hacer rollback MySQL.
 */
final class AsientoCtamovRollbackSupport
{
    public static function registrarSiHayTransaccion(int $empresaId, string $numeroAsiento): void
    {
        if (DB::transactionLevel() <= 0) {
            return;
        }

        $numeroAsiento = trim($numeroAsiento);
        if ($empresaId <= 0 || $numeroAsiento === '') {
            return;
        }

        DB::afterRollBack(static function () use ($empresaId, $numeroAsiento): void {
            try {
                app(AsientoRepositoryInterface::class)->eliminarCtamovAnitaPorNumero(
                    $empresaId,
                    $numeroAsiento,
                );
                Log::warning('asiento_ctamov.compensado_tras_rollback', [
                    'empresa_id' => $empresaId,
                    'numeroasiento' => $numeroAsiento,
                ]);
            } catch (\Throwable $e) {
                Log::error('asiento_ctamov.compensacion_rollback_fallo', [
                    'empresa_id' => $empresaId,
                    'numeroasiento' => $numeroAsiento,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
