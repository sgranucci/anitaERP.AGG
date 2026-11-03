<?php

declare(strict_types=1);

namespace App\Support\Tesoreria\PosicionBancaria;

use App\Services\Compras\ProyeccionPagosReporteService;
use App\Support\Compras\ProyeccionPagosReporteFiltros;
use App\Support\Compras\ProyeccionPagosTramosSupport;
use App\Support\Finanzas\FinanzaPosicionHojaSupport;
use Carbon\Carbon;

/**
 * Proyección de pagos en la posición bancaria (hoja Macro).
 *
 * TRF PROYECCION del día es el total de la proyección de pagos con vencimiento
 * en esa fecha o anterior y medio de pago transferencia. Los cheques de la
 * posición salen de las solapas Cheques (retenidos, tránsito y diferidos por banco),
 * no de esta proyección.
 */
final class PosicionBancariaProyeccionPagosSupport
{
    private const HOJA = 'Macro';

    /**
     * @return array<string, array<string, list<array{
     *   detalle: string, B: ?float, C: ?float, D: ?float, estado: string, tono: string, rubro: string
     * }>>>
     */
    public function lineasPorFechaYHoja(Carbon $desde, Carbon $hasta): array
    {
        if ($hasta->copy()->startOfDay()->lt($desde->copy()->startOfDay())) {
            return [];
        }
        $fecha = $desde->copy()->startOfDay()->toDateString();
        $totales = [];

        foreach ($this->movimientos($fecha) as $mov) {
            if (! $this->esTransferencia((string) ($mov['valores']['medio_pago'] ?? ''))) {
                continue;
            }
            $vencimiento = $this->fecha($mov['valores']['fecha_vencimiento'] ?? $mov['fechavencimiento'] ?? null);
            if ($vencimiento === null || $vencimiento > $fecha) {
                continue;
            }
            $col = FinanzaPosicionHojaSupport::COLUMNA_POR_EMPRESA[(int) ($mov['empresa_id'] ?? 0)] ?? null;
            if ($col === null) {
                continue;
            }
            $importe = round(-1 * (float) ($mov['valores']['total_adeudado'] ?? 0), 2);
            if (abs($importe) < 0.01) {
                continue;
            }
            $totales[$col] = round(($totales[$col] ?? 0) + $importe, 2);
        }

        $fila = [
            'detalle' => 'TRF PROYECCION',
            'B' => isset($totales['B']) ? $totales['B'] : null,
            'C' => isset($totales['C']) ? $totales['C'] : null,
            'D' => isset($totales['D']) ? $totales['D'] : null,
            'estado' => 'Proyección',
            'tono' => 'proyeccion',
            'rubro' => '',
        ];
        if ($fila['B'] === null && $fila['C'] === null && $fila['D'] === null) {
            return [];
        }

        return [
            $fecha => [
                self::HOJA => [$fila],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function movimientos(string $fecha): array
    {
        $filtros = [
            'fecha_base' => $fecha,
            'empresa_ids' => [1, 2, 3],
            'tipo_informe' => ProyeccionPagosReporteFiltros::INFORME_A_VENCER,
            'modo_moneda' => ProyeccionPagosReporteFiltros::MONEDA_TODAS,
            'moneda_id' => (int) config('cotizacion.ID_MONEDA_DEFAULT', 1),
            'incluir_adelantos' => false,
            'estado_aprobacion' => ProyeccionPagosReporteFiltros::APROBACION_TODOS,
        ];
        $tramos = ProyeccionPagosTramosSupport::construir($filtros);

        return app(ProyeccionPagosReporteService::class)->mapearMovimientos($filtros, $tramos);
    }

    private function esTransferencia(string $medio): bool
    {
        $u = mb_strtoupper(trim($medio));

        return $u === 'T' || $u === 'TRF' || str_starts_with($u, 'TRANS');
    }

    private function fecha(mixed $valor): ?string
    {
        $texto = substr(trim((string) $valor), 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) || str_starts_with($texto, '0000')) {
            return null;
        }

        return $texto;
    }
}
