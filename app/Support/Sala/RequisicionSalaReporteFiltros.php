<?php

namespace App\Support\Sala;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

final class RequisicionSalaReporteFiltros
{
    public const ESTADO_TODOS = 'todos';

    public const ESTADO_ENTREGADO = 'entregado';

    public const ESTADO_PARA_RETIRAR = 'para_retirar';

    public const ESTADO_PENDIENTE_REP = 'pendiente_rep';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const AGRUPACION_REQUISICION = 'requisicion';

    public const AGRUPACION_USUARIO = 'usuario';

    public const MODO_MOVIMIENTOS = 'movimientos';

    public const MODO_TOTALES = 'totales';

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_ESTADO_LINEA = [
        ['valor' => self::ESTADO_TODOS, 'etiqueta' => 'Todos los estados'],
        ['valor' => self::ESTADO_ENTREGADO, 'etiqueta' => 'Entregado'],
        ['valor' => self::ESTADO_PARA_RETIRAR, 'etiqueta' => 'Para retirar'],
        ['valor' => self::ESTADO_PENDIENTE_REP, 'etiqueta' => 'Pendiente de reparación'],
        ['valor' => self::ESTADO_PENDIENTE, 'etiqueta' => 'Pendiente de procesar'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_AGRUPACION = [
        ['valor' => self::AGRUPACION_REQUISICION, 'etiqueta' => 'Por requisición'],
        ['valor' => self::AGRUPACION_USUARIO, 'etiqueta' => 'Por usuario'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_MODO_LISTADO = [
        ['valor' => self::MODO_MOVIMIENTOS, 'etiqueta' => 'Movimientos (detalle)'],
        ['valor' => self::MODO_TOTALES, 'etiqueta' => 'Totales solamente'],
    ];

    /**
     * @return array{
     *     empresa_ids: list<int>,
     *     fecha_desde: ?string,
     *     fecha_hasta: ?string,
     *     requisicion_desde: string,
     *     requisicion_hasta: string,
     *     usuarios: string,
     *     estado_linea: string,
     *     agrupacion: string,
     *     modo_listado: string
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $estadoLinea = trim((string) $request->input('estado_linea', self::ESTADO_TODOS));
        if (! self::estadoLineaValido($estadoLinea)) {
            $estadoLinea = self::ESTADO_TODOS;
        }

        $agrupacion = trim((string) $request->input('agrupacion', self::AGRUPACION_REQUISICION));
        if (! self::agrupacionValida($agrupacion)) {
            $agrupacion = self::AGRUPACION_REQUISICION;
        }

        $modoListado = trim((string) $request->input('modo_listado', self::MODO_MOVIMIENTOS));
        if (! self::modoListadoValido($modoListado)) {
            $modoListado = self::MODO_MOVIMIENTOS;
        }

        [$requisicionDesde, $requisicionHasta] = RequisicionSalaReporteCriteriosSupport::normalizarRangoNumeros(
            trim((string) $request->input('requisicion_desde', '')),
            trim((string) $request->input('requisicion_hasta', '')),
        );

        return [
            'empresa_ids' => $empresaIds,
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
            'requisicion_desde' => $requisicionDesde,
            'requisicion_hasta' => $requisicionHasta,
            'usuarios' => trim((string) $request->input('usuarios', '')),
            'estado_linea' => $estadoLinea,
            'agrupacion' => $agrupacion,
            'modo_listado' => $modoListado,
        ];
    }

    /** @return array<string, mixed> */
    public static function paraQueryString(array $filtros): array
    {
        $query = array_filter([
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'requisicion_desde' => ($filtros['requisicion_desde'] ?? '') !== ''
                ? ($filtros['requisicion_desde'] ?? null)
                : null,
            'requisicion_hasta' => ($filtros['requisicion_hasta'] ?? '') !== ''
                ? ($filtros['requisicion_hasta'] ?? null)
                : null,
            'usuarios' => ($filtros['usuarios'] ?? '') !== ''
                ? ($filtros['usuarios'] ?? null)
                : null,
            'estado_linea' => ($filtros['estado_linea'] ?? self::ESTADO_TODOS) !== self::ESTADO_TODOS
                ? ($filtros['estado_linea'] ?? null)
                : null,
            'agrupacion' => ($filtros['agrupacion'] ?? self::AGRUPACION_REQUISICION) !== self::AGRUPACION_REQUISICION
                ? ($filtros['agrupacion'] ?? null)
                : null,
            'modo_listado' => ($filtros['modo_listado'] ?? self::MODO_MOVIMIENTOS) !== self::MODO_MOVIMIENTOS
                ? ($filtros['modo_listado'] ?? null)
                : null,
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');

        if (($filtros['empresa_ids'] ?? []) !== []) {
            $query['empresa_ids'] = array_values(array_map(
                'intval',
                $filtros['empresa_ids'],
            ));
        }

        return $query;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ($filtros['empresa_ids'] ?? []) !== []
            && ! empty($filtros['fecha_desde']);
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $hoy = Carbon::now();

        return [
            'empresa_ids' => [],
            'fecha_desde' => $hoy->copy()->startOfMonth()->format('Y-m-d'),
            'fecha_hasta' => $hoy->copy()->endOfMonth()->format('Y-m-d'),
            'requisicion_desde' => '',
            'requisicion_hasta' => '',
            'usuarios' => '',
            'estado_linea' => self::ESTADO_TODOS,
            'agrupacion' => self::AGRUPACION_REQUISICION,
            'modo_listado' => self::MODO_MOVIMIENTOS,
        ];
    }

    public static function etiquetaAgrupacion(string $agrupacion): string
    {
        foreach (self::OPCIONES_AGRUPACION as $opcion) {
            if ($opcion['valor'] === $agrupacion) {
                return $opcion['etiqueta'];
            }
        }

        return self::OPCIONES_AGRUPACION[0]['etiqueta'];
    }

    public static function etiquetaModoListado(string $modoListado): string
    {
        foreach (self::OPCIONES_MODO_LISTADO as $opcion) {
            if ($opcion['valor'] === $modoListado) {
                return $opcion['etiqueta'];
            }
        }

        return self::OPCIONES_MODO_LISTADO[0]['etiqueta'];
    }

    public static function subtituloEstadoLinea(string $estadoLinea): string
    {
        return match ($estadoLinea) {
            self::ESTADO_ENTREGADO => 'Listando req. entregados',
            self::ESTADO_PARA_RETIRAR => 'Listando req. para retirar',
            self::ESTADO_PENDIENTE_REP => 'Listando req. pendientes de reparación',
            self::ESTADO_PENDIENTE => 'Listando req. pendientes de procesar',
            default => 'Listando todos los requerimientos',
        };
    }

    public static function aplicarEstadoLinea(Builder $query, string $estadoLinea, string $columna = 'rsa.estado'): void
    {
        switch ($estadoLinea) {
            case self::ESTADO_ENTREGADO:
                $query->where($columna, 'E');
                break;
            case self::ESTADO_PARA_RETIRAR:
                $query->where($columna, 'R');
                break;
            case self::ESTADO_PENDIENTE_REP:
                $query->where($columna, 'P');
                break;
            case self::ESTADO_PENDIENTE:
                $query->whereNotIn($columna, ['E', 'R', 'P']);
                break;
        }
    }

    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = self::formatearFechaPantalla($filtros['fecha_desde'] ?? null);
        $hasta = self::formatearFechaPantalla($filtros['fecha_hasta'] ?? null);

        if ($desde !== '' && $hasta !== '') {
            return $desde.' — '.$hasta;
        }

        if ($desde !== '') {
            return 'Desde '.$desde.' hasta último mov.';
        }

        return '—';
    }

    public static function formatearFechaPantalla(?string $fecha): string
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return '';
        }

        try {
            return Carbon::parse($fecha)->format('d/m/y');
        } catch (\Throwable) {
            return $fecha;
        }
    }

    private static function estadoLineaValido(string $estadoLinea): bool
    {
        foreach (self::OPCIONES_ESTADO_LINEA as $opcion) {
            if ($opcion['valor'] === $estadoLinea) {
                return true;
            }
        }

        return false;
    }

    private static function agrupacionValida(string $agrupacion): bool
    {
        foreach (self::OPCIONES_AGRUPACION as $opcion) {
            if ($opcion['valor'] === $agrupacion) {
                return true;
            }
        }

        return false;
    }

    private static function modoListadoValido(string $modoListado): bool
    {
        foreach (self::OPCIONES_MODO_LISTADO as $opcion) {
            if ($opcion['valor'] === $modoListado) {
                return true;
            }
        }

        return false;
    }

    private static function fechaOpcional($valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? substr($valor, 0, 10) : null;
    }
}
