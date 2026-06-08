<?php

namespace App\Console\Commands;

use App\Services\Stock\ArticuloUsoInsumoFormulaService;
use App\Support\Stock\ArticuloUsoInsumoSupport;
use Illuminate\Console\Command;

class VerificarActualizarArticuloUsoInsumoFormula extends Command
{
    protected $signature = 'articulo:verificar-uso-insumo-formula
                            {--aplicar : Asigna uso «INSUMO GASTRONOMIA» a los artículos pendientes}
                            {--limite=0 : Máximo de artículos a actualizar (0 = sin límite)}
                            {--mostrar-insumos-sin-formula=20 : Cantidad de insumos catalogados sin fórmula a listar (0 = omitir)}';

    protected $description = 'Verifica y actualiza articulo.usoarticulo_id en artículos que figuran como insumo en fórmulas (formula_articulo_hijo, incluye subfórmulas).';

    public function handle(ArticuloUsoInsumoFormulaService $service): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $limite = max(0, (int) $this->option('limite'));

        if (! $aplicar) {
            $this->warn('Modo verificación: no se graban cambios. Use --aplicar para actualizar.');
        } elseif ($limite > 0) {
            $this->comment('Límite de actualización: '.$limite.' artículo(s).');
        }

        $ret = $service->verificarYActualizar($aplicar, $limite);

        if ($ret['sin_uso_insumo_maestro']) {
            $this->error('Abortado: falta el uso maestro «'.ArticuloUsoInsumoSupport::NOMBRE_USO_INSUMO.'».');

            return self::FAILURE;
        }

        $this->info('Uso destino: '.ArticuloUsoInsumoSupport::NOMBRE_USO_INSUMO.' (id '.(int) $ret['uso_insumo_id'].')');
        $this->info('Artículos en fórmulas (insumos): '.(int) $ret['insumos_en_formulas']);
        $this->info('Ya catalogados como insumo: '.(int) $ret['ya_catalogados']);
        $this->info('Pendientes de actualizar: '.count($ret['pendientes']));

        if ($aplicar) {
            $this->info('Actualizados: '.(int) $ret['actualizados']);
        }

        foreach ($ret['advertencias'] as $msg) {
            $this->warn($msg);
        }

        if ($ret['pendientes'] !== []) {
            $this->newLine();
            $this->table(
                ['ID', 'SKU', 'Descripción', 'Uso actual'],
                array_map(fn (array $row) => [
                    $row['articulo_id'],
                    $row['sku'],
                    $row['descripcion'],
                    $row['uso_actual_nombre'] !== '' ? $row['uso_actual_nombre'] : '—',
                ], $ret['pendientes']),
            );
        } else {
            $this->comment('Todos los insumos de fórmulas tienen el uso correcto.');
        }

        $mostrarSinFormula = (int) $this->option('mostrar-insumos-sin-formula');
        if ($mostrarSinFormula > 0) {
            $sinFormula = $service->insumosSinFormula($mostrarSinFormula);
            if ($sinFormula !== []) {
                $this->newLine();
                $this->comment('Informativo: artículos con uso insumo que no figuran en fórmulas (hasta '.$mostrarSinFormula.'):');
                $this->table(
                    ['ID', 'SKU', 'Descripción'],
                    array_map(fn (array $row) => [
                        $row['articulo_id'],
                        $row['sku'],
                        $row['descripcion'],
                    ], $sinFormula),
                );
            }
        }

        return self::SUCCESS;
    }
}
