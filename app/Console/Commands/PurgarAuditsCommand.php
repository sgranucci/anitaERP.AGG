<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purga de audits por fecha, en lotes, con retención por modelo.
 *
 * Por qué en lotes: un DELETE único de millones de filas es una transacción gigante
 * (bloqueos largos, binlog enorme, riesgo de timeout). Se va de a `lote` filas con una
 * pausa entre tandas.
 *
 * Por qué retención por modelo: un audit de pagoproveedor respalda un documento fiscal
 * y otro de una comanda de Waitry es ruido operativo.
 *
 * El DELETE de InnoDB no devuelve espacio al SO: frena el crecimiento, no baja el tamaño
 * ya ocupado. Para eso hace falta OPTIMIZE TABLE o particionar por created_at.
 */
class PurgarAuditsCommand extends Command
{
    protected $signature = 'audits:purge
                            {--dry-run : Solo informa cuánto borraría, sin borrar}
                            {--meses= : Pisa la retención por defecto}
                            {--max= : Pisa el tope de filas por corrida}';

    protected $description = 'Purga audits por fecha, en lotes, con retención distinta por modelo';

    public function handle(): int
    {
        if (! Schema::hasTable('audits')) {
            $this->warn('Tabla audits no existe.');

            return self::SUCCESS;
        }

        $simulacion = (bool) $this->option('dry-run');
        $lote = (int) config('audits_purga.lote', 5000);
        $pausaMs = (int) config('audits_purga.pausa_ms', 200);
        $max = (int) ($this->option('max') ?: config('audits_purga.max_por_corrida', 500000));
        $mesesDefecto = max(1, (int) ($this->option('meses') ?: config('audits_purga.retencion_meses', 24)));

        /** @var array<string,int> $porModelo */
        $porModelo = config('audits_purga.retencion_por_modelo', []);

        $borradasTotal = 0;
        $this->line($simulacion ? 'SIMULACIÓN — no se borra nada.' : 'Purga real de audits.');

        // 1) Cada modelo con retención propia, con su propio corte de fecha.
        foreach ($porModelo as $modelo => $meses) {
            if ($borradasTotal >= $max) {
                break;
            }
            $borradasTotal += $this->purgar(
                fn () => DB::table('audits')->where('auditable_type', $modelo),
                now()->subMonths(max(1, (int) $meses)),
                class_basename($modelo),
                $lote,
                $max - $borradasTotal,
                $pausaMs,
                $simulacion
            );
        }

        // 2) Todo lo no listado, con la retención por defecto.
        if ($borradasTotal < $max) {
            $listados = array_keys($porModelo);
            $borradasTotal += $this->purgar(
                fn () => DB::table('audits')->when(
                    $listados !== [],
                    fn ($q) => $q->whereNotIn('auditable_type', $listados)
                ),
                now()->subMonths($mesesDefecto),
                'resto (retención por defecto '.$mesesDefecto.' meses)',
                $lote,
                $max - $borradasTotal,
                $pausaMs,
                $simulacion
            );
        }

        $this->info(($simulacion ? 'Simulado: ' : 'Purgado: ').number_format($borradasTotal).' filas de audits.');
        if (! $simulacion && $borradasTotal >= $max) {
            $this->warn('Se alcanzó el tope por corrida; queda resto para la próxima.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  callable():\Illuminate\Database\Query\Builder  $base  Query nueva en cada lote.
     */
    private function purgar(
        callable $base,
        Carbon $limite,
        string $etiqueta,
        int $lote,
        int $restante,
        int $pausaMs,
        bool $simulacion
    ): int {
        if ($restante <= 0) {
            return 0;
        }

        if ($simulacion) {
            $cuantas = (int) $base()->where('created_at', '<', $limite)->count();
            if ($cuantas > 0) {
                $this->line(sprintf('  %-52s %s filas < %s', $etiqueta, number_format($cuantas), $limite->toDateString()));
            }

            return min($cuantas, $restante);
        }

        $borradas = 0;
        do {
            $tanda = min($lote, $restante - $borradas);
            if ($tanda <= 0) {
                break;
            }
            // DELETE ... LIMIT: usa idx_audits_created para ubicar las filas viejas.
            $n = $base()->where('created_at', '<', $limite)->limit($tanda)->delete();
            $borradas += $n;
            if ($n > 0 && $pausaMs > 0) {
                usleep($pausaMs * 1000);
            }
        } while ($n > 0 && $borradas < $restante);

        if ($borradas > 0) {
            $this->line(sprintf('  %-52s %s borradas', $etiqueta, number_format($borradas)));
        }

        return $borradas;
    }
}
