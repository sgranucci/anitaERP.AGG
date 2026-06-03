<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaBackfillWaitryEmisionOrderIdService;
use Illuminate\Console\Command;

class GastronomiaBackfillWaitryEmisionOrderId extends Command
{
    protected $signature = 'gastronomia:backfill-waitry-emision-order-id
                            {--empresa=0 : Filtrar por empresa_id (0 = todas)}
                            {--fecha-desde= : Fecha jornada mínima Y-m-d (venta.fechajornada)}
                            {--fecha-hasta= : Fecha jornada máxima Y-m-d (venta.fechajornada)}
                            {--limite=0 : Máximo de emisiones a procesar (0 = sin límite)}
                            {--dry-run : Simular sin grabar en venta_gastronomia_emision}';

    protected $description = 'Completa venta_gastronomia_emision.waitry_order_id desde cuenta_gastronomia o waitry_comanda_envio';

    public function handle(GastronomiaBackfillWaitryEmisionOrderIdService $service): int
    {
        $empresaId = (int) $this->option('empresa');
        $fechaDesde = trim((string) ($this->option('fecha-desde') ?? ''));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        $limite = max(0, (int) $this->option('limite'));
        $dryRun = (bool) $this->option('dry-run');

        $this->line('Backfill waitry_order_id → venta_gastronomia_emision');
        $this->line(sprintf(
            'Empresa: %s | Jornada: %s — %s | Límite: %s | Modo: %s',
            $empresaId > 0 ? (string) $empresaId : 'todas',
            $fechaDesde !== '' ? $fechaDesde : '—',
            $fechaHasta !== '' ? $fechaHasta : '—',
            $limite > 0 ? (string) $limite : 'sin límite',
            $dryRun ? 'DRY-RUN (sin grabar)' : 'GRABAR',
        ));

        if (! $dryRun && ! $this->option('no-interaction') && ! $this->confirm('¿Confirmar actualización en base de datos?', true)) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        $stats = $service->ejecutar([
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'fecha_desde' => $fechaDesde !== '' ? $fechaDesde : null,
            'fecha_hasta' => $fechaHasta !== '' ? $fechaHasta : null,
            'limite' => $limite,
            'dry_run' => $dryRun,
        ]);

        $this->newLine();
        $this->info($dryRun ? 'Resultado (simulación)' : 'Resultado');
        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['Emisiones escaneadas (waitry_order_id NULL)', (string) $stats['escaneadas']],
                ['Actualizadas', (string) $stats['actualizadas']],
                ['  → desde cuenta_gastronomia', (string) $stats['desde_cuenta']],
                ['  → desde waitry_comanda_envio', (string) $stats['desde_envio']],
                ['Conflicto (orderId ya en otra venta)', (string) ($stats['conflictos'] ?? 0)],
                ['Sin fuente Waitry', (string) $stats['sin_fuente']],
            ],
        );

        if (($stats['conflictos_detalle'] ?? []) !== []) {
            $this->newLine();
            $this->warn('Conflictos de unique uq_vge_waitry_order_id (muestra, máx. 50)');
            $this->table(
                ['venta_id', 'waitry_order_id', 'ya usado por venta_id', 'comprobante'],
                array_map(static fn (array $row) => [
                    (string) $row['venta_id'],
                    (string) $row['waitry_order_id'],
                    (string) $row['venta_id_ocupante'],
                    $row['codigo'] ?? '—',
                ], $stats['conflictos_detalle']),
            );
        }

        if ($stats['detalle'] !== []) {
            $this->newLine();
            $this->comment('Muestra de actualizaciones (máx. 50)');
            $this->table(
                ['venta_id', 'waitry_order_id', 'origen', 'comprobante'],
                array_map(static fn (array $row) => [
                    (string) $row['venta_id'],
                    (string) $row['waitry_order_id'],
                    $row['origen'],
                    $row['codigo'] ?? '—',
                ], $stats['detalle']),
            );
        }

        if ($dryRun && $stats['actualizadas'] > 0) {
            $this->newLine();
            $this->warn('Ejecute sin --dry-run para persistir los cambios.');
        }

        return self::SUCCESS;
    }
}
