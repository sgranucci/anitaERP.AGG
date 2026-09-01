<?php

declare(strict_types=1);

namespace App\Support\Caja;

/**
 * Formato ASCII Interbanking idéntico a Anita p-pagoxbanco.c (pagobanco.txt).
 *
 * Cabecera *U* + renglones *M* por CBU destino / importe.
 * Ancho fijo 240 + LF, igual que los fprintf de Anita.
 *
 * No usar %c con 'D'/'N': en PHP %c castea el string a int (0) y escribe NUL.
 * Interbanking rechaza eso como "Formato de archivo incorrecto".
 */
final class InterbankingArchivoPagoFormatoSupport
{
    public const ANCHO_REGISTRO = 240;

    /**
     * @param  list<array{cbu:string,importe:float}>  $lineas
     */
    public static function generarArchivo(
        string $cbuOrigen,
        int $fechaSolicitudYmd,
        int $secuencia,
        string $observacion,
        array $lineas,
    ): string {
        $cbuOrigen = self::cbu22($cbuOrigen);
        $obs = self::pad($observacion, 61);
        $fechaFmt = self::fechaDdMmYy($fechaSolicitudYmd);
        $sec = sprintf('%08d', max(0, $secuencia));

        // Igual que Anita:
        // "%3.3s%22.22s%c%08ld%c%61.61s%03ld%02ld%8.8s%8.8s%123.123s\n"
        $out = sprintf(
            "%3.3s%22.22s%s%08d%s%61.61s%03d%02d%8.8s%8.8s%123.123s\n",
            '*U*',
            $cbuOrigen,
            'D',
            $fechaSolicitudYmd,
            'N',
            $obs,
            0,
            0,
            $fechaFmt,
            $sec,
            ' '
        );

        foreach ($lineas as $linea) {
            $cbu = self::cbu22((string) ($linea['cbu'] ?? ''));
            $importe = (float) ($linea['importe'] ?? 0);
            if ($cbu === '' || trim($cbu) === '' || abs($importe) < 0.005) {
                continue;
            }
            $centavos = (int) round(abs($importe) * 100);

            // Igual que Anita:
            // "%3.3s%22.22s%017.0lf%60.60s%2.2s%12.12s%2.2s%12.12s%12.12s%2.2s%012.0lf%12.12s%010.0lf%11.11s%51.51s\n"
            $out .= sprintf(
                "%3.3s%22.22s%017d%60.60s%2.2s%12.12s%2.2s%12.12s%12.12s%2.2s%012d%12.12s%010d%11.11s%51.51s\n",
                '*M*',
                $cbu,
                $centavos,
                ' ',
                ' ',
                ' ',
                ' ',
                ' ',
                ' ',
                ' ',
                0,
                ' ',
                0,
                ' ',
                ' '
            );
        }

        return $out;
    }

    public static function cbu22(string $cbu): string
    {
        $n = preg_replace('/\D+/', '', $cbu) ?? '';

        return str_pad(substr($n, 0, 22), 22, ' ', STR_PAD_RIGHT);
    }

    public static function fechaDdMmYy(int $ymd): string
    {
        $s = sprintf('%08d', $ymd);
        if (strlen($s) !== 8) {
            return '00/00/00';
        }

        return substr($s, 6, 2).'/'.substr($s, 4, 2).'/'.substr($s, 2, 2);
    }

    public static function ymdDesdeFecha(?string $fecha): int
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return (int) date('Ymd');
        }
        $ts = strtotime($fecha);

        return $ts ? (int) date('Ymd', $ts) : (int) date('Ymd');
    }

    private static function pad(string $valor, int $len): string
    {
        return str_pad(mb_substr($valor, 0, $len), $len, ' ', STR_PAD_RIGHT);
    }
}
