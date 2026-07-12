<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Recepcion_Proveedor_Articulo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class RecepcionProveedorArticuloConsultaSupport
{
    public static function puedeConsultar(): bool
    {
        return can('listar-recepcion-proveedor', false);
    }

    /**
     * @return array{
     *     articulo: array{id:int, sku:string, descripcion:string, unidad_medida:string},
     *     ids_filtro: list<int>
     * }
     */
    public static function validarContexto(int $articuloId): array
    {
        if ($articuloId <= 0) {
            throw new \InvalidArgumentException('Artículo requerido.');
        }

        $articulo = Articulo::query()
            ->select('id', 'sku', 'descripcion', 'unidadmedida_id', 'empresa_id', 'skualternativo')
            ->with('unidadesdemedidas:id,nombre,abreviatura')
            ->find($articuloId);

        if (! $articulo) {
            throw new \RuntimeException('Artículo no encontrado.');
        }

        return [
            'articulo' => MovimientosArticuloDepositoSupport::articuloResumen($articulo),
            'ids_filtro' => self::resolverIdsArticuloParaFiltro($articulo),
        ];
    }

    /**
     * @return list<int>
     */
    public static function resolverIdsArticuloParaFiltro(Articulo $articulo): array
    {
        $ids = [(int) $articulo->id];

        RecepcionProveedorDepositoSupport::reiniciarCache();

        $empresaId = (int) ($articulo->empresa_id ?? 0);
        $empresaId = $empresaId > 0 ? $empresaId : null;

        if (RecepcionProveedorDepositoSupport::resolverArticuloInsumo($articulo, $empresaId) === null) {
            RecepcionProveedorDepositoSupport::resolverArticulosCompraDesdeInsumo($articulo, $empresaId)
                ->each(static function (Articulo $compra) use (&$ids): void {
                    $ids[] = (int) $compra->id;
                });
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * @param  list<int>  $articuloIds
     * @return Builder<Recepcion_Proveedor_Articulo>
     */
    public static function query(array $articuloIds): Builder
    {
        $articuloIds = array_values(array_unique(array_filter(
            $articuloIds,
            static fn (int $id): bool => $id > 0
        )));

        $query = Recepcion_Proveedor_Articulo::query()
            ->select([
                'recepcion_proveedor_articulo.*',
                'recepcion_proveedor.numerorecepcion',
                'recepcion_proveedor.fecha',
                'recepcion_proveedor.tipo',
                'recepcion_proveedor.estado as estado_recepcion',
                'recepcion_proveedor.fl_precio_diferencia as fl_precio_diferencia_cab',
                'recepcion_proveedor.fl_diferencia_cantidad as fl_diferencia_cantidad_cab',
                'recepcion_proveedor.fl_articulo_extra as fl_articulo_extra_cab',
                'recepcion_proveedor.fl_faltante_oc as fl_faltante_oc_cab',
                'recepcion_proveedor.fl_laboratorio as fl_laboratorio_cab',
                'recepcion_proveedor.fl_precio_pendiente_aprobacion',
                'recepcion_proveedor.id as recepcion_id',
                'recepcion_proveedor.ordencompra_id',
                'empresa.nombre as nombreempresa',
                'proveedor.nombre as nombreproveedor',
                'ordencompra.numeroordencompra',
                'articulo_linea.sku as sku_linea',
                'articulo_linea.descripcion as descripcion_linea',
            ])
            ->join('recepcion_proveedor', 'recepcion_proveedor.id', '=', 'recepcion_proveedor_articulo.recepcion_proveedor_id')
            ->join('empresa', 'empresa.id', '=', 'recepcion_proveedor.empresa_id')
            ->join('proveedor', 'proveedor.id', '=', 'recepcion_proveedor.proveedor_id')
            ->join('ordencompra', 'ordencompra.id', '=', 'recepcion_proveedor.ordencompra_id')
            ->leftJoin('articulo as articulo_linea', 'articulo_linea.id', '=', 'recepcion_proveedor_articulo.articulo_id')
            ->where(function (Builder $q) use ($articuloIds): void {
                $q->whereIn('recepcion_proveedor_articulo.articulo_id', $articuloIds)
                    ->orWhereIn('recepcion_proveedor_articulo.articulo_stock_id', $articuloIds);
            })
            ->orderByDesc('recepcion_proveedor.fecha')
            ->orderByDesc('recepcion_proveedor.id')
            ->orderBy('recepcion_proveedor_articulo.orden');

        RecepcionProveedorVisibilidadSupport::aplicarFiltroListado($query);

        return $query;
    }

    public static function enriquecerFila(object $row): object
    {
        $cantidad = (float) ($row->cantidad ?? 0);
        $cantidadStock = (float) ($row->cantidad_stock ?? 0);
        $precio = (float) ($row->precio ?? 0);

        $row->cantidad_fmt = self::formatearNumero($cantidad);
        $row->cantidad_stock_fmt = $cantidadStock > 0 ? self::formatearNumero($cantidadStock) : '';
        $row->precio_fmt = $precio > 0 ? self::formatearNumero($precio) : '';

        $recepcionId = (int) ($row->recepcion_id ?? $row->recepcion_proveedor_id ?? 0);
        $row->url_consulta_recepcion = $recepcionId > 0 && self::puedeConsultar()
            ? route('editar_recepcion_proveedor', [
                'id' => $recepcionId,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])
            : null;

        $row->fecha_fmt = ! empty($row->fecha)
            ? date('d/m/Y', strtotime((string) $row->fecha))
            : '';

        $row->tiene_diff = (bool) ($row->fl_precio_diferencia ?? false)
            || (bool) ($row->fl_cantidad_diferencia ?? false)
            || (bool) ($row->fl_precio_diferencia_cab ?? false)
            || (bool) ($row->fl_diferencia_cantidad_cab ?? false)
            || (bool) ($row->fl_articulo_extra_cab ?? false)
            || (bool) ($row->fl_faltante_oc_cab ?? false);

        return $row;
    }

    public static function formatearNumero(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 6, ',', '.'), '0'), ',');
    }

    /**
     * @param  Collection<int, object>  $filas
     */
    public static function nombreempresaDesdeFilas(Collection $filas): ?string
    {
        $empresas = $filas->pluck('nombreempresa')->filter()->unique()->values();

        return $empresas->count() === 1 ? (string) $empresas->first() : null;
    }
}
