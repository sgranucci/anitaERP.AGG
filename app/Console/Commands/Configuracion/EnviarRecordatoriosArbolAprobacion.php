<?php

namespace App\Console\Commands\Configuracion;

use App\Services\Configuracion\ArbolAprobacionRecordatorioService;
use Illuminate\Console\Command;

class EnviarRecordatoriosArbolAprobacion extends Command
{
    protected $signature = 'arbolaprobacion:recordatorios
                            {--dry-run : Solo cuenta candidatos sin enviar}';

    protected $description = 'Envía recordatorios de pendientes del árbol (ABM recordatorio=S).';

    public function handle(ArbolAprobacionRecordatorioService $service): int
    {
        if ($this->option('dry-run')) {
            $this->info('Dry-run: usá el comando sin --dry-run para enviar.');
            $this->warn('El envío real deduplica 1 mail/día por movimiento vía cache.');

            return self::SUCCESS;
        }

        $stats = $service->enviarPendientes();
        $this->info(sprintf(
            'Recordatorios árbol: enviados=%d omitidos=%d errores=%d',
            $stats['enviados'],
            $stats['omitidos'],
            $stats['errores']
        ));

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
