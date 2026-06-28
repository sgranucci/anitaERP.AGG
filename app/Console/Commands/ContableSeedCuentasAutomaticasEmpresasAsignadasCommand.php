<?php

namespace App\Console\Commands;

use App\Services\Contable\ContabilidadCuentaAutomaticaSeedService;
use Illuminate\Console\Command;

class ContableSeedCuentasAutomaticasEmpresasAsignadasCommand extends Command
{
    protected $signature = 'contable:seed-cuentas-automaticas-empresas-asignadas';

    protected $description = 'Crea/Completa contabilidad_cuenta_automatica para empresas con usuarios asignados (usuario_empresa)';

    public function handle(ContabilidadCuentaAutomaticaSeedService $service): int
    {
        $cantidad = $service->asegurarCatalogoEmpresasConUsuariosAsignados();
        $this->info('Catálogo verificado para '.$cantidad.' empresa(s) con usuarios asignados.');

        return self::SUCCESS;
    }
}
