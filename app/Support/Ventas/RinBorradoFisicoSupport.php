<?php

namespace App\Support\Ventas;

use App\Models\Stock\Articulo_Movimiento;
use App\Models\Ventas\Ordentrabajo_Tarea;
use App\Models\Ventas\Pedido_Combinacion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaAnitaReplica;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Stock\ArticuloMovimientoEliminacionSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Borra un remito interno (RIN) y libera la orden para facturarla de nuevo.
 * La venta, la tarea facturada y la línea de picking quedan en audits.
 */
final class RinBorradoFisicoSupport
{
    public static function es(?object $venta): bool
    {
        if ($venta === null) {
            return false;
        }

        $abreviatura = strtoupper(trim((string) (data_get($venta, 'tipotransacciones.abreviatura') ?? '')));
        if ($abreviatura === 'RIN') {
            return true;
        }

        return str_starts_with(strtoupper(trim((string) ($venta->codigo ?? ''))), 'RIN ');
    }

    public static function borrar(int $ventaId): string
    {
        if ($ventaId <= 0) {
            throw new RuntimeException('Remito interno inexistente.');
        }

        return DB::transaction(function () use ($ventaId) {
            $venta = Venta::query()
                ->with('tipotransacciones')
                ->lockForUpdate()
                ->find($ventaId);

            if ($venta === null || ! self::es($venta)) {
                throw new RuntimeException('Solo se puede borrar un remito interno (RIN).');
            }

            if (Venta::query()->where('venta_origen_id', $venta->id)->exists()) {
                throw new RuntimeException('Este RIN tiene otro comprobante asociado. No se puede borrar.');
            }

            if (DB::table('cobranza')->where('venta_id', $venta->id)->exists()
                || DB::table('caja_movimiento')->where('venta_id', $venta->id)->exists()) {
                throw new RuntimeException('Este RIN tiene movimientos de caja. No se puede borrar.');
            }

            $codigo = (string) $venta->codigo;
            $movimientos = (int) Articulo_Movimiento::query()->where('venta_id', $venta->id)->count();

            $tareas = EloquentAuditDeleteSupport::each(
                Ordentrabajo_Tarea::query()->where('venta_id', $venta->id)
            );

            $pedidos = [];
            $lineas = Pedido_Combinacion::query()
                ->where('picking_venta_id', $venta->id)
                ->get();
            foreach ($lineas as $linea) {
                $linea->picking_facturado = PedidoPickingFerliSupport::NO_MARCADO;
                $linea->picking_venta_id = null;
                $linea->save();
                $pedidoId = (int) ($linea->pedido_id ?? 0);
                if ($pedidoId > 0) {
                    $pedidos[$pedidoId] = $pedidoId;
                }
            }

            foreach ($pedidos as $pedidoId) {
                PedidoEstadoCabeceraSupport::refrescar($pedidoId);
            }

            VentaAnitaReplica::query()->where('venta_id', $venta->id)->delete();

            ArticuloMovimientoEliminacionSupport::eliminarPorVentaId((int) $venta->id);

            $venta->delete();

            Log::info('ventas.rin.borrado_fisico', [
                'venta_id' => $ventaId,
                'codigo' => $codigo,
                'usuario_id' => Auth::id(),
                'tareas_facturadas' => $tareas,
                'lineas_picking' => $lineas->count(),
                'movimientos_stock' => $movimientos,
            ]);

            return $codigo;
        });
    }
}
