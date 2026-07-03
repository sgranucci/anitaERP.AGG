<?php

declare(strict_types=1);

namespace App\Support\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\TurnoOperativoEstacionamiento;
use App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Services\Caja\RendicionEstacionamientoJornadaPresentacionService;

/**
 * Visibilidad y reglas de negocio para cambiar medio de pago en Facturas del día estacionamiento.
 */
final class EstacionamientoFacturaMedioPagoUiSupport
{
    public static function puedeCambiarMedioPago(
        VentaEstacionamientoEmision $emision,
        bool $tieneCobranza,
    ): bool {
        if (! can('cambiar-medio-pago-estacionamiento-facturas-dia', false)) {
            return false;
        }

        if (! $tieneCobranza || $emision->venta_factura_origen_id !== null) {
            return false;
        }

        return self::evaluarTurnoEmision($emision)['permite'];
    }

    /**
     * @return array{permite: bool, motivo: ?string}
     */
    public static function evaluarTurnoEmision(VentaEstacionamientoEmision $emision): array
    {
        $emision->loadMissing(['turnoOperativo.turno']);

        $turno = $emision->turnoOperativo;
        if (! $turno instanceof TurnoOperativoEstacionamiento) {
            return [
                'permite' => false,
                'motivo' => 'La factura no está asociada a un turno operativo habilitado.',
            ];
        }

        if ($turno->estado !== TurnoOperativoEstacionamiento::ESTADO_HABILITADO || $turno->cierre_en !== null) {
            $etiquetaTurno = trim((string) ($turno->turno?->nombre ?? ''));
            $sufijoTurno = $etiquetaTurno !== '' ? ' ('.$etiquetaTurno.')' : '';

            return [
                'permite' => false,
                'motivo' => 'El turno operativo de esta factura (#'.$turno->id.$sufijoTurno.') ya fue cerrado. '
                    .'No puede alterar el medio de pago de comprobantes incluidos en un cierre de turno.',
            ];
        }

        if (RendicionEstacionamientoCaja::query()
            ->where('turno_operativo_estacionamiento_id', (int) $turno->id)
            ->exists()) {
            return [
                'permite' => false,
                'motivo' => 'Este turno ya tiene una rendición registrada en caja. '
                    .'No puede modificar medios de pago de facturas ya rendidas.',
            ];
        }

        $jornadaId = (int) ($turno->jornada_estacionamiento_id ?? 0);
        if ($jornadaId > 0
            && app(RendicionEstacionamientoJornadaPresentacionService::class)
                ->jornadaPresentadaBloqueaRendicionesTurno($jornadaId)) {
            return [
                'permite' => false,
                'motivo' => 'La jornada ya fue presentada en caja. '
                    .'No puede modificar medios de pago de comprobantes de esa jornada.',
            ];
        }

        return ['permite' => true, 'motivo' => null];
    }
}
