<?php

namespace App\Jobs\Compras;

use App\Services\Compras\ComprobanteProveedorBatchIaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Procesa un PDF ya reclamado de la carpeta caliente BATCH_IA. */
class ProcesarFacturaBatchIaJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;
    public int $timeout = 900;
    public int $uniqueFor = 1800;

    public function __construct(
        public readonly int $registroId,
    ) {}

    public function uniqueId(): string
    {
        return 'factura-batch-ia-'.$this->registroId;
    }

    public function handle(ComprobanteProveedorBatchIaService $service): void
    {
        $service->procesar($this->registroId);
    }
}
