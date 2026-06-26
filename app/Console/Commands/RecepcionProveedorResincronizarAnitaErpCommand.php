<?php

namespace App\Console\Commands;

use App\Services\Stock\RecepcionProveedorAnitaResincronizacionErpService;
use Illuminate\Console\Command;

class RecepcionProveedorResincronizarAnitaErpCommand extends Command
{
    protected $signature = 'recepcion-proveedor:resincronizar-anita-erp
                            {--id= : Solo una recepción por ID ERP}
                            {--dry-run : Simula sin escribir en Anita}
                            {--reparar-detalle-ref : Repara recepmov/stkmov/aplicped sin tocar cabecera REF vinculada}';

    protected $description = 'Re-sincroniza COM ERP en Anita (recepmae faltante, claves incorrectas; excluye REF vinculadas)';

    public function handle(RecepcionProveedorAnitaResincronizacionErpService $service): int
    {
        $id = $this->option('id') ? (int) $this->option('id') : null;
        $dryRun = (bool) $this->option('dry-run');
        $repararDetalleRef = (bool) $this->option('reparar-detalle-ref');

        if ($dryRun) {
            $this->warn('Dry-run: no se modificará Anita.');
        }

        if (! \Auth::check()) {
            $usuarioId = (int) config('recepcion_proveedor.auditoria_asientos_com_diaria.usuario_id', 1);
            if ($usuarioId <= 0 || ! \Auth::loginUsingId($usuarioId)) {
                $this->error('No se pudo autenticar usuario de sistema para re-sincronizar COM.');

                return self::FAILURE;
            }
        }

        if ($repararDetalleRef) {
            $total = $service->contar($id, true);
            $this->info("COM con cabecera REF vinculada y detalle incompleto: {$total}");

            if ($total === 0) {
                return self::SUCCESS;
            }

            $stats = $service->ejecutarReparacionDetalleRef($dryRun, $id, function ($recepcion, \Throwable $e) {
                $this->error(
                    'Recepción '.$recepcion->id.' COM '.$recepcion->numerorecepcion.': '.$e->getMessage()
                );
            });

            $this->table(['Métrica', 'Cantidad'], [
                ['Procesadas', $stats['procesadas']],
                ['Detalle reparado (cabecera REF intacta)', $stats['reparadas_detalle_ref']],
                ['Errores', $stats['errores']],
            ]);

            return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $total = $service->contar($id);
        $this->info("Recepciones ERP candidatas a re-sincronizar (excluye REF vinculadas): {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $stats = $service->ejecutarResincronizacionCompleta($dryRun, $id, function ($recepcion, \Throwable $e) {
            $this->error(
                'Recepción '.$recepcion->id.' COM '.$recepcion->numerorecepcion.': '.$e->getMessage()
            );
        });

        $this->table(['Métrica', 'Cantidad'], [
            ['Procesadas', $stats['procesadas']],
            ['Claves ERP fuera de sucursal corregidas', $stats['erp_claves_corregidas']],
            ['CONFIRMADA re-sincronizadas', $stats['resincronizadas']],
            ['BORRADOR limpiadas en Anita (solo ERP)', $stats['borrador_limpiadas']],
            ['Omitidas', $stats['omitidas']],
            ['Errores', $stats['errores']],
        ]);

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
