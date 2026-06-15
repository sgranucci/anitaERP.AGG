<?php

namespace App\Console\Commands\Contable;

use App\Repositories\Contable\Cuentacontable_Saldo_MesRepositoryInterface;
use Illuminate\Console\Command;

class ReconstruirSaldosCuentaMes extends Command
{
    protected $signature = 'contable:reconstruir-saldos-cuenta-mes {--empresa=}';

    protected $description = 'Reconstruye cuentacontable_saldo_mes desde asiento + asiento_movimiento. Ejecutar con observer deshabilitado o tras imports masivos.';

    public function handle(Cuentacontable_Saldo_MesRepositoryInterface $repo): int
    {
        $empresa = $this->option('empresa');
        $empresaId = $empresa !== null && $empresa !== '' ? (int) $empresa : null;

        $this->info($empresaId
            ? "Reconstruyendo saldos mensuales para empresa {$empresaId}..."
            : 'Reconstruyendo saldos mensuales para todas las empresas...');

        $count = $repo->reconstruir($empresaId);

        $this->info("Filas de saldo mensual recalculadas: {$count}");

        return self::SUCCESS;
    }
}
