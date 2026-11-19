<?php

namespace App\Services\Ventas\Mercadolibre;

use App\Models\Ventas\MercadolibrePedido;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sube el PDF de la factura al pack/orden en Mercado Libre (máx 1 MB).
 */
final class MercadolibreFiscalDocumentService
{
    private const MAX_BYTES = 1048576;

    public function __construct(
        private readonly MercadolibreApiClient $api,
        private readonly FacturacionService $facturacionService,
    ) {
    }

    /**
     * @return array{ok:bool,error?:string,path?:string}
     */
    public function publicar(MercadolibrePedido $pedido, Venta $venta): array
    {
        if (! config('mercadolibre.publicar_factura', true)) {
            return ['ok' => true];
        }

        try {
            $pdfPath = $this->generarPdf($venta);
            if ($pdfPath === null) {
                return ['ok' => false, 'error' => 'No se pudo generar el PDF de la factura.'];
            }
            $size = (int) filesize($pdfPath);
            if ($size <= 0) {
                return ['ok' => false, 'error' => 'PDF vacío.'];
            }
            if ($size > self::MAX_BYTES) {
                return ['ok' => false, 'error' => sprintf(
                    'PDF supera 1 MB (%.1f KB). MeLi rechaza el archivo.',
                    $size / 1024
                )];
            }

            $destino = $pedido->destinoFiscalId();
            $nombre = 'FAC-'.preg_replace('/\W+/', '-', (string) $venta->codigo).'.pdf';
            $resp = $this->api->subirDocumentoFiscal($destino, $pdfPath, $nombre);
            if (! ($resp['ok'] ?? false)) {
                return ['ok' => false, 'error' => $resp['error'] ?? 'Error al subir PDF a MeLi', 'path' => $pdfPath];
            }

            $pedido->fiscal_publicado_at = now();
            $pedido->save();

            return ['ok' => true, 'path' => $pdfPath];
        } catch (Throwable $e) {
            Log::warning('mercadolibre.fiscal_document', [
                'order_id' => $pedido->order_id,
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function generarPdf(Venta $venta): ?string
    {
        // Sin hoja de remito para achicar el archivo (límite MeLi 1 MB).
        try {
            $path = $this->facturacionService->generarPdfFacturaArchivo(
                (int) $venta->id,
                'ORIGINAL',
                true,
                false
            );
            if (is_string($path) && is_readable($path)) {
                return $path;
            }
        } catch (Throwable $e) {
            Log::warning('mercadolibre.fiscal_pdf_storage', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: temp (CLI / storage sin permiso de escritura).
        try {
            $ctx = $this->facturacionService->prepararContextoPdfFactura((int) $venta->id);
            $ctx['pathDir'] = rtrim(sys_get_temp_dir(), '/');
            $path = $this->facturacionService->renderPdfFacturaDesdeContexto($ctx, 'ORIGINAL', true, false);
            if (is_string($path) && is_readable($path)) {
                return $path;
            }
        } catch (Throwable $e) {
            Log::warning('mercadolibre.fiscal_pdf_temp', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
