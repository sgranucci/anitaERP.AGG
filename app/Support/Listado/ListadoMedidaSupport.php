<?php

declare(strict_types=1);

namespace App\Support\Listado;

/**
 * Medidas con nombre de una grilla. El gráfico y el corte piden la clave, no arman el SUM.
 * conteo existe siempre. Una suma solo si el campo está en la grilla. Si hay moneda, la suma se parte.
 */
final class ListadoMedidaSupport
{
    /**
     * @param  array<string, array{label?: string}>  $campos
     * @return array<string, array{label: string, agregado: string, campo?: string, partir_moneda: bool}>
     */
    public static function catalogo(array $campos): array
    {
        $out = [
            'conteo' => [
                'label' => 'Cantidad',
                'agregado' => 'count',
                'partir_moneda' => false,
            ],
        ];
        foreach ([
            'monto' => ['Suma de monto', true],
            'total' => ['Suma de total', true],
            'ingresos' => ['Ingresos', true],
            'egresos' => ['Egresos', true],
            'tiempo_insumido' => ['Suma de minutos', false],
            'saldo' => ['Suma de saldo dep.', false],
            'sueldo_basico' => ['Suma de sueldo básico', false],
            'valor_origen' => ['Suma de valor origen', true],
            'valor_baja' => ['Suma de valor baja', true],
            'monto_factura' => ['Suma de monto factura', true],
        ] as $key => [$label, $parteMoneda]) {
            if (! isset($campos[$key])) {
                continue;
            }
            $out[$key] = [
                'label' => $label,
                'agregado' => 'sum',
                'campo' => $key,
                'partir_moneda' => $parteMoneda && isset($campos['moneda']),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{label?: string}>  $campos
     */
    public static function normalizarClave(string $medida, array $campos): string
    {
        return isset(self::catalogo($campos)[$medida]) ? $medida : 'conteo';
    }

    /**
     * @param  array<string, array{label?: string}>  $campos
     */
    public static function partirPorMoneda(string $medida, string $dimension, array $campos): bool
    {
        $def = self::catalogo($campos)[$medida] ?? null;

        return is_array($def)
            && ! empty($def['partir_moneda'])
            && $dimension !== 'moneda';
    }
}
