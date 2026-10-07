<?php

declare(strict_types=1);

namespace App\Support\Logistica;

use App\Models\Logistica\SolicitudLogistica;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoCortesSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class SolicitudLogisticaListadoQuery
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<SolicitudLogistica>
     */
    public static function filtrada(array $filtros, int $usuarioId, bool $puedeTodas): Builder
    {
        $query = SolicitudLogistica::query()
            ->leftJoin('usuario', 'usuario.id', '=', 'solicitud_logistica.usuario_id')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'solicitud_logistica.centrocosto_id')
            ->leftJoin('logistica_tipo_solicitud as tipo', 'tipo.id', '=', 'solicitud_logistica.tipo_solicitud_id')
            ->leftJoin('logistica_trabajo_tipo as trabajo', 'trabajo.id', '=', 'solicitud_logistica.trabajo_tipo_id')
            ->select('solicitud_logistica.*')
            ->addSelect([
                'usuario.nombre as nombre_solicitante',
                'centrocosto.codigo as codigo_cc',
                'centrocosto.nombre as nombre_cc',
                'tipo.codigo as tipo_codigo',
                'trabajo.codigo as trabajo_codigo',
                DB::raw('COALESCE(trabajo.nombre, tipo.nombre) as nombre_tipo'),
            ])
            ->withCount('items');

        $alcance = ($filtros['alcance'] ?? 'mias') === 'todas' && $puedeTodas ? 'todas' : 'mias';
        if ($alcance !== 'todas') {
            $query->where('solicitud_logistica.usuario_id', $usuarioId);
        }
        $estado = (string) ($filtros['estado'] ?? '');
        if ($estado !== '' && array_key_exists($estado, SolicitudLogisticaListadoColumnas::ESTADOS)) {
            $query->where('solicitud_logistica.estado', $estado);
        }

        SolicitudLogisticaListadoFiltros::aplicar($query, $filtros);
        SolicitudLogisticaListadoFiltros::aplicarOrden($query, $filtros);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function cortes(array $filtros, int $usuarioId, bool $puedeTodas): array
    {
        $campos = SolicitudLogisticaListadoFiltros::camposOrdenables();
        $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            SolicitudLogisticaListadoColumnas::RECURSO,
            SolicitudLogisticaListadoColumnas::catalogoActivo()
        );

        return ListadoCortesSupport::calcular(
            self::filtrada($filtros, $usuarioId, $puedeTodas),
            $agrupar,
            $campos,
            'solicitud_logistica.id',
            static fn (object $row, string $key): string => SolicitudLogisticaListadoColumnas::valorCelda($row, $key),
            static fn (string $key): ?array => SolicitudLogisticaListadoColumnas::sqlAgrupacion($key),
            $etiquetas,
            [[
                'key' => 'total',
                'column' => 'solicitud_logistica.total_estimado',
                'label' => 'Total estimado',
            ]]
        );
    }
}
