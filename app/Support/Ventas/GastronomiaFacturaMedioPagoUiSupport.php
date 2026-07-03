<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Caja\RendicionGastronomiaJornadaPresentacionService;
use Carbon\Carbon;

/**
 * Visibilidad y reglas de negocio para cambiar medio de pago en Facturas del día gastronomía.
 */
final class GastronomiaFacturaMedioPagoUiSupport
{
    public static function puedeCambiarMedioPago(
        VentaGastronomiaEmision $emision,
        bool $tieneCobranza,
    ): bool {
        if (! can('cambiar-medio-pago-gastronomia-facturas-dia', false)) {
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
    public static function evaluarTurnoEmision(VentaGastronomiaEmision $emision): array
    {
        $turno = self::resolverTurnoOperativoDeEmision($emision);

        if (! $turno instanceof TurnoOperativoGastronomia) {
            return [
                'permite' => false,
                'motivo' => 'La factura no está asociada a un turno operativo habilitado.',
            ];
        }

        if ($turno->estado !== TurnoOperativoGastronomia::ESTADO_HABILITADO || $turno->cierre_en !== null) {
            $etiquetaTurno = trim((string) ($turno->turno?->nombre ?? ''));
            $sufijoTurno = $etiquetaTurno !== '' ? ' ('.$etiquetaTurno.')' : '';

            return [
                'permite' => false,
                'motivo' => 'El turno operativo de esta factura (#'.$turno->id.$sufijoTurno.') ya fue cerrado. '
                    .'No puede alterar el medio de pago de comprobantes incluidos en un cierre de turno.',
            ];
        }

        if (RendicionGastronomiaCaja::query()
            ->where('turno_operativo_gastronomia_id', (int) $turno->id)
            ->exists()) {
            return [
                'permite' => false,
                'motivo' => 'Este turno ya tiene una rendición registrada en caja. '
                    .'No puede modificar medios de pago de facturas ya rendidas.',
            ];
        }

        $jornadaId = (int) ($turno->jornada_gastronomia_id ?? 0);
        if ($jornadaId > 0
            && app(RendicionGastronomiaJornadaPresentacionService::class)
                ->jornadaPresentadaBloqueaRendicionesTurno($jornadaId)) {
            return [
                'permite' => false,
                'motivo' => 'La jornada ya fue presentada en caja. '
                    .'No puede modificar medios de pago de comprobantes de esa jornada.',
            ];
        }

        return ['permite' => true, 'motivo' => null];
    }

    public static function resolverTurnoOperativoDeEmision(VentaGastronomiaEmision $emision): ?TurnoOperativoGastronomia
    {
        $emision->loadMissing(['venta', 'configuracionPuntoventa']);

        $venta = $emision->venta;
        if ($venta?->created_at === null) {
            return null;
        }

        $empresaId = GastronomiaNotaCreditoUiSupport::empresaIdDesdeEmision($emision);
        $pc = trim((string) ($emision->identificador_pc ?? ''));
        if ($empresaId <= 0 || $pc === '') {
            return null;
        }

        $fechaJornada = $venta->fechajornada !== null
            ? Carbon::parse($venta->fechajornada)->format('Y-m-d')
            : ($venta->fecha !== null ? Carbon::parse($venta->fecha)->format('Y-m-d') : null);
        if ($fechaJornada === null) {
            return null;
        }

        $jornadaId = (int) (JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->value('id') ?? 0);
        if ($jornadaId <= 0) {
            return null;
        }

        $ts = $venta->created_at;

        $turnos = TurnoOperativoGastronomia::query()
            ->with('turno')
            ->where('identificador_pc', $pc)
            ->where('jornada_gastronomia_id', $jornadaId)
            ->whereIn('estado', [
                TurnoOperativoGastronomia::ESTADO_CERRADO,
                TurnoOperativoGastronomia::ESTADO_HABILITADO,
            ])
            ->whereNotNull('habilitacion_en')
            ->where('habilitacion_en', '<=', $ts)
            ->orderByDesc('habilitacion_en')
            ->get();

        foreach ($turnos as $turno) {
            if ($turno->cierre_en === null || $ts <= $turno->cierre_en) {
                return $turno;
            }
        }

        return null;
    }
}
