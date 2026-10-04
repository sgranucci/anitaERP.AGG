<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\OrdencompraEntregaDiaSinRecepcionService;
use Illuminate\Console\Command;

class AvisarEntregaDiaSinRecepcion extends Command
{
    protected $signature = 'compras:avisar-entrega-dia-sin-recepcion
                            {--fecha= : Fecha de entrega Y-m-d (default: hoy)}';

    protected $description = 'Avisa artículos de OC con entrega en el día cuya recepción de proveedor todavía no cubre esa cantidad.';

    public function handle(OrdencompraEntregaDiaSinRecepcionService $service): int
    {
        $fecha = $this->option('fecha');
        $fecha = is_string($fecha) && trim($fecha) !== '' ? trim($fecha) : null;

        $resultado = $service->enviar($fecha);

        if ($resultado['omitido'] !== null) {
            $this->info('Sin envíos: '.$resultado['omitido'].' (pendientes: '.$resultado['total'].')');

            return self::SUCCESS;
        }

        $this->info('Mails encolados: '.$resultado['enviados'].' (artículos: '.$resultado['total'].')');

        return self::SUCCESS;
    }
}
