<?php

namespace App\Support\Compras;

/**
 * Detecta FC contra COM donde la diferencia de precio quedó en FAR
 * en lugar de las cuentas de artículos / OC (Contaduría punto 1).
 */
final class ComprobanteProveedorAuditorFarDiferenciaSupport
{
    /**
     * @param  list<array{origen: string, monto_neto: float}>  $lineasEsperadas
     *        monto_neto = debe − haber (signo contable del preview)
     * @return array{
     *     ok: bool,
     *     far_esperado: float,
     *     far_diferencia_esperada: float,
     *     far_asiento: float,
     *     desvio_far: float,
     *     motivo: string|null
     * }
     */
    public static function evaluar(
        array $lineasEsperadas,
        float $farEnAsiento,
        float $tolerancia = 0.05,
    ): array {
        $farEsperado = 0.0;
        $farDiferencia = 0.0;
        foreach ($lineasEsperadas as $linea) {
            $origen = (string) ($linea['origen'] ?? '');
            $monto = round((float) ($linea['monto_neto'] ?? 0), 2);
            if ($origen === 'far') {
                $farEsperado += $monto;
            } elseif ($origen === 'far_diferencia') {
                $farDiferencia += $monto;
            }
        }
        $farEsperado = round($farEsperado, 2);
        $farDiferencia = round($farDiferencia, 2);
        $farAsiento = round($farEnAsiento, 2);
        $desvio = round($farAsiento - $farEsperado, 2);

        if (abs($desvio) < $tolerancia) {
            return [
                'ok' => true,
                'far_esperado' => $farEsperado,
                'far_diferencia_esperada' => $farDiferencia,
                'far_asiento' => $farAsiento,
                'desvio_far' => $desvio,
                'motivo' => null,
            ];
        }

        // Solo alertar cuando había (o debería haber) diferencia a prorratear a OC,
        // o cuando el FAR del asiento absorbió de más respecto del motor actual.
        $motivo = abs($farDiferencia) >= $tolerancia
            ? 'FAR del asiento no coincide con el esperado: la diferencia FC vs COM debía ir a cuentas de la OC/artículos, no a provisión.'
            : 'FAR del asiento (' . number_format($farAsiento, 2, ',', '.')
                . ') ≠ FAR esperado por el motor (' . number_format($farEsperado, 2, ',', '.') . ').';

        return [
            'ok' => false,
            'far_esperado' => $farEsperado,
            'far_diferencia_esperada' => $farDiferencia,
            'far_asiento' => $farAsiento,
            'desvio_far' => $desvio,
            'motivo' => $motivo,
        ];
    }

    /**
     * @param  array<string, mixed>  $payloadAsiento  preview armarPreview()['payload_asiento']
     * @return list<array{origen: string, monto_neto: float}>
     */
    public static function lineasDesdePayloadPreview(array $payloadAsiento): array
    {
        $origenes = $payloadAsiento['origenes'] ?? [];
        $debes = $payloadAsiento['debes'] ?? [];
        $haberes = $payloadAsiento['haberes'] ?? [];
        $out = [];
        $n = max(count($origenes), count($debes), count($haberes));
        for ($i = 0; $i < $n; $i++) {
            $debe = (float) ($debes[$i] ?? 0);
            $haber = (float) ($haberes[$i] ?? 0);
            $out[] = [
                'origen' => (string) ($origenes[$i] ?? ''),
                'monto_neto' => round($debe - $haber, 2),
            ];
        }

        return $out;
    }
}
