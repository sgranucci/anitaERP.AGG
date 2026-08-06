<?php

declare(strict_types=1);

namespace App\Support\Configuracion\PadronIibb;

use DateTime;

/**
 * Normalización de los campos que comparten todos los padrones provinciales.
 */
final class PadronIibbCampoSupport
{
    /**
     * Devuelve el CUIT de 11 dígitos o null.
     *
     * La validación estricta evita repetir el incidente del 14/12/2025, donde un
     * desfasaje de columnas cargó 289.949 filas con la fecha "31122025" como CUIT.
     */
    public static function cuit(?string $valor): ?string
    {
        $cuit = preg_replace('/\D+/', '', (string) $valor) ?? '';

        return strlen($cuit) === 11 ? $cuit : null;
    }

    /**
     * Los padrones provinciales se publican en ISO-8859-1; sin convertir, los
     * acentos quedan como "raz&oacute;n" corrupta en pantalla y en los PDF.
     */
    public static function nombre(?string $valor, int $max = 255): ?string
    {
        $raw = trim((string) $valor);
        if ($raw === '') {
            return null;
        }

        $utf8 = @mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1,UTF-8');
        $nombre = trim(is_string($utf8) && $utf8 !== '' ? $utf8 : $raw);

        return $nombre === '' ? null : mb_substr($nombre, 0, $max);
    }

    /** Acepta coma o punto como separador decimal. */
    public static function tasa(?string $valor): ?float
    {
        $raw = trim((string) $valor);
        if ($raw === '') {
            return null;
        }

        $normalizado = str_replace([' ', ','], ['', '.'], $raw);
        if (! is_numeric($normalizado)) {
            return null;
        }

        return (float) $normalizado;
    }

    public static function texto(?string $valor, int $max = 10): ?string
    {
        $texto = trim((string) $valor);

        return $texto === '' ? null : mb_substr($texto, 0, $max);
    }

    /** Fecha en un formato dado (dmY, Ymd…) a Y-m-d. */
    public static function fecha(?string $valor, string $formato): ?string
    {
        $raw = trim((string) $valor);
        if ($raw === '') {
            return null;
        }

        $fecha = DateTime::createFromFormat($formato, $raw);
        if (! $fecha) {
            return null;
        }

        // createFromFormat completa con la hora actual: sin esto, un archivo
        // procesado a las 23:59 puede rodar de día al formatear.
        $fecha->setTime(0, 0);

        return $fecha->format('Y-m-d');
    }

    /**
     * Período mensual a partir de YYYYMM.
     *
     * @return array{0:string,1:string}|null [primer día, último día]
     */
    public static function periodoMensual(?string $yyyymm): ?array
    {
        $raw = trim((string) $yyyymm);
        if (! preg_match('/^\d{6}$/', $raw)) {
            return null;
        }

        $primero = DateTime::createFromFormat('Ymd', $raw . '01');
        if (! $primero) {
            return null;
        }
        $primero->setTime(0, 0);

        $ultimo = (clone $primero)->modify('last day of this month');

        return [$primero->format('Y-m-d'), $ultimo->format('Y-m-d')];
    }
}
