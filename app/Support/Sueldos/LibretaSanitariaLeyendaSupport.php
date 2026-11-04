<?php

namespace App\Support\Sueldos;

/**
 * Lee las leyendas del legajo (Anita empley) y extrae libreta sanitaria.
 * "DEBE LIBRETA SANITARIA" marca la deuda. "VCE. LIBRETA SANITARIA 03/2007"
 * es un vencimiento: si viene mes/año, se toma el último día de ese mes.
 */
class LibretaSanitariaLeyendaSupport
{
    /**
     * @param  list<string>  $leyendas
     * @return array{debe: bool, vencimiento: ?string}
     */
    public static function interpretar(array $leyendas): array
    {
        $debe = false;
        $vencimiento = null;

        foreach ($leyendas as $linea) {
            $texto = self::normalizar($linea);
            if ($texto === '' || ! self::esLibretaSanitaria($texto)) {
                continue;
            }
            if (str_contains($texto, 'DEBE')) {
                $debe = true;
            }
            $fecha = self::extraerVencimiento($texto);
            if ($fecha !== null) {
                $vencimiento = $fecha;
            }
        }

        return [
            'debe' => $debe,
            'vencimiento' => $vencimiento,
        ];
    }

    public static function esLibretaSanitaria(string $texto): bool
    {
        return (bool) preg_match('/LIBRET\w*\s+SANI/', self::normalizar($texto));
    }

    private static function normalizar(string $texto): string
    {
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N',
        ]);

        return trim((string) preg_replace('/\s+/', ' ', mb_strtoupper($texto, 'UTF-8')));
    }

    private static function extraerVencimiento(string $texto): ?string
    {
        if (preg_match('/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})/', $texto, $m)) {
            return self::fecha((int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('/(\d{1,2})[\/\-.](\d{4})/', $texto, $m)) {
            $mes = (int) $m[1];
            $anio = (int) $m[2];
            if ($mes < 1 || $mes > 12 || $anio < 1900) {
                return null;
            }

            return self::fecha($anio, $mes, (int) date('t', strtotime(sprintf('%04d-%02d-01', $anio, $mes))));
        }

        return null;
    }

    private static function fecha(int $anio, int $mes, int $dia): ?string
    {
        if (! checkdate($mes, $dia, $anio)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }
}
