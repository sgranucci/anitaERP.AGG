<?php

namespace App\Console\Commands;

use App\Repositories\Uif\Cliente_UifRepositoryInterface;
use App\Repositories\Uif\Localidad_UifRepositoryInterface;
use Illuminate\Console\Command;
use Throwable;

class SincronizarLocalidadUifDesdeAnita extends Command
{
    protected $signature = 'localidad-uif:sincronizar-anita
                            {--solo-provincias : Solo reasigna provincia_uif_id desde loc_provincia de Anita}
                            {--clientes=200 : Tras la resincronización, actualizar N clientes UIF (0 = omitir)}';

    protected $description = 'Resincroniza localidad_uif desde Anita (base_admin): upsert por código y elimina obsoletas sin clientes.';

    public function handle(
        Localidad_UifRepositoryInterface $localidadUifRepository,
        Cliente_UifRepositoryInterface $clienteUifRepository
    ): int {
        try {
            if ($this->option('solo-provincias')) {
                $this->info('Reasignando provincia_uif_id desde loc_provincia de Anita…');
                $stats = $localidadUifRepository->reasignarProvinciasDesdeAnita();
                $this->table(
                    ['Operación', 'Cantidad'],
                    [
                        ['Actualizadas', $stats['actualizados']],
                        ['Sin cambio', $stats['sin_cambio']],
                        ['Sin provincia UIF (código Anita sin match)', $stats['sin_provincia_uif']],
                        ['Anita sin localidad local', $stats['omitidos_sin_local']],
                    ]
                );

                return self::SUCCESS;
            }

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
