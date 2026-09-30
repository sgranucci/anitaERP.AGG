<?php

namespace App\Console\Commands;

use App\Models\Ventas\LocalVenta;
use App\Services\Ventas\FacturacionLocal\MarketplaceAnitaSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;

class SincronizarMarketplaceDesdeAnitaLocal extends Command
{
    protected $signature = 'facturacion-local:sync-marketplace
                            {--local= : ID de local_venta (opcional, usa el bridge de ese local)}
                            {--ejecutar : Persiste marketplace y articulo_marketplace (sin esto = dry-run)}';

    protected $description = 'Trae marketplace y stkmplace del Anita Local hacia anitaERP. No escribe en el bridge. Dry-run por defecto.';

    public function handle(MarketplaceAnitaSyncService $sync): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->warn('Solo aplica en Calzados Ferli.');

            return self::SUCCESS;
        }

        $localId = (int) $this->option('local');
        $local = $localId > 0 ? LocalVenta::query()->find($localId) : null;
        if ($localId > 0 && ! $local) {
            $this->error('No existe el local '.$localId.'.');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        if (! $ejecutar) {
            $this->info('Modo dry-run (no escribe). Use --ejecutar para persistir.');
        }

        $resultado = $sync->sincronizar($local, $ejecutar);
        if (! empty($resultado['error'])) {
            $this->error((string) $resultado['error']);

            return self::FAILURE;
        }

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Marketplaces en el bridge', $resultado['maestros_bridge']],
                ['Marketplaces a crear', $resultado['maestros_nuevos']],
                ['Marketplaces a actualizar nombre', $resultado['maestros_actualizados']],
                ['Asignaciones stkmplace', $resultado['asignaciones_bridge']],
                ['Asignaciones a crear', $resultado['asignaciones_nuevas']],
                ['Asignaciones a actualizar combinación', $resultado['asignaciones_actualizadas']],
                ['Sin artículo en el ERP', $resultado['sin_articulo']],
                ['Combinación Anita sin match', $resultado['sin_combinacion']],
                ['Omitidos (código excluido)', $resultado['omitidos'] ?? 0],
            ]
        );

        if (($resultado['skus_sin_articulo'] ?? []) !== []) {
            $this->warn('SKUs del bridge sin artículo ERP (muestra): '.implode(', ', $resultado['skus_sin_articulo']));
        }
        foreach ($resultado['errores'] ?? [] as $err) {
            $this->warn($err);
        }

        if (! $ejecutar && (($resultado['maestros_nuevos'] ?? 0) > 0 || ($resultado['asignaciones_nuevas'] ?? 0) > 0)) {
            $this->warn('Revise el impacto y autorice --ejecutar para persistir.');
        }

        return self::SUCCESS;
    }
}
