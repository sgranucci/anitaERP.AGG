<?php

declare(strict_types=1);

namespace App\Support\Caja\Bingo;

use App\Models\Caja\Bingo\CierreParcialTurnoBingo;
use App\Models\Caja\Bingo\TurnoOperativoBingo;
use Carbon\Carbon;

final class BingoCierreTurnoReporteSupport
{
    /**
     * @return array<string, mixed>
     */
    public function datosComprobanteCierre(TurnoOperativoBingo $turno): array
    {
        $turno->loadMissing(['turno', 'jornada', 'usuarioHabilitado', 'usuarioCierre', 'empresa', 'cierresParciales']);

        $cartones = is_array($turno->cartones_rendicion_json) ? $turno->cartones_rendicion_json : [];
        $conceptos = $this->extraerConceptos($turno);
        $totalesCartones = $this->totalesDesdeCartones($cartones);

        return [
            'empresa' => $turno->empresa?->nombre ?? '',
            'turno' => $turno->turno?->nombre ?? '',
            'fecha_jornada' => $turno->jornada?->fecha_jornada?->format('d/m/Y') ?? '',
            'identificador_pc' => (string) $turno->identificador_pc,
            'habilitacion_en' => $turno->habilitacion_en?->format('d/m/Y H:i') ?? '',
            'cierre_en' => $turno->cierre_en?->format('d/m/Y H:i') ?? '',
            'usuario_habilitado' => $turno->usuarioHabilitado?->nombre ?? '',
            'usuario_cierre' => $turno->usuarioCierre?->nombre ?? '',
            'monto_habilitacion' => (float) ($turno->monto_habilitacion ?? 0),
            'monto_rendicion_turno' => (float) ($turno->monto_rendicion_turno ?? 0),
            'monto_rendicion_dia' => (float) ($turno->monto_rendicion_dia ?? 0),
            'total_cartones' => $totalesCartones['total'],
            'cant_cartones' => $totalesCartones['cant'],
            'saldo_final' => $this->resolverSaldoFinal($conceptos, (float) ($turno->monto_rendicion_turno ?? 0)),
            'redondeo' => (float) ($turno->redondeo ?? 0),
            'sobrante_faltante' => (float) ($turno->sobrante_faltante ?? 0),
            'vales' => (float) ($turno->vales ?? 0),
            'deposito' => (float) ($turno->deposito ?? 0),
            'numero_cierre' => (int) ($turno->numero_cierre ?? 0),
            'cartones' => $cartones,
            'conceptos' => $conceptos,
            'medios_contado' => is_array($turno->medios_contado_cierre_json) ? $turno->medios_contado_cierre_json : [],
            'observacion' => (string) ($turno->observacion_cierre ?? ''),
            'generado' => Carbon::now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datosComprobanteParcial(CierreParcialTurnoBingo $parcial): array
    {
        $parcial->loadMissing(['turnoOperativo.turno', 'turnoOperativo.jornada', 'turnoOperativo.empresa', 'turnoOperativo.usuarioHabilitado']);

        $turno = $parcial->turnoOperativo;

        return [
            'empresa' => $turno?->empresa?->nombre ?? '',
            'turno' => $turno?->turno?->nombre ?? '',
            'fecha_jornada' => $turno?->jornada?->fecha_jornada?->format('d/m/Y') ?? '',
            'numero_parcial' => (int) $parcial->numero_parcial,
            'identificador_pc' => (string) $parcial->identificador_pc,
            'total_rendicion_turno' => (float) ($parcial->total_rendicion_turno ?? 0),
            'usuario_habilitado' => $turno?->usuarioHabilitado?->nombre ?? '',
            'generado' => Carbon::now()->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extraerConceptos(TurnoOperativoBingo $turno): array
    {
        $payload = $turno->conceptos_rendicion_json ?? [];
        if (is_array($payload['lineas'] ?? null)) {
            return $payload['lineas'];
        }
        if (is_array($payload) && array_is_list($payload)) {
            return $payload;
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $cartones
     * @return array{total: float, cant: int}
     */
    private function totalesDesdeCartones(array $cartones): array
    {
        $cant = 0;
        $total = 0.0;

        foreach ($cartones as $linea) {
            if (! empty($linea['anulado'])) {
                continue;
            }
            $c = max(0, (int) ($linea['cantidad'] ?? 0));
            $precio = (float) ($linea['precio_unitario'] ?? 0);
            $cant += $c;
            $total = round($total + ($c * $precio), 2);
        }

        return ['total' => $total, 'cant' => $cant];
    }

    /**
     * @param  list<array<string, mixed>>  $conceptos
     */
    private function resolverSaldoFinal(array $conceptos, float $fallback): float
    {
        if ($conceptos === []) {
            return round($fallback, 2);
        }

        $ultima = end($conceptos);
        if (is_array($ultima) && isset($ultima['saldo_despues'])) {
            return round((float) $ultima['saldo_despues'], 2);
        }

        return round($fallback, 2);
    }
}
