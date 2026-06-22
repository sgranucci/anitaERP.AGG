<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Venta;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaReplicaAuthSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ejecuta replicación Anita diferida (venta, vencae, insumos opcionales).
 * Usado por el job en cola y por fallback terminating().
 */
final class GastronomiaAnitaDeferEjecucionService
{
    public function __construct(
        private readonly GastronomiaFacturacionService $facturacionGastronomiaService,
        private readonly GastronomiaInsumoStkmovAnitaService $insumoStkmovAnitaService,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $anitaPendiente
     * @param  array<string, mixed>|null  $vencaePendiente
     */
    public function ejecutar(
        int $ventaId,
        ?array $anitaPendiente,
        ?array $vencaePendiente,
        int $cfgId,
        float $descuentoPie,
        bool $replicarInsumos,
        string $contexto = 'factura',
    ): void {
        if ($ventaId <= 0) {
            return;
        }

        if (! config('gastronomia.sincronizar_anita_al_facturar', true)) {
            return;
        }

        GastronomiaAnitaReplicaAuthSupport::autenticarSiNecesario($ventaId, $anitaPendiente, $contexto);

        if (is_array($anitaPendiente)) {
            try {
                $this->facturacionGastronomiaService->ejecutarAnitaPendienteGastronomia($anitaPendiente);
            } catch (Throwable $e) {
                Log::error('gastronomia.anita.defer.venta_fallo', [
                    'venta_id' => $ventaId,
                    'contexto' => $contexto,
                    'msg' => $e->getMessage(),
                ]);
                $this->limpiarAnitaParcialTrasFallo($ventaId);
            }
        }

        if (is_array($vencaePendiente)) {
            try {
                $this->facturacionGastronomiaService->ejecutarVencaePendienteGastronomia($vencaePendiente);
            } catch (Throwable $e) {
                Log::error('gastronomia.anita.defer.vencae_fallo', [
                    'venta_id' => $ventaId,
                    'contexto' => $contexto,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        if (! $replicarInsumos) {
            return;
        }

        $cfgDefer = ConfiguracionPuntoventaGastronomia::query()->find($cfgId);
        if ($cfgDefer === null) {
            Log::warning('gastronomia.anita.defer.cfg_inexistente', [
                'venta_id' => $ventaId,
                'cfg_id' => $cfgId,
                'contexto' => $contexto,
            ]);

            return;
        }

        $venta = Venta::query()->find($ventaId);
        if ($venta === null) {
            Log::warning('gastronomia.anita.defer.venta_inexistente', [
                'venta_id' => $ventaId,
                'contexto' => $contexto,
            ]);

            return;
        }

        try {
            $this->insumoStkmovAnitaService->replicarMovimientosInsumos($venta, $cfgDefer, $descuentoPie);
        } catch (Throwable $e) {
            Log::error('gastronomia.anita.defer.insumos_fallo', [
                'venta_id' => $ventaId,
                'contexto' => $contexto,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    private function limpiarAnitaParcialTrasFallo(int $ventaId): void
    {
        if ($ventaId <= 0) {
            return;
        }

        $venta = Venta::query()->find($ventaId);
        if ($venta === null) {
            return;
        }

        try {
            $this->facturacionGastronomiaService->revertirVentaEnAnitaSiHabilitado($venta);
            Log::info('gastronomia.anita.defer.cleanup_ok', [
                'venta_id' => $ventaId,
                'codigo' => $venta->codigo,
            ]);
        } catch (Throwable $e) {
            Log::warning('gastronomia.anita.defer.cleanup_fallo', [
                'venta_id' => $ventaId,
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
