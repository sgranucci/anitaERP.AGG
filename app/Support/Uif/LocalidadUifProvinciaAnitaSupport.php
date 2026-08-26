<?php

namespace App\Support\Uif;

/**
 * Mapea loc_provincia de Anita (tabla localidad / base_admin) al id de provincia_uif.
 * El código Anita coincide con provincia_uif.codigo (CABA=1, Buenos Aires=2, Córdoba=4, …).
 */
final class LocalidadUifProvinciaAnitaSupport
{
    /**
     * @param  iterable<mixed>  $provinciasUif
     * @return array<string, int>
     */
    public static function mapaCodigoAnitaAId(iterable $provinciasUif): array
    {
        $mapa = [];
        foreach ($provinciasUif as $provincia) {
            $codigo = self::normalizarCodigo(
                is_array($provincia) ? ($provincia['codigo'] ?? null) : ($provincia->codigo ?? null)
            );
            if ($codigo === '' || $codigo === '0') {
                continue;
            }

            $id = is_array($provincia) ? ($provincia['id'] ?? null) : ($provincia->id ?? null);
            if ($id === null || (int) $id <= 0) {
                continue;
            }

            $mapa[$codigo] = (int) $id;
        }

        return $mapa;
    }

    /**
     * @param  array<string, int>  $mapaCodigoAnitaAId
     */
    public static function provinciaUifIdDesdeCodigoAnita($codigoProvinciaAnita, array $mapaCodigoAnitaAId): ?int
    {
        $codigo = self::normalizarCodigo($codigoProvinciaAnita);
        if ($codigo === '' || $codigo === '0') {
            return null;
        }

        return $mapaCodigoAnitaAId[$codigo] ?? null;
    }

    public static function normalizarCodigo($codigo): string
    {
        if ($codigo === null) {
            return '';
        }

        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return '';
        }

        if (is_numeric($codigo)) {
            return (string) (int) $codigo;
        }

        return $codigo;
    }
}
