<?php

namespace App\Console\Commands;

use App\Services\Stock\RecepcionProveedorCorregirMonedaTitoAnitaService;
use Illuminate\Console\Command;

class RecepcionProveedorCorregirMonedaTitoAnitaCommand extends Command
{
    protected $signature = 'recepcion-proveedor:corregir-moneda-tito-anita
                            {--desde=2026-08-01 : Fecha desde (YYYY-MM-DD). No puede ser anterior a 2026-08-01}
                            {--hasta=2026-08-31 : Fecha hasta (YYYY-MM-DD)}
                            {--dry-run : Lista cambios sin grabar}';

    protected $description = 'Alinea moneda/cotización de COM TITO con Anita recepmov. Solo agosto 2026; no toca julio.';

    public function handle(RecepcionProveedorCorregirMonedaTitoAnitaService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $desde = (string) $this->option('desde');
        $hasta = (string) $this->option('hasta');

        if ($dryRun) {
            $this->warn('Dry-run: no se modificará recepcion_proveedor ni recepcion_proveedor_articulo.');
        }

        $this->info('Período '.$desde.' a '.$hasta.' (piso '.RecepcionProveedorCorregirMonedaTitoAnitaService::FECHA_PISO.'; julio excluido).');

        try {
            $stats = $service->ejecutar(
                $desde,
                $hasta,
                $dryRun,
                function ($recepcion, \Throwable $e) {
                    $this->error(
                        'Recepción '.$recepcion->id.' COM '.$recepcion->numerorecepcion.': '.$e->getMessage()
                    );
                }
            );
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['Recepciones candidatas', $stats['candidatas']],
            ['Líneas revisadas', $stats['lineas_revisadas']],
            ['Líneas actualizadas', $stats['lineas_actualizadas']],
            ['Cabeceras actualizadas', $stats['cabeceras_actualizadas']],
            ['Sin recepmov Anita', $stats['sin_recepmov']],
            ['Sin línea Anita para el SKU', $stats['sin_linea_anita']],
            ['Omitidas julio (guarda)', $stats['omitidas_julio']],
            ['Sin cambio', $stats['sin_cambio']],
            ['Errores', $stats['errores']],
        ]);

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
