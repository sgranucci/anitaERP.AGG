<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Proveedor;

/**
 * Resolución de artículo para el pickeo de transferencia
 * (SKU, código de barras del artículo o del catálogo proveedor).
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

        $porCatalogo = self::resolverPorCatalogoProveedor($variantes);
        if ($porCatalogo !== null) {
            return $porCatalogo;
        }

        return self::resolverPorCampo('skualternativo', $variantes);
    }

    /**
     * Código de barras o código de artículo cargado en la solapa Proveedores.
     *
     * @param  list<string>  $variantes
     */
    private static function resolverPorCatalogoProveedor(array $variantes): ?Articulo
    {
        if ($variantes === []) {
            return null;
        }

        $normas = [];
        foreach ($variantes as $valor) {
            $norm = ArticuloSkuMatchSupport::normalizar($valor);
            if ($norm !== '') {
                $normas[] = $norm;
            }
        }
        $normas = array_values(array_unique($normas));

        $filas = Articulo_Proveedor::query()
            ->with('articulos')
            ->where('activo', true)
            ->where(function ($q) use ($variantes, $normas) {
                $q->whereIn('codigobarra', $variantes)
                    ->orWhereIn('codigo_articulo_proveedor', $variantes);
                foreach ($normas as $norm) {
                    $q->orWhereRaw('UPPER(TRIM(codigobarra)) = ?', [$norm])
                        ->orWhereRaw('UPPER(TRIM(codigo_articulo_proveedor)) = ?', [$norm]);
                }
            })
            ->orderByDesc('preferido')
            ->orderBy('id')
            ->get();

        $elegibles = [];
        foreach ($filas as $fila) {
            $articulo = $fila->articulos;
            if (! ArticuloSeleccionOperativaSupport::esSeleccionable($articulo)) {
                continue;
            }
            $elegibles[(int) $articulo->id] = $articulo;
        }

        if ($elegibles === []) {
            return null;
        }

        if (count($elegibles) === 1) {
            return array_values($elegibles)[0];
        }

        $sku = (string) (array_values($elegibles)[0]->sku ?? '');

        return ArticuloSkuMatchSupport::resolverCanonico($sku) ?? array_values($elegibles)[0];
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
