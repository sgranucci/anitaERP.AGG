<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purga de bitacora_acceso por fecha, en lotes.
 *
 * Un DELETE único de millones de filas llena /tmp (partición chica en este
 * servidor) y el binlog. Se borra de a `lote` con DELETE … LIMIT.
 * El DELETE de InnoDB no achica el .ibd: para devolver espacio al SO hace
 * falta OPTIMIZE TABLE en ventana aparte.
 */
class PurgarBitacoraAccesoCommand extends Command
{
    protected $signature = 'bitacora-acceso:purge
                            {--dry-run : Solo informa cuántas filas borraría}
                            {--meses= : Meses de retención (default config)}
                            {--lote= : Filas por tanda (default config)}';

    protected $description = 'Elimina filas de bitacora_acceso más viejas que la retención, en lotes';

    public function handle(): int
    {
        if (! Schema::hasTable('bitacora_acceso')) {
            $this->warn('Tabla bitacora_acceso no existe.');

            return self::SUCCESS;
        }

        $meses = max(1, (int) ($this->option('meses') ?: config('bitacora_acceso.retencion_meses', 12)));
        $lote = max(100, (int) ($this->option('lote') ?: config('bitacora_acceso.purga.lote', 50000)));
        $pausaMs = max(0, (int) config('bitacora_acceso.purga.pausa_ms', 50));
        $limite = now()->subMonths($meses);
        $simulacion = (bool) $this->option('dry-run');

        $base = fn () => DB::table('bitacora_acceso')->where('created_at', '<', $limite);

        if ($simulacion) {
            $cuantas = (int) $base()->count();
            $this->info(sprintf(
                'Simulación: %s filas anteriores a %s (retención %d meses). No se borra nada.',
                number_format($cuantas),
                $limite->toDateTimeString(),
                $meses
            ));

            return self::SUCCESS;
        }

        $borradas = 0;
        $tandas = 0;

        do {
            $n = $base()->limit($lote)->delete();
            $borradas += $n;
            $tandas++;

            if ($n > 0 && $pausaMs > 0) {
                usleep($pausaMs * 1000);
            }

            if ($tandas % 5 === 0 || ($n > 0 && $n < $lote)) {
                $this->line(sprintf(
                    '  tanda %d: +%s (acum %s)',
                    $tandas,
                    number_format($n),
                    number_format($borradas)
                ));
            }
        } while ($n > 0);

        $this->info(sprintf(
            'Purga bitácora acceso: %s filas anteriores a %s (retención %d meses, lote %s).',
            number_format($borradas),
            $limite->toDateTimeString(),
            $meses,
            number_format($lote)
        ));

        return self::SUCCESS;
    }
}
