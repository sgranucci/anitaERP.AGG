<?php

declare(strict_types=1);

namespace App\Jobs\Configuracion;

use App\Services\Configuracion\PadronIibbCabaCargaService;
use App\Support\Configuracion\PadronIibbCargaNotificacionSupport;
use App\Support\Configuracion\PadronIibbCargaRegistroSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportarPadronIibbCabaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public readonly string $archivo,
        public readonly int $batchSize = PadronIibbCabaCargaService::DEFAULT_BATCH,
        public readonly int $pauseMs = PadronIibbCabaCargaService::DEFAULT_PAUSE_MS,
        public readonly bool $keepPeriod = false,
        public readonly bool $borrarArchivoAlTerminar = false,
        public readonly ?int $cargaId = null,
    ) {
        $this->timeout = max(600, (int) config('padrones_iibb.job_timeout', 7200));
        $this->onQueue((string) config('padrones_iibb.cola', 'padrones'));
    }

    public function handle(PadronIibbCabaCargaService $service): void
    {
        Log::info('ImportarPadronIibbCabaJob:inicio', ['archivo' => $this->archivo]);

        try {
            $stats = $service->cargar(
                $this->archivo,
                $this->batchSize,
                $this->pauseMs,
                $this->keepPeriod
            );
            Log::info('ImportarPadronIibbCabaJob:ok', $stats);
            PadronIibbCargaRegistroSupport::finalizar($this->cargaId, $stats);

            PadronIibbCargaNotificacionSupport::notificar(
                true,
                'IIBB CABA (AGIP)',
                sprintf(
                    'Importación CABA OK. Período %s → %s.',
                    $stats['desdefecha'] ?? '?',
                    $stats['hastafecha'] ?? '?'
                ),
                $this->archivo,
                [
                    'leidas' => $stats['leidas'],
                    'insertadas' => $stats['insertadas'],
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
        Log::error('ImportarPadronIibbCabaJob:failed', [
            'archivo' => $this->archivo,
            'error' => $e?->getMessage(),
        ]);
        PadronIibbCargaRegistroSupport::fallar($this->cargaId, $e?->getMessage() ?? 'Error desconocido');
        PadronIibbCargaNotificacionSupport::notificar(
            false,
            'IIBB CABA (AGIP)',
            'Job CABA fallido (failed).',
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
