<?php

namespace App\Support\Solicitudpago;

/**
 * Líneas de asiento que realmente se graban en una solicitud de pago.
 * El cierre exige que esas líneas balanceen: una pierna con importe no se descarta en silencio.
 */
final class SolicitudpagoAsientoCierreSupport
{
    public const TOLERANCIA = 0.009;

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     lineas: list<array{empresa_id: int, cuentacontable_id: int, centrocosto_id: ?int, debe_haber: string, monto: float}>,
     *     total_debe: float,
     *     total_haber: float,
     *     error: ?string
     * }
     */
    public static function evaluar(array $data): array
    {
        $empresaIds = is_array($data['empresa_ids'] ?? null) ? $data['empresa_ids'] : [];
        $cuentaIds = is_array($data['cuentacontable_ids'] ?? null) ? $data['cuentacontable_ids'] : [];
        $ccIds = is_array($data['centrocosto_ids'] ?? null) ? $data['centrocosto_ids'] : [];
        $dhs = is_array($data['debe_haberes'] ?? null) ? $data['debe_haberes'] : [];
        $montos = is_array($data['montos_cuenta'] ?? null) ? $data['montos_cuenta'] : [];
        $montosDebe = $data['montos_debe'] ?? null;
        $montosHaber = $data['montos_haber'] ?? null;
        $usaColumnas = is_array($montosDebe) || is_array($montosHaber);

        $n = max(count($empresaIds), count($cuentaIds), count($dhs), count($montos));
        if (is_array($montosDebe)) {
            $n = max($n, count($montosDebe));
        }
        if (is_array($montosHaber)) {
            $n = max($n, count($montosHaber));
        }

        $lineas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $error = null;

        for ($i = 0; $i < $n; $i++) {
            $cuentaId = (int) ($cuentaIds[$i] ?? 0);
            $empresaId = (int) ($empresaIds[$i] ?? 0);
            $dhSelect = strtoupper(trim((string) ($dhs[$i] ?? 'D'))) === 'H' ? 'H' : 'D';

            if ($usaColumnas) {
                $debe = self::numero(is_array($montosDebe) ? ($montosDebe[$i] ?? 0) : 0);
                $haber = self::numero(is_array($montosHaber) ? ($montosHaber[$i] ?? 0) : 0);
                if ($debe <= 0 && $haber <= 0) {
                    continue;
                }
                // Un renglón imputa un solo lado. Si vienen los dos importes, no se suman:
                // el mismo monto en Debe y Haber no puede simular un asiento balanceado.
                if ($debe > 0 && $haber > 0) {
                    $dh = $dhSelect;
                    $monto = $dh === 'H' ? $haber : $debe;
                } elseif ($haber > 0) {
                    $dh = 'H';
                    $monto = $haber;
                } else {
                    $dh = 'D';
                    $monto = $debe;
                }
            } else {
                $monto = self::numero($montos[$i] ?? 0);
                if ($monto <= 0) {
                    continue;
                }
                $dh = $dhSelect;
            }

            if ($cuentaId <= 0) {
                $error ??= 'Hay una línea de asiento con importe y sin cuenta contable. No se puede cerrar la solicitud.';

                continue;
            }
            if ($empresaId <= 0) {
                $error ??= 'Hay una línea de asiento con importe y sin empresa. No se puede cerrar la solicitud.';

                continue;
            }

            $monto = round($monto, 2);
            $cc = (int) ($ccIds[$i] ?? 0);
            $lineas[] = [
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
                'centrocosto_id' => $cc > 0 ? $cc : null,
                'debe_haber' => $dh,
                'monto' => $monto,
            ];
            if ($dh === 'H') {
                $totalHaber += $monto;
            } else {
                $totalDebe += $monto;
            }
        }

        $totalDebe = round($totalDebe, 2);
        $totalHaber = round($totalHaber, 2);

        if ($error === null && $lineas === []) {
            $error = 'Debe cargar el asiento contable (solapa Cuentas) con al menos una cuenta e importe en Debe o Haber antes de cerrar la solicitud.';
        }
        if ($error === null && abs($totalDebe - $totalHaber) >= self::TOLERANCIA) {
            $error = 'El asiento no balancea: Debe ('.number_format($totalDebe, 2, ',', '.').') '
                .'debe ser igual a Haber ('.number_format($totalHaber, 2, ',', '.').'). '
                .'No se puede cerrar la solicitud.';
        }

        return [
            'lineas' => $error === null ? $lineas : [],
            'total_debe' => $totalDebe,
            'total_haber' => $totalHaber,
            'error' => $error,
        ];
    }

    public static function numero(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }
        if (is_int($valor) || is_float($valor)) {
            return round(max(0, (float) $valor), 2);
        }
        $t = trim(str_replace(' ', '', (string) $valor));
        if ($t === '') {
            return 0.0;
        }
        if (str_contains($t, ',')) {
            $t = str_replace('.', '', $t);
            $t = str_replace(',', '.', $t);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $t)) {
            $t = str_replace('.', '', $t);
        }
        if (! is_numeric($t)) {
            return 0.0;
        }

        return round(max(0, (float) $t), 2);
    }
}
