<?php

declare(strict_types=1);

namespace App\Support\Contable\CanonEntidades;

/**
 * Detalle de la solicitud y nombre del PDF adjunto del canon entidades.
 * Placeholders: {EMP} (BSA / KSA / RSA) y {MM.AA} (10.26).
 */
final class CanonEntidadesSolicitudPlantillaSupport
{
    public static function armarDetalle(string $plantilla, string $codigoEmpresa, string $periodoYm): string
    {
        $detalle = str_replace(
            ['{EMP}', '{MM.AA}'],
            [strtoupper(trim($codigoEmpresa)), self::mesAnio($periodoYm, '.')],
            $plantilla
        );

        return mb_substr(trim(preg_replace('/\s+/', ' ', $detalle) ?? $detalle), 0, 180);
    }

    public static function nombreArchivoNota(string $codigoEmpresa, string $periodoYm): string
    {
        return sprintf(
            'Canon entidades %s %s.pdf',
            strtoupper(trim($codigoEmpresa)),
            self::mesAnio($periodoYm, '-')
        );
    }

    /**
     * Mes y año como MM{sep}AA (10.26) o MM{sep}AAAA (10-2026).
     */
    private static function mesAnio(string $periodoYm, string $sep): string
    {
        $periodo = preg_replace('/\D/', '', $periodoYm) ?: date('Ym');
        $mm = substr($periodo, 4, 2);
        $aaaa = substr($periodo, 0, 4);

        return $sep === '.'
            ? $mm.'.'.substr($aaaa, 2, 2)
            : $mm.'-'.$aaaa;
    }
}
