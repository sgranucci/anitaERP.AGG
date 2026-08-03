<?php

namespace App\Support\Sueldos\Anita;

/**
 * Mapea mael_tipo_liq + detalle Anita → tipo de corrida ERP.
 * Preferencia: palabras del detalle; fallback por código numérico.
 */
class AnitaLiquidacionTipoMapa
{
    public static function mapear(string|int|null $tipoLiq, ?string $detalle = null): string
    {
        $d = mb_strtoupper(trim((string) $detalle));

        if ($d !== '') {
            if (str_contains($d, 'VAC')) {
                return 'vacaciones';
            }
            if (str_contains($d, 'FINAL') || str_contains($d, 'FINIQUITO')) {
                return 'final';
            }
            if (str_contains($d, 'SAC') || str_contains($d, 'AGUINALDO')) {
                return 'sac';
            }
            if (str_contains($d, 'AJUST') || str_contains($d, 'AJUTE')) {
                return 'ajuste';
            }
            if (str_contains($d, 'QUINC') && (str_contains($d, '1') || str_contains($d, '1ER') || str_contains($d, '1RA'))) {
                return 'quincena_1';
            }
            if (str_contains($d, 'QUINC') && (str_contains($d, '2') || str_contains($d, '2DA'))) {
                return 'quincena_2';
            }
            if (str_contains($d, 'GRATIF')) {
                return 'gratificacion';
            }
            if (str_contains($d, 'NO REM') || str_contains($d, 'NOREM')) {
                return 'no_remunerativo';
            }
        }

        return match (trim((string) $tipoLiq)) {
            '1' => 'quincena_1',
            '2' => 'quincena_2',
            '5' => 'vacaciones',
            '6' => 'sac',
            '7' => 'final',
            '8' => 'ajuste',
            '9' => 'complementaria',
            default => 'mensual',
        };
    }

    public static function mapearEstado(?string $estadoAnita): string
    {
        $c = strtoupper(trim((string) $estadoAnita));
        if ($c === 'C' || $c === 'P' || $c === 'X') {
            // C=cerrada en operativa Anita observada; P/X tratados como cerrada histórica.
            return $c === 'X' ? 'anulada' : 'cerrada';
        }

        return 'calculada';
    }
}
