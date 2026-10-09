<?php

declare(strict_types=1);

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalTurnoService;

/**
 * Visibilidad del botón «Generar NC» en Facturas Local.
 */
final class FacturacionLocalNotaCreditoUiSupport
{
    public static function puedeGenerarNotaCredito(
        FacturacionLocalEmision $emision,
        Venta $venta,
        ?int $ventaIdViendo = null,
    ): bool {
        if (! can('generar-nota-credito-facturacion-local', false)) {
            return false;
        }

        // Sin fila persistida (Facturante / marketplace histórico): se consulta, no se acredita desde acá.
        if (! $emision->exists) {
            return false;
        }

        $ventaId = $ventaIdViendo ?? (int) $venta->id;
        if (FacturacionLocalFacturaMedioPagoUiSupport::esComprobanteNotaCredito($emision, $ventaId)) {
            return false;
        }

        if ((int) $emision->venta_id !== $ventaId) {
            return false;
        }

        if (! empty($emision->es_ticket_regalo)) {
            return false;
        }

        if ((float) ($venta->total ?? 0) < 0.01) {
            return false;
        }

        $acreditado = FacturacionLocalNotasCreditoFacturaSupport::totalAcreditado((int) $emision->venta_id);
        $disponible = FacturacionLocalNotasCreditoFacturaSupport::disponible((float) ($venta->total ?? 0), $acreditado);
        if ($disponible < FacturacionLocalNotasCreditoFacturaSupport::TOLERANCIA) {
            return false;
        }

        $tipo = $venta->tipotransacciones;
        if ($tipo && $tipo->signo !== 'S') {
            return false;
        }

        $localId = (int) ($emision->local_venta_id ?? 0);
        if ($localId <= 0) {
            return false;
        }

        $turnoAbierto = app(FacturacionLocalTurnoService::class)->turnoAbierto($localId);

        return $turnoAbierto !== null;
    }
}
