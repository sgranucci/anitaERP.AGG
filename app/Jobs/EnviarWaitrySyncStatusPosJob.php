<?php

namespace App\Jobs;

use App\Models\Ventas\WaitrySyncStatusPos;
use App\Services\Ventas\Gastronomia\Waitry\WaitrySyncStatusPosEnvioService;
use App\Services\Ventas\Gastronomia\Waitry\WaitrySyncStatusPosService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * syncStatusPOS + KDS Waitry tras facturar una orden importada (cola).
 */
class EnviarWaitrySyncStatusPosJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /** @var list<int> */
    public array $backoff;

    public int $timeout;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $registroId,
    ) {
        $this->tries = (int) config('waitry.job_tries', 3);
        $this->backoff = array_map('intval', (array) config('waitry.job_backoff_segundos', [60, 300, 900]));
        $this->timeout = max(60, (int) config('waitry.sync_status_pos_job_timeout', 180));
        $this->onQueue((string) config('waitry.cola', 'default'));
        $this->afterCommit = true;
    }

    public function uniqueId(): string
    {
        return 'waitry-sync-status-pos-'.$this->registroId;
    }

    public function handle(WaitrySyncStatusPosService $syncService): void
    {
        if (! config('waitry.habilitado', false)) {
            return;
        }

        $registro = WaitrySyncStatusPos::query()->find($this->registroId);
        if (! $registro) {
            Log::warning('waitry.sync_status_pos.job.registro_inexistente', [
                'registro_id' => $this->registroId,
            ]);

            return;
        }

        if ($registro->estado === WaitrySyncStatusPos::ESTADO_ENVIADO
            || $registro->estado === WaitrySyncStatusPos::ESTADO_OMITIDO
        ) {
            return;
        }

        if (! $registro->puedeReintentar() && $registro->estado !== WaitrySyncStatusPos::ESTADO_ENVIANDO) {
            return;
        }

        $syncService->procesarRegistro($registro);
    }

    public function failed(?Throwable $exception): void
    {
        $registro = WaitrySyncStatusPos::query()->find($this->registroId);
        if (! $registro || $registro->estado === WaitrySyncStatusPos::ESTADO_ENVIADO) {
            return;
        }

        $msg = $exception !== null ? $exception->getMessage() : 'Job Waitry syncStatusPOS falló en cola';
        $envio = app(WaitrySyncStatusPosEnvioService::class);
        $envio->registrarFallo($registro, 'Cola: '.$msg);

        $registro->refresh();
        if ($registro->puedeReintentar()) {
            $envio->encolarReintento($registro);
        }
    }
}
