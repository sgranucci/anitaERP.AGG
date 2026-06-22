<?php

namespace App\Services\Compras;

use App\Support\Compras\PrecargaProveedor\FacturaPdfIa\FacturaProveedorExtraccionFusionSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cliente de extracción PDF: pipeline interno (default), HTTP externo o híbrido.
 */
final class ComprobanteProveedorPdfIaClient
{
    public function __construct(
        private ComprobanteProveedorPdfIaPipelineService $pipeline,
        private FacturaProveedorExtraccionFusionSupport $fusion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extraer(UploadedFile $pdf): array
    {
        $driver = strtolower((string) config('comprobante_proveedor_pdf_ia.driver', 'interno'));

        return match ($driver) {
            'http' => $this->extraerHttp($pdf),
            'hybrid' => $this->extraerHybrid($pdf),
            'interno', 'pipeline' => $this->pipeline->extraer($pdf),
            default => throw new RuntimeException('Driver PDF IA no válido: '.$driver),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function extraerHybrid(UploadedFile $pdf): array
    {
        try {
            return $this->pipeline->extraer($pdf);
        } catch (\Throwable $e) {
            Log::channel($this->logChannel())->warning('pdf_ia.hybrid_pipeline_fallo', [
                'message' => $e->getMessage(),
            ]);

            $url = trim((string) config('comprobante_proveedor_pdf_ia.api_url', ''));
            if ($url === '') {
                throw $e;
            }

            return $this->extraerHttp($pdf);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extraerHttp(UploadedFile $pdf): array
    {
        $url = trim((string) config('comprobante_proveedor_pdf_ia.api_url', ''));
        if ($url === '') {
            throw new RuntimeException('COMPROBANTE_PROVEEDOR_PDF_IA_API_URL no configurada.');
        }

        $timeout = (int) config('comprobante_proveedor_pdf_ia.api_timeout', 120);

        $response = Http::timeout($timeout)
            ->attach(
                'pdf',
                file_get_contents($pdf->getRealPath()),
                $pdf->getClientOriginalName()
            )
            ->post($url);

        if (! $response->successful()) {
            throw new RuntimeException(
                'API externa respondió '.$response->status().': '.$response->body()
            );
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('API externa devolvió respuesta no JSON.');
        }

        return $this->fusion->fusionar($data, null);
    }

    private function logChannel(): string
    {
        return (string) config('comprobante_proveedor_pdf_ia.log_channel', 'precarga_proveedor_api');
    }
}
