<?php

namespace App\Support\Compras;

use App\Models\Stock\Articulo_Proveedor;
use App\Support\Stock\RecepcionProveedorConversionSupport;
use Illuminate\Support\Collection;

/**
 * Lectura operativa del catálogo articulo_proveedor para RQ / OC / recepción.
 *
 * Activación: solo si el artículo tiene filas activas en articulo_proveedor.
 * Sin datos en esa tabla → opcion "ninguno" / "sin_match_cabecera" y la grilla
 * sigue con descripción/precio/UM del maestro (comportamiento previo).
 *
 * Precio vigente sale de lista (ArticuloProveedorPrecioListaSupport), no de la tabla catálogo.
 */
class ArticuloProveedorOperativoSupport
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function listarActivosPorArticulo(int $articuloId, ?int $proveedorId = null): Collection
    {
        if ($articuloId <= 0) {
            return collect();
        }

        $query = Articulo_Proveedor::query()
            ->with(['proveedores', 'unidadesmedidacompra'])
            ->where('articulo_id', $articuloId)
            ->where('activo', true);

        if ($proveedorId !== null && $proveedorId > 0) {
            $query->where('proveedor_id', $proveedorId);
        }

        return $query
            ->orderByDesc('preferido')
            ->orderBy('id')
            ->get()
            ->map(static fn (Articulo_Proveedor $linea) => self::serializarLinea($linea))
            ->values();
    }

    /**
     * Resuelve una fila (o null). Si hay varias del mismo proveedor y no se pasa código,
     * elige preferida / primera; el caller debe abrir modal si count > 1.
     *
     * @return array<string, mixed>|null
     */
    public static function resolverParaArticuloYProveedor(
        int $articuloId,
        int $proveedorId,
        ?string $codigoArticuloProveedor = null,
        ?int $articuloProveedorId = null,
    ): ?array {
        if ($articuloId <= 0 || $proveedorId <= 0) {
            return null;
        }

        if ($articuloProveedorId !== null && $articuloProveedorId > 0) {
            $fila = Articulo_Proveedor::query()
                ->with(['proveedores', 'unidadesmedidacompra'])
                ->where('id', $articuloProveedorId)
                ->where('articulo_id', $articuloId)
                ->where('proveedor_id', $proveedorId)
                ->where('activo', true)
                ->first();

            return $fila ? self::serializarLinea($fila) : null;
        }

        $query = Articulo_Proveedor::query()
            ->with(['proveedores', 'unidadesmedidacompra'])
            ->where('articulo_id', $articuloId)
            ->where('proveedor_id', $proveedorId)
            ->where('activo', true);

        $codigo = trim((string) ($codigoArticuloProveedor ?? ''));
        if ($codigo !== '') {
            $query->where('codigo_articulo_proveedor', $codigo);
        }

        $fila = $query
            ->orderByDesc('preferido')
            ->orderBy('id')
            ->first();

        return $fila ? self::serializarLinea($fila) : null;
    }

    /**
     * @return array{opcion: string, items: array<int, array<string, mixed>>, elegido: array<string, mixed>|null}
     *   opcion: auto | modal | ninguno | sin_match_cabecera
     */
    public static function decidirSeleccion(
        int $articuloId,
        ?int $proveedorCabeceraId = null,
        bool $restrictivoCabecera = false,
    ): array {
        $proveedorCab = ($proveedorCabeceraId !== null && $proveedorCabeceraId > 0)
            ? $proveedorCabeceraId
            : null;

        if ($restrictivoCabecera && $proveedorCab === null) {
            return [
                'opcion' => 'requiere_cabecera',
                'items' => [],
                'elegido' => null,
            ];
        }

        $items = self::listarActivosPorArticulo($articuloId, $proveedorCab)->all();

        if ($proveedorCab !== null) {
            if ($items === []) {
                return [
                    'opcion' => 'sin_match_cabecera',
                    'items' => [],
                    'elegido' => null,
                ];
            }
            if (count($items) === 1) {
                return [
                    'opcion' => 'auto',
                    'items' => $items,
                    'elegido' => $items[0],
                ];
            }

            // Multi-código del mismo proveedor → modal de código
            return [
                'opcion' => 'modal',
                'items' => $items,
                'elegido' => null,
            ];
        }

        // Sin cabecera: todos los proveedores del artículo
        $items = self::listarActivosPorArticulo($articuloId)->all();
        if ($items === []) {
            return [
                'opcion' => 'ninguno',
                'items' => [],
                'elegido' => null,
            ];
        }
        if (count($items) === 1) {
            return [
                'opcion' => 'auto',
                'items' => $items,
                'elegido' => $items[0],
            ];
        }

        return [
            'opcion' => 'modal',
            'items' => $items,
            'elegido' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function serializarLinea(Articulo_Proveedor $linea): array
    {
        ArticuloProveedorPrecioListaSupport::enriquecerLinea($linea);

        $proveedor = $linea->proveedores;
        $um = $linea->unidadesmedidacompra;
        $linea->loadMissing('articulos');
        $umArticuloId = (int) (optional($linea->articulos)->unidadmedida_id ?? 0);
        $coef = RecepcionProveedorConversionSupport::normalizarCoeficienteMismaUm(
            (float) ($linea->coeficiente_conversion ?? 1),
            $linea->unidadmedida_compra_id ? (int) $linea->unidadmedida_compra_id : null,
            $umArticuloId > 0 ? $umArticuloId : null,
        );

        return [
            'articulo_proveedor_id' => (int) $linea->id,
            'articulo_id' => (int) $linea->articulo_id,
            'proveedor_id' => (int) $linea->proveedor_id,
            'proveedor_codigo' => (string) ($proveedor->codigo ?? ''),
            'proveedor_nombre' => (string) ($proveedor->nombre ?? ''),
            'nombre_articulo_proveedor' => (string) ($linea->nombre_articulo_proveedor ?? ''),
            'codigo_articulo_proveedor' => (string) ($linea->codigo_articulo_proveedor ?? ''),
            'codigobarra' => (string) ($linea->codigobarra ?? ''),
            'unidadmedida_compra_id' => $linea->unidadmedida_compra_id
                ? (int) $linea->unidadmedida_compra_id
                : null,
            'um_compra_abreviatura' => (string) ($um->abreviatura ?? $um->nombre ?? ''),
            'um_compra_nombre' => (string) ($um->nombre ?? ''),
            'coeficiente_conversion' => $coef,
            'preferido' => (bool) $linea->preferido,
            'precio' => $linea->precio_vigente !== null ? (float) $linea->precio_vigente : null,
            'moneda_id' => $linea->moneda_vigente_id !== null ? (int) $linea->moneda_vigente_id : null,
            'moneda_abreviatura' => (string) ($linea->moneda_vigente_abreviatura ?? ''),
            'tiene_precio' => (bool) ($linea->tiene_precio_vigente ?? false),
            'lista_nombre' => (string) ($linea->lista_nombre_resuelta ?? ''),
        ];
    }
}
