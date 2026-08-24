<?php

namespace App\Console\Commands;

use App\Services\Ventas\ClienteAnitaSyncService;
use Illuminate\Console\Command;

class ActualizarClienteCoeficienteDesdeAnita extends Command
{
    protected $signature = 'cliente:actualizar-coeficiente-desde-anita
                            {--ejecutar : Persiste cliente.coeficiente_id. Sin este flag solo informa (dry-run)}';

    protected $description = 'Alinea cliente.coeficiente_id en anitaERP con clim_coef de Anita (Anita manda; no escribe en Informix). Dry-run por defecto.';

    public function handle(ClienteAnitaSyncService $sync): int
    {
        $persistir = (bool) $this->option('ejecutar');

        $this->info($persistir
            ? 'EJECUTANDO: se va a actualizar cliente.coeficiente_id según clim_coef de Anita.'
            : 'DRY-RUN: no se escribe nada. Agregue --ejecutar para persistir.');

        try {
            $ret = $sync->actualizarCoeficienteIdDesdeAnita($persistir);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; "
            .($persistir ? 'actualizados' : 'a actualizar').": {$ret['actualizados']}; "
            ."omitidos (iguales): {$ret['omitidos']}; "
            ."sin cliente en ERP: {$ret['sin_cliente']}; "
            ."clim_coef sin mapeo: {$ret['sin_mapeo']}."
        );

        if ($ret['ejemplos'] !== []) {
            $this->table(
                ['codigo', 'clim_coef', 'coeficiente_id ERP', 'coeficiente_id Anita'],
                array_map(static fn (array $e) => [
                    $e['codigo'],
                    $e['clim_coef'],
                    $e['coeficiente_id_erp'] ?? 'null',
                    $e['coeficiente_id_anita'] ?? 'null',
                ], $ret['ejemplos'])
            );
            if ($ret['actualizados'] > count($ret['ejemplos'])) {
                $this->comment('Se listan solo los primeros '.count($ret['ejemplos']).' casos.');
            }
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
