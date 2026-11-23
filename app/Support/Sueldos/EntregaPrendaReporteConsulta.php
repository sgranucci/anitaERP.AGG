<?php

namespace App\Support\Sueldos;

use App\Models\Sueldos\Entrega_Prenda_Articulo_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoCortesSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Query base de la consulta de entregas de indumentaria (una fila por línea entregada).
 */
final class EntregaPrendaReporteConsulta
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Entrega_Prenda_Articulo_Sueldos>
     */
    public static function query(array $filtros): Builder
    {
        $q = Entrega_Prenda_Articulo_Sueldos::query()
            ->join('entrega_prenda_sueldos as e', 'e.id', '=', 'entrega_prenda_articulo_sueldos.entrega_id')
            ->join('empleado_sueldos as emp', 'emp.id', '=', 'e.empleado_id')
            ->leftJoin('prenda_sueldos as pr', 'pr.id', '=', 'entrega_prenda_articulo_sueldos.prenda_id')
            ->leftJoin('color as co', 'co.id', '=', 'entrega_prenda_articulo_sueldos.color_id')
            ->leftJoin('talle as ta', 'ta.id', '=', 'entrega_prenda_articulo_sueldos.talle_id')
            ->leftJoin('empresa as em', 'em.id', '=', 'emp.empresa_id')
            ->leftJoin('agrupamiento_sueldos as ag', 'ag.id', '=', 'emp.agrupamiento_id')
            ->leftJoin('depmae as dep', 'dep.id', '=', 'e.deposito_id')
            ->select([
                'entrega_prenda_articulo_sueldos.id',
                'e.id as entrega_id',
                'e.fecha',
                'e.anio',
                'e.observacion',
                'e.tulegajo_estado',
                'e.deposito_id',
                'emp.id as empleado_id',
                'emp.legajo',
                'emp.nombre as empleado_nombre',
                'emp.agrupamiento_id',
                'emp.empresa_id',
                'em.nombre as nombreempresa',
                'ag.descripcion as agrupamiento_nombre',
                'dep.nombre as deposito_nombre',
                'entrega_prenda_articulo_sueldos.prenda_id',
                'entrega_prenda_articulo_sueldos.articulo_id',
                'entrega_prenda_articulo_sueldos.color_id',
                'entrega_prenda_articulo_sueldos.talle_id',
                'pr.codigo as prenda_codigo',
                'pr.descripcion as prenda',
                'co.nombre as color',
                'ta.nombre as talle',
                'entrega_prenda_articulo_sueldos.sku',
                'entrega_prenda_articulo_sueldos.cantidad',
                'entrega_prenda_articulo_sueldos.vence_el',
            ]);

        app(EmpresaRepositoryInterface::class)->aplicarFiltroEmpresasAsignadas($q, 'emp.empresa_id');
        EntregaPrendaListadoFiltros::aplicar($q, $filtros);
        EntregaPrendaListadoFiltros::aplicarOrden($q, $filtros);

        return $q;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator|Collection<int, Entrega_Prenda_Articulo_Sueldos>
     */
    public static function lee(array $filtros, bool $paginar = false)
    {
        $query = self::query($filtros);
        $perPage = (int) ($filtros['_per_page'] ?? 15);
        if ($perPage < 1) {
            $perPage = 15;
        }

        return $paginar ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Total de unidades del universo filtrado (no solo la página).
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function totalCantidad(array $filtros): float
    {
        $q = self::query($filtros);
        $q->getQuery()->orders = null;
        $q->getQuery()->unionOrders = null;

        return (float) $q->toBase()->sum('entrega_prenda_articulo_sueldos.cantidad');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function cortes(array $filtros): array
    {
        $campos = EntregaPrendaListadoFiltros::camposOrdenables();
        $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            EntregaPrendaListadoColumnas::RECURSO,
            EntregaPrendaListadoColumnas::catalogoActivo()
        );

        return ListadoCortesSupport::calcular(
            self::query($filtros),
            $agrupar,
            $campos,
            'entrega_prenda_articulo_sueldos.id',
            static fn (object $row, string $key): string => EntregaPrendaListadoColumnas::valorCelda($row, $key),
            static fn (string $key): ?array => EntregaPrendaListadoColumnas::sqlAgrupacion($key),
            $etiquetas,
            EntregaPrendaListadoColumnas::MEDIDAS
        );
    }
}
