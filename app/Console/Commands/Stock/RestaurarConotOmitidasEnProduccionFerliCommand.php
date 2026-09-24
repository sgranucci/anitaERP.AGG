<?php

namespace App\Console\Commands\Stock;

use App\Support\Stock\FerliExcelStockRestaurarConotOmitidasSupport;
use Illuminate\Console\Command;
use Throwable;

/**
 * Revierte CONOT del import Excel sobre lotes/OT que el Excel tenía EN PRODUCCION
 * (omitidos de ALTAP) y quedaron en saldo 0. Default dry-run. Solo anitaERP_l12.
 */
class RestaurarConotOmitidasEnProduccionFerliCommand extends Command
{
    protected $signature = 'stock:restaurar-conot-omitidas-ferli
                            {--dir=/home/sergio/tmp/stock : Carpeta de xlsx}
                            {--ejecutar : Persiste altas de restauración; sin esto solo dry-run}';

    protected $description = 'Ferli L12: restaura stock anulado por CONOT en lotes EN PRODUCCION omitidos del Excel';

    public function handle(): int
    {
        $dir = (string) $this->option('dir');
        $ejecutar = (bool) $this->option('ejecutar');

        try {
            FerliExcelStockRestaurarConotOmitidasSupport::assertEntorno();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0');

        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN').' restaurar CONOT omitidos EN PRODUCCION');
        $this->line('Carpeta: '.$dir);

        $plan = FerliExcelStockRestaurarConotOmitidasSupport::planificar($dir);

        $this->table(['Métrica', 'Valor'], [
            ['Lotes/OT EN PRODUCCION en Excel', count($plan['lotes_excel'])],
            ['Movimientos CONOT a restaurar', $plan['movimientos']],
            ['Pares a restaurar', number_format($plan['pares'], 0, ',', '.')],
        ]);

        if ($plan['candidatos'] !== []) {
            $muestra = array_slice($plan['candidatos'], 0, 30);
            $this->info('Muestra (hasta 30)');
            $this->table(
                ['CONOT id', 'Lote', 'SKU', 'Dep', 'Pares', 'OT'],
                array_map(static fn (array $c) => [
                    $c['conot_id'],
                    $c['lote'],
                    $c['sku'],
                    $c['deposito_codigo'],
                    number_format($c['pares'], 0, ',', '.'),
                    $c['ordentrabajo_id'] ?: '',
                ], $muestra)
            );
        }

        if (! $ejecutar) {
            $this->warn('Dry-run: no se escribió nada. Pasá --ejecutar para persistir.');

            return self::SUCCESS;
        }

        $res = FerliExcelStockRestaurarConotOmitidasSupport::ejecutar($plan);
        $this->info('Restaurados: '.$res['restaurados'].' movimientos / '.number_format($res['pares'], 0, ',', '.').' pares');

        return self::SUCCESS;
    }
}
