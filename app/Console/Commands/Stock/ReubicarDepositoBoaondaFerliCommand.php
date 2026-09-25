<?php

namespace App\Console\Commands\Stock;

use App\Support\Stock\FerliBoaondaReubicarDepositoSupport;
use Illuminate\Console\Command;
use Throwable;

/**
 * Reubica solo depósito del stock Boaonda según Excel artesanal.
 * Default dry-run. Solo anitaERP_l12.
 */
class ReubicarDepositoBoaondaFerliCommand extends Command
{
    protected $signature = 'stock:reubicar-deposito-boaonda-ferli
                            {--excel=/home/sergio/tmp/BOA ONDA INYECTADOS (1).xlsx : Excel artesanal Boaonda}
                            {--ejecutar : Persiste salida+entrada por depósito; sin esto solo dry-run}';

    protected $description = 'Ferli L12: reubica depósito Boaonda según Excel (solo depósito)';

    public function handle(): int
    {
        $excel = (string) $this->option('excel');
        $ejecutar = (bool) $this->option('ejecutar');

        try {
            FerliBoaondaReubicarDepositoSupport::assertEntorno();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0');

        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN').' reubicar depósito Boaonda');
        $this->line('Excel: '.$excel);

        $plan = FerliBoaondaReubicarDepositoSupport::planificar($excel);

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Targets lote+artículo Excel', $plan['targets']],
                ['Buckets ya en depósito OK', $plan['buckets_ok']],
                ['Buckets saldo sin target', $plan['buckets_sin_target']],
                ['Buckets a mover', $plan['buckets_mover']],
                ['Pares a reubicar', number_format($plan['pares_mover'], 0, ',', '.')],
            ]
        );

        $rutas = [];
        foreach ($plan['rutas'] as $k => $p) {
            $rutas[] = [$k, number_format($p, 0, ',', '.')];
        }
        if ($rutas !== []) {
            $this->table(['Ruta', 'Pares'], $rutas);
        }

        if (! $ejecutar) {
            $this->warn('Dry-run: no se persistió nada. Relanzá con --ejecutar para aplicar.');

            return self::SUCCESS;
        }

        $res = FerliBoaondaReubicarDepositoSupport::ejecutar($plan);
        $this->info(sprintf(
            'Listo: %d salidas + %d entradas / %s pares',
            $res['salidas'],
            $res['entradas'],
            number_format($res['pares'], 0, ',', '.')
        ));

        return self::SUCCESS;
    }
}
