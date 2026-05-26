<?php

namespace App\Console\Commands;

use App\Services\Ventas\CategoriafidelidadGastronomiaAnitaSyncService;
use Illuminate\Console\Command;

class SincronizarCategoriafidelidadGastronomiaDesdeAnita extends Command
{
    protected $signature = 'categoria-fidelidad-gastronomia:sincronizar-anita
                            {--codigo= : Importar solo una categoría por clcat_categoria Anita}';

    protected $description = 'Importa categorías de fidelidad gastronomía desde Anita (clicat + clicatart) con mapeo SKU → articulo_id.';

    public function handle(CategoriafidelidadGastronomiaAnitaSyncService $sync): int
    {
        $codigo = $this->option('codigo');

        try {
            if ($codigo !== null && $codigo !== '') {
                $this->info("Importando categoría Anita {$codigo}…");
                $estado = $sync->traerCategoriaDeAnita((int) $codigo);
                $this->info("Resultado categoría: {$estado}");
                $this->info('Para importar artículos de la categoría, ejecute la sincronización completa.');

                return self::SUCCESS;
            }

            $this->info('Sincronizando categorías de fidelidad desde Anita…');
            $ret = $sync->sincronizarConAnita();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "Categorías en Anita: {$ret['en_anita_categorias']}; importadas: {$ret['importados']}; actualizadas: {$ret['actualizados']}; omitidas: {$ret['omitidos']}."
        );
        $this->info(
            "Artículos en Anita: {$ret['en_anita_articulos']}; vinculados: {$ret['articulos_importados']}; omitidos: {$ret['articulos_omitidos']}."
        );
        $this->info(
            "Entregas en Anita (fecha >= ".config('categoriafidelidad_gastronomia_anita.fecha_desde')."): {$ret['en_anita_entregas']}; importadas: {$ret['entregas_importadas']}; actualizadas: {$ret['entregas_actualizadas']}; omitidas: {$ret['entregas_omitidas']}."
        );
        foreach ($ret['errores'] as $w) {
            $this->warn($w);
        }

        return self::SUCCESS;
    }
}
