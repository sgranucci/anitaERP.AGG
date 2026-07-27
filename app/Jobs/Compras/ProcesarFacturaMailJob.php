<?php

namespace App\Jobs\Compras;

use App\Services\Compras\ComprobanteProveedorMailIngestaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Procesa un mensaje de la casilla de facturas: cada adjunto PDF pasa por el
 * pipeline PDF+IA (OCR + heurísticas + Ollama) y termina en la grilla de
 * precarga con el PDF en Facturas_scan.
 */
class ProcesarFacturaMailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    /** OCR + Ollama pueden tardar varios minutos por factura. */
    public int $timeout = 900;

    /**
     * @param  array<string, mixed>  $payload  Mensaje + adjuntos preparados en disco
     */
    public function __construct(
        public readonly array $payload,
    ) {}

    public function handle(ComprobanteProveedorMailIngestaService $ingesta): void
    {
        $ingesta->procesarMensajeEncolado($this->payload);
    }

    public function failed(Throwable $e): void
    {
        Log::channel('ai')->error('mail_ingesta.job_fallo', [
            'message_id' => $this->payload['message_id'] ?? null,
            'asunto' => $this->payload['asunto'] ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
