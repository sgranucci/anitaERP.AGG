<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Ventas\Destino;
use Illuminate\Console\Command;

class SincronizarDestinoDesdeAnita extends Command
{
    protected $signature = 'destino:sincronizar-anita {--ejecutar : Upsert en destino}';

    protected $description = 'Sincroniza el maestro destino (zona SENASA) desde Anita ventas.destino.';

    public function handle(): int
    {
        if (! Destino::tablaLista()) {
            $this->error('No existe la tabla destino. Corra la migración 2026_09_02_063000_crear_tabla_destino.');

            return self::FAILURE;
        }

        $api = new ApiAnita();
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'destino',
            'campos' => 'dest_destino, dest_localidad, dest_provincia, dest_pais, dest_patagonico, dest_cod_localidad',
        ]);
        $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : json_encode($raw));
        $this->info('Filas Anita destino: '.count($filas));
        $this->info('Filas ERP destino: '.Destino::query()->count());

        if (! $this->option('ejecutar')) {
            $this->warn('Dry-run: no se escribe. Para grabar: php artisan destino:sincronizar-anita --ejecutar');

            return self::SUCCESS;
        }

        $stats = Destino::sincronizarConAnita();
        $this->table(
            ['Operación', 'Cantidad'],
            [
                ['Insertados', $stats['insertados']],
                ['Actualizados', $stats['actualizados']],
                ['Omitidos', $stats['omitidos']],
            ]
        );

        return self::SUCCESS;
    }
}
