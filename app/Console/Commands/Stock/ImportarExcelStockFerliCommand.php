<?php

namespace App\Console\Commands\Stock;

use App\Support\Stock\FerliExcelStockImportPlanner;
use Illuminate\Console\Command;
use Throwable;

/**
 * Reemplazo de stock Ferli desde Excel de /home/sergio/tmp/stock.
 * Default dry-run. Solo anitaERP_l12. No escribe en Anita.
 */
class ImportarExcelStockFerliCommand extends Command
{
    protected $signature = 'stock:importar-excel-ferli
                            {--dir=/home/sergio/tmp/stock : Carpeta de xlsx}
                            {--ejecutar : Persiste CONOT+ALTAP; sin esto solo dry-run}';

    protected $description = 'Ferli L12: planifica (y opcionalmente ejecuta) el reemplazo de stock desde los Excel artesanales';

    public function handle(): int
    {
        $dir = (string) $this->option('dir');
        $ejecutar = (bool) $this->option('ejecutar');

        try {
            FerliExcelStockImportPlanner::assertEntorno();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0');

        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN').' import stock Excel Ferli (solo ERP '.$this->dbName().', sin Anita)');
        $this->line('Carpeta: '.$dir);

        $plan = FerliExcelStockImportPlanner::planificar($dir);

        $this->newLine();
        $this->info('Excel');
        $mventaIds = $plan['altap_mventa_ids'] ?? [];
        $marcasAltap = $mventaIds === []
            ? '(ninguna)'
            : implode(', ', \App\Models\Stock\Mventa::query()->whereIn('id', $mventaIds)->orderBy('id')->pluck('nombre')->all());
        $artsAltap = count($plan['altap_articulo_ids'] ?? []);

        $this->table(['Métrica', 'Valor'], [
            ['Filas leídas (SKU)', $plan['filas_excel']],
            ['Omitidas EN PRODUCCION / rojo', $plan['omitidas']['en_produccion']],
            ['Pares omitidos (producción)', number_format($plan['pares_en_produccion'], 0, ',', '.')],
            ['Lotes/OT protegidos (no CONOT)', $plan['lotes_protegidos_en_produccion_count'] ?? 0],
            ['Omitidas sin depósito', $plan['omitidas']['sin_deposito']],
            ['Omitidas con error de resolución', $plan['omitidas']['errores']],
            ['ALTAP filas OK', $plan['altap_filas']],
            ['ALTAP pares', number_format($plan['altap_pares'], 0, ',', '.')],
            ['ALTAP lotes importados distintos', $plan['altap_lotes']],
            ['ALTAP OT distintas', $plan['altap_ots']],
            ['Artículos ALTAP (CONOT solo estos)', $artsAltap],
            ['Marcas en Excel (info)', $marcasAltap],
        ]);

        $depRows = [];
        foreach ($plan['altap_por_deposito'] as $cod => $pares) {
            $depRows[] = [$cod, number_format($pares, 0, ',', '.')];
        }
        $this->info('ALTAP pares por depósito');
        $this->table(['Depósito', 'Pares'], $depRows);

        $this->newLine();
        $this->info('CONOT / ajuste stock actual (lote > 0, saldo neto ≠ 0; excluye EN PRODUCCION)');
        $this->table(['Métrica', 'Valor'], [
            ['Grupos a anular', $plan['conot']['grupos_count']],
            ['CONOT (saldos positivos)', number_format($plan['conot']['pares_positivos'] ?? $plan['conot']['pares'], 0, ',', '.')],
            ['Grupos CONOT', $plan['conot']['grupos_conot'] ?? ''],
            ['ALTAP ajuste (saldos negativos)', number_format(abs($plan['conot']['pares_negativos'] ?? 0), 0, ',', '.')],
            ['Grupos ajuste', $plan['conot']['grupos_ajuste'] ?? ''],
            ['Neto lote>0 (debe ir a 0)', number_format($plan['conot']['pares'], 0, ',', '.')],
            ['Saldo lote=0 (no se toca)', number_format($plan['conot']['pares_lote_cero'] ?? 0, 0, ',', '.')],
            ['Grupos NO anulados (protegidos EN PRODUCCION)', $plan['conot']['grupos_omitidos_protegidos'] ?? 0],
            ['Pares protegidos (no CONOT)', number_format($plan['conot']['pares_omitidos_protegidos'] ?? 0, 0, ',', '.')],
        ]);
        $conotDep = [];
        foreach ($plan['conot']['por_deposito'] as $cod => $pares) {
            $conotDep[] = [$cod, number_format($pares, 0, ',', '.')];
        }
        $this->table(['Depósito actual', 'Pares'], $conotDep);

        if ($plan['errores'] !== []) {
            $this->newLine();
            $this->warn('Errores de resolución ('.count($plan['errores']).'). Muestra:');
            $muestra = array_slice($plan['errores'], 0, 25);
            $this->table(
                ['Archivo', 'Fila', 'SKU', 'Error'],
                array_map(static fn (array $e) => [
                    $e['archivo'],
                    $e['fila'],
                    $e['sku'],
                    implode('; ', $e['errores']),
                ], $muestra)
            );
        }

        if (($plan['lotes_inventados'] ?? []) !== []) {
            $this->newLine();
            $this->info('Lotes inventados (Lugano sin número en Excel)');
            $this->table(
                ['Archivo', 'Fila', 'SKU', 'Dep', 'Lote', 'Pares'],
                array_map(static fn (array $a) => [
                    $a['archivo'],
                    $a['fila'],
                    $a['sku_excel'],
                    $a['deposito_codigo'],
                    $a['lote'],
                    $a['pares'],
                ], $plan['lotes_inventados'])
            );
        }

        $this->newLine();
        $this->info('Muestra ALTAP (20)');
        $this->table(
            ['Archivo', 'Fila', 'SKU', 'Dep', 'Lote', 'OT', 'Pares'],
            array_map(static fn (array $a) => [
                $a['archivo'],
                $a['fila'],
                $a['sku_excel'],
                $a['deposito_codigo'],
                $a['lote'] ?: '',
                $a['ordentrabajo_codigo'] ?: '',
                $a['pares'],
            ], $plan['altap_muestra'])
        );

        if (! $ejecutar) {
            $this->comment('Sin cambios en la base. Para persistir en L12: mismo comando con --ejecutar.');

            return self::SUCCESS;
        }

        if ($plan['errores'] !== []) {
            $this->error('No persisto: hay '.count($plan['errores']).' filas con error de resolución.');

            return self::FAILURE;
        }

        $this->warn('Persistiendo CONOT+ALTAP en '.$this->dbName().' (sin Anita)...');
        $hecho = FerliExcelStockImportPlanner::ejecutar($plan);
        $this->info('Grabado CONOT/ajuste: '.$hecho['conot'].' | ALTAP: '.$hecho['altap']);

        $this->info('Reconstruyendo articulo_saldo_deposito...');
        $this->call('stock:reconstruir-saldos');

        return self::SUCCESS;
    }

    private function dbName(): string
    {
        return (string) config('database.connections.'.config('database.default').'.database');
    }
}
