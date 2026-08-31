<?php

namespace App\Support\Sueldos\Lsd;

/**
 * Padding y encoding del diseño de interfaz LSD (ANSI / Windows-1252).
 * Numéricos: ceros a la izquierda. Alfanuméricos: espacios a la derecha.
 * Decimales: sin separador; últimas 2 posiciones = centavos.
 */
class LsdAnsiSupport
{
    public static function n(string|int|float|null $valor, int $largo): string
    {
        $digitos = preg_replace('/\D+/', '', (string) $valor) ?? '';

        return str_pad(substr($digitos, -$largo), $largo, '0', STR_PAD_LEFT);
    }

    public static function a(?string $valor, int $largo): string
    {
        $txt = self::aAnsi(trim((string) $valor));
        if (strlen($txt) > $largo) {
            $txt = substr($txt, 0, $largo);
        }

        return str_pad($txt, $largo, ' ', STR_PAD_RIGHT);
    }

    public static function dec(float|int|string|null $valor, int $largo, int $decimales = 2): string
    {
        $num = (float) $valor;
        $factor = 10 ** $decimales;
        $entero = (int) round(abs($num) * $factor);

        return str_pad((string) $entero, $largo, '0', STR_PAD_LEFT);
    }

    public static function fechaYmd(?string $fecha): string
    {
        $f = trim((string) $fecha);
        if ($f === '') {
            return str_repeat('0', 8);
        }
        $f = str_replace(['-', '/'], '', $f);

        return self::n($f, 8);
    }

    public static function aAnsi(string $utf8): string
    {
        $conv = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $utf8);
        if ($conv === false) {
            $conv = @iconv('UTF-8', 'Windows-1252//IGNORE', $utf8);
        }

        return $conv !== false ? $conv : $utf8;
    }

    public static function archivo(array $lineas): string
    {
        $cuerpo = implode("\r\n", $lineas);
        if ($cuerpo !== '') {
            $cuerpo .= "\r\n";
        }

        // Las líneas ya salen en Windows-1252 desde a()/n(); no reconvertir.

        return $cuerpo;
    }

    public static function cuil11(?string $valor): string
    {
        return self::n($valor, 11);
    }
}
