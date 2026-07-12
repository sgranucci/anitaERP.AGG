<?php

namespace App\Console\Commands;

use App\Services\Stock\RecepcionProveedorCorregirDepositoAnitaImportService;
use Illuminate\Console\Command;

class RecepcionProveedorCorregirDepositoAnitaImportCommand extends Command
{
    protected $signature = 'recepcion-proveedor:corregir-deposito-anita-import
                            {--id= : Solo una recepción por ID ERP}
                            {--articulo-id= : Solo líneas de un artículo}
                            {--dry-run : Lista cambios sin grabar}';

    protected $description = 'Corrige deposito_id en líneas COM importadas desde Anita (solo recepcion_proveedor_articulo; no toca articulo_movimiento)';

    public function handle(RecepcionProveedorCorregirDepositoAnitaImportService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $recepcionId = $this->option('id') ? (int) $this->option('id') : null;
        $articuloId = $this->option('articulo-id') ? (int) $this->option('articulo-id') : null;

        if ($dryRun) {
            $this->warn('Dry-run: no se modificará recepcion_proveedor_articulo.');
        }

        $stats = $service->ejecutar(
            $dryRun,
            $recepcionId,
            $articuloId,
            function ($recepcion, \Throwable $e) {
                $this->error(
                    'Recepción '.$recepcion->id.' COM '.$recepcion->numerorecepcion.': '.$e->getMessage()
                );
            }
        );

        $this->table(['Métrica', 'Cantidad'], [
            ['Recepciones candidatas', $stats['candidatas']],
            ['Líneas revisadas', $stats['lineas_revisadas']],
            ['Líneas actualizadas', $stats['lineas_actualizadas']],
            ['Sin recv_deposito Anita', $stats['sin_deposito_anita']],
            ['Sin depmae ERP para código', $stats['sin_mapeo_erp']],
            ['Errores', $stats['errores']],
        ]);

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
