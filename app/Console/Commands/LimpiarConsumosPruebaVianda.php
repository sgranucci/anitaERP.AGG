<?php

namespace App\Console\Commands;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Ventas\ViandaConsumo;
use App\Models\Ventas\ViandaConsumoLinea;
use App\Support\Stock\ArticuloMovimientoEliminacionSupport;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina consumos de vianda de prueba del ERP: borra articulo_movimiento vía Eloquent
 * (Articulo_MovimientoObserver → articulo_saldo_deposito) y elimina vianda_consumo / líneas.
 * No toca Anita ni maestros (usuarios, tipos menú, terminales).
 */
class LimpiarConsumosPruebaVianda extends Command
{
    protected $signature = 'vianda:limpiar-consumos-prueba
                            {--desde= : Fecha desde Y-m-d inclusive}
                            {--hasta= : Fecha hasta Y-m-d inclusive}
                            {--id=* : IDs concretos de vianda_consumo}
                            {--todos : Todos los consumos (sin filtro de fecha)}
                            {--force : Ejecutar borrado (sin esto solo simula)}
                            {--yes : Confirmar automáticamente (con --force)}';

    protected $description = 'Borra consumos de vianda de prueba: elimina movimientos (actualiza saldo) y cabecera/líneas.';

    public function handle(): int
    {
        $consumos = $this->resolverConsumos();
        if ($consumos->isEmpty()) {
            $this->warn('No hay consumos de vianda que coincidan con el filtro.');

            return self::SUCCESS;
        }

        $this->info('Consumos a eliminar ('.$consumos->count().'):');
        foreach ($consumos as $c) {
            $movCount = $this->contarMovimientosRelacionados($c);
            $this->line(sprintf(
                '  id=%d %s fecha=%s estado=%s movimientos=%d %s',
                $c->id,
                $c->codigo_retiro,
                $c->fecha?->format('Y-m-d') ?? '—',
                $c->estado,
                $movCount,
                $c->nombre_usuario ?? '',
            ));
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->comment('Simulación solamente. Agregue --force para ejecutar el borrado en ERP.');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('¿Confirma borrado permanente de '.$consumos->count().' consumo(s) y sus movimientos de stock?', false)) {
            $this->comment('Cancelado.');

            return self::SUCCESS;
        }

        $ok = 0;
        foreach ($consumos as $consumo) {
            try {
                DB::transaction(function () use ($consumo): void {
                    $this->eliminarConsumoEnCascada($consumo);
                });
                $ok++;
                $this->line("  OK vianda_consumo_id={$consumo->id}");
            } catch (\Throwable $e) {
                $this->error("  FALLÓ vianda_consumo_id={$consumo->id}: ".$e->getMessage());
            }
        }

        $this->newLine();
        $restantes = ViandaConsumo::query()->count();
        $movRestantes = DB::table('articulo_movimiento')
            ->where(function ($q) {
                $q->whereNotNull('vianda_consumo_id')
                    ->orWhere('concepto', 'like', 'Vianda %')
                    ->orWhere('concepto', 'like', 'Reversa Vianda %');
            })
            ->count();

        $this->info("Eliminados {$ok}/".$consumos->count().' consumos.');
        $this->info("Restantes: vianda_consumo={$restantes}, articulo_movimiento vianda={$movRestantes}");

        if ($restantes === 0 && $movRestantes === 0) {
            $this->reiniciarContadoresSiVacias();
            $this->comment('Contadores AUTO_INCREMENT reiniciados en vianda_consumo y vianda_consumo_linea.');
        }

        return $ok === $consumos->count() ? self::SUCCESS : self::FAILURE;
    }

    private function reiniciarContadoresSiVacias(): void
    {
        if (ViandaConsumo::query()->exists() || DB::table('vianda_consumo_linea')->exists()) {
            return;
        }

        if (\App\Support\Database\SqlDialectSupport::esPostgres()) {
            DB::statement('TRUNCATE TABLE vianda_consumo_linea, vianda_consumo RESTART IDENTITY CASCADE');

            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::statement('TRUNCATE TABLE vianda_consumo_linea');
        DB::statement('TRUNCATE TABLE vianda_consumo');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * @return Collection<int, ViandaConsumo>
     */
    private function resolverConsumos(): Collection
    {
        $ids = array_values(array_filter(array_map(
            static fn ($v) => (int) $v,
            (array) $this->option('id'),
        ), static fn (int $v) => $v > 0));

        $query = ViandaConsumo::query()->with('lineas')->orderBy('id');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } elseif ($this->option('todos')) {
            // Sin filtro adicional.
        } else {
            $desde = $this->option('desde');
            $hasta = $this->option('hasta');
            if (! is_string($desde) || $desde === '' || ! is_string($hasta) || $hasta === '') {
                $this->error('Indique --desde=YYYY-MM-DD y --hasta=YYYY-MM-DD, --id=… o --todos.');

                return collect();
            }
            $query->whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta);
        }

        return $query->get();
    }

    private function eliminarConsumoEnCascada(ViandaConsumo $consumo): void
    {
        $queryMovimientos = $this->queryMovimientosRelacionados($consumo);
        $movimientoIds = (clone $queryMovimientos)->pluck('id')->map(static fn ($id) => (int) $id)->all();

        $this->eliminarAuditsMovimientos($movimientoIds);
        ArticuloMovimientoEliminacionSupport::eliminarPorQuery($queryMovimientos);

        $consumoId = (int) $consumo->id;
        $lineaIds = DB::table('vianda_consumo_linea')->where('vianda_consumo_id', $consumoId)->pluck('id');

        $this->eliminarAudits([
            ViandaConsumo::class => [$consumoId],
            ViandaConsumoLinea::class => $lineaIds->all(),
        ]);

        DB::table('vianda_consumo_linea')->where('vianda_consumo_id', $consumoId)->delete();
        DB::table('vianda_consumo')->where('id', $consumoId)->delete();
    }

    private function contarMovimientosRelacionados(ViandaConsumo $consumo): int
    {
        return $this->queryMovimientosRelacionados($consumo)->count();
    }

    /**
     * @return Builder<Articulo_Movimiento>
     */
    private function queryMovimientosRelacionados(ViandaConsumo $consumo): Builder
    {
        $consumoId = (int) $consumo->id;
        $codigo = trim((string) $consumo->codigo_retiro);

        return Articulo_Movimiento::query()->where(function ($q) use ($consumoId, $codigo) {
            $q->where('vianda_consumo_id', $consumoId);
            if ($codigo !== '') {
                $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $codigo);
                $q->orWhere(function ($q2) use ($like) {
                    $q2->whereNull('vianda_consumo_id')
                        ->where(function ($q3) use ($like) {
                            $q3->where('concepto', 'like', 'Vianda '.$like.'%')
                                ->orWhere('concepto', 'like', 'Reversa Vianda '.$like.'%');
                        });
                });
            }
        });
    }

    /**
     * @param  list<int>  $movimientoIds
     */
    private function eliminarAuditsMovimientos(array $movimientoIds): void
    {
        if ($movimientoIds === []) {
            return;
        }

        $this->eliminarAudits([
            Articulo_Movimiento::class => $movimientoIds,
        ]);
    }

    /**
     * @param  array<class-string, list<int>>  $porTipo
     */
    private function eliminarAudits(array $porTipo): void
    {
        if (! Schema::hasTable('audits')) {
            return;
        }

        foreach ($porTipo as $tipo => $ids) {
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if ($ids === []) {
                continue;
            }
            DB::table('audits')
                ->where('auditable_type', $tipo)
                ->whereIn('auditable_id', $ids)
                ->delete();
        }
    }
}
