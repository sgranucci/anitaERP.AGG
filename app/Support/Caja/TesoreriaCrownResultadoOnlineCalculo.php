<?php

namespace App\Support\Caja;

/**
 * Fórmulas de a-resonline.c (totaliza):
 * resultado = apostado − pagado
 * porcentaje = resultado / apostado × 100 (0 si apostado es 0)
 */
final class TesoreriaCrownResultadoOnlineCalculo
{
    public static function resultado(float $apostado, float $pagado): float
    {
        return round($apostado - $pagado, 2);
    }

    public static function porcentaje(float $apostado, float $resultado): float
    {
        if (abs($apostado) < 0.00001) {
            return 0.0;
        }

        return round(($resultado / $apostado) * 100, 4);
    }

    /**
     * @return array{resultado: float, porcentaje: float}
     */
    public static function desdeApostadoPagado(float $apostado, float $pagado): array
    {
        $resultado = self::resultado($apostado, $pagado);

        return [
            'resultado' => $resultado,
            'porcentaje' => self::porcentaje($apostado, $resultado),
        ];
    }
}
