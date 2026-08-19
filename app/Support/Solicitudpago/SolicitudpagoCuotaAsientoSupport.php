<?php

namespace App\Support\Solicitudpago;

/**
 * Asiento de SP hija (cuota): mismas cuentas que la madre, importes = monto de la cuota.
 * No prorratea contra el monto de cabecera de la madre (puede diferir del asiento).
 */
final class SolicitudpagoCuotaAsientoSupport
{
    /**
     * @param  iterable<object|array<string, mixed>>  $cuentasMadre  ítems con debe_haber y monto
     * @return list<float>
     */
    public static function montosHija(iterable $cuentasMadre, float $montoCuota): array
    {
        $filas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        foreach ($cuentasMadre as $cta) {
            $dh = strtoupper(trim((string) (is_array($cta) ? ($cta['debe_haber'] ?? 'D') : ($cta->debe_haber ?? 'D')))) === 'H'
                ? 'H'
                : 'D';
            $monto = round(abs((float) (is_array($cta) ? ($cta['monto'] ?? 0) : ($cta->monto ?? 0))), 2);
            $filas[] = ['dh' => $dh, 'monto' => $monto];
            if ($dh === 'H') {
                $totalHaber += $monto;
            } else {
                $totalDebe += $monto;
            }
        }

        $n = count($filas);
        if ($n === 0) {
            return [];
        }

        $target = round(abs($montoCuota), 2);
        $base = max($totalDebe, $totalHaber);
        if ($base < 0.01) {
            return array_fill(0, $n, $target);
        }

        $montos = [];
        $sumD = 0.0;
        $sumH = 0.0;
        $lastD = null;
        $lastH = null;
        foreach ($filas as $i => $fila) {
            $m = round($fila['monto'] * $target / $base, 2);
            $montos[$i] = $m;
            if ($fila['dh'] === 'H') {
                $sumH += $m;
                $lastH = $i;
            } else {
                $sumD += $m;
                $lastD = $i;
            }
        }

        if ($lastD !== null) {
            $montos[$lastD] = round($montos[$lastD] + ($target - $sumD), 2);
        }
        if ($lastH !== null) {
            $montos[$lastH] = round($montos[$lastH] + ($target - $sumH), 2);
        }

        return array_values($montos);
    }
}
