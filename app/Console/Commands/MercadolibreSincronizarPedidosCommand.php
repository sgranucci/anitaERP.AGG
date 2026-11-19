<?php

namespace App\Console\Commands;

use App\Services\Ventas\Mercadolibre\MercadolibrePedidoSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MercadolibreSincronizarPedidosCommand extends Command
{
    protected $signature = 'mercadolibre:sincronizar-pedidos
                            {--dias= : Ventana hacia atrás (default config)}
                            {--desde= : Fecha Y-m-d}
                            {--hasta= : Fecha Y-m-d}';

    protected $description = 'Sincroniza órdenes pagadas de Mercado Libre (Ferli).';

    public function handle(MercadolibrePedidoSyncService $sync): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->warn('Solo aplica en entorno Ferli.');

            return self::SUCCESS;
        }

        $dias = (int) ($this->option('dias') ?: config('mercadolibre.sync_cron_dias', 7));
        $dias = max(1, min(60, $dias));
        $desde = trim((string) ($this->option('desde') ?: ''));
        $hasta = trim((string) ($this->option('hasta') ?: ''));
        if ($desde === '') {
            $desde = Carbon::now()->subDays($dias)->format('Y-m-d');
        }
        if ($hasta === '') {
            $hasta = Carbon::now()->format('Y-m-d');
        }

        $this->info("Sincronizando Mercado Libre {$desde} → {$hasta}…");
        $resultado = $sync->sincronizarRango($desde, $hasta);
        if (! ($resultado['ok'] ?? false)) {
            $this->error($resultado['error'] ?? 'Error');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'OK: %d nuevos, %d actualizados, %d páginas.',
            (int) $resultado['creados'],
            (int) $resultado['actualizados'],
            (int) $resultado['paginas']
        ));

        return self::SUCCESS;
    }
}
