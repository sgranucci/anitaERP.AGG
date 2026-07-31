<?php

declare(strict_types=1);

namespace App\Jobs\Configuracion;

use App\Services\Configuracion\PadronIibbArbaCargaService;
use App\Support\Configuracion\PadronIibbCargaNotificacionSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportarPadronIibbArbaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public readonly string $archivo,
        public readonly int $batchSize = PadronIibbArbaCargaService::DEFAULT_BATCH,
        public readonly int $pauseMs = PadronIibbArbaCargaService::DEFAULT_PAUSE_MS,
        public readonly bool $borrarArchivoAlTerminar = false,
    ) {
        $this->timeout = max(600, (int) config('padrones_iibb.job_timeout', 7200));
        $this->onQueue((string) config('padrones_iibb.cola', 'padrones'));
    }

    public function handle(PadronIibbArbaCargaService $service): void
    {
        Log::info('ImportarPadronIibbArbaJob:inicio', ['archivo' => $this->archivo]);

        try {
            $resultado = $service->cargar(
                $this->archivo,
                $this->batchSize,
                $this->pauseMs
            );
            Log::info('ImportarPadronIibbArbaJob:ok', $resultado);

            $resumen = [];
            foreach ($resultado['archivos'] as $i => $stats) {
                $resumen['archivo_' . ($i + 1)] = sprintf(
                    '%s %s — leídas=%d insertadas=%d actualizadas=%d errores=%d',
                    $stats['tipo'] ?? '?',
                    basename((string) ($stats['archivo'] ?? '')),
                    $stats['leidas'] ?? 0,
                    $stats['insertadas'] ?? 0,
                    $stats['actualizadas'] ?? 0,
                    $stats['errores'] ?? 0
                );
            }

            PadronIibbCargaNotificacionSupport::notificar(
                true,
                'IIBB ARBA',
                'Importación ARBA finalizada correctamente.',
                $this->archivo,
                $resumen
            );
        } finally {
            $this->limpiarArchivoSiCorresponde();
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('ImportarPadronIibbArbaJob:failed', [
            'archivo' => $this->archivo,
            'error' => $e?->getMessage(),
        ]);
        // Si falló antes del catch del handle (p.ej. timeout), avisar igual.
        PadronIibbCargaNotificacionSupport::notificar(
            false,
            'IIBB ARBA',
            'Job ARBA fallido (failed).',
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
