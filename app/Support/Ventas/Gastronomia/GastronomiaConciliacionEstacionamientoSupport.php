<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Services\Caja\RendicionEstacionamientoAuditoriaAnitaService;

/**
 * Conciliación estacionamiento por PV (ERP vs rendgastro Anita), circuito separado del salón gastronómico.
 */
final class GastronomiaConciliacionEstacionamientoSupport
{
    public function __construct(
        private readonly RendicionEstacionamientoAuditoriaAnitaService $auditoriaService,
    ) {
    }

    /**
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   totales: array{ventas_erp: float, rendgastro_z: float, cantidad: int}
     * }
     */
    public function filasReporte(
        int $empresaId,
        string $fechaJornada,
        float $tolerancia,
        bool $jornadaAbierta,
    ): array {
        if ($jornadaAbierta || ! filter_var(config('rendicion_estacionamiento_anita.sincronizar', true), FILTER_VALIDATE_BOOLEAN)) {
            return [
                'filas' => [],
                'totales' => ['ventas_erp' => 0.0, 'rendgastro_z' => 0.0, 'cantidad' => 0],
            ];
        }

        try {
            $informe = $this->auditoriaService->auditarFechaJornada($empresaId, $fechaJornada, $tolerancia);
        } catch (\Throwable) {
            return [
                'filas' => [],
                'totales' => ['ventas_erp' => 0.0, 'rendgastro_z' => 0.0, 'cantidad' => 0],
            ];
        }

        $filas = [];
        $sumErp = 0.0;
        $sumRendg = 0.0;

        foreach ($informe['filas'] ?? [] as $filaAudit) {
            $estadoRaw = (string) ($filaAudit['estado'] ?? '');
            if ($estadoRaw === 'vending_omitido' || $estadoRaw === 'sin_ventas_erp') {
                continue;
            }

            $erpZ = round((float) ($filaAudit['erp_z'] ?? 0), 2);
            $erpNc = round((float) ($filaAudit['erp_nc'] ?? 0), 2);
            $erpNeto = round($erpZ - $erpNc, 2);
            $anitaZ = $filaAudit['anita_z'] ?? null;
            $anitaNc = round((float) ($filaAudit['anita_nc'] ?? 0), 2);
            $rendgNeto = $anitaZ !== null ? round((float) $anitaZ - $anitaNc, 2) : null;
            $diffRendg = $rendgNeto !== null ? round($erpNeto - $rendgNeto, 2) : null;

            if ($erpNeto <= $tolerancia && ($rendgNeto === null || $rendgNeto <= $tolerancia)) {
                continue;
            }

            $estado = match ($estadoRaw) {
                'ok' => 'OK',
                'sin_anita' => 'SIN RENDG',
                default => 'DIF',
            };
            if ($estado === 'OK' && $diffRendg !== null && abs($diffRendg) > $tolerancia) {
                $estado = 'DIF rendg';
            }

            $codigoPv = (string) ($filaAudit['puntoventa'] ?? '—');
            $filas[] = GastronomiaConciliacionEstadoSupport::aplicarEstadosEnFila([
                'tipo_fila' => 'estacionamiento_pv',
                'circuito' => 'ESTACIONAMIENTO',
                'tipo_pv' => 'ESTACIONAMIENTO',
                'identificador_pc' => 'ESTAC-'.$codigoPv,
                'pv_codigo' => $codigoPv,
                'descripcion_pc' => 'Estacionamiento PV '.$codigoPv.' (suc '.((int) ($filaAudit['sucursal'] ?? 0)).')',
                'pv_cae' => $codigoPv,
                'pv_caea' => '—',
                'ventas_erp_cae' => $erpZ,
                'ventas_erp_caea' => 0.0,
                'ventas_erp_bruto' => $erpZ,
                'notas_credito_erp' => $erpNc,
                'ventas_erp' => $erpNeto,
                'ventas_anita_cae' => $anitaZ !== null ? (float) $anitaZ : 0.0,
                'ventas_anita_caea' => 0.0,
                'ventas_anita' => $rendgNeto ?? 0.0,
                'rendgastro_z' => $anitaZ,
                'notas_credito_rendg' => $anitaNc,
                'rendgastro_neto' => $rendgNeto,
                'diff_erp_anita' => 0.0,
                'diff_erp_rendg' => $diffRendg,
                'estado' => $estado,
                'cantidad_facturas_erp' => (int) ($filaAudit['cantidad_facturas_erp'] ?? 0),
                'es_estacionamiento_pv' => true,
            ], $tolerancia);

            $sumErp += $erpNeto;
            if ($rendgNeto !== null) {
                $sumRendg += $rendgNeto;
            }
        }

        return [
            'filas' => $filas,
            'totales' => [
                'ventas_erp' => round($sumErp, 2),
                'rendgastro_z' => round($sumRendg, 2),
                'cantidad' => count($filas),
            ],
        ];
    }

    /**
     * @param  array{ventas_erp: float, rendgastro_z: float, cantidad: int}  $totales
     * @return array<string, mixed>
     */
    public function filaTotalEstacionamiento(array $totales, bool $jornadaAbierta, float $tolerancia): array
    {
        $erp = round((float) ($totales['ventas_erp'] ?? 0), 2);
        $rendg = $jornadaAbierta ? null : round((float) ($totales['rendgastro_z'] ?? 0), 2);
        $diff = $rendg !== null ? round($erp - $rendg, 2) : null;

        return GastronomiaConciliacionEstadoSupport::aplicarEstadosEnFila([
            'tipo_fila' => 'total_estacionamiento',
            'circuito' => 'ESTACIONAMIENTO',
            'identificador_pc' => 'TOTAL-ESTACIONAMIENTO',
            'tipo_pv' => 'TOTAL',
            'pv_codigo' => '—',
            'descripcion_pc' => 'Total estacionamiento (ERP vs rendgastro por PV)',
            'pv_cae' => '',
            'pv_caea' => '',
            'ventas_erp_cae' => 0.0,
            'ventas_erp_caea' => 0.0,
            'ventas_erp' => $erp,
            'ventas_anita_cae' => 0.0,
            'ventas_anita_caea' => 0.0,
            'ventas_anita' => $rendg ?? 0.0,
            'rendgastro_z' => $rendg,
            'rendgastro_neto' => $rendg,
            'diff_erp_anita' => 0.0,
            'diff_erp_rendg' => $diff,
            'cantidad_facturas_erp' => (int) ($totales['cantidad'] ?? 0),
            'jornada_abierta' => $jornadaAbierta,
            'es_total' => true,
            'es_total_estacionamiento' => true,
        ], $tolerancia);
    }
}
