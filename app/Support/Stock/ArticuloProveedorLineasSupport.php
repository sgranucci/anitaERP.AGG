<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Proveedor;
use App\Support\Compras\ArticuloProveedorPrecioListaSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ArticuloProveedorLineasSupport
{
    /**
     * @return \Illuminate\Support\Collection<int, Articulo_Proveedor>
     */
    public static function lineasParaFormulario(?Articulo $producto): Collection
    {
        if (! $producto || ! $producto->id) {
            return collect();
        }

        $guardados = $producto->articulo_proveedores()
            ->with([
                'proveedores',
                'unidadesmedidacompra',
            ])
            ->orderByDesc('preferido')
            ->orderByDesc('activo')
            ->orderBy('id')
            ->get()
            ->map(fn (Articulo_Proveedor $linea) => ArticuloProveedorPrecioListaSupport::enriquecerLinea($linea));

        $proveedoresGuardados = $guardados->pluck('proveedor_id')->filter()->map(fn ($id) => (int) $id)->all();

        $precarga = self::precargaDesdeListaPrecio((int) $producto->id, $producto)
            ->filter(fn (Articulo_Proveedor $linea) => ! in_array((int) $linea->proveedor_id, $proveedoresGuardados, true));

        return $guardados->concat($precarga)->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Articulo_Proveedor>
     */
    public static function precargaDesdeListaPrecio(int $articuloId, ?Articulo $articulo = null, ?string $fechaRef = null): Collection
    {
        $fechaRef = $fechaRef ?? date('Y-m-d');

        $subMaxFv = DB::table('listaprecio_proveedor_articulo')
            ->select('listaprecio_proveedor_id', DB::raw('MAX(fechavigencia) as max_fv'))
            ->where('articulo_id', $articuloId)
            ->whereDate('fechavigencia', '<=', $fechaRef)
            ->groupBy('listaprecio_proveedor_id');

        $lineIds = DB::table('listaprecio_proveedor_articulo as lpa')
            ->joinSub($subMaxFv, 'mx', function ($join) {
                $join->on('lpa.listaprecio_proveedor_id', '=', 'mx.listaprecio_proveedor_id')
                    ->on('lpa.fechavigencia', '=', 'mx.max_fv');
            })
            ->where('lpa.articulo_id', $articuloId)
            ->groupBy('lpa.listaprecio_proveedor_id')
            ->select(DB::raw('MAX(lpa.id) as id'))
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        if ($lineIds === []) {
            return collect();
        }

        $filas = DB::table('listaprecio_proveedor_articulo as lpa')
            ->join('listaprecio_proveedor as lp', 'lp.id', '=', 'lpa.listaprecio_proveedor_id')
            ->leftJoin('proveedor as prov', 'prov.id', '=', 'lp.proveedor_id')
            ->whereIn('lpa.id', $lineIds)
            ->where('lp.estado', 'ACTIVA')
            ->whereNotNull('lp.proveedor_id')
            ->select([
                'lpa.codigo_articulo_proveedor',
                'lp.proveedor_id',
                'lp.id as lista_id',
                'lp.nombre as lista_nombre',
                'prov.codigo as proveedor_codigo',
                'prov.nombre as proveedor_nombre',
            ])
            ->orderByDesc('lp.fecha')
            ->orderByDesc('lp.id')
            ->get();

        $umDefault = $articulo ? (int) ($articulo->unidadmedida_id ?? 0) : 0;
        $nombreDefault = $articulo ? (string) ($articulo->descripcion ?? '') : '';
        $barraDefault = $articulo ? (string) ($articulo->codigobarra ?? '') : '';

        $porProveedor = [];
        foreach ($filas as $r) {
            $provId = (int) $r->proveedor_id;
            if (isset($porProveedor[$provId])) {
                continue;
            }

            $linea = new Articulo_Proveedor([
                'articulo_id' => $articuloId,
                'proveedor_id' => $provId,
                'nombre_articulo_proveedor' => $nombreDefault,
                'codigobarra' => $barraDefault ?: null,
                'codigo_articulo_proveedor' => $r->codigo_articulo_proveedor,
                'unidadmedida_compra_id' => $umDefault > 0 ? $umDefault : null,
                'coeficiente_conversion' => 1,
                'activo' => true,
                'preferido' => false,
            ]);

            $linea->setRelation('proveedores', (object) [
                'id' => $provId,
                'codigo' => $r->proveedor_codigo,
                'nombre' => $r->proveedor_nombre,
            ]);

            $porProveedor[$provId] = ArticuloProveedorPrecioListaSupport::enriquecerLinea($linea, $fechaRef);
        }

        return collect(array_values($porProveedor));
    }
}
