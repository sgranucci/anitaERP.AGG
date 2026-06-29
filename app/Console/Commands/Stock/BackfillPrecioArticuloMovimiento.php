<?php

namespace App\Console\Commands\Stock;

use App\Services\Stock\BackfillArticuloMovimientoPrecioService;
use Illuminate\Console\Command;

class BackfillPrecioArticuloMovimiento extends Command
{
    protected $signature = 'stock:backfill-precio-articulo-movimiento
                            {--dry-run : Simula sin grabar cambios}
                            {--solo= : ventas|insumos|movimientos|todos (default todos)}
                            {--chunk=500 : Tamaño de lote}';

    protected $description = 'Completa precio/costo histórico en articulo_movimiento desde venta_emision o última compra (backfill).';

    public function handle(BackfillArticuloMovimientoPrecioService $service): int
    {
        $solo = strtolower(trim((string) ($this->option('solo') ?: 'todos')));
        $permitidos = ['ventas', 'insumos', 'movimientos', 'todos'];
        if (! in_array($solo, $permitidos, true)) {
            $this->error('Valor --solo inválido. Use: ventas, insumos, movimientos o todos.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $service->dryRun($dryRun)->chunkSize((int) $this->option('chunk'));

        $pendientes = $service->contarPendientes();
        $this->info('Pendientes detectados:');
        $this->line('  Ventas (precio desde venta_emision): '.$pendientes['ventas_pendientes']);
        $this->line('  Insumos (costo última compra estimada): '.$pendientes['insumos_pendientes']);
        $this->line('  Otros movimientos (costo última compra estimada): '.$pendientes['movimientos_pendientes']);

        if ($dryRun) {
            $this->warn('Modo dry-run: no se grabarán cambios.');
        }

        $this->info('Procesando ('.$solo.')...');

        $resultado = $service->ejecutar($solo);

        $this->info('Actualizados:');
        $this->line('  Ventas: '.$resultado['ventas']);
        $this->line('  Insumos: '.$resultado['insumos']);
        $this->line('  Movimientos: '.$resultado['movimientos']);

        if ($dryRun) {
            $this->comment('Ejecute sin --dry-run para persistir los cambios.');
        }

        return self::SUCCESS;
    }
}
