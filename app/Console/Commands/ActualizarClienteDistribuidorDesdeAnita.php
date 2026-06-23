<?php

namespace App\Console\Commands;

use App\Services\Ventas\ClienteAnitaSyncService;
use Illuminate\Console\Command;

class ActualizarClienteDistribuidorDesdeAnita extends Command
{
    protected $signature = 'cliente:actualizar-distribuidor-desde-anita';

    protected $description = 'Actualiza cliente.distribuidor_id en anitaERP leyendo clim_distribuidor desde Anita (solo lectura bridge; no escribe en Informix).';

    public function handle(ClienteAnitaSyncService $sync): int
    {
        try {
            $this->info('Leyendo clim_distribuidor desde Anita y actualizando clientes en anitaERP…');
            $ret = $sync->actualizarDistribuidorIdDesdeAnita();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; actualizados: {$ret['actualizados']}; omitidos (sin cambio): {$ret['omitidos']}; sin cliente en ERP: {$ret['sin_cliente']}."
        );

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
