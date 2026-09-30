<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Ventas\CambioDevolucionMarketplace;
use App\Models\Ventas\Venta;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplacePuenteSupport;
use App\Support\Ventas\FacturacionLocal\MotivoDevolucionSupport;
use Illuminate\Support\Facades\Log;

/**
 * Emite NC completa de la factura original con medio puente NCD (solo FL Ferli).
 * Extiende FacturacionLocalNotaCreditoService; no toca gastronomía AGG.
 */
final class CambioDevolucionMarketplaceNcService
{
    public function __construct(
        private readonly FacturacionLocalNotaCreditoService $notaCreditoService,
    ) {
    }

    /**
     * @return array{ok:bool,error?:string,venta_id?:int,factura?:string,pdf_urls?:list<string>,mensaje?:string}
     */
    public function emitirNcOriginal(CambioDevolucionMarketplace $cambio): array
    {
        $cambio->loadMissing(['localVenta', 'ventaOriginal']);
        $ventaOriginalId = (int) $cambio->venta_original_id;
        if ($ventaOriginalId <= 0) {
            return ['ok' => false, 'error' => 'Sin factura original.'];
        }

        /** @var Venta|null $venta */
        $venta = $cambio->ventaOriginal;
        if (! $venta) {
            return ['ok' => false, 'error' => 'Factura original inexistente.'];
        }

        $motivo = MotivoDevolucionSupport::resolverDeCambio($cambio);
        if (! $motivo) {
            return ['ok' => false, 'error' => 'Elegí un motivo de devolución activo antes de emitir la nota de crédito.'];
        }

        $total = abs((float) $venta->total);
        try {
            $medios = CambioDevolucionMarketplacePuenteSupport::medioPagoUnico($total);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $resultado = $this->notaCreditoService->generarDesdeFactura(
            $ventaOriginalId,
            null,
            'Cambio/devolución marketplace legajo Nº '.$cambio->numero,
            [
                'medios_forzados' => $medios,
                'local_venta_id' => (int) $cambio->local_venta_id,
                'motivo_devolucion_id' => (int) $motivo->id,
                'omitir_reingreso_stock' => ! $motivo->vuelve_stock,
                'registrar_historial' => false,
            ]
        );

        if (empty($resultado['ok'])) {
            Log::warning('cambio_devolucion_marketplace.nc_original.fallo', [
                'cambio_id' => $cambio->id,
                'venta_original_id' => $ventaOriginalId,
                'error' => $resultado['error'] ?? null,
            ]);

            return [
                'ok' => false,
                'error' => (string) ($resultado['error'] ?? 'No se pudo emitir la nota de crédito.'),
            ];
        }

        return [
            'ok' => true,
            'venta_id' => (int) ($resultado['venta_id'] ?? 0),
            'factura' => (string) ($resultado['factura'] ?? ''),
            'pdf_urls' => $resultado['pdf_urls'] ?? [],
            'mensaje' => $resultado['mensaje'] ?? 'Nota de crédito emitida con medio puente NCD.',
        ];
    }
}
