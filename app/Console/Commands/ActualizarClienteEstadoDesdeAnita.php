<?php

namespace App\Console\Commands;

use App\Services\Ventas\ClienteAnitaSyncService;
use Illuminate\Console\Command;

class ActualizarClienteEstadoDesdeAnita extends Command
{
    protected $signature = 'cliente:actualizar-estado-desde-anita
                            {--ejecutar : Persiste cliente.estado. Sin este flag solo informa (dry-run)}';

    protected $description = 'Alinea cliente.estado en anitaERP con clim_estado_cli de Anita (Anita manda; no escribe Informix). Dry-run por defecto.';

    public function handle(ClienteAnitaSyncService $sync): int
    {
        $persistir = (bool) $this->option('ejecutar');

        $this->info($persistir
            ? 'EJECUTANDO: se va a actualizar cliente.estado según clim_estado_cli de Anita.'
            : 'DRY-RUN: no se escribe nada. Agregue --ejecutar para persistir.');

        try {
            $ret = $sync->actualizarEstadoDesdeAnita($persistir);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "En Anita: {$ret['en_anita']}; "
            .($persistir ? 'actualizados' : 'a actualizar').": {$ret['actualizados']}; "
            ."omitidos (iguales): {$ret['omitidos']}; "
            ."sin cliente en ERP: {$ret['sin_cliente']}."
        );

        if ($ret['por_cambio'] !== []) {
            $this->newLine();
            $this->comment('Resumen por cambio (ERP → Anita):');
            foreach ($ret['por_cambio'] as $clave => $cantidad) {
                $this->line("  {$clave}: {$cantidad}");
            }
        }

        if ($ret['ejemplos'] !== []) {
            $this->newLine();
            $this->table(
                ['codigo', 'nombre', 'ERP', 'Anita', 'raw Anita'],
                array_map(static fn (array $e) => [
                    $e['codigo'],
                    $e['nombre'],
                    $e['etiqueta_erp'].' ('.$e['estado_erp'].')',
                    $e['etiqueta_anita'].' ('.$e['estado_anita'].')',
                    $e['anita_raw'] === '' ? '(vacío)' : $e['anita_raw'],
                ], $ret['ejemplos'])
            );
            if ($ret['actualizados'] > count($ret['ejemplos'])) {
                $this->comment('Se listan solo los primeros '.count($ret['ejemplos']).' casos.');
            }
        }

        foreach ($ret['errores'] as $err) {
            $this->warn($err);
        }

        if (! $persistir && $ret['actualizados'] > 0) {
            $this->newLine();
            $this->warn('Nada persistido. Para grabar: php artisan cliente:actualizar-estado-desde-anita --ejecutar');
        }

        return self::SUCCESS;
    }
}
