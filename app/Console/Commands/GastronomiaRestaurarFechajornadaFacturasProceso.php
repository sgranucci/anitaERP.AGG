<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaFechajornadaSupport;
use Illuminate\Console\Command;

/**
 * Restaura fechajornada de FAC proceso Waitry a la fecha de la leyenda (día del cierre/asiento).
 * No toca venta.fecha (CbteFch CAEA).
 */
class GastronomiaRestaurarFechajornadaFacturasProceso extends Command
{
    protected $signature = 'gastronomia:restaurar-fechajornada-facturas-proceso
                            {--force : Aplicar cambios}
                            {--yes : Sin confirmación}';

    protected $description = 'Alinea fechajornada de FAC proceso cierre Waitry con la jornada del asiento (leyenda)';

    public function handle(): int
    {
        $dryRun = ! $this->option('force');

        $candidatas = CierreJornadaProcesoFacturaFechajornadaSupport::listarDesalineadas();
        $this->info('FAC proceso con fechajornada ≠ jornada de leyenda: '.$candidatas->count());
        foreach ($candidatas->take(30) as $fila) {
            $this->line(sprintf(
                '  id=%d %s fecha=%s jornada=%s → %s',
                $fila->id,
                $fila->codigo,
                $fila->fecha,
                $fila->fechajornada,
                $fila->jornada_leyenda,
            ));
        }
        if ($candidatas->count() > 30) {
            $this->line('  … +'.($candidatas->count() - 30).' más');
        }

        if ($dryRun) {
            $this->warn('Dry-run: no se modificó nada. Ejecutar con --force --yes para aplicar.');

            return self::SUCCESS;
        }

        if ($candidatas->isEmpty()) {
            $this->comment('Nada para corregir.');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('¿Restaurar fechajornada en '.$candidatas->count().' factura(s)?')) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        $resultado = CierreJornadaProcesoFacturaFechajornadaSupport::restaurarDesdeLeyenda();
        $this->info(sprintf('Revisadas %d · corregidas %d', $resultado['revisadas'], $resultado['corregidas']));

        return self::SUCCESS;
    }
}
