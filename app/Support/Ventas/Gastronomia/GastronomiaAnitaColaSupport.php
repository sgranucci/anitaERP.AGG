<?php

namespace App\Support\Ventas\Gastronomia;

use App\Jobs\Ventas\ReplicarAnitaGastronomiaJob;
use Illuminate\Support\Facades\Log;

/**
 * Despacha replicación Anita post-emisión a cola Laravel (libera workers Apache).
 */
final class GastronomiaAnitaColaSupport
{
    /**
     * @param  array<string, mixed>|null  $anitaPendiente
     * @param  array<string, mixed>|null  $vencaePendiente
     */
    public static function despacharReplicacionDiferida(
        int $ventaId,
        ?array $anitaPendiente,
        ?array $vencaePendiente,
        int $cfgId,
        float $descuentoPie,
        bool $replicarInsumos,
        string $contexto = 'factura',
    ): bool {
        if ($ventaId <= 0) {
            return false;
        }

        if (! filter_var(config('gastronomia.anita_tras_respuesta', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        if (! config('gastronomia.sincronizar_anita_al_facturar', true)) {
            return false;
        }

        if (! filter_var(config('gastronomia.anita_en_cola', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $tieneAnita = is_array($anitaPendiente) && $anitaPendiente !== [];
        $tieneVencae = is_array($vencaePendiente) && $vencaePendiente !== [];
        if (! $tieneAnita && ! $tieneVencae && ! $replicarInsumos) {
            return false;
        }

        if (config('queue.default') === 'sync') {
            Log::warning('gastronomia.anita.cola.sync_driver', [
                'venta_id' => $ventaId,
                'contexto' => $contexto,
            ]);

            return false;
        }

        ReplicarAnitaGastronomiaJob::dispatch(
            $ventaId,
            $anitaPendiente,
            $vencaePendiente,
            $cfgId,
            $descuentoPie,
            $replicarInsumos,
            $contexto,
        );

        Log::info('gastronomia.anita.cola.despachado', [
            'venta_id' => $ventaId,
            'contexto' => $contexto,
            'cola' => config('gastronomia.anita_cola', 'default'),
        ]);

        return true;
    }
}
