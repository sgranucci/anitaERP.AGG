<?php

namespace App\Support\Ventas;

use App\Models\Ventas\DescuentoGastronomia;

/**
 * Interpreta códigos de descuento gastronomía: único, lista (comas) o rango (barra / guión).
 */
final class GastronomiaDescuentoReporteCodigoSupport
{
    /**
     * @return list<string>
     */
    public static function expandir(string $entrada): array
    {
        $entrada = trim($entrada);
        if ($entrada === '') {
            return [];
        }

        $tokens = preg_split('/[,;]+/', $entrada) ?: [];
        $codigos = [];

        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '') {
                continue;
            }

            foreach (self::expandirToken($token) as $codigo) {
                $codigos[$codigo] = true;
            }
        }

        $lista = array_keys($codigos);
        usort($lista, fn (string $a, string $b) => self::compararCodigos($a, $b));

        return $lista;
    }

    public static function formatearCriterio(string $entrada): string
    {
        $entrada = trim($entrada);
        if ($entrada === '') {
            return '';
        }

        $codigos = self::expandir($entrada);
        if ($codigos === []) {
            return $entrada;
        }

        return implode(', ', $codigos);
    }

    /**
     * @return list<string>
     */
    public static function resolverExistentes(array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }

        return DescuentoGastronomia::query()
            ->whereIn('codigo', $codigos)
            ->orderBy('codigo')
            ->pluck('codigo')
            ->map(fn ($c) => trim((string) $c))
            ->filter(fn (string $c) => $c !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private static function expandirToken(string $token): array
    {
        foreach (['/', '-'] as $sep) {
            if (str_contains($token, $sep)) {
                $partes = array_map('trim', explode($sep, $token, 2));
                $desde = $partes[0] ?? '';
                $hasta = $partes[1] ?? '';
                if ($desde !== '' && $hasta !== '') {
                    return self::expandirRangoNumerico($desde, $hasta);
                }
            }
        }

        $normalizado = self::normalizarCodigo($token);

        return $normalizado !== '' ? [$normalizado] : [];
    }

    /**
     * @return list<string>
     */
    private static function expandirRangoNumerico(string $desde, string $hasta): array
    {
        $numDesde = self::aNumero($desde);
        $numHasta = self::aNumero($hasta);
        if ($numDesde === null || $numHasta === null) {
            $out = [];
            $cDesde = self::normalizarCodigo($desde);
            $cHasta = self::normalizarCodigo($hasta);
            if ($cDesde !== '') {
                $out[] = $cDesde;
            }
            if ($cHasta !== '' && $cHasta !== $cDesde) {
                $out[] = $cHasta;
            }

            return $out;
        }

        if ($numDesde > $numHasta) {
            [$numDesde, $numHasta] = [$numHasta, $numDesde];
        }

        $out = [];
        for ($n = $numDesde; $n <= $numHasta; $n++) {
            $out[] = (string) $n;
        }

        return $out;
    }

    private static function normalizarCodigo(string $valor): string
    {
        return trim($valor);
    }

    private static function aNumero(string $valor): ?int
    {
        $valor = trim($valor);
        if ($valor === '' || ! ctype_digit($valor)) {
            return null;
        }

        return (int) $valor;
    }

    private static function compararCodigos(string $a, string $b): int
    {
        $na = self::aNumero($a);
        $nb = self::aNumero($b);
        if ($na !== null && $nb !== null) {
            return $na <=> $nb;
        }

        return strcmp($a, $b);
    }
}
