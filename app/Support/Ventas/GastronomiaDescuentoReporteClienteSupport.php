<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;

/**
 * Resuelve clientes internos del reporte de descuentos (códigos sueltos o rango numérico).
 */
final class GastronomiaDescuentoReporteClienteSupport
{
    /**
     * @param  list<int>  $idsExplicitos
     * @return list<int>
     */
    public static function fusionarSeleccion(array $idsExplicitos, string $codigoDesde, string $codigoHasta): array
    {
        $ids = self::normalizarIds($idsExplicitos);

        foreach (self::resolverIdsPorRangoCodigo($codigoDesde, $codigoHasta) as $id) {
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
    public static function resolverIdsPorRangoCodigo(string $codigoDesde, string $codigoHasta): array
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

        return self::resolverIdsPorCodigos($codigos);
    }

    /**
     * @param  list<string>  $codigos
     * @return list<int>
     */
    public static function resolverIdsPorCodigos(array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }

        $variantes = [];
        foreach ($codigos as $codigo) {
            $codigo = trim((string) $codigo);
            if ($codigo === '') {
                continue;
            }
            $variantes[$codigo] = true;
            $alt = ltrim($codigo, '0') ?: $codigo;
            if ($alt !== $codigo) {
                $variantes[$alt] = true;
            }
        }

        if ($variantes === []) {
            return [];
        }

        $clientes = Cliente::query()
            ->whereIn('codigo', array_keys($variantes))
            ->orderBy('codigo')
            ->get(['id', 'codigo']);

        $porCodigo = [];
        foreach ($clientes as $cliente) {
            $porCodigo[trim((string) $cliente->codigo)] = (int) $cliente->id;
        }

        $ids = [];
        foreach ($codigos as $codigo) {
            $codigo = trim((string) $codigo);
            if ($codigo === '') {
                continue;
            }
            $id = $porCodigo[$codigo] ?? $porCodigo[ltrim($codigo, '0') ?: $codigo] ?? null;
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
    public static function codigosSinClienteRegistrado(array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }

        $ids = self::resolverIdsPorCodigos($codigos);
        if ($ids === []) {
            return $codigos;
        }

        $codigosEncontrados = Cliente::query()
            ->whereIn('id', $ids)
            ->pluck('codigo')
            ->map(static fn ($c) => trim((string) $c))
            ->filter(static fn (string $c) => $c !== '')
            ->all();

        $setEncontrados = [];
        foreach ($codigosEncontrados as $codigo) {
            $setEncontrados[$codigo] = true;
            $alt = ltrim($codigo, '0') ?: $codigo;
            $setEncontrados[$alt] = true;
        }

        $faltantes = [];
        foreach ($codigos as $codigo) {
            $codigo = trim((string) $codigo);
            if ($codigo === '') {
                continue;
            }
            $alt = ltrim($codigo, '0') ?: $codigo;
            if (! isset($setEncontrados[$codigo]) && ! isset($setEncontrados[$alt])) {
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
