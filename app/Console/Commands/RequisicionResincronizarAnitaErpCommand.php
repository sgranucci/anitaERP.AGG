<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Compras\RequisicionAnitaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class RequisicionResincronizarAnitaErpCommand extends Command
{
    protected $signature = 'requisicion:resincronizar-anita-erp
                            {--id= : Solo una requisición por ID ERP}
                            {--dry-run : Simula sin escribir en Anita}';

    protected $description = 'Re-sincroniza requisiciones ERP → Anita (corrige reqm_usuario con usu_usuario de Anita bridge)';

    public function handle(RequisicionAnitaSyncService $syncService): int
    {
        $id = $this->option('id') ? (int) $this->option('id') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry-run: no se modificará Anita.');
        }

        if (! Auth::check()) {
            $usuarioId = (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
            if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
                $this->error('No se pudo autenticar usuario de sistema para re-sincronizar requisiciones.');

                return self::FAILURE;
            }
        }

        $total = $syncService->contarResincronizacionErp($id);
        $this->info("Requisiciones ERP candidatas a re-sincronizar (SYNC_OK): {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry-run: se procesarían {$total} requisición(es).");

            return self::SUCCESS;
        }

        $stats = $syncService->resincronizarErpEnAnita($id, function ($requisicion, \Throwable $e) {
            $this->error(
                'Requisición '.$requisicion->id.' nro '.$requisicion->numerorequisicion.': '.$e->getMessage()
            );
        });

        $this->table(['Métrica', 'Cantidad'], [
            ['Procesadas', $stats['procesadas']],
            ['Re-sincronizadas', $stats['resincronizadas']],
            ['Omitidas (sin reqmae en Anita)', $stats['omitidas']],
            ['Errores', $stats['errores']],
        ]);

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
