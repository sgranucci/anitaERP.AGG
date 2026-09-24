<?php

namespace App\Console\Commands;

use App\Support\Stock\ArticuloStkleyAnitaBridgeSupport;
use Illuminate\Console\Command;

/**
 * Importa Anita stkley línea 100 → articulo.descripcion_exportacion.
 */
class SincronizarDescripcionExportacionStkleyCommand extends Command
{
    protected $signature = 'stock:sincronizar-descripcion-exportacion-stkley
                            {--dry-run : Solo muestra impacto, no graba}
                            {--ejecutar : Persiste en articulo.descripcion_exportacion}';

    protected $description = 'Sincroniza descripción de exportación desde Anita stkley línea 100';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = (bool) $this->option('dry-run') || ! $ejecutar;

        if ($ejecutar && $this->option('dry-run')) {
            $this->error('Usá --dry-run o --ejecutar, no ambos.');

            return self::FAILURE;
        }

        $this->info($dryRun
            ? 'Modo análisis (--dry-run): no se graba.'
            : 'Modo ejecución: se actualizará articulo.descripcion_exportacion.');

        try {
            $r = ArticuloStkleyAnitaBridgeSupport::sincronizarDesdeAnita(! $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Filas Anita línea 100 (con texto)', $r['total_anita']],
                ['A actualizar / actualizados', $r['actualizados']],
                ['Sin cambio', $r['sin_cambio']],
                ['Sin match ERP', $r['sin_match']],
            ]
        );

        if ($r['detalle_actualizados'] !== []) {
            $this->line('Muestra cambios (máx. 20):');
            foreach (array_slice($r['detalle_actualizados'], 0, 20) as $d) {
                $this->line('  '.$d['sku'].': "'.$d['antes'].'" → "'.$d['despues'].'"');
            }
        }

        if ($dryRun) {
            $this->warn('Para persistir: php artisan stock:sincronizar-descripcion-exportacion-stkley --ejecutar');
        }

        return self::SUCCESS;
    }
}
