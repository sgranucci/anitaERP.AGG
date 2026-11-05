<?php

namespace App\Support\Ventas;

use App\Support\Contable\AsientoBalanceSupport;

/**
 * Factura de mostrador sin artículo (concepto de archivo o texto libre).
 * El asiento se carga en la solapa: se asumen las cuentas que se pueden
 * resolver y los netos sin cuenta quedan pendientes.
 */
final class FacturaMostradorAsientoManualSupport
{
    public const MENSAJE_NETO_PENDIENTE = 'Completá la cuenta del neto en la solapa Asiento contable.';

    /**
     * Hay al menos un renglón que no es artículo (concepto o texto).
     *
     * @param  list<array<string, mixed>>  $dataFactura
     */
    public static function lleva(array $dataFactura): bool
    {
        foreach ($dataFactura as $item) {
            if (! is_array($item) || (int) ($item['articulo_id'] ?? 0) > 0) {
                continue;
            }
            $importe = abs((float) ($item['cantidad'] ?? 0) * (float) ($item['precio'] ?? 0));
            $texto = trim((string) ($item['descripcion'] ?? ''));
            if ($texto === '') {
                $texto = trim((string) ($item['detalle'] ?? ''));
            }
            if ($importe >= 0.009 || $texto !== '' || (int) ($item['concepto_venta_id'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Asiento publicado por la solapa. Montos con signo: Debe positivo, Haber negativo.
     *
     * @param  array<string, mixed>  $data
     * @return array{error?: string, lineas: list<array<string, mixed>>}
     */
    public static function desdeFormulario(array $data): array
    {
        $cuentas = is_array($data['cuentacontable_ids'] ?? null) ? $data['cuentacontable_ids'] : [];
        $debes = is_array($data['debeasientos'] ?? null) ? $data['debeasientos'] : [];
        $haberes = is_array($data['haberasientos'] ?? null) ? $data['haberasientos'] : [];
        $centros = is_array($data['centrocostoasiento_ids'] ?? null) ? $data['centrocostoasiento_ids'] : [];
        $n = max(count($cuentas), count($debes), count($haberes));
        $lineas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $debe = round(AsientoBalanceSupport::parseMonto($debes[$i] ?? 0), 2);
            $haber = round(AsientoBalanceSupport::parseMonto($haberes[$i] ?? 0), 2);
            if ($debe < 0.009 && $haber < 0.009) {
                continue;
            }
            if ($debe >= 0.009 && $haber >= 0.009) {
                return [
                    'error' => 'Hay un renglón del asiento con Debe y Haber. Dejá un solo lado.',
                    'lineas' => [],
                ];
            }
            $cuentaId = (int) ($cuentas[$i] ?? 0);
            if ($cuentaId <= 0) {
                return [
                    'error' => self::MENSAJE_NETO_PENDIENTE,
                    'lineas' => [],
                ];
            }
            $cc = (int) ($centros[$i] ?? 0);
            $lineas[] = [
                'cuentacontable_id' => $cuentaId,
                'monto' => $debe >= 0.009 ? $debe : -$haber,
                'centrocosto_id' => $cc > 0 ? $cc : null,
            ];
            if ($debe >= 0.009) {
                $totalDebe += $debe;
            } else {
                $totalHaber += $haber;
            }
        }

        $totalDebe = round($totalDebe, 2);
        $totalHaber = round($totalHaber, 2);
        if ($lineas !== [] && abs($totalDebe - $totalHaber) >= 0.02) {
            return [
                'error' => 'El asiento de la solapa no balancea: Debe '
                    .number_format($totalDebe, 2, ',', '.')
                    .' vs Haber '
                    .number_format($totalHaber, 2, ',', '.')
                    .'.',
                'lineas' => [],
            ];
        }

        return ['lineas' => $lineas];
    }
}
