<?php

namespace App\Support\Ventas;

use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Models\Ventas\Cliente;

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
     * Lista a usar cuando el renglón no trae una. Primero la del cliente, después la default,
     * y si el artículo tiene precio vigente en otra lista, esa.
     */
    public static function resolverFallback(int $articuloId, ?int $clienteId, mixed $fecha = null): int
    {
        if ($articuloId <= 0) {
            return 0;
        }

        $fechaYmd = self::fechaYmd($fecha);
        $candidatos = [];

        if ($clienteId !== null && $clienteId > 0) {
            $listaCliente = (int) Cliente::query()->whereKey($clienteId)->value('listaprecio_id');
            if ($listaCliente > 0 && Listaprecio::query()->whereKey($listaCliente)->exists()) {
                $candidatos[] = $listaCliente;
            }
        }

        $listaDefault = (int) config('precio.listaprecio_default_id');
        if ($listaDefault > 0 && ! in_array($listaDefault, $candidatos, true)) {
            $candidatos[] = $listaDefault;
        }

        foreach ($candidatos as $listaId) {
            if (self::tienePrecioVigente($articuloId, $listaId, $fechaYmd)) {
                return $listaId;
            }
        }

        $listaConPrecio = Precio::query()
            ->where('articulo_id', $articuloId)
            ->where('listaprecio_id', '>', 0)
            ->where('fechavigencia', '<=', $fechaYmd)
            ->where('precio', '>', 0)
            ->orderByDesc('fechavigencia')
            ->orderBy('listaprecio_id')
            ->value('listaprecio_id');

        return (int) $listaConPrecio;
    }

    private static function tienePrecioVigente(int $articuloId, int $listaId, string $fechaYmd): bool
    {
        if ($listaId <= 0) {
            return false;
        }

        return Precio::query()
            ->where('articulo_id', $articuloId)
            ->where('listaprecio_id', $listaId)
            ->where('fechavigencia', '<=', $fechaYmd)
            ->where('precio', '>', 0)
            ->exists();
    }

    private static function fechaYmd(mixed $fecha): string
    {
        $texto = trim((string) ($fecha ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $texto) === 1) {
            return substr($texto, 0, 10);
        }

        $dma = \DateTime::createFromFormat('d/m/Y', substr($texto, 0, 10));
        if ($dma instanceof \DateTime) {
            return $dma->format('Y-m-d');
        }

        $timestamp = $texto !== '' ? strtotime($texto) : false;

        return $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
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
