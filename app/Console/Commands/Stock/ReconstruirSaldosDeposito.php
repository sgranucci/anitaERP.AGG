<?php

namespace App\Console\Commands\Stock;

use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use Illuminate\Console\Command;

class ReconstruirSaldosDeposito extends Command
{
    protected $signature = 'stock:reconstruir-saldos {--deposito=}';

    protected $description = 'Reconstruye la tabla articulo_saldo_deposito desde articulo_movimiento. Útil si los saldos quedaron desincronizados (por ejemplo tras imports masivos).';

    public function handle(Articulo_Saldo_DepositoRepositoryInterface $repo): int
    {
        $deposito = $this->option('deposito');
        $depositoId = $deposito !== null ? (int) $deposito : null;

        $this->info($depositoId
            ? "Reconstruyendo saldos para depósito {$depositoId}..."
            : 'Reconstruyendo saldos para todos los depósitos...');

        $count = $repo->reconstruir($depositoId);

        $this->info("Saldos recalculados: {$count}");

        return self::SUCCESS;
    }
}
