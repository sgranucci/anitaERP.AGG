<?php

namespace App\Console\Commands;

use App\Services\Configuracion\UserTaskSlaService;
use Illuminate\Console\Command;

class UserTaskEscalarVencidosCommand extends Command
{
    protected $signature = 'user-task:escalar-vencidos
                            {--limite=100 : Máximo de tareas a escalar}';

    protected $description = 'Escala user_task pendientes con SLA vencido (notifica + marca escalado).';

    public function handle(UserTaskSlaService $sla): int
    {
        $limite = max(1, (int) $this->option('limite'));
        $resultado = $sla->escalarVencidas($limite);
        $this->info('Escaladas: '.(int) ($resultado['escaladas'] ?? 0));

        return self::SUCCESS;
    }
}
