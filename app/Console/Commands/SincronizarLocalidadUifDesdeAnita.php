<?php

namespace App\Console\Commands;

use App\Repositories\Uif\Cliente_UifRepositoryInterface;
use App\Repositories\Uif\Localidad_UifRepositoryInterface;
use Illuminate\Console\Command;
use Throwable;

class SincronizarLocalidadUifDesdeAnita extends Command
{
    protected $signature = 'localidad-uif:sincronizar-anita
                            {--clientes=200 : Tras la resincronización, actualizar N clientes UIF (0 = omitir)}';

    protected $description = 'Resincroniza localidad_uif desde Anita (base_admin): upsert por código y elimina obsoletas sin clientes.';

    public function handle(
        Localidad_UifRepositoryInterface $localidadUifRepository,
        Cliente_UifRepositoryInterface $clienteUifRepository
    ): int {
        try {
            $this->info('Resincronizando localidad_uif desde Anita…');
            $stats = $localidadUifRepository->resincronizarConAnita();

            $this->table(
                ['Operación', 'Cantidad'],
                [
                    ['Insertadas', $stats['insertados']],
                    ['Actualizadas', $stats['actualizados']],
                    ['Eliminadas (sin clientes)', $stats['eliminados']],
                    ['Obsoletas omitidas (con clientes)', $stats['omitidos_con_clientes']],
                ]
            );

            $limiteClientes = (int) $this->option('clientes');
            if ($limiteClientes <= 0) {
                $this->info('Resincronización de localidades finalizada.');

                return self::SUCCESS;
            }

            $this->info("Resincronizando {$limiteClientes} clientes UIF para actualizar localidad_uif_id…");
            $this->call('cliente-uif:sincronizar-anita', [
                '--limite' => $limiteClientes,
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
