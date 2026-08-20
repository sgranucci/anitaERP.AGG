<?php

namespace App\Services\Ventas;

use App\Support\Ventas\Gastronomia\GastronomiaAnitaReplicaAuthSupport;
use App\Support\Ventas\PedidoFacturaAnitaArchivosSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Replica venta + vencae en Anita tras responder la factura de pedido (El Bierzo).
 * Antes de grabar inspecciona archivos: si está completo no escribe; si falta alguno, solo esos.
 */
final class PedidoFacturaAnitaDeferEjecucionService
{
    public function __construct(
        private readonly FacturacionService $facturacionService,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $anitaPendiente
     * @param  array<string, mixed>|null  $vencaePendiente
     */
    public function ejecutar(int $ventaId, ?array $anitaPendiente, ?array $vencaePendiente): void
    {
        if ($ventaId <= 0 && ! is_array($anitaPendiente) && ! is_array($vencaePendiente)) {
            return;
        }

        GastronomiaAnitaReplicaAuthSupport::autenticarSiNecesario($ventaId, $anitaPendiente, 'pedido_bierzo');

        $inspeccion = PedidoFacturaAnitaArchivosSupport::inspeccionar($anitaPendiente, $vencaePendiente);
        $this->regrabacion()->registrarInspeccion($ventaId, $inspeccion);

        if (! $inspeccion['ok']) {
            $this->regrabacion()->marcarError($ventaId, (string) $inspeccion['error']);

            return;
        }

        if ($inspeccion['completo']) {
            Log::info('pedido.anita.archivos.completo', [
                'venta_id' => $ventaId,
                'presentes' => $inspeccion['presentes'],
            ]);
            $this->regrabacion()->marcarOk($ventaId);

            return;
        }

        $faltantes = $inspeccion['faltantes'];
        $path = PedidoFacturaAnitaArchivosSupport::pathSistema($anitaPendiente);
        $this->facturacionService->prepararGrabacionPedidoAnitaDiferida($path, []);

        try {
            $faltantesVenta = array_values(array_diff($faltantes, ['vencae']));
            if ($faltantesVenta !== [] && is_array($anitaPendiente)) {
                try {
                    $this->facturacionService->ejecutarAnitaPendientePedidoBierzo(
                        $anitaPendiente,
                        $inspeccion['presentes'],
                    );
                } catch (Throwable $e) {
                    Log::error('pedido.anita.defer.venta_fallo', [
                        'venta_id' => $ventaId,
                        'faltantes' => $faltantesVenta,
                        'msg' => $e->getMessage(),
                    ]);
                    $this->regrabacion()->marcarError($ventaId, $e->getMessage());

                    return;
                }
            }

            if (in_array('vencae', $faltantes, true) && is_array($vencaePendiente)) {
                try {
                    $this->facturacionService->ejecutarVencaePendienteGastronomia($vencaePendiente);
                } catch (Throwable $e) {
                    Log::error('pedido.anita.defer.vencae_fallo', [
                        'venta_id' => $ventaId,
                        'msg' => $e->getMessage(),
                    ]);
                    $this->regrabacion()->marcarError($ventaId, 'vencae: '.$e->getMessage());

                    return;
                }
            }
        } finally {
            $this->facturacionService->resetearGrabacionPedidoAnitaDiferida();
        }

        $despues = PedidoFacturaAnitaArchivosSupport::inspeccionar($anitaPendiente, $vencaePendiente);
        $this->regrabacion()->registrarInspeccion($ventaId, $despues);

        if (! $despues['ok']) {
            $this->regrabacion()->marcarError($ventaId, (string) $despues['error']);

            return;
        }

        if ($despues['completo']) {
            $this->regrabacion()->marcarOk($ventaId);

            return;
        }

        $this->regrabacion()->marcarError(
            $ventaId,
            'Anita incompleta: faltan '.implode(', ', $despues['faltantes']),
        );
    }

    private function regrabacion(): PedidoFacturaAnitaRegrabacionService
    {
        return app(PedidoFacturaAnitaRegrabacionService::class);
    }
}
