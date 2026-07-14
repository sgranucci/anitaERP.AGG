<?php

namespace App\Console\Commands;

use App\Services\Ventas\ClienteAnitaSyncService;
use Illuminate\Console\Command;

class ActualizarClienteCobradorDesdeAnita extends Command
{
    protected $signature = 'cliente:actualizar-cobrador-desde-anita';

    protected $description = 'Actualiza cliente.cobrador_id en anitaERP leyendo clim_cobrador desde Anita (solo lectura bridge; no escribe en Informix).';

    public function handle(ClienteAnitaSyncService $sync): int
    {
        try {
            $this->info('Leyendo clim_cobrador desde Anita y actualizando clientes en anitaERP…');
            $ret = $sync->actualizarCobradorIdDesdeAnita();
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
