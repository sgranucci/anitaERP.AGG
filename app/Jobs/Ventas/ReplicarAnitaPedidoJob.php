<?php

namespace App\Jobs\Ventas;

use App\Models\Ventas\VentaAnitaReplica;
use App\Services\Ventas\PedidoFacturaAnitaDeferEjecucionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Replica venta/vencae/ctamov Anita en cola (pedido, mostrador, remito).
 * No ocupa el worker Apache: el HTTP responde antes de hablar con Informix.
 */
class ReplicarAnitaPedidoJob implements ShouldBeUnique, ShouldQueue
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
        public readonly string $contexto = 'factura',
    ) {
        $this->tries = max(1, (int) config('facturacion.ANITA_PEDIDO_JOB_TRIES', 3));
        $this->backoff = array_map('intval', (array) config('facturacion.ANITA_PEDIDO_JOB_BACKOFF_SEGUNDOS', [60, 300, 900]));
        $this->timeout = max(60, (int) config('facturacion.ANITA_PEDIDO_JOB_TIMEOUT', 300));
        $this->afterCommit = true;
        $this->onQueue((string) config('facturacion.ANITA_PEDIDO_COLA', 'default'));
    }

    public function uniqueId(): string
    {
        return 'anita-pedido-venta-'.$this->ventaId;
    }

    public function handle(PedidoFacturaAnitaDeferEjecucionService $ejecucion): void
    {
        $fila = VentaAnitaReplica::query()->where('venta_id', $this->ventaId)->first();
        $anita = is_array($fila?->payload_anita) ? $fila->payload_anita : $this->anitaPendiente;
        $vencae = is_array($fila?->payload_vencae) ? $fila->payload_vencae : $this->vencaePendiente;

        Log::info('pedido.anita.cola.procesando', [
            'venta_id' => $this->ventaId,
            'contexto' => $this->contexto,
            'intento' => $this->attempts(),
        ]);

        $ejecucion->ejecutar($this->ventaId, $anita, $vencae);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('pedido.anita.cola.job_fallo', [
            'venta_id' => $this->ventaId,
            'contexto' => $this->contexto,
            'msg' => $exception !== null ? $exception->getMessage() : 'Job Anita falló en cola',
        ]);
    }
}
