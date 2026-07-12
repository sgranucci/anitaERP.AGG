<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\ClienteVipGastronomia;

/**
 * Resuelve clientes VIP del reporte de descuentos (códigos Anita sueltos o rango numérico).
 */
final class GastronomiaDescuentoReporteVipSupport
{
    /**
     * @param  list<int>  $idsExplicitos
     * @return list<int>
     */
    public static function fusionarSeleccion(array $idsExplicitos, string $codigoDesde, string $codigoHasta, int $empresaId = 0): array
    {
        $ids = self::normalizarIds($idsExplicitos);

        foreach (self::resolverIdsPorRangoCodigo($codigoDesde, $codigoHasta, $empresaId) as $id) {
            if (! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        usort($ids, static fn (int $a, int $b) => $a <=> $b);

        return $ids;
    }

    /**
     * @return list<int>
     */
    public static function resolverIdsPorRangoCodigo(string $codigoDesde, string $codigoHasta, int $empresaId = 0): array
    {
        $desde = trim($codigoDesde);
        $hasta = trim($codigoHasta);

        if ($desde === '' && $hasta === '') {
            return [];
        }

        if ($desde !== '' && $hasta === '') {
            $hasta = $desde;
        } elseif ($hasta !== '' && $desde === '') {
            $desde = $hasta;
        }

        $codigos = GastronomiaDescuentoReporteCodigoSupport::expandir($desde.'/'.$hasta);
        if ($codigos === []) {
            return [];
        }

        return self::resolverIdsPorCodigos($codigos, $empresaId);
    }

    /**
     * @param  list<string>  $codigos
     * @return list<int>
     */
    public static function resolverIdsPorCodigos(array $codigos, int $empresaId = 0): array
    {
        if ($codigos === []) {
            return [];
        }

        $numeroids = [];
        foreach ($codigos as $codigo) {
            $codigo = trim((string) $codigo);
            if ($codigo === '' || ! ctype_digit($codigo)) {
                continue;
            }
            $numeroids[(int) $codigo] = true;
        }

        if ($numeroids === []) {
            return [];
        }

        $query = ClienteVipGastronomia::query()
            ->whereIn('numeroid', array_keys($numeroids));
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $vips = $query->orderBy('numeroid')->get(['id', 'numeroid']);

        $porNumeroid = [];
        foreach ($vips as $vip) {
            $porNumeroid[(int) $vip->numeroid] = (int) $vip->id;
        }

        $ids = [];
        foreach ($codigos as $codigo) {
            $codigo = trim((string) $codigo);
            if ($codigo === '' || ! ctype_digit($codigo)) {
                continue;
            }
            $id = $porNumeroid[(int) $codigo] ?? null;
            if ($id !== null && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        usort($ids, static fn (int $a, int $b) => $a <=> $b);

        return $ids;
    }

    public static function tieneRangoCodigo(string $codigoDesde, string $codigoHasta): bool
    {
        return trim($codigoDesde) !== '' || trim($codigoHasta) !== '';
    }

    /**
     * @param  list<string>  $codigos
     * @return list<string>
     */
    public static function codigosSinVipRegistrado(array $codigos, int $empresaId = 0): array
    {
        if ($codigos === []) {
            return [];
        }

        $ids = self::resolverIdsPorCodigos($codigos, $empresaId);
        if ($ids === []) {
            return $codigos;
        }

        $query = ClienteVipGastronomia::query()->whereIn('id', $ids);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $numeroidsEncontrados = $query
            ->pluck('numeroid')
            ->map(static fn ($c) => (int) $c)
            ->all();

        $setEncontrados = [];
        foreach ($numeroidsEncontrados as $numeroid) {
            $setEncontrados[$numeroid] = true;
        }

        $faltantes = [];
        foreach ($codigos as $codigo) {
            $codigo = trim((string) $codigo);
            if ($codigo === '' || ! ctype_digit($codigo)) {
                continue;
            }
            if (! isset($setEncontrados[(int) $codigo])) {
                $faltantes[] = $codigo;
            }
        }

        return $faltantes;
    }

    public static function etiquetaRangoCodigo(string $codigoDesde, string $codigoHasta): string
    {
        $desde = trim($codigoDesde);
        $hasta = trim($codigoHasta);

        if ($desde === '' && $hasta === '') {
            return '';
        }

        if ($desde !== '' && ($hasta === '' || $desde === $hasta)) {
            return $desde;
        }

        if ($desde === '' && $hasta !== '') {
            return $hasta;
        }

        return $desde.' — '.$hasta;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private static function normalizarIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $out, true)) {
                $out[] = $id;
            }
        }

        return $out;
    }
}
