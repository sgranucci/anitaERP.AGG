<?php

namespace App\Console\Commands;

use App\Models\Caja\TesoreriaCrownBoveda;
use App\Services\Caja\TesoreriaCrownBovedaAnitaImportService;
use App\Support\Caja\TesoreriaCrownBovedaMonedaExtranjeraPesosSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;

/**
 * Copia a los retiros de dólares / euros importados de Anita el importe en pesos con que
 * Anita los valorizó (FIFO de partidas). Sin --ejecutar solo muestra el plan.
 */
class ImportePesosMonedaExtranjeraCrownDesdeAnita extends Command
{
    protected $signature = 'tesoreria-crown:importe-pesos-moneda-extranjera-anita
        {--desde= : Fecha de jornada desde (Y-m-d)}
        {--hasta= : Fecha de jornada hasta (Y-m-d)}
        {--ejecutar : Graba importe_pesos (sin esto es dry-run)}';

    protected $description = 'Graba en los retiros de moneda extranjera importados de Anita el importe en pesos del FIFO de Anita';

    public function handle(TesoreriaCrownBovedaAnitaImportService $import): int
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->error('Solo corre en Crown.');

            return self::FAILURE;
        }
        $desde = (string) ($this->option('desde') ?: '2000-01-01');
        $hasta = (string) ($this->option('hasta') ?: date('Y-m-d'));

        $movimientos = TesoreriaCrownBoveda::query()
            ->with(['jornada:id,fecha', 'tipo:id,codigo,abreviatura'])
            ->whereHas('tipo', fn ($q) => $q->whereIn('codigo', array_keys(TesoreriaCrownBovedaMonedaExtranjeraPesosSupport::MONEDAS)))
            ->whereHas('jornada', fn ($q) => $q->whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta))
            ->whereNull('usuario_id')
            ->whereNull('comprobante_id')
            ->orderBy('numero')
            ->get();
        if ($movimientos->isEmpty()) {
            $this->info('No hay retiros de moneda extranjera importados de Anita en el rango.');

            return self::SUCCESS;
        }

        $pesos = $import->importePesosAnita($movimientos->pluck('numero')->map(fn ($n) => (int) $n)->all());
        $filas = [];
        $cambios = [];
        foreach ($movimientos as $mov) {
            $nuevo = $pesos[(int) $mov->numero] ?? null;
            $actual = $mov->importe_pesos !== null ? (float) $mov->importe_pesos : null;
            $accion = $nuevo === null ? 'sin monemov en Anita'
                : ($actual !== null && abs($actual - $nuevo) < 0.005 ? 'igual' : 'grabar');
            if ($accion === 'grabar') {
                $cambios[] = [$mov, $nuevo];
            }
            $filas[] = [
                $mov->jornada?->fecha?->format('d/m/Y'),
                $mov->numero,
                $mov->tipo?->abreviatura,
                $actual === null ? '—' : number_format($actual, 2, ',', '.'),
                $nuevo === null ? '—' : number_format($nuevo, 2, ',', '.'),
                $accion,
            ];
        }
        $this->table(['Fecha', 'Operación', 'Tipo', 'Pesos ERP', 'Pesos Anita (FIFO)', 'Acción'], $filas);

        if (! $this->option('ejecutar')) {
            $this->info(count($cambios).' a grabar. Dry-run: no se grabó nada (usá --ejecutar).');

            return self::SUCCESS;
        }
        foreach ($cambios as [$mov, $nuevo]) {
            $mov->importe_pesos = $nuevo;
            $mov->save();
        }
        $this->info(count($cambios).' retiros actualizados.');

        return self::SUCCESS;
    }
}
