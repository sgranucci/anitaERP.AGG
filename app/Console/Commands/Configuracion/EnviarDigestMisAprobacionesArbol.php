<?php

namespace App\Console\Commands\Configuracion;

use App\Services\Configuracion\ArbolAprobacionDigestService;
use Illuminate\Console\Command;

class EnviarDigestMisAprobacionesArbol extends Command
{
    protected $signature = 'arbolaprobacion:digest-pendientes';

    protected $description = 'Envía digest diario de pendientes del árbol a cada firmante.';

    public function handle(ArbolAprobacionDigestService $service): int
    {
        $stats = $service->enviarDigestDiario();
        $this->info(sprintf(
            'Digest árbol: firmantes=%d enviados=%d omitidos=%d errores=%d',
            $stats['firmantes'],
            $stats['enviados'],
            $stats['omitidos'],
            $stats['errores']
        ));

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
