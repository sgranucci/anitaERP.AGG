<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;

/**
 * Resolución de artículo para el pickeo de transferencia (SKU o código de barras).
 */
final class TransferenciaMercaderiaPickeoSupport
{
    /**
     * @return list<string>
     */
    public static function variantesCodigo(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return [];
        }

        $variantes = [$codigo];
        $sinCeros = ltrim($codigo, '0');
        if ($sinCeros !== '' && $sinCeros !== $codigo) {
            $variantes[] = $sinCeros;
        }

        $norm = ArticuloSkuMatchSupport::normalizar($codigo);
        if ($norm !== '' && ! in_array($norm, $variantes, true)) {
            $variantes[] = $norm;
        }

        return array_values(array_unique($variantes));
    }

    public static function resolver(string $codigo): ?Articulo
    {
        $variantes = self::variantesCodigo($codigo);
        if ($variantes === []) {
            return null;
        }

        foreach ($variantes as $candidato) {
            $porSku = ArticuloSkuMatchSupport::resolverCanonico($candidato);
            if (ArticuloSeleccionOperativaSupport::esSeleccionable($porSku)) {
                return $porSku;
            }
        }

        $porBarra = self::resolverPorCampo('codigobarra', $variantes);
        if ($porBarra !== null) {
            return $porBarra;
        }

        return self::resolverPorCampo('skualternativo', $variantes);
    }

    /**
     * @param  list<string>  $variantes
     */
    private static function resolverPorCampo(string $columna, array $variantes): ?Articulo
    {
        if ($variantes === []) {
            return null;
        }

        $query = ArticuloSeleccionOperativaSupport::aplicarSoloActivosTablaArticulo(
            Articulo::query()->where(function ($q) use ($columna, $variantes) {
                $q->whereIn($columna, $variantes);
                foreach ($variantes as $valor) {
                    $q->orWhereRaw('UPPER(TRIM('.$columna.')) = ?', [ArticuloSkuMatchSupport::normalizar($valor)]);
                }
            })
        );

        $candidatos = $query->orderBy('id')->get();
        if ($candidatos->isEmpty()) {
            return null;
        }

        if ($candidatos->count() === 1) {
            return $candidatos->first();
        }

        $sku = (string) ($candidatos->first()->sku ?? '');

        return ArticuloSkuMatchSupport::resolverCanonico($sku) ?? $candidatos->first();
    }
}
