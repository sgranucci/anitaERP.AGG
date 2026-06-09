<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Búsqueda y resolución de artículos por SKU evitando duplicados por mayúsculas/minúsculas.
 */
final class ArticuloSkuMatchSupport
{
    public static function normalizar(string $sku): string
    {
        return strtoupper(trim($sku));
    }

    /**
     * @return Builder<Articulo>
     */
    public static function queryPorSku(string $sku): Builder
    {
        $norm = self::normalizar($sku);

        return Articulo::query()->whereRaw('UPPER(TRIM(sku)) = ?', [$norm]);
    }

    /**
     * @return Collection<int, Articulo>
     */
    public static function todosPorSku(string $sku): Collection
    {
        return self::queryPorSku($sku)->orderBy('id')->get();
    }

    public static function existe(string $sku): bool
    {
        return self::queryPorSku($sku)->exists();
    }

    /**
     * Elige un único registro cuando hay colisión de casing (v0432 vs V0432).
     */
    public static function resolverCanonico(string $sku): ?Articulo
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $exacto = Articulo::query()->where('sku', $sku)->orderBy('id')->first();
        if ($exacto !== null) {
            return $exacto;
        }

        $candidatos = self::todosPorSku($sku);
        if ($candidatos->isEmpty()) {
            return null;
        }

        if ($candidatos->count() === 1) {
            return $candidatos->first();
        }

        $ids = $candidatos->pluck('id')->all();
        $usoVentas = DB::table('venta_emision')
            ->select('articulo_id', DB::raw('COUNT(*) as total'))
            ->whereIn('articulo_id', $ids)
            ->groupBy('articulo_id')
            ->pluck('total', 'articulo_id');
        $usoCuentas = DB::table('cuenta_gastronomia_linea')
            ->select('articulo_id', DB::raw('COUNT(*) as total'))
            ->whereIn('articulo_id', $ids)
            ->groupBy('articulo_id')
            ->pluck('total', 'articulo_id');

        return $candidatos->sort(function (Articulo $a, Articulo $b) use ($usoVentas, $usoCuentas) {
            $usoA = (int) ($usoVentas[$a->id] ?? 0) + (int) ($usoCuentas[$a->id] ?? 0);
            $usoB = (int) ($usoVentas[$b->id] ?? 0) + (int) ($usoCuentas[$b->id] ?? 0);
            if ($usoA !== $usoB) {
                return $usoB <=> $usoA;
            }

            return $a->id <=> $b->id;
        })->first();
    }

    /**
     * Inactiva duplicados del mismo SKU (normalizado) dejando solo el canónico.
     *
     * @return list<int> ids inactivados
     */
    public static function inactivarDuplicados(string $skuCanonico, ?int $idCanonico = null): array
    {
        $canonico = $idCanonico !== null
            ? Articulo::query()->find($idCanonico)
            : self::resolverCanonico($skuCanonico);

        if ($canonico === null) {
            return [];
        }

        $inactivados = [];
        $duplicados = self::queryPorSku($skuCanonico)
            ->where('id', '!=', $canonico->id)
            ->get();

        foreach ($duplicados as $dup) {
            $dup->update([
                'sku' => self::skuLegacyDuplicado($dup),
                'estado' => 'INACTIVO',
            ]);
            $inactivados[] = (int) $dup->id;
        }

        if ($canonico->sku !== $skuCanonico) {
            $canonico->update(['sku' => $skuCanonico]);
        }

        return $inactivados;
    }

    public static function skuLegacyDuplicado(Articulo $articulo): string
    {
        $base = 'DUP-'.$articulo->id.'-'.self::normalizar((string) $articulo->sku);

        return strlen($base) > 50 ? substr($base, 0, 50) : $base;
    }
}
