<?php

namespace App\Console\Commands;

use App\Services\Configuracion\UserTaskSyncService;
use Illuminate\Console\Command;

class UserTaskSincronizarCommand extends Command
{
    protected $signature = 'user-task:sincronizar
                            {--usuario= : Solo un usuario_id}
                            {--limite=200 : Máximo de usuarios activos}';

    protected $description = 'Materializa pendientes de Mis aprobaciones en la tabla user_task.';

    public function handle(UserTaskSyncService $sync): int
    {
        $usuarioOpt = $this->option('usuario');
        if ($usuarioOpt !== null && $usuarioOpt !== '') {
            $r = $sync->sincronizarUsuario((int) $usuarioOpt);
            $this->info('Usuario '.(int) $usuarioOpt.': abiertas='.$r['abiertas'].' cerradas='.$r['cerradas']);

            return self::SUCCESS;
        }

        $r = $sync->sincronizarActivos((int) $this->option('limite'));
        $this->info('Usuarios sincronizados: '.$r['usuarios']);

        return self::SUCCESS;
    }
}
