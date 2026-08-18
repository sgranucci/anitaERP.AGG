<?php

namespace App\Jobs\Sueldos;

use App\Models\Sueldos\ReporteSueldosDefinibleEjecucion;
use App\Models\Sueldos\ReporteSueldosDefinibleWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispararReporteSueldosDefinibleWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly int $ejecucionId,
        public readonly string $evento
    ) {
        $this->onQueue((string) config('sueldos.reporte_definible.cola', 'reports'));
    }

    public function handle(): void
    {
        $ejecucion = ReporteSueldosDefinibleEjecucion::query()->find($this->ejecucionId);
        if (! $ejecucion) {
            return;
        }

        $webhooks = ReporteSueldosDefinibleWebhook::query()
            ->where('reporte_sueldos_definible_id', (int) $ejecucion->reporte_sueldos_definible_id)
            ->where('activo', true)
            ->get();

        $body = [
            'evento' => $this->evento,
            'reporte_id' => (int) $ejecucion->reporte_sueldos_definible_id,
            'ejecucion_id' => (int) $ejecucion->id,
            'estado' => (string) $ejecucion->estado,
            'dataset_id' => $ejecucion->dataset_id ? (int) $ejecucion->dataset_id : null,
        ];
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return;
        }

        foreach ($webhooks as $webhook) {
            if (! $webhook->escucha($this->evento)) {
                continue;
            }
            $firma = hash_hmac('sha256', $payload, (string) $webhook->secret);
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-Anita-Signature' => $firma,
                        'X-Anita-Event' => $this->evento,
                    ])
                    ->withBody($payload, 'application/json')
                    ->post((string) $webhook->url);

                if (! $response->successful()) {
                    Log::warning('rsd.webhook.http_error', [
                        'webhook_id' => $webhook->id,
                        'status' => $response->status(),
                        'ejecucion_id' => $ejecucion->id,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('rsd.webhook.exception', [
                    'webhook_id' => $webhook->id,
                    'ejecucion_id' => $ejecucion->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }
    }
}
