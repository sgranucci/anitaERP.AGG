<?php

namespace App\Support\Stock;

use App\Models\Stock\Depmae;

/**
 * Mapeo depósito Anita (recv_deposito / código depmae) ↔ id ERP (depmae.id).
 */
final class RecepcionProveedorDepositoAnitaSupport
{
    /** @var array<string, int|null> */
    private static array $cacheIdPorCodigoEmpresa = [];

    public static function reiniciarCache(): void
    {
        self::$cacheIdPorCodigoEmpresa = [];
    }

    public static function normalizarSku(string $sku): string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return '';
        }

        return ltrim($sku, '0') ?: '0';
    }

    public static function skusCoinciden(string $skuA, string $skuB): bool
    {
        return self::normalizarSku($skuA) === self::normalizarSku($skuB);
    }

    /**
     * recv_deposito en Anita es el código del depósito (depmae.codigo), no el id ERP.
     * Si no existe en la empresa indicada, busca en cualquier empresa (depósitos cross-empresa como TITOS).
     */
    public static function resolverIdDesdeCodigoAnita(int $codigoDepositoAnita, int $empresaId): ?int
    {
        if ($codigoDepositoAnita <= 0) {
            return null;
        }

        $cacheKey = $empresaId.'-'.$codigoDepositoAnita;
        if (array_key_exists($cacheKey, self::$cacheIdPorCodigoEmpresa)) {
            return self::$cacheIdPorCodigoEmpresa[$cacheKey];
        }

        $codigo = (string) $codigoDepositoAnita;
        $candidatos = array_values(array_unique(array_filter([
            $codigo,
            ltrim($codigo, '0') ?: '0',
        ], static fn (string $c): bool => $c !== '')));

        $id = self::buscarPorCodigoYEmpresa($candidatos, $empresaId);
        if ($id === null && $empresaId > 0) {
            $id = self::buscarPorCodigoSinEmpresa($candidatos);
        }

        self::$cacheIdPorCodigoEmpresa[$cacheKey] = $id;

        return $id;
    }

    /**
     * @param  list<string>  $candidatos
     */
    private static function buscarPorCodigoYEmpresa(array $candidatos, int $empresaId): ?int
    {
        foreach ($candidatos as $codigoBusqueda) {
            $encontrado = Depmae::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', $codigoBusqueda)
                ->value('id');
            if ($encontrado !== null && (int) $encontrado > 0) {
                return (int) $encontrado;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $candidatos
     */
    private static function buscarPorCodigoSinEmpresa(array $candidatos): ?int
    {
        foreach ($candidatos as $codigoBusqueda) {
            $encontrado = Depmae::query()
                ->where('codigo', $codigoBusqueda)
                ->orderBy('id')
                ->value('id');
            if ($encontrado !== null && (int) $encontrado > 0) {
                return (int) $encontrado;
            }
        }

        return null;
    }

    /**
     * @param  list<object>  $lineasAnita
     */
    public static function codigoDepositoAnitaParaSku(array $lineasAnita, string $skuErp): ?int
    {
        foreach ($lineasAnita as $lin) {
            $skuAnita = (string) ($lin->recv_articulo ?? '');
            if (! self::skusCoinciden($skuAnita, $skuErp)) {
                continue;
            }

            $codigo = (int) ($lin->recv_deposito ?? 0);

            return $codigo > 0 ? $codigo : null;
        }

        return null;
    }

    /**
     * @param  list<object>  $lineasAnita
     */
    public static function resolverIdDepositoParaSku(array $lineasAnita, string $skuErp, int $empresaId): ?int
    {
        $codigoAnita = self::codigoDepositoAnitaParaSku($lineasAnita, $skuErp);
        if ($codigoAnita === null) {
            return null;
        }

        return self::resolverIdDesdeCodigoAnita($codigoAnita, $empresaId);
    }
}
