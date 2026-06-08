<?php

namespace App\Console\Commands\Stock;

use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use App\Services\Stock\CorregirSignoArticuloMovimientoService;
use Illuminate\Console\Command;

class CorregirSignoArticuloMovimiento extends Command
{
    protected $signature = 'stock:corregir-signo-articulo-movimiento
        {--dry-run : Simular sin grabar cambios}
        {--venta= : Solo movimientos de una venta (ID)}
        {--desde= : Fecha desde (Y-m-d) sobre articulo_movimiento.fecha}
        {--hasta= : Fecha hasta (Y-m-d)}
        {--deposito= : Solo un depósito}
        {--todos : Revisar todos los movimientos, no solo los con signo incorrecto}
        {--anular-sin-operacion : Poner cantidad 0 en ventas con operacionstock N/O}
        {--chunk=500 : Tamaño de lote}
        {--force : Aplicar sin confirmación interactiva}
        {--reconstruir-saldos : Tras corregir, ejecutar stock:reconstruir-saldos}';

    protected $description = 'Corrige el signo de articulo_movimiento.cantidad según operacionstock (ventas) o signo del tipo stock.';

    public function handle(
        CorregirSignoArticuloMovimientoService $service,
        Articulo_Saldo_DepositoRepositoryInterface $saldoRepo,
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $ventaId = $this->option('venta') !== null ? (int) $this->option('venta') : null;
        $depositoId = $this->option('deposito') !== null ? (int) $this->option('deposito') : null;
        $chunk = max(50, min(5000, (int) $this->option('chunk')));
        $soloIncorrectos = ! (bool) $this->option('todos');

        if (! $dryRun && ! (bool) $this->option('force') && ! $this->confirm('¿Aplicar corrección de signo en articulo_movimiento?', false)) {
            $this->warn('Cancelado. Use --dry-run para simular.');

            return self::FAILURE;
        }

        if ($soloIncorrectos) {
            $pendientes = $service->contarIncorrectos(
                $ventaId,
                $this->option('desde'),
                $this->option('hasta'),
                $depositoId,
            );
            $this->info("Movimientos con signo incorrecto (filtro actual): {$pendientes}");
        }

        if ($dryRun) {
            $this->comment('Modo simulación (--dry-run): no se graban cambios.');
        }

        $stats = $service->ejecutar(
            dryRun: $dryRun,
            ventaId: $ventaId,
            fechaDesde: $this->option('desde'),
            fechaHasta: $this->option('hasta'),
            depositoId: $depositoId,
            soloIncorrectos: $soloIncorrectos,
            anularSinOperacionStock: (bool) $this->option('anular-sin-operacion'),
            chunkSize: $chunk,
        );

        $this->table(
            ['Métrica', 'Valor'],
            collect($stats)
                ->except('muestra')
                ->map(fn ($v, $k) => [$k, $v])
                ->values()
                ->all()
        );

        if ($stats['muestra'] !== []) {
            $this->newLine();
            $this->info('Muestra de correcciones:');
            $this->table(
                ['ID', 'Antes', 'Después', 'Origen', 'operacionstock', 'venta_id'],
                array_map(fn ($r) => [
                    $r['id'],
                    $r['antes'],
                    $r['despues'],
                    $r['origen'],
                    $r['operacionstock'] ?? '—',
                    $r['venta_id'] ?? '—',
                ], $stats['muestra'])
            );
        }

        if (! $dryRun && $stats['corregidos'] > 0 && $this->option('reconstruir-saldos')) {
            $this->newLine();
            $this->info('Reconstruyendo articulo_saldo_deposito...');
            $count = $saldoRepo->reconstruir($depositoId > 0 ? $depositoId : null);
            $this->info("Saldos recalculados: {$count}");
        } elseif (! $dryRun && $stats['corregidos'] > 0) {
            $this->newLine();
            $this->warn('Ejecute stock:reconstruir-saldos (o use --reconstruir-saldos) para alinear saldos por depósito.');
        }

        return self::SUCCESS;
    }
}
