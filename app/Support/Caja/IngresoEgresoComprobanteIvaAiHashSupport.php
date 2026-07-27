<?php

namespace App\Support\Caja;

/**
 * Firma de los campos sugeridos por IA para detectar edición humana antes de persistir.
 */
final class IngresoEgresoComprobanteIvaAiHashSupport
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function calcular(array $payload): string
    {
        $cabecera = is_array($payload['cabecera'] ?? null) ? $payload['cabecera'] : $payload;
        $conceptos = is_array($payload['conceptos'] ?? null) ? $payload['conceptos'] : [];

        $canon = [
            'proveedor_id' => (int) ($cabecera['proveedor_id'] ?? 0),
            'proveedor_documento_eventual' => preg_replace('/\D/', '', (string) ($cabecera['proveedor_documento_eventual'] ?? '')),
            'letra' => strtoupper(trim((string) ($cabecera['letra'] ?? ''))),
            'sucursal' => (int) ($cabecera['sucursal'] ?? 0),
            'numerocomprobante' => (int) ($cabecera['numerocomprobante'] ?? 0),
            'fechacomprobante' => substr((string) ($cabecera['fechacomprobante'] ?? ''), 0, 10),
            'fechaiva' => substr((string) ($cabecera['fechaiva'] ?? ''), 0, 10),
            'total' => round((float) ($cabecera['total'] ?? 0), 2),
            'numerocae' => preg_replace('/\D/', '', (string) ($cabecera['numerocae'] ?? '')),
            'conceptos' => [],
        ];

        foreach ($conceptos as $concepto) {
            if (! is_array($concepto)) {
                continue;
            }
            $canon['conceptos'][] = [
                'concepto_ivacompra_id' => (int) ($concepto['concepto_ivacompra_id'] ?? 0),
                'monto' => round((float) ($concepto['monto'] ?? 0), 2),
            ];
        }

        usort($canon['conceptos'], static fn (array $a, array $b): int =>
            [$a['concepto_ivacompra_id'], $a['monto']] <=> [$b['concepto_ivacompra_id'], $b['monto']]
        );

        return hash('sha256', json_encode($canon, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) ?: '');
    }
}
