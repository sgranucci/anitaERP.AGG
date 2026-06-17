<?php

namespace App\Support\Ventas;

use App\Models\Stock\Articulo;

/**
 * Valida que cada ítem con artículo tenga lista de precios asignada (pedidos / facturación).
 */
final class ArticuloListaprecioLineaVentasSupport
{
    public static function listaprecioIdValido(mixed $listaprecioId): bool
    {
        if ($listaprecioId === null || $listaprecioId === '') {
            return false;
        }

        return (int) $listaprecioId > 0;
    }

    /**
     * @return array{error: string}|null
     */
    public static function validarLineas(
        ?array $articuloIds,
        ?array $listaspreciosIds,
        ?array $codigosArticulo = null,
    ): ?array {
        if (! is_array($articuloIds)) {
            return null;
        }

        foreach ($articuloIds as $i => $articuloId) {
            $articuloId = (int) $articuloId;
            if ($articuloId <= 0) {
                continue;
            }

            $listaprecioId = is_array($listaspreciosIds) && array_key_exists($i, $listaspreciosIds)
                ? $listaspreciosIds[$i]
                : null;

            if (! self::listaprecioIdValido($listaprecioId)) {
                return [
                    'error' => self::mensajeError(
                        (int) $i + 1,
                        self::etiquetaArticulo($articuloId, $codigosArticulo, (int) $i),
                    ),
                ];
            }
        }

        return null;
    }

    /**
     * Red de seguridad al facturar ítems ya grabados en pedido.
     * Un pedido nuevo debería haber validado la lista al cargarse; cubre pedidos legacy o datos inconsistentes.
     *
     * @return array{error: string}|null
     */
    public static function validarPedidoArticuloPersistido(
        mixed $listaprecioId,
        int $numeroItem,
        string $etiquetaArticulo,
    ): ?array {
        if (self::listaprecioIdValido($listaprecioId)) {
            return null;
        }

        return ['error' => self::mensajeError($numeroItem, $etiquetaArticulo)];
    }

    private static function etiquetaArticulo(int $articuloId, ?array $codigos, int $indice): string
    {
        if (is_array($codigos) && isset($codigos[$indice]) && trim((string) $codigos[$indice]) !== '') {
            return trim((string) $codigos[$indice]);
        }

        $articulo = Articulo::query()->select('sku', 'descripcion')->find($articuloId);
        if ($articulo) {
            $sku = trim((string) $articulo->sku);

            return $sku !== '' ? $sku : trim((string) $articulo->descripcion);
        }

        return (string) $articuloId;
    }

    public static function mensajeError(int $numeroItem, string $etiquetaArticulo): string
    {
        return 'El artículo '.$etiquetaArticulo.' (ítem '.$numeroItem.') no tiene lista de precios asignada.';
    }
}
