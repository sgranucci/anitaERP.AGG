<?php

namespace App\Support\Uif;

final class UifWigosConciliacionEmpresaSupport
{
    /** @var array<string, int> */
    private const CODIGO_EMPRESA_ID = [
        'BSA' => 1,
        'KSA' => 2,
        'RSA' => 3,
    ];

    /** @var array<int, string> */
    private const EMPRESA_ID_CODIGO = [
        1 => 'BSA',
        2 => 'KSA',
        3 => 'RSA',
    ];

    /** @return array<string, int> */
    public static function mapaCodigoEmpresaId(): array
    {
        $config = config('uif.conciliacion_wigos.empresas', []);

        return is_array($config) && $config !== [] ? $config : self::CODIGO_EMPRESA_ID;
    }

    public static function empresaIdDesdeCodigo(string $codigo): ?int
    {
        $codigo = strtoupper(trim($codigo));
        $mapa = self::mapaCodigoEmpresaId();

        return isset($mapa[$codigo]) ? (int) $mapa[$codigo] : null;
    }

    public static function codigoDesdeEmpresaId(int $empresaId): ?string
    {
        $mapa = self::mapaCodigoEmpresaId();
        foreach ($mapa as $codigo => $id) {
            if ((int) $id === $empresaId) {
                return strtoupper($codigo);
            }
        }

        return self::EMPRESA_ID_CODIGO[$empresaId] ?? null;
    }

    /** @return list<int> */
    public static function empresaIdsOrdenados(): array
    {
        return array_values(array_unique(array_map('intval', self::mapaCodigoEmpresaId())));
    }

    public static function detectarCodigoDesdeNombreHoja(string $nombreHoja): ?string
    {
        $upper = strtoupper(trim($nombreHoja));

        if (preg_match('/\b(BSA|KSA|RSA)\b/', $upper, $m)) {
            return $m[1];
        }

        if (str_contains($upper, 'WILDE')) {
            return 'KSA';
        }

        return null;
    }

    public static function nombreSolapaTitos(int $empresaId): string
    {
        $codigo = self::codigoDesdeEmpresaId($empresaId) ?? 'UIF';

        return $codigo.' Tito Wigos';
    }

    public static function nombreSolapaPm(int $empresaId): string
    {
        $codigo = self::codigoDesdeEmpresaId($empresaId) ?? 'UIF';

        return $codigo.' PM Wigos';
    }

    public static function nombreSolapaUnificado(int $empresaId): string
    {
        $codigo = self::codigoDesdeEmpresaId($empresaId) ?? 'UIF';

        return $codigo.' UNIFICADO';
    }

    public static function nombreArchivoLibro(int $empresaId, int $anio, int $mes): string
    {
        $codigo = self::codigoDesdeEmpresaId($empresaId) ?? 'UIF';

        return sprintf('conciliacion_wigos_%s_%04d%02d.xlsx', strtolower($codigo), $anio, $mes);
    }

    public static function nombreArchivoLibroGlobal(int $anio, int $mes): string
    {
        return sprintf('conciliacion_wigos_global_%04d%02d.xlsx', $anio, $mes);
    }
}
