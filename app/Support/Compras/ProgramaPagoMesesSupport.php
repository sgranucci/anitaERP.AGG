<?php

namespace App\Support\Compras;

use Carbon\Carbon;

/**
 * Columnas de meses / TRANSF del programa de pagos (cashflow Ferli).
 */
final class ProgramaPagoMesesSupport
{
    public const CLAVE_TRANSF = 'transf';

    /**
     * @return list<array{clave: string, etiqueta: string, anio_mes: ?string}>
     */
    public static function columnas(string $anioMesInicio, int $cantidadMeses, bool $incluyeTransf = true): array
    {
        $cantidadMeses = max(1, min(12, $cantidadMeses));
        $columnas = [];

        if ($incluyeTransf) {
            $columnas[] = [
                'clave' => self::CLAVE_TRANSF,
                'etiqueta' => 'TRANSF',
                'anio_mes' => null,
            ];
        }

        $inicio = Carbon::createFromFormat('Y-m', $anioMesInicio)->startOfMonth();
        $nombres = [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
            5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ];
        for ($i = 0; $i < $cantidadMeses; $i++) {
            $mes = (clone $inicio)->addMonths($i);
            $columnas[] = [
                'clave' => $mes->format('Y-m'),
                'etiqueta' => $nombres[(int) $mes->format('n')] ?? $mes->format('m/Y'),
                'anio_mes' => $mes->format('Y-m'),
            ];
        }

        return $columnas;
    }

    /**
     * @param  list<array{clave: string, etiqueta: string, anio_mes: ?string}>  $columnas
     * @return list<string>
     */
    public static function claves(array $columnas): array
    {
        return array_values(array_map(fn (array $c) => $c['clave'], $columnas));
    }
}
