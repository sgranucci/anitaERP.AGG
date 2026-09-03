<?php

namespace App\Support\Compras;

/**
 * Tipo ARCA de compras: el operador carga el tipo de clase A (001/002/003)
 * y la letra. La B suma 5 al tipo (001→006); la C suma 10 (001→011).
 */
final class OrdencompraLegajoFacturaArcaSupport
{
    /** @var array<string, int> */
    private const OFFSET_LETRA = [
        'A' => 0,
        'B' => 5,
        'C' => 10,
        'E' => 18,
        'M' => 50,
    ];

    /** @var array<int, string> */
    private const ETIQUETA_TIPO_BASE = [
        1 => 'Factura',
        2 => 'Nota de débito',
        3 => 'Nota de crédito',
    ];

    public static function normalizarTipo(string|int $tipo): int
    {
        $n = (int) preg_replace('/\D+/', '', (string) $tipo);

        return $n > 0 ? $n : 0;
    }

    public static function normalizarLetra(string $letra): string
    {
        $l = strtoupper(substr(trim($letra), 0, 1));

        return $l !== '' ? $l : '';
    }

    /**
     * Si el tipo ya es serie B/C (6–8, 11–13, …) se respeta.
     * Si es 1–3 (o 001–003), se aplica el offset de la letra.
     */
    public static function codigoArcaEfectivo(string|int $tipo, string $letra): int
    {
        $tipoN = self::normalizarTipo($tipo);
        $letraN = self::normalizarLetra($letra);
        if ($tipoN <= 0) {
            return 0;
        }

        if ($tipoN >= 6) {
            return $tipoN;
        }

        $offset = self::OFFSET_LETRA[$letraN] ?? 0;

        return $tipoN + $offset;
    }

    public static function codigoArcaPad(string|int $tipo, string $letra): string
    {
        return str_pad((string) self::codigoArcaEfectivo($tipo, $letra), 3, '0', STR_PAD_LEFT);
    }

    public static function etiquetaTipoBase(string|int $tipo): string
    {
        $n = self::normalizarTipo($tipo);
        $base = $n >= 1 && $n <= 3 ? $n : (($n % 5) === 1 || ($n % 5) === 2 || ($n % 5) === 3 ? $n % 5 : $n);

        return self::ETIQUETA_TIPO_BASE[$base] ?? ('Tipo '.$n);
    }

    public static function resumen(string|int $tipo, string $letra): string
    {
        $letraN = self::normalizarLetra($letra);
        $pad = self::codigoArcaPad($tipo, $letra);
        $nombre = self::etiquetaTipoBase($tipo);

        return trim($nombre.' '.$letraN.' · ARCA '.$pad);
    }
}
