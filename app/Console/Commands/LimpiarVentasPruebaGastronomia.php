<?php

namespace App\Console\Commands;

use App\Models\Contable\Asiento;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Ventas\Venta;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Elimina ventas de prueba del ERP (sin Anita) respetando FKs: cobranza, caja, stock, gastronomía, etc.
 */
class LimpiarVentasPruebaGastronomia extends Command
{
    protected $signature = 'gastronomia:limpiar-ventas-prueba
                            {--puntoventa= : puntoventa_id (obligatorio)}
                            {--desde= : Fecha desde Y-m-d inclusive}
                            {--hasta= : Fecha hasta Y-m-d inclusive}
                            {--force : Ejecutar borrado (sin esto solo muestra qué se borraría)}
                            {--yes : Confirmar automáticamente (con --force)}';

    protected $description = 'Borra ventas de prueba del ERP (cascada cobranza/caja/stock/gastro). No toca Informix.';

    public function handle(): int
    {
        $puntoventaId = (int) $this->option('puntoventa');
        if ($puntoventaId <= 0) {
            $this->error('Indique --puntoventa=ID (ej. 4 para CAEA Rebisco).');

            return self::FAILURE;
        }

        $desde = $this->option('desde');
        $hasta = $this->option('hasta');
        if (! is_string($desde) || $desde === '' || ! is_string($hasta) || $hasta === '') {
            $this->error('Indique --desde=YYYY-MM-DD y --hasta=YYYY-MM-DD.');

            return self::FAILURE;
        }

        $ventas = $this->ventasEnRango($puntoventaId, $desde, $hasta);
        if ($ventas->isEmpty()) {
            $this->warn("No hay ventas para puntoventa_id={$puntoventaId} entre {$desde} y {$hasta}.");

            return self::SUCCESS;
        }

        $ordenadas = $this->ordenarParaBorrado($ventas);

        $this->info('Ventas a eliminar ('.$ordenadas->count().'):');
        foreach ($ordenadas as $v) {
            $this->line(sprintf(
                '  id=%d %s fecha=%s',
                $v->id,
                $v->codigo,
                $v->fecha,
            ));
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->comment('Simulación solamente. Agregue --force para ejecutar el borrado en ERP.');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirm('¿Confirma borrado permanente en ERP de '.$ordenadas->count().' venta(s)?', false)) {
            $this->comment('Cancelado.');

            return self::SUCCESS;
        }

        $ok = 0;
        foreach ($ordenadas as $venta) {
            try {
                DB::transaction(function () use ($venta): void {
                    $this->eliminarVentaEnCascada((int) $venta->id);
                });
                $ok++;
                $this->line("  OK venta_id={$venta->id}");
            } catch (\Throwable $e) {
                $this->error("  FALLÓ venta_id={$venta->id}: ".$e->getMessage());
            }
        }

        $this->info("Eliminadas {$ok}/".$ordenadas->count().' ventas.');

        return $ok === $ordenadas->count() ? self::SUCCESS : self::FAILURE;
    }

    public function eliminarVentaPorId(int $ventaId): void
    {
        DB::transaction(function () use ($ventaId): void {
            $this->eliminarVentaEnCascada($ventaId);
        });
    }

    private function ventasEnRango(int $puntoventaId, string $desde, string $hasta): Collection
    {
        return Venta::withTrashed()
            ->where('puntoventa_id', $puntoventaId)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->orderBy('id')
            ->get(['id', 'codigo', 'fecha', 'tipotransaccion_id']);
    }

    /**
     * Notas de crédito antes que la factura origen.
     *
     * @param  Collection<int, Venta>  $ventas
     * @return Collection<int, Venta>
     */
    private function ordenarParaBorrado(Collection $ventas): Collection
    {
        $ids = $ventas->pluck('id')->all();
        $ncIds = DB::table('venta_gastronomia_emision')
            ->whereIn('venta_id', $ids)
            ->whereNotNull('venta_factura_origen_id')
            ->pluck('venta_id')
            ->all();

        return $ventas->sortBy(function ($v) use ($ncIds) {
            return in_array($v->id, $ncIds, true) ? 0 : 1;
        })->values();
    }

