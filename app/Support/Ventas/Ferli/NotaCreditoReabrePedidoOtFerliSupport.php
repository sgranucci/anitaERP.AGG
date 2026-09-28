<?php

declare(strict_types=1);

namespace App\Support\Ventas\Ferli;

use App\Models\Ventas\Ordentrabajo_Tarea;
use App\Models\Ventas\Pedido_Combinacion;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Emision;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\PedidoEstadoCabeceraSupport;
use App\Support\Ventas\PedidoPickingFerliSupport;
use Illuminate\Support\Facades\Log;

/**
 * Ferli: NC total sobre FAC de OT/picking → libera la línea del pedido para refacturar.
 * Quita tarea FACTURADA (venta_id), restaura stock con el lote/OT asignado (picking o OT desde stock) y desmarca picking.
 */
final class NotaCreditoReabrePedidoOtFerliSupport
{
    /**
     * @param  array<string, mixed>|null  $opcionesEmision
     * @return array{aplicado: bool, tareas_borradas: int, picking_reabiertos: int, movimientos_revertidos: int, motivo?: string}
     */
    public static function alGrabarNc(
        int $ventaOrigenId,
        float $totalNcAbsoluto,
        $tipotransaccion,
        ?array $opcionesEmision = null,
        int $ventaNcId = 0,
        ?string $fechaNc = null
    ): array {
        $vacio = ['aplicado' => false, 'tareas_borradas' => 0, 'picking_reabiertos' => 0, 'movimientos_revertidos' => 0];

        if (! EntornoEmpresaSupport::esFerli()) {
            return $vacio + ['motivo' => 'no_ferli'];
        }

        if (! is_object($tipotransaccion) || ! method_exists($tipotransaccion, 'esNotaCredito') || ! $tipotransaccion->esNotaCredito()) {
            return $vacio + ['motivo' => 'no_nc'];
        }

        $ventaOrigen = $ventaOrigenId > 0 ? Venta::query()->find($ventaOrigenId) : null;
        if (! $ventaOrigen || ! self::esNcTotal($ventaOrigen, $totalNcAbsoluto, $opcionesEmision)) {
            return self::reabrirLineasPickingDeNcParcial($ventaNcId, $vacio);
        }

        $tareaFacturadaId = (int) config('consprod.TAREA_FACTURADA');

        $movimientosRevertidos = PedidoPickingFerliSupport::revertirConsumoStockPorVenta(
            $ventaOrigenId,
            $ventaNcId,
            $fechaNc
        );

        $tareasBorradas = EloquentAuditDeleteSupport::each(
            Ordentrabajo_Tarea::query()
                ->where('venta_id', $ventaOrigenId)
                ->where('tarea_id', $tareaFacturadaId)
        );

        $pickingReabiertos = PedidoPickingFerliSupport::reabrirFacturadoPorVenta($ventaOrigenId);

        if ($tareasBorradas > 0 || $pickingReabiertos > 0) {
            $pedidoId = (int) ($ventaOrigen->pedido_id ?? 0);
            if ($pedidoId > 0) {
                PedidoEstadoCabeceraSupport::refrescar($pedidoId);
            }
        }

        try {
            Log::info('ferli.nc.reabre_pedido_ot', [
                'venta_origen_id' => $ventaOrigenId,
                'venta_nc_id' => $ventaNcId,
                'tareas_borradas' => $tareasBorradas,
                'picking_reabiertos' => $pickingReabiertos,
                'movimientos_revertidos' => $movimientosRevertidos,
            ]);
        } catch (\Throwable) {
            // No tumbar la NC por fallo de log (permisos storage).
        }

        return [
            'aplicado' => $tareasBorradas > 0 || $pickingReabiertos > 0 || $movimientosRevertidos > 0,
            'tareas_borradas' => $tareasBorradas,
            'picking_reabiertos' => $pickingReabiertos,
            'movimientos_revertidos' => $movimientosRevertidos,
        ];
    }

