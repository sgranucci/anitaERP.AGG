<?php

namespace App\Console\Commands\Stock;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\CrownArticuloTipoUsoAsignacionSupport;
use Illuminate\Console\Command;

class AsignarTipoUsoArticuloCrownCommand extends Command
{
    protected $signature = 'stock:asignar-tipo-uso-articulo-crown
                            {--ejecutar : Persiste maestros y asignación (sin esto solo dry-run)}';

    protected $description = 'Crown: da de alta tipos/usos de artículo y asigna por SKU I* (insumo) y categoría';

    public function handle(): int
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->error('Solo corre en EMPRESA=CROWN (actual: '.EntornoEmpresaSupport::codigo().').');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        if (! $ejecutar) {
            $this->warn('Dry-run: no se graba. Use --ejecutar para persistir.');
        }

        try {
            $r = CrownArticuloTipoUsoAsignacionSupport::asegurarMaestrosYAsignar($ejecutar);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Tipos: creados=%d actualizados=%d | Usos: creados=%d actualizados=%d',
            $r['tipos_creados'],
            $r['tipos_actualizados'],
            $r['usos_creados'],
            $r['usos_actualizados']
        ));
        $this->info(sprintf(
            'Artículos con cambio de tipo=%d uso=%d | Categorías tipo=%d',
            $r['articulos_tipo'],
            $r['articulos_uso'],
            $r['categorias_tipo']
        ));

        $this->newLine();
        $this->comment('Distribución tipo (abrev):');
        foreach ($r['por_tipo'] as $k => $n) {
            $this->line("  {$k}: {$n}");
        }
        $this->comment('Distribución uso:');
        foreach ($r['por_uso'] as $k => $n) {
            $this->line("  {$k}: {$n}");
        }

        if ($ejecutar) {
            $this->info('Persistido.');
        }

        return self::SUCCESS;
    }
}
