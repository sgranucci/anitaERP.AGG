<?php

declare(strict_types=1);

namespace App\Support\Caja;

/**
 * Formato ASCII Interbanking idéntico a Anita p-pagoxbanco.c (pagobanco.txt).
 *
 * Cabecera *U* + renglones *M* por CBU destino / importe.
 */
final class InterbankingArchivoPagoFormatoSupport
{
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
        $obs = self::pad(mb_substr($observacion, 0, 61), 61);
        $fechaFmt = self::fechaDdMmYy($fechaSolicitudYmd);
        $sec = sprintf('%08d', max(0, $secuencia));

        $out = sprintf(
            "%-3.3s%-22.22s%c%08d%c%-61.61s%03d%02d%-8.8s%-8.8s%-123.123s\n",
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
            if ($cbu === '' || abs($importe) < 0.005) {
                continue;
            }
            $centavos = (int) round(abs($importe) * 100);

            $out .= sprintf(
                "%-3.3s%-22.22s%017d%-60.60s%-2.2s%-12.12s%-2.2s%-12.12s%-12.12s%-2.2s%012d%-12.12s%010d%-11.11s%-51.51s\n",
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
