<?php

declare(strict_types=1);

namespace App\Jobs\Configuracion;

use App\Services\Configuracion\PadronSircipCargaService;
use App\Support\Configuracion\PadronIibbBandejaSupport;
use App\Support\Configuracion\PadronIibbCargaNotificacionSupport;
use App\Support\Configuracion\PadronIibbCargaRegistroSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Importa el padrón SIRCIP mensual (ZIP o TXT) en la cola de padrones.
 * El servicio reemplaza el período completo en una transacción.
 */
class ImportarPadronSircipJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const ETIQUETA = 'SIRCIP (padrón único)';

    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public readonly string $archivo,
        public readonly ?int $cargaId = null,
    ) {
        $this->timeout = max(600, (int) config('padrones_iibb.job_timeout', 7200));
        $this->onQueue((string) config('padrones_iibb.cola', 'padrones'));
    }

    public function handle(PadronSircipCargaService $service): void
    {
        Log::info('ImportarPadronSircipJob:inicio', ['archivo' => $this->archivo]);

        $ok = false;
        try {
            $cargaId = $this->cargaId;
            $stats = $service->cargar(
                $this->archivo,
                static function (array $parcial) use ($cargaId): void {
                    PadronIibbCargaRegistroSupport::progreso($cargaId, $parcial);
                },
            );
            Log::info('ImportarPadronSircipJob:ok', $stats);
            PadronIibbCargaRegistroSupport::finalizar($this->cargaId, $stats);
            $ok = true;

            PadronIibbCargaNotificacionSupport::notificar(
                true,
                self::ETIQUETA,
                sprintf('Importación SIRCIP OK. Período %s.', $stats['periodo'] ?? '?'),
                $this->archivo,
                [
                    'leidas' => $stats['leidas'],
                    'insertadas' => $stats['insertadas'],
                    'borrados' => $stats['borrados'],
                    'omitidas' => $stats['omitidas'],
                    'errores' => $stats['errores'],
                ]
            );
        } finally {
            PadronIibbBandejaSupport::archivar($this->archivo, $ok);
            PadronIibbBandejaSupport::limpiarExtraidosJuntoA($this->archivo);
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('ImportarPadronSircipJob:failed', [
            'archivo' => $this->archivo,
            'error' => $e?->getMessage(),
        ]);
        PadronIibbCargaRegistroSupport::fallar($this->cargaId, $e?->getMessage() ?? 'Error desconocido');
        PadronIibbCargaNotificacionSupport::notificar(
            false,
            self::ETIQUETA,
            'Job SIRCIP fallido (failed).',
            $this->archivo,
            [],
            $e?->getMessage()
        );
        PadronIibbBandejaSupport::archivar($this->archivo, false);
        PadronIibbBandejaSupport::limpiarExtraidosJuntoA($this->archivo);
    }
}
