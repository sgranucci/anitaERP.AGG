<?php

namespace App\Support\Stock\RecepcionProveedorOcr;

/**
 * Firma de cantidades/precios sugeridos por OCR para detectar edición humana.
 */
final class RecepcionProveedorOcrAiHashSupport
{
    /**
     * @param  array<string, mixed>  $resultado
     */
    public static function calcular(array $resultado): string
    {
        $canon = [
            'ordencompra_id' => (int) ($resultado['ordencompra_id'] ?? 0),
            'numeroordencompra' => (int) ($resultado['numeroordencompra'] ?? 0),
            'lineas' => [],
        ];

        foreach ((array) ($resultado['lineas'] ?? []) as $linea) {
            if (! is_array($linea)) {
                continue;
            }
            $canon['lineas'][] = [
                'articulo_id' => (int) ($linea['articulo_id'] ?? 0),
                'ordencompra_articulo_id' => (int) ($linea['ordencompra_articulo_id'] ?? 0),
                'cantidad' => round((float) ($linea['cantidad'] ?? 0), 6),
                'precio' => round((float) ($linea['precio'] ?? 0), 4),
            ];
        }

        usort($canon['lineas'], static fn (array $a, array $b): int =>
            [$a['ordencompra_articulo_id'], $a['articulo_id']]
            <=> [$b['ordencompra_articulo_id'], $b['articulo_id']]
        );

        return hash('sha256', json_encode($canon, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) ?: '');
    }

    /**
     * @param  array<int, array<string, mixed>>  $itemsForm
     */
    public static function calcularDesdeItemsForm(array $itemsForm, int $ordencompraId, int $numeroOc): string
    {
        return self::calcular([
            'ordencompra_id' => $ordencompraId,
            'numeroordencompra' => $numeroOc,
            'lineas' => $itemsForm,
        ]);
    }
}
