<?php

namespace App\Support\Ventas\Waitry;

/**
 * Estado operativo de órdenes Waitry (getordersdetails / getOrdersPOS).
 */
final class WaitryOrdenEstadoSupport
{
    /**
     * @param  array<string, mixed>  $orden
     */
    public static function esCancelada(array $orden): bool
    {
        foreach (['canceled', 'cancelada', 'cancelled'] as $clave) {
            if (! array_key_exists($clave, $orden)) {
                continue;
            }
            if (in_array($orden[$clave], [true, 1, '1'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    public static function esCanceladaLinea(array $linea): bool
    {
        return ! empty($linea['waitry_cancelada']);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array{
     *   activas: list<array<string, mixed>>,
     *   canceladas: list<array<string, mixed>>,
     *   resumen: array{cantidad:int,total:float}
     * }
     */
    public static function separarCanceladas(array $lineas): array
    {
        $activas = [];
        $canceladas = [];

        foreach ($lineas as $linea) {
            if (self::esCanceladaLinea($linea)) {
                $canceladas[] = $linea;
            } else {
                $activas[] = $linea;
            }
        }

        return [
            'activas' => $activas,
            'canceladas' => $canceladas,
            'resumen' => self::resumenCanceladas($canceladas),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $canceladas
     * @return array{cantidad:int,total:float}
     */
    public static function resumenCanceladas(array $canceladas): array
    {
        $total = 0.0;
        foreach ($canceladas as $linea) {
            $total = round($total + (float) ($linea['total'] ?? 0), 2);
        }

        return [
            'cantidad' => count($canceladas),
            'total' => $total,
        ];
    }
}
