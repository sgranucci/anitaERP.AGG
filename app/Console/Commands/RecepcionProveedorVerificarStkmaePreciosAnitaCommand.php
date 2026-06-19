<?php

namespace App\Console\Commands;

use App\Services\Stock\RecepcionProveedorStkmaePrecioAnitaVerificacionService;
use Illuminate\Console\Command;

class RecepcionProveedorVerificarStkmaePreciosAnitaCommand extends Command
{
    protected $signature = 'recepcion-proveedor:verificar-stkmae-precios-anita
                            {--incluir-importadas : Incluye recepciones ANITA_IMPORT}
                            {--todas : Verifica todas las confirmadas, no solo las con sync_at}';

    protected $description = 'Compara stkm_pre_compra3/fe_ult_compra en Anita stkmae vs última recepción ERP sincronizada';

    public function handle(RecepcionProveedorStkmaePrecioAnitaVerificacionService $service): int
    {
        $resultado = $service->verificar([
            'incluir_importadas' => (bool) $this->option('incluir-importadas'),
            'solo_sync_at' => ! (bool) $this->option('todas'),
        ]);

        $this->info('Recepciones ERP analizadas: '.$resultado['recepciones']);
        $this->info('Artículos distintos en recepciones: '.$resultado['articulos_erp']);

        $this->table(['Métrica', 'Cantidad'], [
            ['stkm_pre_compra3 OK (tolerancia ±0.02)', $resultado['ok_precio3']],
            ['stkm_fe_ult_compra OK', $resultado['ok_fecha']],
            ['Sin fila en stkmae', count($resultado['sin_stkmae'])],
            ['Diferencias de precio', count($resultado['diferencias_precio'])],
            ['Diferencias de fecha', count($resultado['diferencias_fecha'])],
        ]);

        if ($resultado['sin_stkmae'] !== []) {
            $this->warn('Artículos sin fila stkmae:');
            $this->table(
                ['Código Anita', 'Recepción', 'COM', 'Precio ERP esperado'],
                array_map(static fn (array $r) => [
                    $r['codigo_anita'],
                    $r['recepcion_id'],
                    $r['numerorecepcion'],
                    number_format($r['precio_pesos'], 4, '.', ''),
                ], $resultado['sin_stkmae'])
            );
        }

        if ($resultado['diferencias_precio'] !== []) {
            $this->error('Diferencias stkm_pre_compra3 (última recepción ERP vs Anita):');
            $this->table(
                ['Código', 'COM', 'Esperado ERP', 'Anita pre3', 'pre2', 'pre1', 'Diff'],
                array_map(static fn (array $r) => [
                    $r['codigo_anita'],
                    $r['numerorecepcion'],
                    number_format($r['precio_pesos'], 4, '.', ''),
                    number_format($r['precio_anita'], 4, '.', ''),
                    number_format($r['pre_compra2_anita'], 4, '.', ''),
                    number_format($r['pre_compra1_anita'], 4, '.', ''),
                    number_format($r['diferencia'], 4, '.', ''),
                ], $resultado['diferencias_precio'])
            );
        }

        if ($resultado['diferencias_fecha'] !== []) {
            $this->warn('Diferencias stkm_fe_ult_compra:');
            $this->table(
                ['Código', 'COM', 'Fecha ERP', 'Fecha Anita stkmae'],
                array_map(static fn (array $r) => [
                    $r['codigo_anita'],
                    $r['numerorecepcion'],
                    $r['fecha_anita'],
                    $r['fecha_anita_stk'],
                ], $resultado['diferencias_fecha'])
            );
        }

        $hayProblemas = $resultado['sin_stkmae'] !== []
            || $resultado['diferencias_precio'] !== [];

        if (! $hayProblemas) {
            $this->info('Verificación OK: todos los últimos precios de compra coinciden con Anita stkmae.');
        }

        return $hayProblemas ? self::FAILURE : self::SUCCESS;
    }
}
