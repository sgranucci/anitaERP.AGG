<?php

declare(strict_types=1);

namespace App\Jobs\Configuracion;

use App\Services\Configuracion\PadronIibbSantaFeCargaService;
use App\Support\Configuracion\PadronIibbCargaNotificacionSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportarPadronIibbSantaFeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public readonly string $archivo,
        public readonly int $provinciaId,
        public readonly int $batchSize = PadronIibbSantaFeCargaService::DEFAULT_BATCH,
        public readonly int $pauseMs = PadronIibbSantaFeCargaService::DEFAULT_PAUSE_MS,
        public readonly bool $keepPeriod = false,
        public readonly bool $borrarArchivoAlTerminar = false,
    ) {
        $this->timeout = max(600, (int) config('padrones_iibb.job_timeout', 7200));
        $this->onQueue((string) config('padrones_iibb.cola', 'padrones'));
    }

    public function handle(PadronIibbSantaFeCargaService $service): void
    {
        Log::info('ImportarPadronIibbSantaFeJob:inicio', [
            'archivo' => $this->archivo,
            'provincia_id' => $this->provinciaId,
        ]);

        try {
            $stats = $service->cargar(
                $this->archivo,
                $this->provinciaId,
                $this->batchSize,
                $this->pauseMs,
                $this->keepPeriod
            );
            Log::info('ImportarPadronIibbSantaFeJob:ok', $stats);

            PadronIibbCargaNotificacionSupport::notificar(
                true,
                'IIBB Santa Fe (API)',
                sprintf(
                    'Importación Santa Fe OK. Período %s → %s.',
                    $stats['desdefecha'] ?? '?',
                    $stats['hastafecha'] ?? '?'
                ),
                $this->archivo,
                [
                    'leidas' => $stats['leidas'],
                    'insertadas_cuit' => $stats['insertadas_cuit'],
                    'insertadas_tasa' => $stats['insertadas_tasa'],
                    'borrados' => $stats['borrados'],
                    'omitidas' => $stats['omitidas'],
                    'errores' => $stats['errores'],
                    'lotes' => $stats['lotes'],
                ]
            );
        } finally {
            $this->limpiarArchivoSiCorresponde();
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('ImportarPadronIibbSantaFeJob:failed', [
            'archivo' => $this->archivo,
            'error' => $e?->getMessage(),
        ]);
        PadronIibbCargaNotificacionSupport::notificar(
            false,
            'IIBB Santa Fe (API)',
            'Job Santa Fe fallido (failed).',
            $this->archivo,
            [],
            $e?->getMessage()
        );
        $this->limpiarArchivoSiCorresponde();
    }

    private function limpiarArchivoSiCorresponde(): void
    {
        if (! $this->borrarArchivoAlTerminar) {
            return;
        }
        $storageRoot = realpath(storage_path('app')) ?: storage_path('app');
        $real = realpath($this->archivo);
        if ($real && str_starts_with($real, rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            @unlink($real);
        }
    }
}
