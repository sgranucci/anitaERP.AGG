<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Configuracion\Sircip\SircipVigenciaSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Borra los períodos viejos de padron_sircip (≈1,1 millones de filas por mes).
 *
 * Conserva `padrones_iibb.sircip.conservar_periodos` meses contando el actual,
 * y nunca borra el último período cargado aunque sea viejo (evita dejar la
 * tabla vacía si dejó de llegar el padrón). Con --dry-run solo informa.
 */
class PurgePadronSircipAntiguos extends Command
{
    protected $signature = 'padron-sircip:purge
        {--dry-run : Muestra qué períodos borraría, sin borrar}
        {--fecha= : Fecha de referencia (Y-m-d). Por defecto hoy}';

    protected $description = 'Elimina períodos viejos del padrón SIRCIP (conserva los últimos N meses)';

    private const TANDA = 50000;

    public function handle(): int
    {
        $conservar = max(2, (int) config('padrones_iibb.sircip.conservar_periodos', 4));
        $ref = $this->option('fecha') ? \Carbon\Carbon::parse((string) $this->option('fecha')) : now();
        $corte = $ref->copy()->startOfMonth()->subMonthsNoOverflow($conservar - 1)->format('Ym');

        $cargados = SircipVigenciaSupport::periodosCargados();
        if ($cargados === []) {
            $this->info('No hay períodos cargados.');

            return self::SUCCESS;
        }

        $ultimo = (string) array_key_last($cargados);
        $aBorrar = array_filter(
            $cargados,
            static fn (int $filas, string $periodo): bool => $periodo < $corte && $periodo !== $ultimo,
            ARRAY_FILTER_USE_BOTH
        );

        $this->info("Se conservan los períodos desde {$corte} (y el último cargado: {$ultimo}).");
        if ($aBorrar === []) {
            $this->info('Nada para borrar.');

            return self::SUCCESS;
        }

        foreach ($aBorrar as $periodo => $filas) {
            $this->line(sprintf('%s: %s filas%s', $periodo, number_format($filas, 0, ',', '.'), $this->option('dry-run') ? ' (se borraría)' : ''));
        }
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $total = 0;
        foreach (array_keys($aBorrar) as $periodo) {
            do {
                $n = DB::table('padron_sircip')->where('periodo', (string) $periodo)->limit(self::TANDA)->delete();
                $total += $n;
            } while ($n > 0);
        }

        Log::info('padron_sircip:purge', ['periodos' => array_keys($aBorrar), 'eliminadas' => $total, 'corte' => $corte]);
        $this->info("Eliminadas {$total} fila(s) de ".count($aBorrar).' período(s).');

        return self::SUCCESS;
    }
}
