<?php

namespace App\Jobs\Sueldos;

use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefiniblePivotSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PivotReporteSueldosDefinibleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    /**
     * @param list<array<string, mixed>> $filas
     * @param array<string, mixed> $spec
     */
    public function __construct(
        public readonly string $uuid,
        public readonly array $filas,
        public readonly array $spec,
    ) {
        $this->onQueue('reports');
    }

    public function handle(ReporteSueldosDefiniblePivotSupport $support): void
    {
        Cache::put('pivot:'.$this->uuid, [
            'estado' => 'ok',
            'data' => $support->pivotar($this->filas, $this->spec),
        ], now()->addMinutes(30));
    }

    public function failed(Throwable $e): void
    {
        Cache::put('pivot:'.$this->uuid, [
            'estado' => 'error',
            'mensaje' => mb_substr($e->getMessage(), 0, 1000),
        ], now()->addMinutes(30));
    }
}
