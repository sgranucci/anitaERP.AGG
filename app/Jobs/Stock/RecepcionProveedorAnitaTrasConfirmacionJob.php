<?php

declare(strict_types=1);

namespace App\Jobs\Stock;

use App\Services\Stock\RecepcionProveedorAnitaTrasConfirmacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tras confirmar COM en ERP: verifica recepmae + ctamov Anita y repara si hace falta.
 */
class RecepcionProveedorAnitaTrasConfirmacionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    /** @var list<int> */
    public array $backoff;

    public int $timeout;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $recepcionId,
    ) {
        $cfg = config('recepcion_proveedor.anita_tras_confirmacion', []);
        $this->tries = max(1, (int) ($cfg['job_tries'] ?? 3));
        $this->backoff = array_map('intval', (array) ($cfg['job_backoff_segundos'] ?? [30, 120, 600]));
        $this->timeout = max(60, (int) ($cfg['job_timeout'] ?? 300));
        $this->onQueue((string) ($cfg['cola'] ?? 'default'));
    }

    public function uniqueId(): string
    {
        return 'recepcion-proveedor-anita-tras-confirmacion-'.$this->recepcionId;
    }

    public function handle(RecepcionProveedorAnitaTrasConfirmacionService $service): void
    {
        if (! filter_var(
            config('recepcion_proveedor.anita_tras_confirmacion.habilitada', true),
            FILTER_VALIDATE_BOOLEAN
        )) {
            return;
        }

        Log::info('recepcion_proveedor.anita_tras_confirmacion.procesando', [
            'recepcion_id' => $this->recepcionId,
            'intento' => $this->attempts(),
        ]);

        $resultado = $service->verificarYReparar($this->recepcionId);

        Log::info('recepcion_proveedor.anita_tras_confirmacion.ok', [
            'recepcion_id' => $this->recepcionId,
            'estado' => $resultado['estado'] ?? '',
            'com' => $resultado['com'] ?? null,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('recepcion_proveedor.anita_tras_confirmacion.fallo', [
            'recepcion_id' => $this->recepcionId,
            'intento' => $this->attempts(),
            'mensaje' => $exception->getMessage(),
        ]);
    }
}
