<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\OrdencompraAlertasAbiertasService;
use Illuminate\Console\Command;

class EnviarAlertasOrdencompraAbiertas extends Command
{
    protected $signature = 'compras:alertas-ordencompra-abiertas
                            {--dias= : Días sin recepción para alertar (default: config compras.oc_alertas_abiertas.dias_sin_recepcion)}';

    protected $description = 'Envía por mail el resumen de OC abiertas (sin recepción, parciales, vencidas y saldos pendientes) según el configurador de avisos.';

    public function handle(OrdencompraAlertasAbiertasService $service): int
    {
        $diasOpt = $this->option('dias');
        $dias = $diasOpt !== null && $diasOpt !== '' ? (int) $diasOpt : null;

        $resultado = $service->enviarResumen($dias);

        if ($resultado['omitido'] !== null) {
            $this->info('Sin envíos: '.$resultado['omitido']);

            return self::SUCCESS;
        }

        $this->info('Mails encolados: '.$resultado['enviados']);

        return self::SUCCESS;
    }
}
