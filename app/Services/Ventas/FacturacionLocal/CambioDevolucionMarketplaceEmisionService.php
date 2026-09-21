<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Ventas\CambioDevolucionMarketplace;
use App\Models\Ventas\LocalVenta;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplaceCatalogoSupport;
use App\Support\Ventas\FacturacionLocal\CambioDevolucionMarketplacePuenteSupport;
use Illuminate\Support\Facades\Log;

/**
 * Emite FAC de reemplazo del legajo con medio puente NCD (solo FL Ferli).
 */
final class CambioDevolucionMarketplaceEmisionService
{
    public function __construct(
        private readonly FacturacionLocalEmisionService $emisionService,
    ) {
    }

    /**
     * @return array{ok:bool,error?:string,venta_id?:int,factura?:string,pdf_urls?:list<string>,mensaje?:string}
     */
    public function emitirFacReemplazo(CambioDevolucionMarketplace $cambio): array
    {
        $cambio->loadMissing(['lineas', 'localVenta', 'ventaOriginal']);
        /** @var LocalVenta|null $local */
        $local = $cambio->localVenta;
        if (! $local) {
            return ['ok' => false, 'error' => 'Local de venta inexistente.'];
        }

        $lineasReemplazo = $cambio->lineas
            ->where('tipo', CambioDevolucionMarketplaceCatalogoSupport::TIPO_REEMPLAZO)
            ->values();
        if ($lineasReemplazo->isEmpty()) {
            return ['ok' => false, 'error' => 'No hay líneas de reemplazo para facturar.'];
        }

        $lineas = [];
        $total = 0.0;
        foreach ($lineasReemplazo as $linea) {
            $cant = (float) $linea->cantidad;
            $precio = (float) $linea->precio_unitario;
            $total += $cant * $precio;
            $lineas[] = [
                'articulo_id' => (int) $linea->articulo_id,
                'cantidad' => $cant,
                'precio' => $precio,
                'descuento' => 0,
                'descripcion' => (string) ($linea->descripcion ?? ''),
                'combinacion_id' => (int) ($linea->combinacion_id ?? 0),
                'talle_id' => (int) ($linea->talle_id ?? 0),
                'color_id' => (int) ($linea->color_id ?? 0),
            ];
        }

        try {
            $medios = CambioDevolucionMarketplacePuenteSupport::medioPagoUnico($total);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $input = [
            'lineas' => $lineas,
            'medios_pago' => $medios,
            'cliente_id' => $cambio->cliente_id,
            'receptor' => [
                'nombre' => $cambio->receptor_nombre,
                'nro_documento' => $cambio->receptor_documento,
            ],
        ];

        $resultado = $this->emisionService->emitir($local, $input);
        if (empty($resultado['ok'])) {
            Log::warning('cambio_devolucion_marketplace.fac_reemplazo.fallo', [
                'cambio_id' => $cambio->id,
                'error' => $resultado['error'] ?? null,
            ]);

            return [
                'ok' => false,
                'error' => (string) ($resultado['error'] ?? 'No se pudo emitir la factura de reemplazo.'),
            ];
        }

        $ventaId = (int) ($resultado['venta_id'] ?? 0);
        $factura = '';
        if ($ventaId > 0) {
            $venta = \App\Models\Ventas\Venta::query()->find($ventaId);
            $factura = trim((string) ($venta->codigo ?? ''));
        }

        return [
            'ok' => true,
            'venta_id' => $ventaId,
            'factura' => $factura,
            'pdf_urls' => $ventaId > 0 ? [url('ventas/listaunafactura/'.$ventaId)] : [],
            'mensaje' => 'Factura de reemplazo emitida con medio puente NCD.',
        ];
    }
}
