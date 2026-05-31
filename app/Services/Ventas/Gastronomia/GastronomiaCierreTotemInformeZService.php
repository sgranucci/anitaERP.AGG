<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\CierreTotemJornadaGastronomia;
use App\Support\Ventas\Waitry\WaitryInformeZConciliacionSupport;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

final class GastronomiaCierreTotemInformeZService
{
    /**
     * @return array<string, mixed>
     */
    public function datosParaConciliacion(int $jornadaId): array
    {
        $cierre = $this->cierrePorJornada($jornadaId);
        $detalle = is_array($cierre->detalle_json) ? $cierre->detalle_json : [];
        $resumen = $detalle['resumen_totems'] ?? ['por_totem' => [], 'total_general' => []];

        $plantilla = WaitryInformeZConciliacionSupport::plantillaCarga(
            (int) $cierre->empresa_id,
            $resumen,
        );
        $informeZ = is_array($cierre->informe_z_json) ? $cierre->informe_z_json : null;
        $plantilla = WaitryInformeZConciliacionSupport::fusionarInformeZEnPlantilla($plantilla, $informeZ);

        $conciliacion = $informeZ !== null
            ? WaitryInformeZConciliacionSupport::conciliar($plantilla)
            : null;

        return [
            'jornada_id' => $jornadaId,
            'cierre_totem_id' => (int) $cierre->id,
            'fecha_jornada' => $cierre->jornada?->fecha_jornada?->format('d/m/Y') ?? '',
            'informe_z_cargado' => $informeZ !== null,
            'informe_z_en' => $informeZ['informe_z_en'] ?? null,
            'usuario_informe_z' => $informeZ['usuario_nombre'] ?? null,
            'totems' => $plantilla,
            'conciliacion' => $conciliacion,
            'tolerancia' => WaitryInformeZConciliacionSupport::toleranciaMonto(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload  totems: [{totem_id, lineas: [{tipo_waitry, monto}]}]
     * @return array<string, mixed>
     */
    public function guardarInformeZ(int $jornadaId, array $payload): array
    {
        $cierre = $this->cierrePorJornada($jornadaId);
        $detalle = is_array($cierre->detalle_json) ? $cierre->detalle_json : [];
        $resumen = $detalle['resumen_totems'] ?? ['por_totem' => [], 'total_general' => []];

        $plantilla = WaitryInformeZConciliacionSupport::plantillaCarga(
            (int) $cierre->empresa_id,
            $resumen,
        );

        $totemsPayload = $payload['totems'] ?? [];
        if (! is_array($totemsPayload)) {
            throw new InvalidArgumentException('Debe enviar los montos del Informe Z por tótem.');
        }

        $plantilla = $this->aplicarPayloadEnPlantilla($plantilla, $totemsPayload);
        $conciliacion = WaitryInformeZConciliacionSupport::conciliar($plantilla);

        $informeZ = [
            'totems' => array_map(function (array $bloque) {
                return [
                    'totem_id' => (int) ($bloque['totem_id'] ?? 0),
                    'lineas' => array_map(fn (array $ln) => [
                        'tipo_waitry' => $ln['tipo_waitry'] ?? null,
                        'monto' => round((float) ($ln['monto_informe_z'] ?? 0), 2),
                    ], $bloque['lineas'] ?? []),
                ];
            }, $plantilla),
            'informe_z_en' => now()->format('Y-m-d H:i:s'),
            'usuario_id' => Auth::id(),
            'usuario_nombre' => Auth::user()?->nombre ?? '',
            'conciliacion' => $conciliacion,
        ];

        $cierre->informe_z_json = $informeZ;
        $cierre->save();

        return [
            'ok' => true,
            'mensaje' => $conciliacion['ok']
                ? 'Informe Z registrado: cuadra con el sistema.'
                : 'Informe Z registrado: hay diferencias respecto al sistema.',
            'conciliacion' => $conciliacion,
            'informe_z_cargado' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $plantilla
     * @param  array<int, mixed>  $totemsPayload
     * @return list<array<string, mixed>>
     */
    private function aplicarPayloadEnPlantilla(array $plantilla, array $totemsPayload): array
    {
        $porId = [];
        foreach ($totemsPayload as $t) {
            if (! is_array($t)) {
                continue;
            }
            $tid = (int) ($t['totem_id'] ?? 0);
            if ($tid > 0) {
                $porId[$tid] = $t;
            }
        }

        foreach ($plantilla as &$bloque) {
            $tid = (int) ($bloque['totem_id'] ?? 0);
            $payload = $porId[$tid] ?? null;
            if ($payload === null) {
                continue;
            }
            $montosPorTipo = [];
            foreach ($payload['lineas'] ?? [] as $ln) {
                if (! is_array($ln)) {
                    continue;
                }
                $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($ln['tipo_waitry'] ?? null) ?? 'totem';
                $montosPorTipo[$tipo] = round((float) ($ln['monto'] ?? $ln['monto_informe_z'] ?? 0), 2);
            }
            foreach ($bloque['lineas'] as &$ln) {
                $tipo = WaitryMedioPagoCuentacajaSupport::normalizarTipo($ln['tipo_waitry'] ?? null) ?? 'totem';
                $ln['monto_informe_z'] = $montosPorTipo[$tipo] ?? 0.0;
            }
            unset($ln);
        }
        unset($bloque);

        return $plantilla;
    }

    private function cierrePorJornada(int $jornadaId): CierreTotemJornadaGastronomia
    {
        $cierre = CierreTotemJornadaGastronomia::query()
            ->with(['jornada', 'empresa'])
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();

        if ($cierre === null) {
            throw new InvalidArgumentException(
                'No hay cierre de tótem Waitry para esta jornada. Cierre la jornada primero.'
            );
        }

        return $cierre;
    }
}
