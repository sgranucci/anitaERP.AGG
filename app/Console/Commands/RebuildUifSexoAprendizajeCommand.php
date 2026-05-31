<?php

namespace App\Console\Commands;

use App\Services\Uif\ClienteUifSexoAprendizajeService;
use Illuminate\Console\Command;

class RebuildUifSexoAprendizajeCommand extends Command
{
    protected $signature = 'uif:rebuild-sexo-aprendizaje';

    protected $description = 'Reconstruye el aprendizaje de sexo UIF desde los clientes ya cargados';

    public function handle(ClienteUifSexoAprendizajeService $service): int
    {
        $total = $service->reconstruirDesdeClientes();
        $this->info("Aprendizaje reconstruido desde {$total} clientes UIF.");

        return self::SUCCESS;
    }
}
