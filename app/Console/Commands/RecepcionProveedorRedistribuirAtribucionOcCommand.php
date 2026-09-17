<?php

namespace App\Console\Commands;

use App\Services\Stock\RecepcionProveedorRedistribuirAtribucionOcService;
use Illuminate\Console\Command;

class RecepcionProveedorRedistribuirAtribucionOcCommand extends Command
{
    protected $signature = 'recepcion-proveedor:redistribuir-atribucion-oc
                            {--oc=* : Número(s) de OC (default: 216512,216513,216515)}
                            {--dry-run : Lista cambios sin grabar (default si no se pasa --aplicar)}
                            {--aplicar : Aplica los cambios en recepcion_proveedor_articulo}';

    protected $description = 'Repara COM apiladas en una sola línea OC (mismo SKU). No matchea por penvp_nro_interno: con internos duplicados ese matching es ambiguo.';

    public function handle(RecepcionProveedorRedistribuirAtribucionOcService $service): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $dryRun = ! $aplicar || (bool) $this->option('dry-run');
        if ($aplicar && (bool) $this->option('dry-run')) {
            $this->error('No combine --aplicar con --dry-run.');

            return self::FAILURE;
        }

        $ocs = array_values(array_filter(array_map('intval', (array) $this->option('oc'))));
        if ($ocs === []) {
            $ocs = [216512, 216513, 216515];
        }

        if ($dryRun) {
            $this->warn('Dry-run: no se modificará recepcion_proveedor_articulo.');
        } else {
            $this->warn('Aplicando cambios en recepcion_proveedor_articulo (no toca Anita ni stock).');
        }

        $this->line('OCs: '.implode(', ', $ocs));
        $this->comment(
            'Matching por interno NO se usa: si penvp_nro_interno está duplicado, '
            .'atribuir por interno concentra todo en una línea (first-wins). '
            .'Acá se redistribuye 1:1 por ordencompra_articulo.id cuando un COM '
            .'tiene N renglones del mismo artículo apuntando a un único oc_art.'
        );

        $resultado = $service->ejecutar(
            $ocs,
            $dryRun,
            function (int $numeroOc, \Throwable $e) {
                $this->error("OC {$numeroOc}: ".$e->getMessage());
            }
        );

        foreach ($resultado['ocs'] as $detalle) {
            if (isset($detalle['error'])) {
                continue;
            }

            $this->newLine();
            $this->info(sprintf(
                'OC %s (id=%s) líneas=%s | visibles %s → %s | cambios=%s | COM redistribuidos=%s | rpa sin atribuir=%s',
                $detalle['numeroordencompra'],
                $detalle['ordencompra_id'],
                $detalle['lineas_oc'],
                $detalle['lineas_visibles_antes'],
                $detalle['lineas_visibles_despues'],
                $detalle['cambios_planificados'],
                $detalle['coms_redistribuidos'],
                $detalle['lineas_sin_atribuir']
            ));

            if ($detalle['internos_duplicados'] !== []) {
                $this->warn('  Internos duplicados en ERP (no se corrigen acá; matching por interno sería ambiguo):');
                foreach ($detalle['internos_duplicados'] as $dup) {
                    $this->line(sprintf(
                        '    interno=%s x%s → oc_art [%s]',
                        $dup['penvp_nro_interno'],
                        $dup['cantidad'],
                        implode(',', $dup['ordencompra_articulo_ids'])
                    ));
                }
            }

            if ($detalle['cambios'] !== []) {
                $rows = array_map(static fn (array $c): array => [
                    $c['numerorecepcion'],
                    $c['rpa_id'],
                    $c['desde_oc_art'],
                    $c['hacia_oc_art'],
                    $c['penvp_nro_interno'],
                ], $detalle['cambios']);
                $this->table(
                    ['COM', 'rpa_id', 'desde oc_art', 'hacia oc_art', 'interno destino'],
                    $rows
                );
            }

            $pendRows = array_map(static fn (array $p): array => [
                $p['ordencompra_articulo_id'],
                $p['pedida'],
                $p['recibida'],
                $p['pendiente'],
                $p['pendiente'] > 0.000001 ? 'sí' : 'NO (oculta)',
            ], $detalle['pendiente_despues']);
            $this->table(
                ['oc_art', 'pedida', 'recibida'.($dryRun ? ' (sim)' : ''), 'pendiente', 'visible'],
                $pendRows
            );
        }

        $this->newLine();
        $this->table(['Métrica', 'Cantidad'], [
            ['OCs procesadas', count($resultado['ocs'])],
            ['Cambios '.($dryRun ? 'planificados' : 'aplicados'), $resultado['total_cambios']],
            ['Errores', $resultado['errores']],
        ]);

        return $resultado['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
