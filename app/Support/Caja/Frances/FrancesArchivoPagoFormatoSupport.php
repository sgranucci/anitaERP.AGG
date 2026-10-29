<?php

declare(strict_types=1);

namespace App\Support\Caja\Frances;

/**
 * Línea de frances.csv tal como p-enviafrances.c:
 * nombre (30), CBU, importe %013.2f, concepto FAC, CUIT de 11 dígitos.
 */
final class FrancesArchivoPagoFormatoSupport
{
    /** Concepto fijo del fprintf de p-enviafrances.c. */
    public const CONCEPTO = 'FAC';

    public static function linea(string $nombre, string $cbu, float $monto, string $cuit): string
    {
        return sprintf(
            "%s,%s,%013.2f,%s,%s\n",
            self::nombre30($nombre),
            $cbu,
            $monto,
            self::CONCEPTO,
            $cuit
        );
    }

    public static function nombre30(string $nombre): string
    {
        $nombre = str_replace([',', "\r", "\n", "\t"], ' ', $nombre);
        if (strlen($nombre) > 30) {
            $nombre = substr($nombre, 0, 30);
        }

        return str_pad($nombre, 30, ' ', STR_PAD_RIGHT);
    }

    public static function cuit11(string $cuit): string
    {
        $digitos = preg_replace('/\D+/', '', $cuit) ?? '';
        if (strlen($digitos) >= 11) {
            return substr($digitos, 0, 11);
        }

        return $digitos;
    }

    public static function numeroOp(string $letra, int $sucursal, int $numero): string
    {
        $letra = substr(trim($letra), 0, 1);

        return sprintf('%s%04d-%08d', $letra, max(0, $sucursal), max(0, $numero));
    }
}
