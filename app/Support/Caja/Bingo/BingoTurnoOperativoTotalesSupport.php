<?php

namespace App\Support\Caja\Bingo;

use App\Models\Caja\Bingo\TurnoOperativoBingo;
use Carbon\Carbon;

/**
 * Totales de rendición bingo por terminal (sin facturación POS).
 */
final class BingoTurnoOperativoTotalesSupport
{
    private const TOLERANCIA = 0.02;

    /**
     * @return array<string, mixed>
     */
    public static function calcular(
        string $identificadorPc,
        int $empresaId,
        string $fechaJornada,
        ?Carbon $desdeHabilitacion = null,
        ?Carbon $hastaInclusive = null,
    ): array {
        $query = TurnoOperativoBingo::query()
            ->where('identificador_pc', $identificadorPc)
            ->where('empresa_id', $empresaId)
            ->whereHas('jornada', fn ($q) => $q->whereDate('fecha_jornada', $fechaJornada));

        if ($desdeHabilitacion !== null) {
            $query->where('habilitacion_en', '>=', $desdeHabilitacion);
        }
        if ($hastaInclusive !== null) {
            $query->where('habilitacion_en', '<=', $hastaInclusive);
        }

        $turnos = $query->get();
        $totalRendicion = round($turnos->sum(fn (TurnoOperativoBingo $t) => (float) ($t->monto_rendicion_turno ?? 0)), 2);
        $totalHabilitacion = round($turnos->sum(fn (TurnoOperativoBingo $t) => (float) ($t->monto_habilitacion ?? 0)), 2);

        return [
            'total_rendicion' => $totalRendicion,
            'total_habilitacion' => $totalHabilitacion,
            'total_general' => $totalRendicion,
            'cantidad_turnos' => $turnos->count(),
            'conciliacion_ok' => true,
            'diferencia_cobranza' => 0.0,
            'total_cobrado' => $totalRendicion,
            'total_ventas_cobrables' => $totalRendicion,
            'medios_pago' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $totalesTurno
     */
    public static function cierreCuadraConAjustesManuales(
        array $totalesTurno,
        ?float $redondeo,
        ?float $sobranteFaltante,
        ?float $totalMediosContado = null,
    ): bool {
        $esperado = round((float) ($totalesTurno['total_general'] ?? 0), 2);
        $redondeo = round((float) ($redondeo ?? 0), 2);
        $sobrante = round((float) ($sobranteFaltante ?? 0), 2);

        if ($totalMediosContado === null) {
            return true;
        }

        $contado = round($totalMediosContado, 2);
        $ajustado = round($contado + $redondeo + $sobrante, 2);

        return abs($ajustado - $esperado) <= self::TOLERANCIA;
    }
}
