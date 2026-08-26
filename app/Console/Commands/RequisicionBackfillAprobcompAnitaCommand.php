<?php

namespace App\Console\Commands;

use App\Services\Compras\RequisicionAnitaAprobcompSyncService;
use Illuminate\Console\Command;

class RequisicionBackfillAprobcompAnitaCommand extends Command
{
    protected $signature = 'requisicion:backfill-aprobcomp-anita
                            {--id= : Solo una requisición por ID ERP (insert)}
                            {--desde-nro=0 : Solo numerorequisicion mayor o igual (insert)}
                            {--nro= : Al reparar, solo este numerorequisicion}
                            {--limite=200 : Máximo a procesar}
                            {--reparar : Completa nro_int_ap/fecha en snapshots ERP incompletos}
                            {--dry-run : Cuenta candidatas sin escribir en Anita}';

    protected $description = 'Completa aprobcomp Anita (autorizante l-proy) solo si la REQ no tiene árbol nativo';

    public function handle(RequisicionAnitaAprobcompSyncService $sync): int
    {
        if (! $sync->habilitado()) {
            $this->error('Sync aprobcomp deshabilitado (REQUISICION_ANITA_SYNC_ACTIVO / REQUISICION_ANITA_SYNC_APROBCOMP).');

            return self::FAILURE;
        }

        if ((bool) $this->option('reparar')) {
            return $this->handleReparar($sync);
        }

        return $this->handleBackfill($sync);
    }

    private function handleBackfill(RequisicionAnitaAprobcompSyncService $sync): int
    {
        $id = $this->option('id') ? (int) $this->option('id') : null;
        $desdeNro = max(0, (int) $this->option('desde-nro'));
        $limite = max(1, (int) $this->option('limite'));
        $dryRun = (bool) $this->option('dry-run');

        $total = $sync->contarCandidatas($id, $desdeNro);
        $this->info("Requisiciones ERP con historia APROBADA (fuera de árbol/pendiente): {$total}");

        if ($dryRun) {
            $this->warn('Dry-run: no se escribe Anita. Se consulta aprobcomp para ver cuántas faltan.');
        }

        $stats = $sync->backfill($id, $desdeNro, $limite, $dryRun);

        $this->table(['Métrica', 'Cantidad'], [
            ['Procesadas', $stats['procesadas']],
            [$dryRun ? 'Faltarían escribir' : 'Insertadas', $stats['insertadas']],
            ['Omitidas (ya hay árbol Anita / sin dato)', $stats['omitidas']],
            ['Errores', $stats['errores']],
        ]);

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function handleReparar(RequisicionAnitaAprobcompSyncService $sync): int
    {
        $limite = max(1, (int) $this->option('limite'));
        $dryRun = (bool) $this->option('dry-run');
        $nro = $this->option('nro') ? (int) $this->option('nro') : null;

        $this->info($nro
            ? "Reparar snapshot ERP incompleto de REQ {$nro}"
            : 'Reparar snapshots ERP incompletos (motivo=ERP, sin nro_int_ap/fecha_envio)'
        );

        if ($dryRun) {
            $this->warn('Dry-run: no se escribe Anita ni se reserva numabm.');
        }

        $stats = $sync->repararIncompletos($limite, $dryRun, $nro);

        $this->table(['Métrica', 'Cantidad'], [
            ['Procesadas', $stats['procesadas']],
            [$dryRun ? 'Faltarían reparar' : 'Reparadas', $stats['reparadas']],
            ['Omitidas', $stats['omitidas']],
            ['Errores', $stats['errores']],
        ]);

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
