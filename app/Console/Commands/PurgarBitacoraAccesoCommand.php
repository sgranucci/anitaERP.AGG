<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgarBitacoraAccesoCommand extends Command
{
    protected $signature = 'bitacora-acceso:purge {--meses= : Meses de retención (default config)}';

    protected $description = 'Elimina filas de bitacora_acceso más viejas que la retención configurada';

    public function handle(): int
    {
        if (! Schema::hasTable('bitacora_acceso')) {
            $this->warn('Tabla bitacora_acceso no existe.');

            return self::SUCCESS;
        }

        $meses = (int) ($this->option('meses') ?: config('bitacora_acceso.retencion_meses', 12));
        $meses = max(1, $meses);
        $limite = now()->subMonths($meses);

        $borradas = DB::table('bitacora_acceso')->where('created_at', '<', $limite)->delete();
        $this->info("Purga bitácora acceso: {$borradas} filas anteriores a {$limite->toDateTimeString()} (retención {$meses} meses).");

        return self::SUCCESS;
    }
}
