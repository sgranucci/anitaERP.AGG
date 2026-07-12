<?php

namespace App\Support\Caja\Bingo;

use App\Models\Caja\Bingo\BingoConceptoRendicion;

/**
 * Cálculo de rendición bingo según cartones y conceptos configurados.
 *
 * Referencia manual (scan 01/07/2026 — planilla sala bingo):
 * - Cartones: $2.000 / $3.000 / $4.000 (columna derecha del formulario)
 * - Recaudación ejemplo: $6.024.000 (1350×2000 + 648×3000 + 347×4000 ≈ total)
 * - Conceptos: Bingo 47%, Línea 6%, B.U.B 0,50%, premios %, vales, sobrante, redondeo
 * - Depósito final ejemplo: $2.417.000
 */
final class BingoRendicionCalculoSupport
{
    /**
     * @param  list<array{carton_id?: int, cantidad: int, precio_unitario: float}>  $lineasCartones
     * @param  iterable<BingoConceptoRendicion>  $conceptos
     * @param  array<int, float>  $montosManuales  concepto_id => monto
     * @return array{
     *   cant_cartones: int,
     *   total_cartones: float,
     *   lineas_concepto: list<array<string, mixed>>,
     *   saldo_final: float,
     *   monto_comision: float
     * }
     */
    public static function calcular(array $lineasCartones, iterable $conceptos, array $montosManuales = []): array
    {
        $cantCartones = 0;
        $totalCartones = 0.0;

        foreach ($lineasCartones as $linea) {
            $cant = max(0, (int) ($linea['cantidad'] ?? 0));
            $precio = (float) ($linea['precio_unitario'] ?? 0);
            $cantCartones += $cant;
            $totalCartones += round($cant * $precio, 2);
        }

        $saldo = $totalCartones;
        $montoComision = 0.0;
        $lineasConcepto = [];

        foreach ($conceptos as $concepto) {
            if ($concepto->estado !== BingoConceptoRendicion::ESTADO_ACTIVO) {
                continue;
            }

            if ($concepto->es_saldo_rendicion) {
                continue;
            }

            $monto = self::resolverMontoConcepto($concepto, $totalCartones, $saldo, $montoComision, $montosManuales);
            $montoAplicado = round(abs($monto), 2);

            if ($concepto->signo === BingoConceptoRendicion::SIGNO_SUMA) {
                $saldo = round($saldo + $montoAplicado, 2);
            } else {
                $saldo = round($saldo - $montoAplicado, 2);
            }

            if ($concepto->base_calculo === BingoConceptoRendicion::BASE_TOTAL_CARTONES
                && $concepto->signo === BingoConceptoRendicion::SIGNO_RESTA
                && (float) ($concepto->porcentaje ?? 0) > 0) {
                $montoComision = round($montoComision + $montoAplicado, 2);
            }

            $lineasConcepto[] = [
                'concepto_id' => (int) $concepto->id,
                'detalle' => (string) $concepto->detalle,
                'signo' => (string) $concepto->signo,
                'porcentaje' => $concepto->porcentaje !== null ? (float) $concepto->porcentaje : null,
                'base_calculo' => (string) $concepto->base_calculo,
                'monto' => $montoAplicado,
                'saldo_despues' => $saldo,
            ];
        }

        foreach ($conceptos as $concepto) {
            if ($concepto->estado !== BingoConceptoRendicion::ESTADO_ACTIVO || ! $concepto->es_saldo_rendicion) {
                continue;
            }

            $lineasConcepto[] = [
                'concepto_id' => (int) $concepto->id,
                'detalle' => (string) $concepto->detalle,
                'signo' => (string) $concepto->signo,
                'porcentaje' => null,
                'base_calculo' => (string) $concepto->base_calculo,
                'es_saldo_rendicion' => true,
                'monto' => round($saldo, 2),
                'saldo_despues' => round($saldo, 2),
            ];
        }

        return [
            'cant_cartones' => $cantCartones,
            'total_cartones' => round($totalCartones, 2),
            'lineas_concepto' => $lineasConcepto,
            'saldo_final' => $saldo,
            'monto_comision' => $montoComision,
        ];
    }

    /**
     * @param  array<int, float>  $montosManuales
     */
    private static function resolverMontoConcepto(
        BingoConceptoRendicion $concepto,
        float $totalCartones,
        float $saldoAnterior,
        float $montoComision,
        array $montosManuales,
    ): float {
        if ($concepto->es_saldo_rendicion) {
            return 0.0;
        }

        if ($concepto->base_calculo === BingoConceptoRendicion::BASE_MANUAL) {
            $manual = (float) ($montosManuales[(int) $concepto->id] ?? $concepto->monto_fijo ?? 0);

            return max(0, $manual);
        }

        if ($concepto->monto_fijo !== null && (float) $concepto->monto_fijo > 0) {
            return (float) $concepto->monto_fijo;
        }

        $pct = (float) ($concepto->porcentaje ?? 0);
        if ($pct <= 0) {
            return 0.0;
        }

        $base = match ($concepto->base_calculo) {
            BingoConceptoRendicion::BASE_SALDO_ANTERIOR => $saldoAnterior,
            BingoConceptoRendicion::BASE_MONTO_COMISION => $montoComision,
            default => $totalCartones,
        };

        return round($base * ($pct / 100), 2);
    }
}
