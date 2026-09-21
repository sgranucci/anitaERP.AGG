<?php

declare(strict_types=1);

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\TurnoOperativoLocal;

/**
 * Visibilidad y reglas para cambiar medio de pago en Facturas Local.
 */
final class FacturacionLocalFacturaMedioPagoUiSupport
{
    public static function puedeCambiarMedioPago(
        FacturacionLocalEmision $emision,
        bool $tieneCobranza,
        ?int $ventaIdViendo = null,
    ): bool {
        if (! can('cambiar-medio-pago-facturacion-local', false)) {
            return false;
        }

        if (! $tieneCobranza) {
            return false;
        }

        $ventaId = $ventaIdViendo ?? (int) $emision->venta_id;
        if (self::esComprobanteNotaCredito($emision, $ventaId)) {
            return false;
        }

        return self::evaluarTurnoEmision($emision)['permite'];
    }

    public static function esComprobanteNotaCredito(FacturacionLocalEmision $emision, int $ventaId): bool
    {
        $ncId = (int) ($emision->venta_nc_id ?? 0);

        return $ncId > 0 && $ncId === $ventaId;
    }

    /**
     * @return array{permite: bool, motivo: ?string}
     */
    public static function evaluarTurnoEmision(FacturacionLocalEmision $emision): array
    {
        $emision->loadMissing(['turno.turnoLocal']);

        $turno = $emision->turno;
        if (! $turno instanceof TurnoOperativoLocal) {
            return [
                'permite' => false,
                'motivo' => 'La factura no está asociada a un turno operativo del local.',
            ];
        }

        if ($turno->estado !== TurnoOperativoLocal::ESTADO_ABIERTO || $turno->cierre_en !== null) {
            $etiquetaTurno = trim((string) ($turno->turnoLocal?->nombre ?? ''));
            $sufijoTurno = $etiquetaTurno !== '' ? ' ('.$etiquetaTurno.')' : '';

            return [
                'permite' => false,
                'motivo' => 'El turno operativo de esta factura (#'.$turno->id.$sufijoTurno.') ya fue cerrado. '
                    .'No puede alterar el medio de pago de comprobantes incluidos en un cierre de turno.',
            ];
        }

        return ['permite' => true, 'motivo' => null];
    }
}
