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
                            {--clientes=0 : Tras la resincronización, actualizar N clientes UIF (0 = omitir; requiere --force-clientes)}
                            {--force-clientes : Permite re-sync de clientes tras localidades}';

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
                $this->info('Resincronización de localidades finalizada (sin tocar clientes).');

                return self::SUCCESS;
            }

            if (! (bool) $this->option('force-clientes')) {
                $this->error("Omitido sync de {$limiteClientes} clientes: use --force-clientes además de --clientes=N.");
                $this->line('El ERP es fuente de verdad en geo; no re-sync masivo de clientes por defecto.');

                return self::FAILURE;
            }

            $this->warn("Resincronizando {$limiteClientes} clientes UIF con --force…");
            $this->call('cliente-uif:sincronizar-anita', [
                '--limite' => $limiteClientes,
                '--force' => true,
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
