<?php

namespace App\Console\Commands;

use App\Services\Ventas\PuntoventaAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarPuntoventaDesdeAnita extends Command
{
    protected $signature = 'puntoventa:sincronizar-anita
                            {--codigo= : Importar solo una sucursal por suc_numero Anita}';

    protected $description = 'Importa puntos de venta desde Anita (tabla sucursal), mapeo alineado al repositorio histórico.';

    public function handle(PuntoventaAnitaSyncService $sync): int
    {
        $codigo = $this->option('codigo');
        if ($codigo !== null && $codigo !== '') {
            $this->info("Importando sucursal Anita suc_numero={$codigo}…");
            $estado = $sync->traerRegistroDeAnita((int) $codigo);
            $this->info("Resultado: {$estado}");

            return self::SUCCESS;
        }

        $this->info('Sincronizando puntos de venta desde Anita…');
        $ret = $sync->sincronizarConAnita();
        $this->info(
            "En Anita: {$ret['en_anita']}; importados: {$ret['importados']}; actualizados: {$ret['actualizados']}; omitidos: {$ret['omitidos']}."
        );
        if ($ret['en_anita'] === 0) {
            $this->warn('Anita devolvió 0 sucursales. Revise la tabla sucursal en Informix, variables ANITA_* y que el SELECT no falle por columnas inexistentes.');
            $this->warn('Puede definir columnas en .env: PUNTOVENTA_SYNC_ANITA_CAMPOS_LISTADO (comentario en config/puntoventa_anita.php).');
            $this->warn('Con bridge por archivo SSH, revise el .sql generado en storage/logs al ejecutar la sync.');
        }
        if (! empty($ret['errores'])) {
            foreach (array_slice($ret['errores'], 0, 20) as $err) {
                $this->warn($err);
            }
        }

        return self::SUCCESS;
    }
}
