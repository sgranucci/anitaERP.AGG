<?php

namespace App\Console\Commands;

use App\Models\Configuracion\ReglaNegocioLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ReglaNegocioPurgarLogsCommand extends Command
{
    protected $signature = 'regla-negocio:purgar-logs
                            {--dias= : Días a conservar (default config regla_negocio.retencion_logs_dias)}
                            {--dry-run : Solo contar}';

    protected $description = 'Purga regla_negocio_log más antiguos que la retención configurada';

    public function handle(): int
    {
        if (! Schema::hasTable('regla_negocio_log')) {
            $this->warn('Tabla regla_negocio_log inexistente.');

            return self::SUCCESS;
        }

        $dias = (int) ($this->option('dias') ?: config('regla_negocio.retencion_logs_dias', 90));
        $dias = max(1, $dias);
        $corte = now()->subDays($dias);

        $q = ReglaNegocioLog::query()->where('ejecutado_en', '<', $corte);
        $n = (int) $q->count();
        if ($this->option('dry-run')) {
            $this->info("Dry-run: se purgarían {$n} logs anteriores a {$corte->toDateTimeString()} (retención {$dias}d).");

            return self::SUCCESS;
        }

        $borrados = 0;
        $q->orderBy('id')->chunkById(500, function ($rows) use (&$borrados) {
            $ids = $rows->pluck('id')->all();
            $borrados += ReglaNegocioLog::query()->whereIn('id', $ids)->delete();
        });

        $this->info("Purgados {$borrados} logs (retención {$dias}d, corte {$corte->toDateTimeString()}).");

        return self::SUCCESS;
    }
}
