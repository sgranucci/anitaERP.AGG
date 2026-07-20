<?php

namespace App\Support\Sueldos;

use App\Models\Sueldos\Entrega_Prenda_Articulo_Sueldos;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query base del reporte de entregas de indumentaria (una fila por línea entregada).
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
            ->select([
                'entrega_prenda_articulo_sueldos.id',
                'e.id as entrega_id',
                'e.fecha',
                'e.anio',
                'e.observacion',
                'emp.id as empleado_id',
                'emp.legajo',
                'emp.nombre as empleado_nombre',
                'emp.agrupamiento_id',
                'emp.empresa_id',
                'em.nombre as nombreempresa',
                'pr.codigo as prenda_codigo',
                'pr.descripcion as prenda',
                'co.nombre as color',
                'ta.nombre as talle',
                'entrega_prenda_articulo_sueldos.sku',
                'entrega_prenda_articulo_sueldos.cantidad',
                'entrega_prenda_articulo_sueldos.vence_el',
            ]);

        if (! empty($filtros['anio'])) {
            $q->where('e.anio', (int) $filtros['anio']);
        }
        if (! empty($filtros['fecha_desde'])) {
            $q->whereDate('e.fecha', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $q->whereDate('e.fecha', '<=', $filtros['fecha_hasta']);
        }
        if (! empty($filtros['agrupamiento_id'])) {
            $q->where('emp.agrupamiento_id', (int) $filtros['agrupamiento_id']);
        }
        if (! empty($filtros['empresa_id'])) {
            $q->where('emp.empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['texto'])) {
            $texto = $filtros['texto'];
            $q->where(function ($w) use ($texto) {
                $w->where('emp.nombre', 'like', "%{$texto}%")
                    ->orWhere('emp.legajo', 'like', "%{$texto}%")
                    ->orWhere('pr.descripcion', 'like', "%{$texto}%")
                    ->orWhere('entrega_prenda_articulo_sueldos.sku', 'like', "%{$texto}%");
            });
        }

        return $q->orderByDesc('e.fecha')->orderByDesc('e.id');
    }
}
