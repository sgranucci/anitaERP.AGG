<?php

declare(strict_types=1);

namespace App\Support\Ventas\Ferli;

use App\Models\Ventas\Ordentrabajo_Tarea;
use App\Models\Ventas\Venta;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\PedidoPickingFerliSupport;
use Illuminate\Support\Facades\Log;

/**
 * Ferli: NC total sobre FAC de OT/picking → libera la línea del pedido para refacturar.
 * Quita tarea FACTURADA (venta_id) y marca picking_facturado de esa venta.
 */
final class NotaCreditoReabrePedidoOtFerliSupport
{
    /**
     * @param  array<string, mixed>|null  $opcionesEmision
     * @return array{aplicado: bool, tareas_borradas: int, picking_reabiertos: int, motivo?: string}
     */
    public static function alGrabarNc(
        int $ventaOrigenId,
        float $totalNcAbsoluto,
        $tipotransaccion,
        ?array $opcionesEmision = null
    ): array {
        $vacio = ['aplicado' => false, 'tareas_borradas' => 0, 'picking_reabiertos' => 0];

        if (! EntornoEmpresaSupport::esFerli()) {
            return $vacio + ['motivo' => 'no_ferli'];
        }

        if ($ventaOrigenId <= 0) {
            return $vacio + ['motivo' => 'sin_venta_origen'];
        }

        if (! is_object($tipotransaccion) || ! method_exists($tipotransaccion, 'esNotaCredito') || ! $tipotransaccion->esNotaCredito()) {
            return $vacio + ['motivo' => 'no_nc'];
        }

        $ventaOrigen = Venta::query()->find($ventaOrigenId);
        if (! $ventaOrigen) {
            return $vacio + ['motivo' => 'venta_origen_inexistente'];
        }

        if (! self::esNcTotal($ventaOrigen, $totalNcAbsoluto, $opcionesEmision)) {
            return $vacio + ['motivo' => 'nc_parcial'];
        }

        $tareaFacturadaId = (int) config('consprod.TAREA_FACTURADA');

        $tareasBorradas = EloquentAuditDeleteSupport::each(
            Ordentrabajo_Tarea::query()
                ->where('venta_id', $ventaOrigenId)
                ->where('tarea_id', $tareaFacturadaId)
        );

        $pickingReabiertos = PedidoPickingFerliSupport::reabrirFacturadoPorVenta($ventaOrigenId);

        try {
            Log::info('ferli.nc.reabre_pedido_ot', [
                'venta_origen_id' => $ventaOrigenId,
                'tareas_borradas' => $tareasBorradas,
                'picking_reabiertos' => $pickingReabiertos,
            ]);
        } catch (\Throwable) {
            // No tumbar la NC por fallo de log (permisos storage).
        }

        return [
            'aplicado' => $tareasBorradas > 0 || $pickingReabiertos > 0,
            'tareas_borradas' => $tareasBorradas,
            'picking_reabiertos' => $pickingReabiertos,
        ];
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
