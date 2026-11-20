<?php

namespace App\Console\Commands;

use App\Support\Stock\RecepcionProveedorCorregirOcAnitaImportSupport;
use Illuminate\Console\Command;

/**
 * Corrige ordencompra_id de COM ANITA_IMPORT colgadas de OCs históricas
 * (bug recm_com_nro/DEP vs recm_nro_fac/PEP). Default dry-run.
 */
class RecepcionProveedorCorregirOcAnitaImportCommand extends Command
{
    protected $signature = 'recepcion-proveedor:corregir-oc-anita-import
                            {--oc-fecha-hasta=2014-12-31 : OCs con fecha <= este día}
                            {--rp-fecha-desde=2025-01-01 : Recepciones con fecha >= este día}
                            {--com= : Lista de anita_nro COM separada por comas}
                            {--mostrar=30 : Filas de detalle a mostrar}
                            {--ejecutar : Persiste; sin esto solo dry-run}';

    protected $description = 'Reasigna COM importadas al PEP Anita correcto (corrige vínculo DEP→OC histórica)';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $ocHasta = (string) $this->option('oc-fecha-hasta');
        $rpDesde = (string) $this->option('rp-fecha-desde');
        $mostrar = max(0, (int) $this->option('mostrar'));
        $coms = array_values(array_filter(array_map(
            static fn ($v) => (int) trim((string) $v),
            explode(',', (string) ($this->option('com') ?? ''))
        ), static fn (int $n) => $n > 0));

        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN')." corregir-oc-anita-import oc<= {$ocHasta} rp>= {$rpDesde}"
            .($coms !== [] ? ' com=['.implode(',', $coms).']' : ''));

        $plan = RecepcionProveedorCorregirOcAnitaImportSupport::planificar($ocHasta, $rpDesde, $coms);

        $this->table(['Métrica', 'Cantidad'], [
            ['Candidatas', $plan['stats']['candidatas']],
            ['Ya OK (PEP=OC actual)', $plan['stats']['ya_ok']],
            ['Vincularía / vincular', $plan['stats']['vincular']],
            ['Sin PEP Anita', $plan['stats']['sin_pep_anita']],
            ['OC faltante en ERP', $plan['stats']['oc_faltante_erp']],
            ['Sin anita_nro', $plan['stats']['sin_anita_nro']],
        ]);

        if ($mostrar > 0 && $plan['vincular'] !== []) {
            $this->line('Detalle a reasignar (máx '.$mostrar.'):');
            $rows = array_slice($plan['vincular'], 0, $mostrar);
            $this->table(
                ['rp_id', 'COM', 'OC mala', 'PEP Anita', 'oc_id_nuevo', 'emp'],
                array_map(static fn (array $r) => [
                    $r['rp_id'],
                    $r['com'],
                    $r['nro_oc_actual'],
                    $r['nro_oc_anita'],
                    $r['oc_id_nuevo'],
                    $r['empresa_id'],
                ], $rows)
            );
        }

        if ($mostrar > 0 && $plan['omitidas'] !== []) {
            $this->warn('Omitidas (máx '.$mostrar.'):');
            $rows = array_slice($plan['omitidas'], 0, $mostrar);
            $this->table(
                ['rp_id', 'COM', 'motivo', 'detalle'],
                array_map(static fn (array $r) => [
                    $r['rp_id'],
                    $r['com'],
                    $r['motivo'],
                    $r['detalle'] ?? '',
                ], $rows)
            );
        }

        if (! $ejecutar) {
            $this->comment('Sin cambios. Agregue --ejecutar para persistir.');

            return self::SUCCESS;
        }

        $n = RecepcionProveedorCorregirOcAnitaImportSupport::aplicar($plan['vincular']);
        $this->info("Recepciones actualizadas: {$n}");

        return self::SUCCESS;
    }
}
