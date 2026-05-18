<?php

namespace App\Console\Commands;

use App\Services\Stock\FormulaArticuloVinculoService;
use Illuminate\Console\Command;

class VincularFormulaArticuloConArticulos extends Command
{
    protected $signature = 'formula-articulo:vincular-articulos-por-codigo
                            {--dry-run : Simula sin grabar cambios}';

    protected $description = 'Vincula formula_articulo y articulo según código de fórmula → SKU (ej. 365 → V0365).';

    public function handle(FormulaArticuloVinculoService $vinculo): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Modo simulación (--dry-run): no se graban cambios.');
        }

        $ret = $vinculo->vincularPorCodigoSku($dryRun);

        $this->info('Fórmulas procesadas: '.$ret['formulas_procesadas']);
        $this->info('Fórmulas vinculadas/actualizadas: '.$ret['formulas_vinculadas']);
        $this->info('Artículos con formula actualizada: '.$ret['articulos_actualizados']);
        $this->info('Artículos corregidos por SKU: '.$ret['articulos_corregidos']);
        $this->info('Artículos desvinculados (formula incorrecta): '.$ret['articulos_desvinculados']);

        foreach ($ret['sin_articulo'] as $msg) {
            $this->warn($msg);
        }
        foreach ($ret['advertencias'] as $msg) {
            $this->comment($msg);
        }

        return self::SUCCESS;
    }
}