    private function eliminarVentaEnCascada(int $ventaId): void
    {
        $cobranzaIds = DB::table('cobranza')->where('venta_id', $ventaId)->pluck('id');
        foreach ($cobranzaIds as $cobranzaId) {
            $this->eliminarCobranza((int) $cobranzaId);
        }

        $cajaMovimientoIds = DB::table('caja_movimiento')->where('venta_id', $ventaId)->pluck('id');
        foreach ($cajaMovimientoIds as $cmId) {
            $this->eliminarCajaMovimiento((int) $cmId);
        }

        $this->eliminarAsientos('venta_id', $ventaId);

        $articuloMovimientoIds = DB::table('articulo_movimiento')->where('venta_id', $ventaId)->pluck('id');
        foreach ($articuloMovimientoIds as $amId) {
            DB::table('articulo_movimiento_talle')->where('articulo_movimiento_id', $amId)->delete();
            Articulo_Movimiento::query()->find((int) $amId)?->delete();
        }

        $ccVentaIds = DB::table('cliente_cuentacorriente')->where('venta_id', $ventaId)->pluck('id');
        foreach ($ccVentaIds as $ccId) {
            $this->eliminarClienteCuentacorriente((int) $ccId);
        }

        DB::table('cliente_cuentacorriente_aplicacion')->where('ventaaplicado_id', $ventaId)->delete();

        DB::table('waitry_comanda_envio')->where('venta_id', $ventaId)->delete();
        DB::table('ticketcanje_gastronomia')->where('venta_id', $ventaId)->delete();
        DB::table('tickettarjeta_gastronomia')->where('venta_id', $ventaId)->delete();
        DB::table('categoriafidelidad_entrega_gastronomia')->where('venta_id', $ventaId)->delete();

        DB::table('venta_gastronomia_emision')
            ->where('venta_id', $ventaId)
            ->orWhere('venta_factura_origen_id', $ventaId)
            ->delete();

        DB::table('venta_estacionamiento_emision')
            ->where('venta_id', $ventaId)
            ->orWhere('venta_factura_origen_id', $ventaId)
            ->delete();

        DB::table('cuenta_gastronomia')->where('venta_id', $ventaId)->update(['venta_id' => null]);

        DB::table('ordentrabajo_tarea')->where('venta_id', $ventaId)->delete();
        DB::table('ordenventa_cuota')->where('venta_id', $ventaId)->delete();

        DB::table('venta_impuesto')->where('venta_id', $ventaId)->delete();
        DB::table('venta_emision')->where('venta_id', $ventaId)->delete();
        DB::table('venta_exportacion')->where('venta_id', $ventaId)->delete();

        DB::table('venta')->where('id', $ventaId)->delete();
    }

    private function eliminarCobranza(int $cobranzaId): void
    {
        $ccIds = DB::table('cliente_cuentacorriente')->where('cobranza_id', $cobranzaId)->pluck('id');
        foreach ($ccIds as $ccId) {
            DB::table('cobranza_comprobante')->where('cliente_cuentacorriente_id', $ccId)->delete();
            $this->eliminarClienteCuentacorriente((int) $ccId);
        }

        DB::table('cobranza_comprobante')->where('cobranza_id', $cobranzaId)->delete();
        DB::table('cobranza_retencion')->where('cobranza_id', $cobranzaId)->delete();
        DB::table('cobranza_estado')->where('cobranza_id', $cobranzaId)->delete();
        DB::table('cobranza_archivo')->where('cobranza_id', $cobranzaId)->delete();
        DB::table('cheque')->where('cobranza_id', $cobranzaId)->delete();

        $this->eliminarAsientos('cobranza_id', $cobranzaId);

        $cajaMovimientoIds = DB::table('caja_movimiento')->where('cobranza_id', $cobranzaId)->pluck('id');
        foreach ($cajaMovimientoIds as $cmId) {
            $this->eliminarCajaMovimiento((int) $cmId);
        }

        DB::table('cobranza')->where('id', $cobranzaId)->delete();
    }

    private function eliminarCajaMovimiento(int $cajaMovimientoId): void
    {
        DB::table('caja_movimiento_cuentacaja')->where('caja_movimiento_id', $cajaMovimientoId)->delete();
        DB::table('caja_movimiento_estado')->where('caja_movimiento_id', $cajaMovimientoId)->delete();
        DB::table('caja_movimiento_archivo')->where('caja_movimiento_id', $cajaMovimientoId)->delete();
        DB::table('cheque')->where('caja_movimiento_id', $cajaMovimientoId)->delete();
        DB::table('caja_movimiento')->where('id', $cajaMovimientoId)->delete();
    }

    private function eliminarAsientos(string $columna, int $valor): void
    {
        $asientoIds = DB::table('asiento')->where($columna, $valor)->pluck('id');
        foreach ($asientoIds as $asientoId) {
            DB::table('asiento_archivo')->where('asiento_id', $asientoId)->delete();
            Asiento::query()->find((int) $asientoId)?->delete();
        }
    }

    private function eliminarClienteCuentacorriente(int $clienteCuentacorrienteId): void
    {
        DB::table('cobranza_comprobante')->where('cliente_cuentacorriente_id', $clienteCuentacorrienteId)->delete();
        DB::table('cliente_cuentacorriente_aplicacion')
            ->where('cliente_cuentacorriente_id', $clienteCuentacorrienteId)
            ->orWhere('cliente_cuentacorriente_aplicado_id', $clienteCuentacorrienteId)
            ->delete();
        DB::table('cliente_cuentacorriente')->where('id', $clienteCuentacorrienteId)->delete();
    }
}
