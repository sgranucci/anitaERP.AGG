<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Services\Compras\OrdencompraAnitaBridgeService;
use Illuminate\Console\Command;

class OrdencompraRepararDescripcionCondicionpagoAnita extends Command
{
    protected $signature = 'ordencompra:reparar-desc-condpago-anita
                            {--desde=2026-06-30 : Fecha mínima created_at de OC en ERP (Y-m-d)}
                            {--numero= : Reparar solo esta numeroordencompra (opcional)}
                            {--todas : No filtrar por penvp_nro_interno (procesa también OC no generadas desde el ERP)}
                            {--dry-run : Lista cambios a aplicar sin escribir en Informix}';

    protected $description = 'Completa penvp_desc vacío y occ_cond_pago = 0 en Anita para OCs generadas desde el ERP';

    public function handle(OrdencompraAnitaBridgeService $bridge): int
    {
        if (! $bridge->habilitado()) {
            $this->error('ORDENCOMPRA_ANITA_ESCRITURA_HABILITADA está desactivada.');

            return self::FAILURE;
        }

        $desde = trim((string) $this->option('desde'));
        if ($desde === '') {
            $this->error('Indique --desde=Y-m-d.');

            return self::FAILURE;
        }

        $numeroOpt = $this->option('numero');
        $numeroFiltro = is_string($numeroOpt) && trim($numeroOpt) !== '' ? (int) $numeroOpt : null;
        $dryRun = (bool) $this->option('dry-run');
        $todas = (bool) $this->option('todas');
        $piso = (int) config('ordencompra_anita.escritura.piso_nro_interno', 500000);

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'OCs ERP created_at >= %s%s%s%s',
            $desde,
            $numeroFiltro !== null ? ' | numero '.$numeroFiltro : '',
            $todas ? ' | TODAS' : ' | solo generadas desde ERP (penvp_nro_interno >= '.$piso.')',
            $dryRun ? ' | MODO SIMULACIÓN' : '',
        ));

        $query = Ordencompra::query()
            ->where('numeroordencompra', '>', 0)
            ->where('created_at', '>=', $desde.' 00:00:00')
            ->orderBy('numeroordencompra');

        if ($numeroFiltro !== null) {
            $query->where('numeroordencompra', $numeroFiltro);
        } elseif (! $todas) {
            $query->whereHas('ordencompra_articulos', function ($q) use ($piso) {
                $q->where('penvp_nro_interno', '>=', $piso);
            });
        }

        $ocs = $query->get();
        if ($ocs->isEmpty()) {
            $this->warn('No hay órdenes de compra que coincidan con el filtro.');

            return self::SUCCESS;
        }

        $totalDesc = 0;
        $totalCond = 0;
        $ok = 0;
        $errores = 0;
        $filasTabla = [];

        foreach ($ocs as $oc) {
            $numero = (int) $oc->numeroordencompra;

            try {
                $r = $bridge->repararDescripcionCondicionpagoAnita($oc, $dryRun);
                if ($r['penvp_desc'] === 0 && $r['occ_cond_pago'] === 0) {
                    continue;
                }
                $filasTabla[] = [
                    (string) $numero,
                    (string) ($oc->estadoordencompra ?? ''),
                    (string) $r['penvp_desc'],
                    (string) $r['occ_cond_pago'],
                ];
                $totalDesc += $r['penvp_desc'];
                $totalCond += $r['occ_cond_pago'];
                $ok++;
            } catch (\Throwable $e) {
                $this->error("OC {$numero}: ".$e->getMessage());
                $errores++;
            }
        }

        if ($filasTabla !== []) {
            $this->table(
                ['OC', 'Estado ERP', 'penvp_desc', 'occ_cond_pago'],
                $filasTabla
            );
        }

        $verbo = $dryRun ? 'A reparar' : 'Reparado';
        $this->info(sprintf(
            '%s: %d OC(s) | %d penvp_desc | %d occ_cond_pago | %d error(es).',
            $verbo,
            $ok,
            $totalDesc,
            $totalCond,
            $errores,
        ));

        if ($dryRun) {
            $this->info('Simulación: no se escribió nada en Anita.');
        }

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
