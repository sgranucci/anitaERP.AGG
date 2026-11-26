<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionJuegoCorregirCentrocostoMesasSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Pasa al CC de mesas los asientos de mesas de facturación juego que quedaron con el CC de slots.
 * Sin --ejecutar no escribe nada (dry-run).
 */
class FacturacionJuegoCorregirCentrocostoMesasCommand extends Command
{
    protected $signature = 'facturacion-juego:corregir-centrocosto-mesas
                            {--desde=2026-10-01 : Fecha flash desde YYYY-MM-DD}
                            {--hasta=2026-10-31 : Fecha flash hasta YYYY-MM-DD}
                            {--sala= : sala_id opcional}
                            {--dry-run : Solo lista el impacto (default si no hay --ejecutar)}
                            {--ejecutar : Persiste el cambio de centro de costo}
                            {--ignorar-cierre : Permite escribir en períodos contables cerrados (solo con OK explícito)}';

    protected $description = 'Corrige el centro de costo de asientos de mesas de facturación juego (CC de slots → CC de mesas)';

    public function handle(FacturacionJuegoCorregirCentrocostoMesasSupport $support): int
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->error('Solo corre en entorno CROWN (EMPRESA=CROWN).');

            return self::FAILURE;
        }
        if (! Schema::hasTable('flash_crown') || ! Schema::hasColumn('facturacion_juego_sala', 'centrocosto_mesa_id')) {
            $this->error('Falta la columna facturacion_juego_sala.centrocosto_mesa_id (migración pendiente).');

            return self::FAILURE;
        }

        $desde = trim((string) $this->option('desde'));
        $hasta = trim((string) $this->option('hasta'));
        $salaOpt = $this->option('sala');
        $salaId = ($salaOpt !== null && $salaOpt !== '') ? (int) $salaOpt : null;
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');

        if ($desde === '' || $hasta === '') {
            $this->error('Indique --desde y --hasta (YYYY-MM-DD).');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry-run: no se persisten cambios.');
        }

        $res = $support->planificar($desde, $hasta, $salaId);
        $planes = $res['planes'];

        $this->info(sprintf(
            'Alcance flash %s → %s%s',
            $desde,
            $hasta,
            $salaId ? ' sala_id='.$salaId : ' (todas las salas)',
        ));

        if ($res['sin_cc_mesa'] !== []) {
            $this->error('Salas sin CC de mesas configurado (no se corrigen): '.implode(', ', $res['sin_cc_mesa']));
        }

        $asientos = count($planes);
        $movs = array_sum(array_map(fn ($p) => count($p['movimientos']), $planes));
        $this->line("Asientos a corregir: {$asientos}");
        $this->line("Movimientos a corregir: {$movs}");

        $porSala = [];
        foreach ($planes as $p) {
            $k = $p['sala_codigo'].' '.$p['cc_actual'].' → '.$p['cc_nuevo'];
            $porSala[$k] = ($porSala[$k] ?? 0) + 1;
        }
        if ($porSala !== []) {
            $this->newLine();
            $this->line('Por sala (CC actual → CC nuevo):');
            foreach ($porSala as $k => $n) {
                $this->line("  · {$k}: {$n} asiento(s)");
            }
        }

        $cerrados = array_values(array_filter($planes, fn ($p) => ! empty($p['periodo_cerrado'])));
        if ($cerrados !== []) {
            $fechas = array_unique(array_column($cerrados, 'fecha'));
            sort($fechas);
            $this->newLine();
            $this->warn(sprintf(
                'Hay %d asiento(s) en período contable cerrado (fechas %s … %s).',
                count($cerrados),
                $fechas[0],
                end($fechas),
            ));
        }

        if ($planes === []) {
            $this->info('Nada para corregir.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Para persistir: mismo comando con --ejecutar (sin --dry-run).');

            return self::SUCCESS;
        }

        if ($cerrados !== [] && ! $this->option('ignorar-cierre')) {
            $this->error('Abortado: hay asientos en período cerrado. Reabra con apertura/OK explícito y vuelva a correr con --ignorar-cierre.');

            return self::FAILURE;
        }

        $out = $support->ejecutar($planes);
        $this->newLine();
        $this->info("Asientos corregidos: {$out['asientos']}");
        $this->info("Movimientos corregidos: {$out['movimientos']}");
        foreach ($out['errores'] as $err) {
            $this->error($err);
        }

        return $out['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
