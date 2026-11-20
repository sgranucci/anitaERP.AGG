<?php

declare(strict_types=1);

namespace App\Support\Contable\CanonMunicipal;

use App\Models\Contable\Canon_Municipal_Config;

/**
 * Arma el detalle de la SP desde la plantilla de config.
 * Placeholders: {ORDINAL} (PRIMERA/SEGUNDA/…), {MES} (NOVIEMBRE).
 */
final class CanonMunicipalDetallePlantillaSupport
{
    /** @var array<int, string> */
    private const ORDINALES = [
        1 => 'PRIMERA',
        2 => 'SEGUNDA',
        3 => 'TERCERA',
        4 => 'CUARTA',
        5 => 'QUINTA',
        6 => 'SEXTA',
    ];

    /** @var array<int, string> */
    private const MESES = [
        1 => 'ENERO',
        2 => 'FEBRERO',
        3 => 'MARZO',
        4 => 'ABRIL',
        5 => 'MAYO',
        6 => 'JUNIO',
        7 => 'JULIO',
        8 => 'AGOSTO',
        9 => 'SEPTIEMBRE',
        10 => 'OCTUBRE',
        11 => 'NOVIEMBRE',
        12 => 'DICIEMBRE',
    ];

    /** @var array<string, string> */
    private const ABREV_PLANTILLA = [
        'biyemas' => 'BSA',
        'kandiko' => 'KSA',
        'rebisco' => 'RSA',
    ];

    public static function armar(?string $plantilla, int $liquidacion, string $periodoYm): string
    {
        $tpl = trim((string) $plantilla);
        if ($tpl === '') {
            $tpl = Canon_Municipal_Config::PLANTILLA_DETALLE_DEFAULT;
        }

        $mesNum = (int) substr(preg_replace('/\D/', '', $periodoYm) ?: date('Ym'), 4, 2);
        $ordinal = self::ORDINALES[$liquidacion] ?? (string) $liquidacion;
        $mes = self::MESES[$mesNum] ?? '';

        $detalle = str_replace(
            ['{ORDINAL}', '{MES}', '{ordinal}', '{mes}'],
            [$ordinal, $mes, $ordinal, $mes],
            $tpl
        );

        return mb_substr(trim(preg_replace('/\s+/', ' ', $detalle) ?? $detalle), 0, 180);
    }

    public static function abreviaturaEmpresa(string $plantilla, string $nombreEmpresa = ''): string
    {
        $key = strtolower(trim($plantilla));
        if (isset(self::ABREV_PLANTILLA[$key])) {
            return self::ABREV_PLANTILLA[$key];
        }

        $nombre = strtoupper(trim($nombreEmpresa));
        if (str_contains($nombre, 'BIYEMAS')) {
            return 'BSA';
        }
        if (str_contains($nombre, 'KANDIKO')) {
            return 'KSA';
        }
        if (str_contains($nombre, 'REBISCO')) {
            return 'RSA';
        }

        return 'EMP';
    }

    public static function nombreArchivoNota(string $abrev, int $liquidacion, string $periodoYm): string
    {
        $periodo = preg_replace('/\D/', '', $periodoYm) ?: date('Ym');
        $mm = substr($periodo, 4, 2);
        $aaaa = substr($periodo, 0, 4);

        return sprintf(
            'Nota canon bingo %s semana %d %s-%s.pdf',
            $abrev,
            max(1, $liquidacion),
            $mm,
            $aaaa
        );
    }
}
