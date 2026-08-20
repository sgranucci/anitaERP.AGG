<?php

namespace App\Console\Commands;

use App\Support\Stock\LimpiezaSkuAlternativoCargaSupport;
use Illuminate\Console\Command;

class CargarSkuAlternativoLimpiezaCommand extends Command
{
    protected $signature = 'stock:cargar-sku-alternativo-limpieza
                            {--dry-run : Informe sin grabar}
                            {--ejecutar : Crea insumos I8xxx y graba skualternativo en LIM*}';

    protected $description = 'Carga SKU alternativo en artículos de limpieza (LIM*) del ERP y da de alta el insumo correspondiente.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ejecutar = (bool) $this->option('ejecutar');

        if ($dryRun && $ejecutar) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $ejecutar) {
            $this->warn('Sin --ejecutar no se escribe. Use --dry-run para ver el impacto.');
            $dryRun = true;
        }

        $ret = LimpiezaSkuAlternativoCargaSupport::ejecutar(! $dryRun);

        $this->info('LIM* ya vinculados a insumo: '.$ret['ya_vinculados']);
        $this->info('Pendientes: '.count($ret['pendientes']));
        if (! $dryRun) {
            $this->info('Insumos creados: '.$ret['creados']);
            $this->info('LIM* actualizados: '.$ret['actualizados']);
        }

        if ($ret['pendientes'] !== []) {
            $this->newLine();
            $this->table(
                ['LIM', 'Descripción', 'Alt actual', 'Insumo', 'SKU alt nuevo'],
                array_map(static fn (array $r): array => [
                    $r['sku'],
                    mb_substr((string) $r['descripcion'], 0, 40),
                    $r['skualternativo_actual'] !== '' ? $r['skualternativo_actual'] : '—',
                    $r['insumo_sku'],
                    $r['skualternativo_nuevo'],
                ], $ret['pendientes'])
            );
        }

        if ($dryRun) {
            $this->warn('Dry-run: no se persistió nada. Para grabar: php artisan stock:cargar-sku-alternativo-limpieza --ejecutar');
        }

        return self::SUCCESS;
    }
}
