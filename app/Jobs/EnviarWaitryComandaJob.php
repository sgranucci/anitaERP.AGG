<?php

namespace App\Jobs;

use App\Models\Ventas\WaitryComandaEnvio;
use App\Services\Ventas\Gastronomia\Waitry\WaitryComandaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reintento asíncrono de comanda Waitry (cola).
 */
class EnviarWaitryComandaJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /** @var list<int> */
    public array $backoff;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $envioId,
    ) {
        $this->tries = (int) config('waitry.job_tries', 3);
        $this->backoff = array_map('intval', (array) config('waitry.job_backoff_segundos', [60, 300, 900]));
        $this->onQueue((string) config('waitry.cola', 'default'));
    }

    public function uniqueId(): string
    {
        return 'waitry-comanda-envio-'.$this->envioId;
    }

    public function handle(WaitryComandaService $comandaService): void
    {
        if (! config('waitry.habilitado', false)) {
            return;
        }

        $envio = WaitryComandaEnvio::query()->find($this->envioId);
        if (! $envio) {
            Log::warning('waitry.job.envio_inexistente', ['envio_id' => $this->envioId]);

            return;
        }

        if ($envio->estado === WaitryComandaEnvio::ESTADO_ENVIADO) {
            return;
        }

        if (! $envio->puedeReintentar()) {
            return;
        }

        $comandaService->procesarEnvioRegistro($envio);
    }

    public function failed(?Throwable $exception): void
    {
        $envio = WaitryComandaEnvio::query()->find($this->envioId);
        if (! $envio || $envio->estado === WaitryComandaEnvio::ESTADO_ENVIADO) {
            return;
        }

        $msg = $exception !== null ? $exception->getMessage() : 'Job Waitry falló en cola';
        app(\App\Services\Ventas\Gastronomia\Waitry\WaitryComandaEnvioService::class)
            ->registrarFallo($envio, 'Cola: '.$msg);

        $envio->refresh();
        if ($envio->puedeReintentar()) {
            app(\App\Services\Ventas\Gastronomia\Waitry\WaitryComandaEnvioService::class)
                ->encolarReintento($envio);
        }
    }
}
