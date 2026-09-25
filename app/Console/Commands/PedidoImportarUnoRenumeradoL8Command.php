<?php

namespace App\Console\Commands;

use App\Services\Ventas\Ferli\PedidoImportarUnoRenumeradoDesdeL8Service;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;

class PedidoImportarUnoRenumeradoL8Command extends Command
{
    protected $signature = 'ventas:importar-pedido-l8-renumerado
                            {codigo_l8 : Código del pedido en L8}
                            {--codigo-nuevo= : Código destino en L12 (default: max+1; con --mover-ocupante suele ser el mismo de L8)}
                            {--mover-ocupante : Si el código destino está ocupado, lo renumera a --codigo-ocupante (o max+1)}
                            {--codigo-ocupante= : Código nuevo para el pedido L12 que libera el número}
                            {--conservar-ot : Reutiliza OTs L8 ya existentes en L12 (etiqueta) desvinculándolas del ocupante}
                            {--dry-run : Muestra el impacto sin grabar}
                            {--ejecutar : Persiste el pedido renumerado en L12}';

    protected $description = 'Ferli: trae un pedido de L8 a L12 con código e IDs nuevos (si el número ya está ocupado).';

    public function handle(PedidoImportarUnoRenumeradoDesdeL8Service $service): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->warn('Solo aplica a Calzados Ferli.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $ejecutar = (bool) $this->option('ejecutar');
        if ($dryRun && $ejecutar) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }
        if (! $dryRun && ! $ejecutar) {
            $this->warn('Sin flags: modo dry-run.');
            $dryRun = true;
        }

        $codigoNuevo = $this->option('codigo-nuevo');
        $codigoNuevo = is_string($codigoNuevo) && trim($codigoNuevo) !== '' ? trim($codigoNuevo) : null;

        $moverOcupante = (bool) $this->option('mover-ocupante');
        $codigoOcupante = $this->option('codigo-ocupante');
        $codigoOcupante = is_string($codigoOcupante) && trim($codigoOcupante) !== '' ? trim($codigoOcupante) : null;
        $conservarOt = (bool) $this->option('conservar-ot');

        // Caso típico "liberar el número de L8": destino = mismo código L8.
        if ($moverOcupante && $codigoNuevo === null) {
            $codigoNuevo = (string) $this->argument('codigo_l8');
        }
        if ($moverOcupante && ! $conservarOt) {
            $conservarOt = true;
            $this->comment('Con --mover-ocupante se activa --conservar-ot (OTs de etiqueta).');
        }

        try {
            $stats = $service->importar(
                (string) $this->argument('codigo_l8'),
                $codigoNuevo,
                $dryRun,
                $moverOcupante,
                $codigoOcupante,
                $conservarOt
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s · L8 %s → L12 %s · %s · pedido +%d · comb +%d · talles +%d · OT +%d · tareas +%d · OCT +%d',
            $dryRun ? 'DRY-RUN' : 'EJECUTADO',
            $stats['l8_codigo'],
            $stats['l12_codigo'],
            $stats['cliente'],
            $stats['insert_pedido'],
            $stats['insert_combinacion'],
            $stats['insert_talle'],
            $stats['insert_ordentrabajo'],
            $stats['insert_tarea'],
            $stats['insert_oct']
        ));

        foreach ($stats['detalle'] as $line) {
            $this->line($line);
        }

        if (! $dryRun && $stats['l12_pedido_id']) {
            $this->info('Pedido L12 id='.$stats['l12_pedido_id']);
        }

        return self::SUCCESS;
    }
}
