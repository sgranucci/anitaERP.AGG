<?php

namespace App\Console\Commands;

use App\Support\Stock\ArticuloSkuMapeoSupport;
use Illuminate\Console\Command;

/**
 * Recodifica SKU de artículos no facturables que contienen "M" a una serie numérica nueva.
 * Persiste el mapeo en articulo_sku_mapeo para imports/migraciones futuras.
 */
class RecodificarSkuNofacturableConMCommand extends Command
{
    protected $signature = 'stock:recodificar-sku-nofacturable-con-m
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste SKU nuevos + filas en articulo_sku_mapeo}
                            {--base= : Primer número a usar (default '.ArticuloSkuMapeoSupport::BASE_SKU_NUMERICA.')}
                            {--csv= : Ruta CSV preview (default storage/app/sku_mapeo_nofacturable_m_preview.csv)}
                            {--mostrar=20 : Filas de muestra en consola}';

    protected $description = 'Recodifica SKU no facturables con M a numéricos nuevos y guarda mapeo sku_anterior→sku_nuevo';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        if ($ejecutar && $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        if (! $ejecutar) {
            $this->warn('Modo dry-run (pase --ejecutar para persistir).');
        }

        $baseOpt = $this->option('base');
        $base = $baseOpt !== null && $baseOpt !== ''
            ? (int) $baseOpt
            : ArticuloSkuMapeoSupport::BASE_SKU_NUMERICA;

        if ($base < 1) {
            $this->error('La base debe ser un entero positivo.');

            return self::FAILURE;
        }

        try {
            $resultado = ArticuloSkuMapeoSupport::recodificarNofacturablesConM($ejecutar, $base);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($resultado['mensaje']);

        $csv = (string) ($this->option('csv') ?: storage_path('app/sku_mapeo_nofacturable_m_preview.csv'));
        ArticuloSkuMapeoSupport::exportarCsvPreview($resultado['filas'], $csv);
        $this->line('CSV: '.$csv);

        $mostrar = max(0, (int) $this->option('mostrar'));
        if ($mostrar > 0 && $resultado['filas'] !== []) {
            $muestra = array_slice($resultado['filas'], 0, $mostrar);
            $this->table(
                ['ID', 'SKU anterior', 'SKU nuevo', 'Descripción'],
                array_map(
                    static fn (array $f) => [
                        $f['articulo_id'],
                        $f['sku_anterior'],
                        $f['sku_nuevo'],
                        mb_substr($f['descripcion'], 0, 50),
                    ],
                    $muestra
                )
            );
            if (count($resultado['filas']) > $mostrar) {
                $this->line('… y '.(count($resultado['filas']) - $mostrar).' más (ver CSV).');
            }
        }

        return self::SUCCESS;
    }
}
