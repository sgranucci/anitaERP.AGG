<?php

namespace App\Console\Commands;

use App\Services\Sueldos\EntregaPrendaAnitaImportService;
use App\Services\Sueldos\EntregaPrendaRopaempAnitaImportService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;

/**
 * Importa el histórico de entregas de indumentaria desde Anita al ledger
 * entrega_prenda_sueldos. Backfill puro (sin stock ni asiento), idempotente.
 *
 * - AGG/resto: entrprenda + entrprendav
 * - El Bierzo: ropaemp (no existen entrprenda/entrprendav)
 */
class IndumentariaImportarEntregasAnita extends Command
{
    protected $signature = 'indumentaria:importar-entregas-anita
        {--desde= : Solo entregas con fecha >= YYYY-MM-DD}
        {--empresa= : Filtrar por código de empresa Anita (El Bierzo default 1)}
        {--estado=A : Estado de cabecera entrprenda (ignorado en ropaemp)}
        {--dry-run : Simula sin grabar}';

    protected $description = 'Importa histórico de entregas de indumentaria desde Anita (idempotente, sin stock ni asiento).';

    public function handle(
        EntregaPrendaAnitaImportService $entrprenda,
        EntregaPrendaRopaempAnitaImportService $ropaemp
    ): int {
        @ini_set('memory_limit', '-1');
        @ini_set('max_execution_time', '0');

        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $this->warn('DRY-RUN: no se graba nada.');
        }

        $usaRopaemp = EntornoEmpresaSupport::esElBierzo();
        $this->info($usaRopaemp
            ? 'Leyendo entregas desde Anita (ropaemp / El Bierzo)...'
            : 'Leyendo entregas desde Anita (entrprenda/entrprendav)...');

        $opciones = [
            'desde' => $this->option('desde') ?: null,
            'empresa_anita' => $this->option('empresa') !== null && $this->option('empresa') !== ''
                ? (int) $this->option('empresa')
                : null,
            'dry_run' => $dry,
        ];

        if ($usaRopaemp) {
            $r = $ropaemp->importar($opciones);
        } else {
            $opciones['estado'] = $this->option('estado') !== null
                ? (string) $this->option('estado')
                : 'A';
            $r = $entrprenda->importar($opciones);
        }

        $headers = ['Leídas', 'Importadas', 'Ya existían', 'Sin empleado', 'Sin empresa', 'Sin líneas', 'Líneas', 'Líneas s/prenda'];
        $row = [
            $r['leidas'], $r['importadas'], $r['ya_existentes'], $r['sin_empleado'],
            $r['sin_empresa'], $r['sin_lineas'], $r['lineas'], $r['lineas_sin_prenda'],
        ];
        if (isset($r['omitidas_empresa'])) {
            $headers[] = 'Omitidas emp≠filtro';
            $row[] = $r['omitidas_empresa'];
        }
        $this->table($headers, [$row]);

        if (! empty($r['errores'])) {
            $this->warn('Avisos ('.count($r['errores']).', máx 50):');
            foreach ($r['errores'] as $e) {
                $this->line('  - '.$e);
            }
        }

        $this->info('Listo.');

        return self::SUCCESS;
    }
}
