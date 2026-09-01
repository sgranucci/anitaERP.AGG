<?php

declare(strict_types=1);

namespace App\Support\Contable\CanonEntidades;

/**
 * Alícuotas del canon entidad de bien público (F2015).
 * Máquinas es 1% en las tres empresas; bingo cambia (Biyemas escalonado).
 */
final class CanonEntidadesReglasSupport
{
    public const CUIT_BIYEMAS = '30682403671';

    public const CUIT_KANDIKO = '30685217720';

    public const CUIT_REBISCO = '30705464592';

    public const REGLA_BIYEMAS = 'biyemas';

    public const REGLA_KANDIKO = 'kandiko';

    public const REGLA_REBISCO = 'rebisco';

    public const REGLA_PLANA = 'plana';

    public const CODIGO_BSA = 'BSA';

    public const CODIGO_KSA = 'KSA';

    public const CODIGO_RSA = 'RSA';

    public const CUENTA_CODIGO = '215010003';

    public const CUENTA_ETIQUETA = '215010-003';

    public const ALICUOTA_MAQUINAS = 0.01;

    public const ALICUOTA_BINGO_PLANA = 0.01;

    public const PISO_BINGO_BIYEMAS = 1_500_000.0;

    public const ALICUOTA_BINGO_BIYEMAS_TRAMO1 = 0.02;

    public const ALICUOTA_BINGO_BIYEMAS_TRAMO2 = 0.0325;

    public const TOLERANCIA = 1.0;

    /**
     * @return array<string, mixed>
     */
    public static function resolver(string $cuit, string $nombre = ''): array
    {
        $cuitNorm = self::normalizarCuit($cuit);
        $regla = match ($cuitNorm) {
            self::CUIT_BIYEMAS => self::REGLA_BIYEMAS,
            self::CUIT_KANDIKO => self::REGLA_KANDIKO,
            self::CUIT_REBISCO => self::REGLA_REBISCO,
            default => self::resolverPorNombre($nombre),
        };

        $reconocida = $regla !== self::REGLA_PLANA;

        return [
            'regla' => $regla,
            'codigo' => self::codigoCorto($regla),
            'reconocida' => $reconocida,
            'cuit' => $cuitNorm,
            'bingo_escalonado' => $regla === self::REGLA_BIYEMAS,
            'etiqueta_bingo' => $regla === self::REGLA_BIYEMAS
                ? '2% hasta $1.500.000 + 3,25% excedente'
                : '1% del total de ventas de bingo',
            'etiqueta_maquinas' => '1% Win Electrónico (días +)',
            'cuenta_codigo' => self::CUENTA_CODIGO,
            'cuenta_etiqueta' => self::CUENTA_ETIQUETA,
            'tolerancia' => self::TOLERANCIA,
        ];
    }

    public static function esBingoEscalonado(string $regla): bool
    {
        return $regla === self::REGLA_BIYEMAS;
    }

    public static function cuadra(float $calculado, float $haberMayor): bool
    {
        return abs(round($calculado - $haberMayor, 2)) <= self::TOLERANCIA;
    }

    public static function normalizarCuit(string $cuit): string
    {
        return preg_replace('/\D/', '', $cuit) ?? '';
    }

    public static function formatearCuit(string $cuit): string
    {
        $n = self::normalizarCuit($cuit);
        if (strlen($n) !== 11) {
            return $cuit;
        }

        return substr($n, 0, 2).'-'.substr($n, 2, 8).'-'.substr($n, 10, 1);
    }

    private static function resolverPorNombre(string $nombre): string
    {
        $u = strtoupper(self::sinAcentos($nombre));
        if (str_contains($u, 'BIYEMAS')) {
            return self::REGLA_BIYEMAS;
        }
        if (str_contains($u, 'KANDIKO')) {
            return self::REGLA_KANDIKO;
        }
        if (str_contains($u, 'REBISCO')) {
            return self::REGLA_REBISCO;
        }

        return self::REGLA_PLANA;
    }

    private static function codigoCorto(string $regla): string
    {
        return match ($regla) {
            self::REGLA_BIYEMAS => self::CODIGO_BSA,
            self::REGLA_KANDIKO => self::CODIGO_KSA,
            self::REGLA_REBISCO => self::CODIGO_RSA,
            default => '',
        };
    }

    private static function sinAcentos(string $texto): string
    {
        $map = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        ];

        return strtr($texto, $map);
    }
}
