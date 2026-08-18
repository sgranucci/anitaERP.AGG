<?php

namespace App\Console\Commands;

use App\Models\Contable\Asiento;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Stock\RecepcionProveedorAsientoService;
use App\Support\Stock\RecepcionProveedorEstados;
use Illuminate\Console\Command;

class RecepcionProveedorLimpiarAsientosHuerfanosCommand extends Command
{
    protected $signature = 'recepcion-proveedor:limpiar-asientos-huerfanos
                            {--id= : Solo una recepción ERP}
                            {--dry-run : Solo informa, no elimina}';

    protected $description = 'Elimina asientos ERP huérfanos (mismo recepcionproveedor_id, distinto del vigente)';

    public function handle(RecepcionProveedorAsientoService $asientoService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $idFiltro = $this->option('id') ? (int) $this->option('id') : null;

        $query = Asiento::query()
            ->selectRaw('recepcionproveedor_id, COUNT(*) as total')
            ->whereNotNull('recepcionproveedor_id')
            ->where('recepcionproveedor_id', '>', 0)
            ->groupBy('recepcionproveedor_id')
            ->havingRaw('COUNT(*) > 1');

        if ($idFiltro !== null && $idFiltro > 0) {
            $query->where('recepcionproveedor_id', $idFiltro);
        }

        $grupos = $query->get();
        if ($grupos->isEmpty()) {
            $this->info('No hay recepciones con asientos huérfanos.');

            return self::SUCCESS;
        }

        $totalEliminados = 0;

        foreach ($grupos as $grupo) {
            $recepcionId = (int) $grupo->recepcionproveedor_id;
            $recepcion = Recepcion_Proveedor::query()->find($recepcionId);
            if (! $recepcion) {
                $this->warn("Recepción id {$recepcionId}: sin registro; omitida.");

                continue;
            }

            $vigenteId = (int) ($recepcion->asiento_id ?? 0);
            if ($vigenteId <= 0 && $recepcion->estado === RecepcionProveedorEstados::CONFIRMADA) {
                $this->warn("COM {$recepcion->numerorecepcion} (id {$recepcionId}): confirmada sin asiento_id; revisar manualmente.");

                continue;
            }

            $huerfanos = Asiento::query()
                ->where('recepcionproveedor_id', $recepcionId)
                ->when($vigenteId > 0, fn ($q) => $q->where('id', '!=', $vigenteId))
                ->orderBy('id')
                ->get(['id', 'numeroasiento', 'created_at']);

            if ($huerfanos->isEmpty()) {
                continue;
            }

            $this->line(sprintf(
                'COM %s (rec %d): vigente asiento_id=%s · huérfanos=%d',
                (string) $recepcion->numerorecepcion,
                $recepcionId,
                $vigenteId > 0 ? (string) $vigenteId : '—',
                $huerfanos->count(),
            ));

            foreach ($huerfanos as $asiento) {
                $this->line("  · id={$asiento->id} nro={$asiento->numeroasiento} ({$asiento->created_at})");
            }

            if ($dryRun) {
                continue;
            }

            $eliminados = $asientoService->eliminarAsientosHuerfanosDeRecepcion(
                $recepcionId,
                $vigenteId > 0 ? $vigenteId : null,
            );
            $totalEliminados += count($eliminados);
        }

        if ($dryRun) {
            $this->warn('Dry-run: no se eliminó ningún asiento.');
        } else {
            $this->info("Asientos huérfanos eliminados: {$totalEliminados}");
        }

        return self::SUCCESS;
    }
}
