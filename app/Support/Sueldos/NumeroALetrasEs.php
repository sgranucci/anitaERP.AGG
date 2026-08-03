<?php

namespace App\Support\Sueldos;

/**
 * Convierte enteros a letras (español AR) para neto en recibo.
 * Suficiente para montos de liquidación; sin centavos (Anita redondea neto).
 */
class NumeroALetrasEs
{
    /** @var list<string> */
    private const UNIDADES = [
        '', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
        'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete',
        'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidós', 'veintitrés',
        'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve',
    ];

    /** @var list<string> */
    private const DECENAS = [
        '', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa',
    ];

    /** @var list<string> */
    private const CENTENAS = [
        '', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos',
        'seiscientos', 'setecientos', 'ochocientos', 'novecientos',
    ];

    public static function entero(int|float $n): string
    {
        $n = (int) round(abs($n));
        if ($n === 0) {
            return 'cero';
        }

        return self::grupo($n);
    }

    private static function grupo(int $n): string
    {
        if ($n < 30) {
            return self::UNIDADES[$n];
        }
        if ($n < 100) {
            $d = intdiv($n, 10);
            $u = $n % 10;
            if ($u === 0) {
                return self::DECENAS[$d];
            }

            return self::DECENAS[$d].' y '.self::UNIDADES[$u];
        }
        if ($n < 1000) {
            if ($n === 100) {
                return 'cien';
            }
            $c = intdiv($n, 100);
            $r = $n % 100;

            return self::CENTENAS[$c].($r ? ' '.self::grupo($r) : '');
        }
        if ($n < 1000000) {
            $m = intdiv($n, 1000);
            $r = $n % 1000;
            $pref = $m === 1 ? 'mil' : self::grupo($m).' mil';

            return $pref.($r ? ' '.self::grupo($r) : '');
        }
        if ($n < 1000000000) {
            $mill = intdiv($n, 1000000);
            $r = $n % 1000000;
            $pref = $mill === 1 ? 'un millón' : self::grupo($mill).' millones';

            return $pref.($r ? ' '.self::grupo($r) : '');
        }

        return (string) $n;
    }
}
