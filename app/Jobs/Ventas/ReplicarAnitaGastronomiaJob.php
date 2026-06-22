<?php

namespace App\Jobs\Ventas;

use App\Services\Ventas\Gastronomia\GastronomiaAnitaDeferEjecucionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Replica venta/vencae Anita en cola (no ocupa workers Apache post-respuesta).
 */
class ReplicarAnitaGastronomiaJob implements ShouldBeUnique, ShouldQueue
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

    /**
     * @param  array<string, mixed>|null  $anitaPendiente
     * @param  array<string, mixed>|null  $vencaePendiente
     */
    public function __construct(
        public readonly int $ventaId,
        public readonly ?array $anitaPendiente,
        public readonly ?array $vencaePendiente,
        public readonly int $cfgId,
        public readonly float $descuentoPie,
        public readonly bool $replicarInsumos,
        public readonly string $contexto = 'factura',
    ) {
        $this->tries = max(1, (int) config('gastronomia.anita_job_tries', 3));
        $this->backoff = array_map('intval', (array) config('gastronomia.anita_job_backoff_segundos', [60, 300, 900]));
        $this->timeout = max(60, (int) config('gastronomia.anita_job_timeout', 300));
        $this->onQueue((string) config('gastronomia.anita_cola', 'default'));
    }

    public function uniqueId(): string
    {
        return 'anita-gastronomia-venta-'.$this->ventaId.'-'.$this->contexto;
    }

    public function handle(GastronomiaAnitaDeferEjecucionService $ejecucion): void
    {
        if (! config('gastronomia.sincronizar_anita_al_facturar', true)) {
            return;
        }

        Log::info('gastronomia.anita.cola.procesando', [
            'venta_id' => $this->ventaId,
            'contexto' => $this->contexto,
            'intento' => $this->attempts(),
        ]);

        $ejecucion->ejecutar(
            $this->ventaId,
            $this->anitaPendiente,
            $this->vencaePendiente,
            $this->cfgId,
            $this->descuentoPie,
            $this->replicarInsumos,
            $this->contexto,
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('gastronomia.anita.cola.job_fallo', [
            'venta_id' => $this->ventaId,
            'contexto' => $this->contexto,
            'msg' => $exception !== null ? $exception->getMessage() : 'Job Anita falló en cola',
        ]);
    }
}
