<?php

namespace App\Support\Compras;

use App\Models\Compras\Listaprecio_Proveedor;
use App\Models\Compras\Listaprecio_Proveedor_Articulo;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Proveedor;

class ArticuloProveedorCodigoSyncSupport
{
    public const ORIGEN_CATALOGO = 'catalogo';

    public const ORIGEN_LISTA = 'lista';

    public static function desdeCatalogo(int $articuloId, int $proveedorId, ?string $codigo): void
    {
        self::propagar($articuloId, $proveedorId, $codigo, self::ORIGEN_CATALOGO);
    }

    public static function desdeLista(int $articuloId, int $proveedorId, ?string $codigo, ?int $listaprecioProveedorId = null): void
    {
        if ($listaprecioProveedorId !== null && $listaprecioProveedorId > 0) {
            $lista = Listaprecio_Proveedor::query()->find($listaprecioProveedorId);
            if (! $lista || (string) ($lista->estado ?? '') !== 'ACTIVA') {
                return;
            }
        }

        self::propagar($articuloId, $proveedorId, $codigo, self::ORIGEN_LISTA);
    }

    public static function propagar(int $articuloId, int $proveedorId, ?string $codigo, string $origen): void
    {
        if ($articuloId <= 0 || $proveedorId <= 0) {
            return;
        }

        $codigo = self::normalizar($codigo);
        if ($codigo === null) {
            return;
        }

        if ($origen === self::ORIGEN_CATALOGO) {
            self::haciaListaVigenteActiva($articuloId, $proveedorId, $codigo);

            return;
        }

        self::haciaCatalogo($articuloId, $proveedorId, $codigo);
    }

    /**
     * Código efectivo para mostrar: catálogo, o lista vigente activa si el catálogo está vacío.
     */
    public static function codigoEfectivoParaLinea(Articulo_Proveedor $linea, ?string $fechaRef = null): ?string
    {
        $catalogo = self::normalizar($linea->codigo_articulo_proveedor);
        if ($catalogo !== null) {
            return $catalogo;
        }

        $articuloId = (int) ($linea->articulo_id ?? 0);
        $proveedorId = (int) ($linea->proveedor_id ?? 0);
        if ($articuloId <= 0 || $proveedorId <= 0) {
            return null;
        }

        $vigente = ArticuloProveedorPrecioListaSupport::precioVigente($articuloId, $proveedorId, null, $fechaRef);

        return self::normalizar($vigente['codigo_articulo_proveedor'] ?? null);
    }

    private static function haciaListaVigenteActiva(int $articuloId, int $proveedorId, string $codigo): void
    {
        $vigente = ArticuloProveedorPrecioListaSupport::precioVigente($articuloId, $proveedorId);
        $lineaId = (int) ($vigente['linea_lista_id'] ?? 0);
        if ($lineaId <= 0) {
            return;
        }

        $linea = Listaprecio_Proveedor_Articulo::query()->find($lineaId);
        if (! $linea) {
            return;
        }

        $destino = self::normalizar($linea->codigo_articulo_proveedor);
        $nuevo = self::resolverCodigo($codigo, $destino);
        if ($nuevo === null || $nuevo === $destino) {
            return;
        }

        $linea->update(['codigo_articulo_proveedor' => $nuevo]);
    }

    private static function haciaCatalogo(int $articuloId, int $proveedorId, string $codigo): void
    {
        $fila = Articulo_Proveedor::query()
            ->where('articulo_id', $articuloId)
            ->where('proveedor_id', $proveedorId)
            ->first();

        if (! $fila) {
            self::crearFilaCatalogoDesdeLista($articuloId, $proveedorId, $codigo);

            return;
        }

        $destino = self::normalizar($fila->codigo_articulo_proveedor);
        $nuevo = self::resolverCodigo($codigo, $destino);
        if ($nuevo === null || $nuevo === $destino) {
            return;
        }

        $fila->update(['codigo_articulo_proveedor' => $nuevo]);
    }

    private static function crearFilaCatalogoDesdeLista(int $articuloId, int $proveedorId, string $codigo): void
    {
        $articulo = Articulo::query()->find($articuloId);
        $umId = $articulo ? (int) ($articulo->unidadmedida_id ?? 0) : 0;

        Articulo_Proveedor::query()->create([
            'articulo_id' => $articuloId,
            'proveedor_id' => $proveedorId,
            'nombre_articulo_proveedor' => $articulo
                ? (substr((string) ($articulo->descripcion ?? ''), 0, 255) ?: null)
                : null,
            'codigobarra' => $articulo && ! empty($articulo->codigobarra)
                ? substr((string) $articulo->codigobarra, 0, 50)
                : null,
            'codigo_articulo_proveedor' => $codigo,
            'unidadmedida_compra_id' => $umId > 0 ? $umId : null,
            'coeficiente_conversion' => 1,
            'activo' => true,
            'preferido' => false,
        ]);
    }

    /**
     * Origen gana si el destino está vacío o difiere (última modificación).
     */
    private static function resolverCodigo(string $origen, ?string $destino): ?string
    {
        if ($origen === '') {
            return null;
        }

        if ($destino === null || $destino === '' || $destino !== $origen) {
            return $origen;
        }

        return null;
    }

    private static function normalizar(?string $codigo): ?string
    {
        if ($codigo === null) {
            return null;
        }

        $codigo = trim($codigo);

        return $codigo === '' ? null : substr($codigo, 0, 100);
    }
}
