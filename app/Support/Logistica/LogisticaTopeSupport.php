<?php

namespace App\Support\Logistica;

use App\Models\Logistica\LogisticaCentrocostoTope;
use App\Models\Logistica\LogisticaParametro;
use App\Models\Logistica\SolicitudLogistica;

final class LogisticaTopeSupport
{
    /**
     * @return array{estado: string, observacion: ?string}
     */
    public static function resolverInsumos(int $centrocostoId, float $total): array
    {
        $tope = (float) (LogisticaCentrocostoTope::query()
            ->where('centrocosto_id', $centrocostoId)
            ->value('monto_mensual') ?? 0);
        if ($tope > 0) {
            $consumido = (float) SolicitudLogistica::query()
                ->where('centrocosto_id', $centrocostoId)
                ->whereYear('fecha', now()->year)
                ->whereMonth('fecha', now()->month)
                ->where('estado', '!=', 'rechazada')
                ->sum('total_estimado');
            if (($consumido + $total) > $tope) {
                return [
                    'estado' => 'pendiente_aprobacion',
                    'observacion' => 'Supera el tope mensual del centro de costo.',
                ];
            }
        }

        $umbral = LogisticaParametro::montoAprobacion();
        if ($umbral > 0 && $total > $umbral) {
            return [
                'estado' => 'pendiente_aprobacion',
                'observacion' => 'Supera el monto que pide aprobación.',
            ];
        }

        return ['estado' => 'enviada', 'observacion' => null];
    }
}
