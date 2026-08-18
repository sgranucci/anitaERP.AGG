<?php

namespace App\Support\Ventas\Vianda;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del reporte de viandas (consumos). Por defecto muestra el día en curso.
 */
final class ViandaConsumoListadoFiltros
{
    public const ORDEN_CENTROCOSTO = 'centrocosto';

    public const ORDEN_USUARIO = 'usuario';

    /**
     * @return array{
     *   fecha_desde:string,
     *   fecha_hasta:string,
     *   empresa_id:?int,
     *   centrocosto_id:?int,
     *   texto:string,
     *   estado:string,
     *   orden_por:string,
     *   presentacion_columnas:bool
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $hoy = Carbon::today()->format('Y-m-d');

        $desde = self::normalizarFecha((string) $request->input('fecha_desde', '')) ?: $hoy;
        $hasta = self::normalizarFecha((string) $request->input('fecha_hasta', '')) ?: $hoy;
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $estado = strtoupper(trim((string) $request->input('estado', 'A')));
        if (! in_array($estado, ['A', 'N', 'TODOS'], true)) {
            $estado = 'A';
        }

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'empresa_id' => self::nullableInt($request->input('empresa_id')),
            'centrocosto_id' => self::nullableInt($request->input('centrocosto_id')),
            'texto' => trim((string) $request->input('texto', '')),
            'estado' => $estado,
            'orden_por' => self::normalizarOrden($request->input('orden_por')),
            'presentacion_columnas' => $request->boolean('presentacion_columnas'),
        ];
    }

    /**
     * @param  Builder<\App\Models\Ventas\ViandaConsumo>  $query
     * @param  array<string, mixed>  $filtros
     * @return Builder<\App\Models\Ventas\ViandaConsumo>
     */
    public static function aplicar(Builder $query, array $filtros): Builder
    {
        // Filtro tradicional del sistema: solo empresas asignadas al usuario en sesión.
        ViandaEmpresaSupport::aplicarFiltroAsignadas($query, 'empresa_id');

        $query->whereDate('fecha', '>=', $filtros['fecha_desde'])
            ->whereDate('fecha', '<=', $filtros['fecha_hasta']);

        if (! empty($filtros['empresa_id'])) {
            $query->where('empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['centrocosto_id'])) {
            $query->where('centrocosto_id', (int) $filtros['centrocosto_id']);
        }
        if (($filtros['estado'] ?? 'A') !== 'TODOS') {
            $query->where('estado', $filtros['estado']);
        }
        $texto = trim((string) ($filtros['texto'] ?? ''));
        if ($texto !== '') {
            $query->where(function ($q) use ($texto) {
                $q->where('login_usuario', 'like', '%'.$texto.'%')
                    ->orWhere('nombre_usuario', 'like', '%'.$texto.'%')
                    ->orWhere('codigo_retiro', 'like', '%'.$texto.'%');
            });
        }

        return $query;
    }

    /**
     * @param  Builder<\App\Models\Ventas\ViandaConsumo>  $query
     * @param  array<string, mixed>  $filtros
     * @return Builder<\App\Models\Ventas\ViandaConsumo>
     */
    public static function aplicarOrden(Builder $query, array $filtros): Builder
    {
        $orden = self::normalizarOrden($filtros['orden_por'] ?? self::ORDEN_CENTROCOSTO);

        if ($orden === self::ORDEN_USUARIO) {
            return $query
                ->orderBy('nombre_usuario')
                ->orderBy('login_usuario')
                ->orderByDesc('fecha')
                ->orderByDesc('id');
        }

        return $query
            ->leftJoin('centrocosto as cc_orden_vianda', 'cc_orden_vianda.id', '=', 'vianda_consumo.centrocosto_id')
            ->orderByRaw('(cc_orden_vianda.nombre IS NULL)')
            ->orderBy('cc_orden_vianda.nombre')
            ->orderByDesc('vianda_consumo.fecha')
            ->orderByDesc('vianda_consumo.id')
            ->select('vianda_consumo.*');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $orden = self::normalizarOrden($filtros['orden_por'] ?? self::ORDEN_CENTROCOSTO);

        $params = array_filter([
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'empresa_id' => $filtros['empresa_id'] ?? null,
            'centrocosto_id' => $filtros['centrocosto_id'] ?? null,
            'texto' => $filtros['texto'] ?? null,
            'estado' => $filtros['estado'] ?? null,
            'orden_por' => $orden !== self::ORDEN_CENTROCOSTO ? $orden : null,
        ], fn ($v) => $v !== null && $v !== '');

        if (! empty($filtros['presentacion_columnas'])) {
            $params['presentacion_columnas'] = 1;
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>|null  $resultado
     */
    public static function debeUsarVistaColumnas(array $filtros, ?array $resultado): bool
    {
        if (empty($filtros['presentacion_columnas']) || $resultado === null) {
            return false;
        }

        $vista = $resultado['vista_columnas'] ?? null;
        if (! is_array($vista)) {
            return false;
        }

        $columnas = $vista['columnas'] ?? [];

        return is_array($columnas) && $columnas !== [];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ! empty($filtros['empresa_id'])
            || ! empty($filtros['centrocosto_id'])
            || trim((string) ($filtros['texto'] ?? '')) !== ''
            || ($filtros['estado'] ?? 'A') !== 'A';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros, ?string $empresaNombre = null, ?string $centrocostoNombre = null): string
    {
        $partes = [];
        $desde = self::fmt($filtros['fecha_desde'] ?? '');
        $hasta = self::fmt($filtros['fecha_hasta'] ?? '');
        if ($desde === $hasta) {
            $partes[] = 'Fecha: '.$desde;
        } else {
            $partes[] = 'Del '.$desde.' al '.$hasta;
        }
        if ($empresaNombre) {
            $partes[] = 'Empresa: '.$empresaNombre;
        }
        if ($centrocostoNombre) {
            $partes[] = 'Centro de costo: '.$centrocostoNombre;
        }
        if (trim((string) ($filtros['texto'] ?? '')) !== '') {
            $partes[] = 'Empleado: '.$filtros['texto'];
        }
        $estado = $filtros['estado'] ?? 'A';
        $partes[] = 'Estado: '.match ($estado) {
            'A' => 'Activos',
            'N' => 'Anulados',
            default => 'Todos',
        };
        if (! empty($filtros['presentacion_columnas'])) {
            $partes[] = 'Vista: columnas por centro de costo';
        } else {
            $partes[] = 'Orden: '.match (self::normalizarOrden($filtros['orden_por'] ?? self::ORDEN_CENTROCOSTO)) {
                self::ORDEN_USUARIO => 'Usuario',
                default => 'Centro de costo',
            };
        }

        return implode(' · ', $partes);
    }

    public static function normalizarOrden(mixed $valor): string
    {
        $orden = strtolower(trim((string) $valor));

        return $orden === self::ORDEN_USUARIO ? self::ORDEN_USUARIO : self::ORDEN_CENTROCOSTO;
    }

    private static function fmt(string $ymd): string
    {
        try {
            return Carbon::parse($ymd)->format('d/m/Y');
        } catch (\Throwable) {
            return $ymd;
        }
    }

    private static function normalizarFecha(string $fecha): ?string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return null;
        }
        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
