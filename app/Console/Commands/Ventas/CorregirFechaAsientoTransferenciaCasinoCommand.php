<?php

declare(strict_types=1);

namespace App\Console\Commands\Ventas;

use App\Support\Ventas\TransferenciaCrown\CorregirFechaAsientoTransferenciaCasinoSupport;
use Illuminate\Console\Command;

/**
 * Crown: asiento.fecha = venta.fechajornada en transferencias casino/gastronomía.
 */
class CorregirFechaAsientoTransferenciaCasinoCommand extends Command
{
    protected $signature = 'ventas:corregir-fecha-asiento-transferencia-casino
                            {--dry-run : Solo lista candidatos sin grabar}
                            {--ejecutar : Persiste el cambio de fecha}';

    protected $description = 'Crown: alinea asiento.fecha a venta.fechajornada en transferencias gastronomía (no ARCA)';

    public function handle(CorregirFechaAsientoTransferenciaCasinoSupport $support): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');
        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('Usá --dry-run o --ejecutar, no ambos.');

            return self::FAILURE;
        }
        if (! $ejecutar && ! (bool) $this->option('dry-run')) {
            $this->warn('Sin flag: corre en dry-run. Para persistir: --ejecutar');
            $dryRun = true;
        }

        $resultado = $support->ejecutar($dryRun);
        $this->info(($dryRun ? 'DRY-RUN' : 'EJECUTADO').': candidatos='.$resultado['candidatos']
            .' total_venta='.$resultado['total_venta']
            .' corregidos='.$resultado['corregidos']);

        foreach ($resultado['muestra'] as $fila) {
            $this->line(sprintf(
                '  asiento %s (%s): %s → %s | %s $%s',
                $fila['numeroasiento'],
                $fila['asiento_id'],
                $fila['fecha_antes'],
                $fila['fecha_despues'],
                $fila['codigo'],
                number_format($fila['total'], 2, ',', '.')
            ));
        }
        if ($resultado['candidatos'] > count($resultado['muestra'])) {
            $this->line('  … y '.($resultado['candidatos'] - count($resultado['muestra'])).' más');
        }

        return self::SUCCESS;
    }
}
