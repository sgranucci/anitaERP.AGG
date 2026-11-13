<?php

namespace App\Console\Commands\Contable;

use App\Support\Contable\AsientoOrdencompraBackfillSupport;
use Illuminate\Console\Command;

class BackfillAsientoOrdencompraCommand extends Command
{
    protected $signature = 'contable:backfill-asiento-ordencompra
                            {--desde=2026-01-01 : Fecha desde Y-m-d}
                            {--hasta=2026-10-31 : Fecha hasta Y-m-d}
                            {--empresas=1,2,3 : IDs de empresa}
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste ordencompra_id en asiento}';

    protected $description = 'Rellena asiento.ordencompra_id desde recepción o comprobante vinculado; dry-run por defecto';

    public function handle(): int
    {
        $persistir = (bool) $this->option('ejecutar');
        if ($persistir && $this->option('dry-run')) {
            $this->error('No combinar --dry-run con --ejecutar.');

            return self::FAILURE;
        }

        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        $empresas = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $this->option('empresas')),
        )));

        $modo = $persistir ? 'EJECUTAR' : 'DRY-RUN';
        $this->info("contable:backfill-asiento-ordencompra [{$modo}] {$desde} → {$hasta} empresas=".implode(',', $empresas));

        $r = AsientoOrdencompraBackfillSupport::ejecutar($desde, $hasta, $empresas, $persistir);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Candidatos a actualizar', $r['candidatos']],
                ['Desde recepción', $r['desde_recepcion']],
                ['Desde comprobante', $r['desde_comprobante']],
                ['Conflictos OC rec≠cp (omitidos)', $r['conflictos']],
                ['Actualizados', $r['actualizados']],
            ]
        );

        foreach ($r['ejemplos'] as $e) {
            $this->line('  '.json_encode($e, JSON_UNESCAPED_UNICODE));
        }

        if (! $persistir) {
            $this->warn('Sin cambios. Para persistir: php artisan contable:backfill-asiento-ordencompra --ejecutar');
        }

        return self::SUCCESS;
    }
}
