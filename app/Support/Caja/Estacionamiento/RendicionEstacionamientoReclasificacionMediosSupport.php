<?php

declare(strict_types=1);

namespace App\Support\Caja\Estacionamiento;

/**
 * Pase de un medio a otro (efectivo ↔ Mercado Pago) cuando el total contado
 * cierra con lo facturado. No es faltante, sobrante ni diferencia de caja:
 * cambia la distribución y esa distribución es la que deben mostrar todos los listados.
 */
final class RendicionEstacionamientoReclasificacionMediosSupport
{
    public const TOLERANCIA = 0.02;

    /**
     * @param  array<int, float>  $facturado  cuentacaja_id => importe de las facturas
     * @param  array<int, float>  $contado  cuentacaja_id => importe de la rendición
     * @return array<int, float>|null distribución contada, o null si el total no cierra
     */
    public static function distribucionContada(array $facturado, array $contado): ?array
    {
        if ($contado === []) {
            return null;
        }

        $sumFacturado = 0.0;
        foreach ($facturado as $monto) {
            $sumFacturado += (float) $monto;
        }
        $sumContado = 0.0;
        foreach ($contado as $monto) {
            $sumContado += (float) $monto;
        }
        if (abs(round($sumFacturado, 2) - round($sumContado, 2)) > self::TOLERANCIA) {
            return null;
        }

        $out = [];
        foreach ($contado as $cuentaId => $monto) {
            $neto = round((float) $monto, 2);
            if (abs($neto) <= 0.0001) {
                continue;
            }
            $out[(int) $cuentaId] = $neto;
        }

        return $out;
    }
}
