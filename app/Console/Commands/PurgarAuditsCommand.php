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
                            {--reglas : Lista cada modelo con la retención que le queda y por qué}
                            {--meses= : Pisa la retención por defecto}
                            {--max= : Pisa el tope de filas por corrida}';

    protected $description = 'Purga audits por fecha, en lotes, con retención distinta por modelo';

    public function handle(): int
    {
        if (! Schema::hasTable('audits')) {
            $this->warn('Tabla audits no existe.');

            return self::SUCCESS;
        }

        if ($this->option('reglas')) {
            return $this->mostrarReglas();
        }

        $simulacion = (bool) $this->option('dry-run');
        $lote = (int) config('audits_purga.lote', 5000);
        $pausaMs = (int) config('audits_purga.pausa_ms', 200);
        $tope = (int) ($this->option('max') ?: config('audits_purga.max_por_corrida', 500000));
        // El tope acota cada corrida real; en simulación estorba, porque lo que se quiere
        // ver es el total pendiente y cuántas corridas va a llevar.
        $max = $simulacion ? PHP_INT_MAX : $tope;

        $grupos = $this->agruparPorRetencion();

        $this->line($simulacion ? 'SIMULACIÓN — no se borra nada.' : 'Purga real de audits.');
        $borradasTotal = 0;

        // De la retención más corta a la más larga: primero lo que más sobra.
        ksort($grupos);

        foreach ($grupos as $meses => $tipos) {
            if ($borradasTotal >= $max) {
                break;
            }
            $borradasTotal += $this->purgar(
                $tipos,
                now()->subMonths($meses),
                $meses,
                $lote,
                $max - $borradasTotal,
                $pausaMs,
                $simulacion
            );
        }

        $this->info(($simulacion ? 'Simulado: ' : 'Purgado: ').number_format($borradasTotal).' filas de audits.');

        if ($simulacion && $borradasTotal > $tope) {
            $this->warn(sprintf(
                'Con el tope de %s por corrida, hacen falta %d corridas para drenar el atraso.',
                number_format($tope),
                (int) ceil($borradasTotal / $tope)
            ));
        }
        if (! $simulacion && $borradasTotal >= $max) {
            $this->warn('Se alcanzó el tope por corrida; queda resto para la próxima.');
        }

        return self::SUCCESS;
    }

    /**
     * Lista la política resuelta modelo por modelo, para poder revisarla antes de
     * habilitar la purga. Con la retención por defecto en 6 meses, lo que importa es
     * chequear que nada sensible haya quedado en el grupo "defecto".
     */
    private function mostrarReglas(): int
    {
        $exactos = config('audits_purga.retencion_por_modelo', []);
        $patrones = config('audits_purga.retencion_por_patron', []);
        $defecto = max(1, (int) ($this->option('meses') ?: config('audits_purga.retencion_meses', 6)));

        $filas = DB::table('audits')
            ->selectRaw('auditable_type, COUNT(*) c')
            ->groupBy('auditable_type')
            ->pluck('c', 'auditable_type');

        $datos = [];
        foreach ($filas as $tipo => $cuantas) {
            $meses = $this->resolverMeses($tipo, $exactos, $patrones, $defecto);
            $origen = isset($exactos[$tipo]) ? 'lista exacta' : ($meses === $defecto ? 'DEFECTO' : 'patrón');
            $datos[] = [$meses, class_basename($tipo), number_format($cuantas), $origen];
        }

        usort($datos, fn ($a, $b) => [$b[0], -1 * (int) str_replace(',', '', $a[2])] <=> [$a[0], -1 * (int) str_replace(',', '', $b[2])]);

        $this->table(['Meses', 'Modelo', 'Filas', 'Regla'], $datos);
        $this->info('Retención por defecto: '.$defecto.' meses. Revisar que nada sensible diga DEFECTO.');

        return self::SUCCESS;
    }

    /**
     * Resuelve la retención de cada auditable_type presente en la tabla y los agrupa.
     *
     * Se parte de lo que hay en la tabla (y no de la config) para que ningún modelo
     * quede sin regla: los 208 auditable_type distintos caen en algún grupo, aunque sea
     * el de la retención por defecto.
     *
     * @return array<int, list<string>> meses => tipos
     */
    private function agruparPorRetencion(): array
    {
        $exactos = config('audits_purga.retencion_por_modelo', []);
        $patrones = config('audits_purga.retencion_por_patron', []);
        $defecto = max(1, (int) ($this->option('meses') ?: config('audits_purga.retencion_meses', 6)));

        $tipos = DB::table('audits')->distinct()->pluck('auditable_type');

        // Los de la config que todavía no tienen filas igual se agrupan, para que el día
        // que empiecen a auditarse ya queden con su retención.
        $tipos = $tipos->merge(array_keys($exactos))->unique();

        $grupos = [];
        foreach ($tipos as $tipo) {
            $grupos[$this->resolverMeses($tipo, $exactos, $patrones, $defecto)][] = $tipo;
        }

        return $grupos;
    }

    /**
     * @param  array<string, int>  $exactos
     * @param  array<string, int>  $patrones
     */
    private function resolverMeses(string $tipo, array $exactos, array $patrones, int $defecto): int
    {
        if (isset($exactos[$tipo])) {
            return max(1, (int) $exactos[$tipo]);
        }

        foreach ($patrones as $patron => $meses) {
            if (preg_match($patron, $tipo) === 1) {
                return max(1, (int) $meses);
            }
        }

        return $defecto;
    }

    /**
     * @param  list<string>  $tipos
     */
    private function purgar(
        array $tipos,
        Carbon $limite,
        int $meses,
        int $lote,
        int $restante,
        int $pausaMs,
        bool $simulacion
    ): int {
        if ($restante <= 0 || $tipos === []) {
            return 0;
        }

        $etiqueta = sprintf('%3d meses (%d modelos, corte %s)', $meses, count($tipos), $limite->toDateString());
        $base = fn () => DB::table('audits')->whereIn('auditable_type', $tipos)->where('created_at', '<', $limite);

        if ($simulacion) {
            $cuantas = (int) $base()->count();
            if ($cuantas > 0) {
                $this->line(sprintf('  %-46s %12s filas', $etiqueta, number_format($cuantas)));
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
            $n = $base()->limit($tanda)->delete();
            $borradas += $n;
            if ($n > 0 && $pausaMs > 0) {
                usleep($pausaMs * 1000);
            }
        } while ($n > 0 && $borradas < $restante);

        if ($borradas > 0) {
            $this->line(sprintf('  %-46s %12s borradas', $etiqueta, number_format($borradas)));
        }

        return $borradas;
    }
}
