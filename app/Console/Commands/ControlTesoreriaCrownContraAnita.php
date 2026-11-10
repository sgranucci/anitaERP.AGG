<?php

namespace App\Console\Commands;

use App\Services\Caja\TesoreriaCrownControlAnitaService;
use Illuminate\Console\Command;

class ControlTesoreriaCrownContraAnita extends Command
{
    protected $signature = 'tesoreria-crown:control-anita
                            {--desde= : Fecha inicial Y-m-d (default: ayer)}
                            {--hasta= : Fecha final Y-m-d (default: igual a desde)}
                            {--todo : Muestra también los controles que coinciden}';

    protected $description = 'Compara día por día la tesorería Crown del ERP contra Anita (cajas, fiscal, bóveda, público, poder público, anexos I y VIII). Solo lectura.';

    public function handle(TesoreriaCrownControlAnitaService $control): int
    {
        $desde = trim((string) $this->option('desde')) ?: date('Y-m-d', strtotime('-1 day'));
        $hasta = trim((string) $this->option('hasta')) ?: $desde;

        try {
            $filas = $control->controlar($desde, $hasta);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $diferencias = array_values(array_filter($filas, fn (array $f) => ! $f['ok']));
        $mostrar = $this->option('todo') ? $filas : $diferencias;
        $formato = fn (?float $v) => $v === null ? '—' : number_format($v, 2, ',', '.');
        if ($mostrar !== []) {
            $this->table(
                ['Fecha', 'Área', 'Control', 'ERP', 'Anita', 'Diferencia', 'Nota'],
                array_map(fn (array $f) => [
                    $f['fecha'],
                    $f['area'],
                    $f['concepto'],
                    $formato($f['erp']),
                    $formato($f['anita']),
                    $formato($f['dif']),
                    $f['ok'] ? 'ok' : $f['nota'],
                ], $mostrar)
            );
        }

        if ($diferencias === []) {
            $this->info(sprintf('Sin diferencias (%d controles, %s → %s).', count($filas), $desde, $hasta));

            return self::SUCCESS;
        }
        $this->warn(sprintf('%d diferencias en %d controles.', count($diferencias), count($filas)));

        return self::FAILURE;
    }
}
