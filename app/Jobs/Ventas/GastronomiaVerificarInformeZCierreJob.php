<?php

declare(strict_types=1);

namespace App\Jobs\Ventas;

use App\Services\Ventas\Gastronomia\GastronomiaInformeZTransmisionFaltanteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tras el cierre de jornada: relee Waitry y documenta comandas faltantes en el Informe Z.
 * El Z histórico no se pisa; avisa a Tesorería por mail si hay diferencia.
 */
class GastronomiaVerificarInformeZCierreJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 180, 600];

    public int $timeout = 300;

    public int $uniqueFor = 7200;

    public function __construct(
        public readonly int $jornadaId,
    ) {
        $this->onQueue((string) config(
            'gastronomia.informe_z_transmision_faltante.cola',
            'default',
        ));
    }

    public function uniqueId(): string
    {
        return 'gastronomia-verificar-informe-z-jornada-'.$this->jornadaId;
    }

    public function handle(GastronomiaInformeZTransmisionFaltanteService $service): void
    {
        if (! filter_var(config('gastronomia.informe_z_transmision_faltante.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $resultado = $service->verificarYPersistir($this->jornadaId, enviarMail: true);

        if (! ($resultado['ok'] ?? false)) {
            Log::warning('gastronomia.informe_z_transmision_faltante.job_omitido', [
                'jornada_id' => $this->jornadaId,
                'error' => $resultado['error'] ?? 'desconocido',
            ]);
        }
    }

    public function failed(?Throwable $e): void
    {
        Log::error('gastronomia.informe_z_transmision_faltante.job_failed', [
            'jornada_id' => $this->jornadaId,
            'error' => $e?->getMessage(),
        ]);
    }
}
