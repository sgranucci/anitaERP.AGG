<?php

namespace App\Console\Commands;

use App\Services\Ventas\Ferli\PedidoSincronizarFaltantesDesdeL8Service;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;

class PedidoSincronizarFaltantesL8Command extends Command
{
    protected $signature = 'ventas:sincronizar-faltantes-l8
                            {--dry-run : Lista el impacto sin grabar}
                            {--ejecutar : Persiste solo lo faltante desde L8}';

    protected $description = 'Trae a L12 OT, talles, tareas, movimientos y OCT faltantes de L8 (Ferli), sin pisar pedidos divergidos ni precios.';

    public function handle(PedidoSincronizarFaltantesDesdeL8Service $service): int
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

        try {
            $stats = $service->sincronizar($dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s · fuente %s · OT +%d · ot_id %d · PC +%d · talles +%d · qty %d · extras -%d · tareas +%d · mov +%d · OCT +%d · huérfanos OCT -%d · omitidos divergidos %d',
            $dryRun ? 'DRY-RUN' : 'EJECUTADO',
            $stats['fuente'] ?? '',
            $stats['insert_ot'] ?? 0,
            $stats['link_ot'] ?? 0,
            $stats['insert_pc'] ?? 0,
            $stats['insert_talle'] ?? 0,
            $stats['update_qty'] ?? 0,
            $stats['delete_talle_extra'] ?? 0,
            $stats['insert_tarea'] ?? 0,
            $stats['insert_movimiento'] ?? 0,
            $stats['insert_oct'] ?? 0,
            $stats['delete_oct_huerfano'] ?? 0,
            $stats['omitidos_divergidos'] ?? 0
        ));

        foreach ($stats['detalle'] ?? [] as $line) {
            $this->line($line);
        }
        foreach ($stats['errores'] ?? [] as $err) {
            $this->error($err);
        }

        return self::SUCCESS;
    }
}
