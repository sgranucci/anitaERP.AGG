<?php

namespace App\Console\Commands\Compras;

use App\Support\Compras\AnitaImport\ComprasFkAnitaBackfillSupport;
use Illuminate\Console\Command;

/**
 * Ola FK compras: recepción→OC, CP→OC (aplicped), asiento↔CP/COM/pago.
 * Default dry-run; requiere --ejecutar para persistir. No toca CC.
 */
class ComprasFkAnitaBackfillCommand extends Command
{
    protected $signature = 'compras:backfill-fks-anita
                            {--desde=2025-01-01 : Fecha ISO desde}
                            {--hasta=2025-12-31 : Fecha ISO hasta}
                            {--fase=all : all|recepcion-oc|cp-oc|asiento|asiento-pago}
                            {--ejecutar : Persiste; sin esto solo dry-run}';

    protected $description = 'Backfill FKs compras desde Anita (OC / asiento / pago) sin cuenta corriente';

    public function handle(): int
    {
        $desde = (string) $this->option('desde');
        $hasta = (string) $this->option('hasta');
        $fase = strtolower((string) $this->option('fase'));
        $ejecutar = (bool) $this->option('ejecutar');

        if (! in_array($fase, ['all', 'recepcion-oc', 'cp-oc', 'asiento', 'asiento-pago'], true)) {
            $this->error('Fase inválida. Use all|recepcion-oc|cp-oc|asiento|asiento-pago');

            return self::FAILURE;
        }

        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN')." FKs compras {$desde}..{$hasta} fase={$fase}");

        $indices = null;
        if (in_array($fase, ['all', 'recepcion-oc', 'cp-oc'], true)) {
            $this->line('Cargando índices OC / recepmae...');
            $indices = ComprasFkAnitaBackfillSupport::cargarIndicesOc($desde, $hasta);
            $this->line('  mapa COM→OC Anita: '.count($indices['mapa_com_oc']));
            $this->line('  OCs ERP indexadas: '.count($indices['oc_ids']));
        }

        if (in_array($fase, ['all', 'recepcion-oc'], true)) {
            $plan = ComprasFkAnitaBackfillSupport::planRecepcionOc(
                $desde,
                $hasta,
                $indices['mapa_com_oc'],
                $indices['oc_ids'],
                $indices['oc_ids_por_nro'] ?? [],
            );
            $this->table(['Métrica recepción→OC', 'Cantidad'], [
                ['Candidatas sin OC', $plan['stats']['candidatas']],
                ['Vincularía / vincular', $plan['stats']['vincular']],
                ['Vía misma empresa', $plan['stats']['via_empresa'] ?? 0],
                ['Vía cross-empresa', $plan['stats']['via_cross_empresa'] ?? 0],
                ['OC faltante en ERP', $plan['stats']['oc_faltante_erp']],
                ['Sin OC en Anita', $plan['stats']['sin_oc_anita']],
            ]);
            if ($ejecutar) {
                $n = ComprasFkAnitaBackfillSupport::aplicarRecepcionOc($plan['vincular']);
                $this->info("Recepciones actualizadas: {$n}");
            }
        }

        if (in_array($fase, ['all', 'cp-oc'], true)) {
            $this->line('Resolviendo CP→OC vía aplicped...');
            $plan = ComprasFkAnitaBackfillSupport::planCpOc(
                $desde,
                $hasta,
                $indices['mapa_com_oc'],
                $indices['oc_ids'],
                $indices['com_oc_erp'],
            );
            $this->table(['Métrica CP→OC', 'Cantidad'], [
                ['Candidatas sin OC', $plan['stats']['candidatas']],
                ['Vincularía / vincular', $plan['stats']['vincular']],
                ['Vía PEP', $plan['stats']['via_pep']],
                ['Vía COM', $plan['stats']['via_com']],
                ['OC faltante en ERP', $plan['stats']['oc_faltante_erp']],
                ['Sin aplicped', $plan['stats']['sin_aplicped']],
                ['Errores bridge', count($plan['stats']['errores_bridge'])],
            ]);
            if ($ejecutar) {
                $n = ComprasFkAnitaBackfillSupport::aplicarCpOc($plan['vincular']);
                $this->info("Comprobantes actualizados: {$n}");
            }
        }

        if (in_array($fase, ['all', 'asiento'], true)) {
            $plan = ComprasFkAnitaBackfillSupport::planAsientoDocs($desde, $hasta);
            $this->table(['Métrica asiento↔docs', 'Cantidad'], [
                ['Asientos C sin FK', $plan['stats']['candidatos']],
                ['Match CP único', $plan['stats']['vincular_cp']],
                ['Match CP vía emisor', $plan['stats']['via_emisor'] ?? 0],
                ['Match recepción único', $plan['stats']['vincular_rec']],
                ['Ambiguo CP (omitidos)', $plan['stats']['ambiguo_cp']],
                ['Ambiguo recepción', $plan['stats']['ambiguo_rec']],
                ['Sin match', $plan['stats']['sin_match']],
            ]);
            if ($ejecutar) {
                $n = ComprasFkAnitaBackfillSupport::aplicarAsientoDocs($plan['vincular_cp'], $plan['vincular_rec']);
                $this->info(sprintf(
                    'Asientos CP=%d REC=%d | reverse CP.asiento=%d REC.asiento=%d',
                    $n['asientos_cp'],
                    $n['asientos_rec'],
                    $n['cp_asiento'],
                    $n['rec_asiento'],
                ));
            }
        }

        if (in_array($fase, ['all', 'asiento-pago'], true)) {
            $plan = ComprasFkAnitaBackfillSupport::planAsientoPagos($desde, $hasta);
            $this->table(['Métrica asiento↔pago', 'Cantidad'], [
                ['Asientos T OPP/OPA sin FK', $plan['stats']['candidatos']],
                ['Match 1:1 vincular', $plan['stats']['vincular']],
                ['Ambiguo (omitidos)', $plan['stats']['ambiguo']],
                ['Sin match (falta doc)', $plan['stats']['sin_match']],
            ]);
            if ($ejecutar) {
                $n = ComprasFkAnitaBackfillSupport::aplicarAsientoPagos($plan['vincular']);
                $this->info(sprintf('Asientos pago=%d | reverse pago.asiento=%d', $n['asientos'], $n['pagos']));
            }
        }

        if (! $ejecutar) {
            $this->comment('Dry-run: no se grabó nada. Relanzá con --ejecutar para persistir.');
        }

        return self::SUCCESS;
    }
}