    /**
     * NC parcial: si una línea del picking quedó acreditada por completo, vuelve a pendiente
     * en el mismo picking. No se desarma el picking ni se tocan las líneas que siguen facturadas.
     *
     * @param  array{aplicado: bool, tareas_borradas: int, picking_reabiertos: int, movimientos_revertidos: int}  $vacio
     * @return array{aplicado: bool, tareas_borradas: int, picking_reabiertos: int, movimientos_revertidos: int, motivo?: string}
     */
    private static function reabrirLineasPickingDeNcParcial(int $ventaNcId, array $vacio): array
    {
        $ids = self::idsLineasPickingAcreditadasPorCompleto($ventaNcId);
        $reabiertos = PedidoPickingFerliSupport::reabrirFacturadoDeLineas($ids);

        return [
            'aplicado' => $reabiertos > 0,
            'tareas_borradas' => 0,
            'picking_reabiertos' => $reabiertos,
            'movimientos_revertidos' => 0,
            'motivo' => $reabiertos > 0 ? 'nc_parcial_linea' : 'nc_parcial',
        ] + $vacio;
    }

    /**
     * Líneas de picking cuya cantidad facturada quedó cubierta por esta NC.
     *
     * @return list<int>
     */
    public static function idsLineasPickingAcreditadasPorCompleto(int $ventaNcId): array
    {
        if ($ventaNcId <= 0) {
            return [];
        }

        $cantidadesNc = [];
        $emisionesNc = Venta_Emision::query()
            ->where('venta_id', $ventaNcId)
            ->where('pedido_combinacion_id', '>', 0)
            ->get(['pedido_combinacion_id', 'cantidad']);

        foreach ($emisionesNc as $emision) {
            $id = (int) $emision->pedido_combinacion_id;
            $cantidadesNc[$id] = ($cantidadesNc[$id] ?? 0) + abs((float) $emision->cantidad);
        }

        if ($cantidadesNc === []) {
            return [];
        }

        $lineas = Pedido_Combinacion::query()
            ->whereIn('id', array_keys($cantidadesNc))
            ->where('picking_facturado', PedidoPickingFerliSupport::FACTURADO)
            ->get(['id', 'picking_venta_id', 'cantidad']);

        $ids = [];
        foreach ($lineas as $linea) {
            $facturada = self::cantidadFacturadaLinea($linea);
            if (self::cantidadCubierta((float) ($cantidadesNc[(int) $linea->id] ?? 0), $facturada)) {
                $ids[] = (int) $linea->id;
            }
        }

        return $ids;
    }

    /**
     * @param  Pedido_Combinacion  $linea
     */
    private static function cantidadFacturadaLinea($linea): float
    {
        $ventaId = (int) ($linea->picking_venta_id ?? 0);
        if ($ventaId > 0) {
            $cantidad = (float) Venta_Emision::query()
                ->where('venta_id', $ventaId)
                ->where('pedido_combinacion_id', (int) $linea->id)
                ->sum('cantidad');
            if (abs($cantidad) > 0.0001) {
                return abs($cantidad);
            }
        }

        return abs((float) ($linea->cantidad ?? 0));
    }

    public static function cantidadCubierta(float $acreditada, float $facturada): bool
    {
        return $facturada > 0.0001 && ($acreditada + 0.0001) >= $facturada;
    }

    /**
     * @param  array<string, mixed>|null  $opcionesEmision
     */
    private static function esNcTotal(Venta $ventaOrigen, float $totalNcAbsoluto, ?array $opcionesEmision): bool
    {
        $anul = strtoupper(trim((string) (
            $opcionesEmision['fce_anulacion']
            ?? $opcionesEmision['fceAnulacion']
            ?? ''
        )));
        if ($anul === 'S') {
            return true;
        }

        $totalFac = abs((float) $ventaOrigen->total);
        if ($totalFac <= 0.0) {
            return $totalNcAbsoluto > 0.0;
        }

        return ($totalNcAbsoluto / $totalFac) >= 0.999;
    }
}
