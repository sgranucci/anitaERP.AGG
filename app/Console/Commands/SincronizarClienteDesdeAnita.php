<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Repositories\Ventas\Cliente_ArchivoRepositoryInterface;
use App\Repositories\Ventas\Cliente_EntregaRepositoryInterface;
use App\Services\Ventas\ClienteAnitaSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class SincronizarClienteDesdeAnita extends Command
{
    protected $signature = 'cliente:sincronizar-anita
                            {--codigo= : Importar/actualizar un solo cliente por código Anita (clim_cliente)}
                            {--usuario= : ID usuario para usuario_id en altas (default: primer usuario)}
                            {--sin-entregas : No sincronizar domicilios de entrega}
                            {--sin-archivos : No sincronizar archivos adjuntos}';

    protected $description = 'Importa/actualiza clientes en anitaERP desde Anita (climae). Anita es la fuente de verdad; no modifica Informix.';

    public function handle(
        ClienteAnitaSyncService $sync,
        Cliente_EntregaRepositoryInterface $clienteEntregaRepository,
        Cliente_ArchivoRepositoryInterface $clienteArchivoRepository
    ): int {
        $usuarioId = $this->option('usuario');
        $usuarioId = ($usuarioId !== null && $usuarioId !== '')
            ? (int) $usuarioId
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0) {
            $this->error('usuario inválido.');

            return self::FAILURE;
        }

        if (! Auth::loginUsingId($usuarioId)) {
            $this->error("No existe usuario id {$usuarioId}.");

            return self::FAILURE;
        }

        $codigo = $this->option('codigo');
        $codigo = is_string($codigo) ? trim($codigo) : '';

        try {
            if ($codigo !== '') {
                $this->info("Sincronizando cliente Anita clim_cliente={$codigo}…");
                $estado = $sync->traerRegistroDeAnita($codigo);
                $this->info("Resultado: {$estado}");

                return self::SUCCESS;
            }

            $this->info('Sincronizando clientes desde Anita (climae). Puede tardar varios minutos…');

            $ret = $sync->sincronizarConAnita();

            $this->info(
                "En Anita: {$ret['en_anita']}; importados: {$ret['importados']}; actualizados: {$ret['actualizados']}; omitidos: {$ret['omitidos']}."
            );

            if ($ret['en_anita'] === 0) {
                $this->warn('Anita devolvió 0 clientes. Revise ANITA_* y CLIENTE_SYNC_ANITA_CAMPOS_LISTADO en .env.');
            }

            foreach ($ret['errores'] as $err) {
                $this->warn($err);
            }

            if (! $this->option('sin-entregas')) {
                $this->info('Sincronizando domicilios de entrega…');
                $clienteEntregaRepository->sincronizarConAnita();
            }

            if (! $this->option('sin-archivos')) {
                $this->info('Sincronizando archivos de cliente…');
                $clienteArchivoRepository->sincronizarConAnita();
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sincronización de clientes finalizada.');

        return self::SUCCESS;
    }
}
