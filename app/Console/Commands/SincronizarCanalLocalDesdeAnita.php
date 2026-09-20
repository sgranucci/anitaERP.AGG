<?php

namespace App\Console\Commands;

use App\Models\Ventas\LocalVenta;
use App\Services\Ventas\FacturacionLocal\ArticuloCanalSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;

class SincronizarCanalLocalDesdeAnita extends Command
{
    protected $signature = 'facturacion-local:sync-canal
                            {--local= : ID de local_venta (opcional, usa bridge del local)}
                            {--ejecutar : Persiste altas faltantes + asignaciones articulo_canal (sin esto = dry-run)}';

    protected $description = 'Desde stkmae Anita Local: crea artículos ERP faltantes y asigna canal LOCAL. Dry-run por defecto.';

    public function handle(ArticuloCanalSyncService $sync): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->warn('Solo aplica en Calzados Ferli.');

            return self::SUCCESS;
        }

        $localId = (int) $this->option('local');
        $local = $localId > 0 ? LocalVenta::query()->find($localId) : null;
        $ejecutar = (bool) $this->option('ejecutar');

        if (! $ejecutar) {
            $this->info('Modo dry-run (no escribe). Use --ejecutar para persistir.');
        }

        $resultado = $sync->sincronizar($local, $ejecutar);
        if (! empty($resultado['error'])) {
            $this->error($resultado['error']);

            return self::FAILURE;
        }

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['SKUs Anita Local', $resultado['skus_anita']],
                ['Encontrados en ERP', $resultado['encontrados_erp']],
                ['Ya tenían canal', $resultado['ya_asignados']],
                ['A asignar canal', $resultado['a_asignar']],
                ['Asignados ahora', $resultado['asignados']],
                ['A crear en ERP', $resultado['a_crear']],
                ['Creados ahora', $resultado['creados']],
                ['Errores de alta', $resultado['errores_alta']],
                ['Sin match residual', $resultado['sin_match']],
                ['Ya tenían Fábrica', $resultado['ya_fabrica'] ?? 0],
                ['A asignar Fábrica (no están en Anita Local)', $resultado['a_asignar_fabrica'] ?? 0],
                ['Asignados Fábrica ahora', $resultado['asignados_fabrica'] ?? 0],
                ['LOCAL en ERP y no en Anita Local', $resultado['extras_local_n'] ?? 0],
            ]
        );

        if ($resultado['skus_a_asignar'] !== []) {
            $this->line('SKUs a asignar LOCAL (muestra): '.implode(', ', array_slice($resultado['skus_a_asignar'], 0, 30)));
        }
        if (($resultado['skus_a_asignar_fabrica'] ?? []) !== []) {
            $this->line('SKUs a asignar FÁBRICA (muestra): '.implode(', ', $resultado['skus_a_asignar_fabrica']));
        }
        if (($resultado['extras_local'] ?? []) !== []) {
            $this->warn('LOCAL en ERP y ausentes en Anita Local (muestra): '.implode(', ', $resultado['extras_local']));
        }
        if (($resultado['skus_a_crear'] ?? []) !== []) {
            $this->line('SKUs a crear (muestra): '.implode(', ', array_slice($resultado['skus_a_crear'], 0, 30)));
        }
        if (($resultado['errores_muestra'] ?? []) !== []) {
            $this->warn('Errores de alta (muestra):');
            foreach ($resultado['errores_muestra'] as $err) {
                $this->line('  - '.$err);
            }
        }

        if (! $ejecutar && ($resultado['a_asignar'] > 0 || $resultado['a_crear'] > 0)) {
            $this->warn('Revise el impacto y autorice --ejecutar para persistir.');
        }

        return self::SUCCESS;
    }
}
