<?php

namespace App\Console\Commands;

use App\Services\Ventas\Tiendanube\TiendanubePedidoSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\Tiendanube\TiendanubeApiHealthSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Sync programado de pedidos pagados Tiendanube → staging anitaERP.
 */
class TiendanubeSincronizarPedidosCommand extends Command
{
    protected $signature = 'tiendanube:sincronizar-pedidos
                            {--dias= : Ventana hacia atrás (default config)}
                            {--desde= : Fecha Y-m-d (override)}
                            {--hasta= : Fecha Y-m-d (override)}
                            {--sin-mail : No alerta mail si falla auth}';

    protected $description = 'Sincroniza pedidos pagados de Tiendanube (Ferli y Boaonda).';

    public function handle(
        TiendanubePedidoSyncService $sync,
        TiendanubeApiHealthSupport $health,
    ): int {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->warn('Solo aplica en entorno Ferli.');

            return self::SUCCESS;
        }

        $dias = (int) ($this->option('dias') ?: config('tiendanube.sync_cron_dias', 7));
        $dias = max(1, min(60, $dias));

        $desde = trim((string) ($this->option('desde') ?: ''));
        $hasta = trim((string) ($this->option('hasta') ?: ''));
        if ($desde === '') {
            $desde = Carbon::now()->subDays($dias)->format('Y-m-d');
        }
        if ($hasta === '') {
            $hasta = Carbon::now()->format('Y-m-d');
        }

        $this->info("Sincronizando Tiendanube {$desde} → {$hasta}…");

        try {
            $resultado = $sync->sincronizarRango($desde, $hasta);
        } catch (\Throwable $e) {
            TiendanubeApiHealthSupport::marcarSyncError($e->getMessage(), 0);
            $this->error($e->getMessage());
            if (! (bool) $this->option('sin-mail')) {
                $health->notificarAuthInvalidaSiCorresponde();
            }

            return self::FAILURE;
        }

        if (! ($resultado['ok'] ?? false)) {
            $this->error($resultado['error'] ?? 'Error de sync');
            if (! (bool) $this->option('sin-mail')
                && TiendanubeApiHealthSupport::esErrorAuth((int) ($resultado['status'] ?? 0), $resultado['error'] ?? '')) {
                $enviado = $health->notificarAuthInvalidaSiCorresponde();
                $this->comment($enviado ? 'Alerta mail enviada.' : 'Alerta mail omitida.');
            }

            return self::FAILURE;
        }

        $this->info(sprintf(
            'OK: %d nuevos, %d actualizados, %d páginas. SKUs rematch: %d.',
            (int) $resultado['creados'],
            (int) $resultado['actualizados'],
            (int) $resultado['paginas'],
            (int) ($resultado['skus_rematch'] ?? 0)
        ));
        if (! empty($resultado['advertencias'])) {
            $this->warn((string) $resultado['advertencias']);
        }

        return self::SUCCESS;
    }
}
