<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

/**
 * Estados de conciliación ERP ↔ Anita ↔ rendgastro (por PC y totales).
 */
final class GastronomiaConciliacionEstadoSupport
{
    public static function resolver(
        float $diffErpAnita,
        ?float $diffErpRendg,
        float $tolerancia,
        bool $jornadaAbierta = false,
        float $ventasErp = 0.0,
    ): string {
        if ($ventasErp <= $tolerancia && abs($diffErpAnita) <= $tolerancia) {
            return '—';
        }

        $okAnita = abs($diffErpAnita) <= $tolerancia;

        if ($jornadaAbierta) {
            return $okAnita ? 'OK' : 'DIF';
        }

        if ($diffErpRendg === null) {
            if ($ventasErp > $tolerancia) {
                return $okAnita ? 'SIN RENDG' : 'DIF';
            }

            return $okAnita ? 'OK' : 'DIF';
        }

        $okRendg = abs($diffErpRendg) <= $tolerancia;

        return ($okAnita && $okRendg) ? 'OK' : 'DIF';
    }
}
