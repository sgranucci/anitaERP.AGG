<?php

namespace App\Console\Commands;

use App\Services\Caja\ChequeDiferidoReclasificacionService;
use Illuminate\Console\Command;

class CajaReclasificarChequesDiferidosCommand extends Command
{
    protected $signature = 'caja:reclasificar-cheques-diferidos
                            {--fecha= : Fecha de corte Y-m-d (default: hoy)}';

    protected $description = 'Reclasifica cheques propios posdatados de cheques diferidos al banco correspondiente';

    public function handle(ChequeDiferidoReclasificacionService $service): int
    {
        $fecha = $this->option('fecha');
        $resultado = $service->reclasificarPendientes(is_string($fecha) && $fecha !== '' ? $fecha : null);

        $this->info('Procesados: '.$resultado['procesados'].', omitidos: '.$resultado['omitidos']);
        foreach ($resultado['errores'] as $error) {
            $this->warn($error);
        }

        return empty($resultado['errores']) ? self::SUCCESS : self::FAILURE;
    }
}
