<?php

namespace App\Console\Commands;

use App\Services\Ventas\MaquinavendingRmvBackfillService;
use Illuminate\Console\Command;

/**
 * Backfill RMV interno para cierres contables vending sin venta (IVA ventas).
 */
class BackfillMaquinavendingRmvCierreCommand extends Command
{
    protected $signature = 'vending:backfill-rmv-cierre
                            {--fecha-desde=2026-07-01 : Fecha jornada desde (Y-m-d)}
                            {--fecha-hasta=2026-07-31 : Fecha jornada hasta (Y-m-d)}
                            {--empresa= : Filtrar empresa_id}
                            {--recalcular : Recalcula impuestos de RMV ya emitidos (neto=total/1.21)}
                            {--dry-run : Simular sin grabar}';

    protected $description = 'Emite RMV (letra Z) faltantes en cierres contables vending ya asientos, para IVA ventas';

    public function handle(MaquinavendingRmvBackfillService $service): int
    {
        $fechaDesde = trim((string) $this->option('fecha-desde'));
        $fechaHasta = trim((string) $this->option('fecha-hasta'));
        if ($fechaDesde === '' || $fechaHasta === '') {
            $this->error('Indique --fecha-desde y --fecha-hasta.');

            return self::FAILURE;
        }

        $empresaOpt = $this->option('empresa');
        $empresaId = $empresaOpt !== null && $empresaOpt !== '' ? (int) $empresaOpt : null;
        $dryRun = (bool) $this->option('dry-run');
        $recalcular = (bool) $this->option('recalcular');

        if ($recalcular) {
            return $this->handleRecalcular($service, $fechaDesde, $fechaHasta, $dryRun, $empresaId);
        }

        $this->line(
            "Backfill RMV vending {$fechaDesde} — {$fechaHasta}"
            .($empresaId ? " empresa={$empresaId}" : '')
            .($dryRun ? ' [dry-run]' : '')
        );

        try {
            $resultado = $service->ejecutar($fechaDesde, $fechaHasta, $dryRun, $empresaId);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($resultado['detalle'] as $d) {
            $rows[] = [
                $d['empresa_id'],
                $d['fecha_dia'],
                $d['puntoventa_codigo'],
                $d['asiento_numero'],
                number_format((float) $d['total'], 2, ',', '.'),
                $d['venta_codigo'] ?? '—',
                $d['estado'],
            ];
        }

        if ($rows !== []) {
            $this->table(
                ['Empresa', 'Jornada', 'PV', 'Asiento', 'Total', 'RMV', 'Estado'],
                $rows,
            );
        }

        $this->table(['Concepto', 'Valor'], [
            ['Grupos encontrados', (string) $resultado['grupos_encontrados']],
            ['Emitidos / dry-run', (string) $resultado['emitidos']],
            ['Omitidos', (string) $resultado['omitidos']],
            ['Errores', (string) count($resultado['errores'])],
        ]);

        foreach ($resultado['errores'] as $err) {
            $this->warn($err);
        }

        return $resultado['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }

    private function handleRecalcular(
        MaquinavendingRmvBackfillService $service,
        string $fechaDesde,
        string $fechaHasta,
        bool $dryRun,
        ?int $empresaId,
    ): int {
        $this->line(
            "Recalcular impuestos RMV {$fechaDesde} — {$fechaHasta}"
            .($empresaId ? " empresa={$empresaId}" : '')
            .($dryRun ? ' [dry-run]' : '')
        );

        try {
            $resultado = $service->recalcularExistentes($fechaDesde, $fechaHasta, $dryRun, $empresaId);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($resultado['detalle'] as $d) {
            $rows[] = [
                $d['codigo'],
                $d['fecha_dia'],
                number_format((float) $d['total'], 2, ',', '.'),
                number_format((float) $d['gravado'], 2, ',', '.'),
                number_format((float) $d['iva'], 2, ',', '.'),
                number_format((float) $d['exento'], 2, ',', '.'),
                $d['estado'],
            ];
        }

        if ($rows !== []) {
            $this->table(
                ['RMV', 'Jornada', 'Total', 'Neto', 'IVA', 'Exento', 'Estado'],
                $rows,
            );
        }

        $this->table(['Concepto', 'Valor'], [
            ['Encontrados', (string) $resultado['encontrados']],
            ['Recalculados / dry-run', (string) $resultado['recalculados']],
            ['Errores', (string) count($resultado['errores'])],
        ]);

        foreach ($resultado['errores'] as $err) {
            $this->warn($err);
        }

        return $resultado['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
