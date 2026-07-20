<?php

namespace App\Console\Commands;

use App\Services\Sueldos\EntregaPrendaAnitaImportService;
use Illuminate\Console\Command;

/**
 * Importa el histórico de entregas de indumentaria desde Anita (entrprenda/entrprendav)
 * al ledger entrega_prenda_sueldos. Backfill puro (sin stock ni asiento), idempotente.
 */
class IndumentariaImportarEntregasAnita extends Command
{
    protected $signature = 'indumentaria:importar-entregas-anita
        {--desde= : Solo entregas con fecha >= YYYY-MM-DD}
        {--empresa= : Filtrar por código de empresa Anita}
        {--estado=A : Estado de cabecera a importar (A = activa)}
        {--dry-run : Simula sin grabar}';

    protected $description = 'Importa histórico de entregas de indumentaria desde Anita (idempotente, sin stock ni asiento).';

    public function handle(EntregaPrendaAnitaImportService $servicio): int
    {
        @ini_set('memory_limit', '-1');
        @ini_set('max_execution_time', '0');

        $dry = (bool) $this->option('dry-run');
        if ($dry) {
            $this->warn('DRY-RUN: no se graba nada.');
        }

        $this->info('Leyendo entregas desde Anita...');
        $r = $servicio->importar([
            'desde' => $this->option('desde') ?: null,
            'empresa_anita' => $this->option('empresa') ? (int) $this->option('empresa') : null,
            'estado' => $this->option('estado') !== null ? (string) $this->option('estado') : 'A',
            'dry_run' => $dry,
        ]);

        $this->table(
            ['Leídas', 'Importadas', 'Ya existían', 'Sin empleado', 'Sin empresa', 'Sin líneas', 'Líneas', 'Líneas s/prenda'],
            [[$r['leidas'], $r['importadas'], $r['ya_existentes'], $r['sin_empleado'], $r['sin_empresa'], $r['sin_lineas'], $r['lineas'], $r['lineas_sin_prenda']]]
        );

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
