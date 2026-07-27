<?php

namespace App\Support\Configuracion;

use App\Models\Configuracion\BitacoraAcceso;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

class BitacoraAccesoListadoFiltros
{
    /**
     * @return array{
     *   consultar: bool,
     *   pestana: string,
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   usuario_id: ?int,
     *   tipo: string,
     *   ruta: string,
     *   metodo: string,
     *   archivo_log: string,
     *   lineas_log: int
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $hoy = now()->toDateString();
        $desdeDefault = now()->subDays(7)->toDateString();

        $pestana = (string) $request->input('pestana', 'navegacion');
        if (! in_array($pestana, ['navegacion', 'archivos', 'datos'], true)) {
            $pestana = 'navegacion';
        }

        $usuarioId = $request->input('usuario_id');
        $usuarioId = $usuarioId !== null && $usuarioId !== '' ? (int) $usuarioId : null;

        $lineas = (int) $request->input('lineas_log', 200);
        $lineas = max(50, min(2000, $lineas));

        $base = [
            'consultar' => $request->boolean('consultar') || $request->has('fecha_desde') || $request->has('pestana'),
            'pestana' => $pestana,
            'fecha_desde' => (string) ($request->input('fecha_desde') ?: $desdeDefault),
            'fecha_hasta' => (string) ($request->input('fecha_hasta') ?: $hoy),
            'usuario_id' => $usuarioId,
            'tipo' => trim((string) $request->input('tipo', '')),
            'ruta' => trim((string) $request->input('ruta', '')),
            'metodo' => strtoupper(trim((string) $request->input('metodo', ''))),
            'archivo_log' => basename((string) $request->input('archivo_log', '')),
            'lineas_log' => $lineas,
        ];

        return array_merge($base, AuditoriaDatosListadoFiltros::resolverDesdeRequest($request, $base));
    }

    /** @param  array<string, mixed>  $filtros */
    public static function paraQueryString(array $filtros): array
    {
        $out = [
            'consultar' => 1,
            'pestana' => $filtros['pestana'] ?? 'navegacion',
            'fecha_desde' => $filtros['fecha_desde'] ?? '',
            'fecha_hasta' => $filtros['fecha_hasta'] ?? '',
        ];
        if (! empty($filtros['usuario_id'])) {
            $out['usuario_id'] = $filtros['usuario_id'];
        }
        if (($filtros['tipo'] ?? '') !== '') {
            $out['tipo'] = $filtros['tipo'];
        }
        if (($filtros['ruta'] ?? '') !== '') {
            $out['ruta'] = $filtros['ruta'];
        }
        if (($filtros['metodo'] ?? '') !== '') {
            $out['metodo'] = $filtros['metodo'];
        }
        if (($filtros['archivo_log'] ?? '') !== '') {
            $out['archivo_log'] = $filtros['archivo_log'];
            $out['lineas_log'] = $filtros['lineas_log'] ?? 200;
        }

        return array_merge($out, AuditoriaDatosListadoFiltros::paraQueryString($filtros));
    }

    /**
     * @param  EloquentBuilder|QueryBuilder  $query
     * @param  array<string, mixed>  $filtros
     * @return EloquentBuilder|QueryBuilder
     */
    public static function aplicar($query, array $filtros)
    {
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde !== '') {
            $query->where('created_at', '>=', $desde.' 00:00:00');
        }
        if ($hasta !== '') {
            $query->where('created_at', '<=', $hasta.' 23:59:59');
        }
        if (! empty($filtros['usuario_id'])) {
            $query->where('usuario_id', (int) $filtros['usuario_id']);
        }
        if (($filtros['tipo'] ?? '') !== '') {
            $query->where('tipo', $filtros['tipo']);
        }
        if (($filtros['metodo'] ?? '') !== '') {
            $query->where('metodo', $filtros['metodo']);
        }
        if (($filtros['ruta'] ?? '') !== '') {
            $ruta = $filtros['ruta'];
            $query->where(function ($q) use ($ruta) {
                $q->where('ruta', 'like', '%'.$ruta.'%')
                    ->orWhere('nombre_ruta', 'like', '%'.$ruta.'%')
                    ->orWhere('url', 'like', '%'.$ruta.'%');
            });
        }

        return $query;
    }

    /** @param  array<string, mixed>  $filtros */
    public static function queryBase(array $filtros): EloquentBuilder
    {
        return self::aplicar(BitacoraAcceso::query(), $filtros)->orderByDesc('created_at')->orderByDesc('id');
    }
}
