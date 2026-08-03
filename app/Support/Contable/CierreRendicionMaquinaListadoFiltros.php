<?php

namespace App\Support\Contable;

use App\Models\Caja\RendicionMaquina;
use App\Support\Caja\RendicionMaquinaListadoFiltros;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class CierreRendicionMaquinaListadoFiltros
{
    public const MODO_TODOS = RendicionMaquinaListadoFiltros::MODO_TODOS;

    public const MODO_CAMPO = RendicionMaquinaListadoFiltros::MODO_CAMPO;

    public const ESTADO_TODOS = 'todos';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CERRADA = 'cerrada';

    /** @var array<string, array{column?: string, type: string, label: string, relation?: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'rendicion_maquina.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo' => ['column' => 'rendicion_maquina.codigo', 'type' => 'texto', 'label' => 'Código'],
        'empresa' => ['relation' => 'empresa', 'type' => 'texto', 'label' => 'Empresa'],
        'fecha' => ['column' => 'rendicion_maquina.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
        'turno' => ['column' => 'rendicion_maquina.turno', 'type' => 'texto', 'label' => 'Turno'],
        'estado' => ['column' => 'rendicion_maquina.estado', 'type' => 'texto', 'label' => 'Estado rendición'],
        'estado_cierre' => ['type' => 'estado_cierre', 'label' => 'Estado cierre contable'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId ?? 0,
                'empresa_scope' => $empresaScope,
            ]);
        }

        $filtros = RendicionMaquinaListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
        $filtros['empresa_id'] = $empresaId ?? 0;
        $filtros['empresa_scope'] = $empresaScope;

        $estado = (string) $request->input('estado_cierre', self::ESTADO_TODOS);
        if (! in_array($estado, [self::ESTADO_TODOS, self::ESTADO_PENDIENTE, self::ESTADO_CERRADA], true)) {
            $estado = self::ESTADO_TODOS;
        }
        $filtros['estado_cierre'] = $estado;

        $filtros['fecha_desde'] = trim((string) $request->input('fecha_desde', ''));
        $filtros['fecha_hasta'] = trim((string) $request->input('fecha_hasta', ''));

        return $filtros;
    }

    /**
     * @return array{0:?int,1:string}
     */
    private static function resolverEmpresaExterna(Request $request, ?int $empresaDefault): array
    {
        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            return [null, 'todas'];
        }
        if ($request->filled('empresa_id')) {
            return [(int) $request->input('empresa_id'), 'una'];
        }
        if ($empresaDefault !== null && $empresaDefault > 0) {
            return [$empresaDefault, 'una'];
        }

        return [null, 'todas'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return array_merge(RendicionMaquinaListadoFiltros::filtrosVacios(), [
            'estado_cierre' => self::ESTADO_TODOS,
            'empresa_scope' => 'una',
            'fecha_desde' => '',
            'fecha_hasta' => '',
        ]);
    }

    public static function tieneCriteriosUsuario(array $filtros): bool
    {
        if (($filtros['estado_cierre'] ?? self::ESTADO_TODOS) !== self::ESTADO_TODOS) {
            return true;
        }

        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            return true;
        }

        return RendicionMaquinaListadoFiltros::tieneCriteriosAplicados($filtros);
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosUsuario($filtros);
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);

        $resto = RendicionMaquinaListadoFiltros::paraQueryString($filtros);
        unset($resto['empresa_id'], $resto['empresa_todas']);
        $params = array_merge($params, $resto);

        if (($filtros['estado_cierre'] ?? self::ESTADO_TODOS) !== self::ESTADO_TODOS) {
            $params['estado_cierre'] = $filtros['estado_cierre'];
        }
        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== '') {
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }

        return $params;
    }

    /**
     * @return array<string, int>
     */
    public static function paraQueryStringEmpresa(array $filtros): array
    {
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            return ['empresa_todas' => 1];
        }
        if (! empty($filtros['empresa_id'])) {
            return ['empresa_id' => (int) $filtros['empresa_id']];
        }

        return [];
    }

    /**
     * @param  Builder<RendicionMaquina>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        self::aplicarEstadoCierre($query, $filtros);
        self::aplicarRangoFecha($query, $filtros);
        self::aplicarTurnoCierre($query);
        RendicionMaquinaListadoFiltros::aplicarScopeEmpresasAsignadas($query, $filtros);
        RendicionMaquinaListadoFiltros::aplicar($query, $filtros);
    }

    /**
     * @param  Builder<RendicionMaquina>  $query
     */
    public static function aplicarEstadoCierre(Builder $query, array $filtros): void
    {
        $estado = (string) ($filtros['estado_cierre'] ?? self::ESTADO_TODOS);
        if ($estado === self::ESTADO_PENDIENTE) {
            $query->where(function ($w) {
                $w->whereNull('rendicion_maquina.asiento_id')
                    ->orWhere('rendicion_maquina.asiento_id', 0);
            });

            return;
        }
        if ($estado === self::ESTADO_CERRADA) {
            $query->whereNotNull('rendicion_maquina.asiento_id')
                ->where('rendicion_maquina.asiento_id', '>', 0);
        }
    }

    /**
     * @param  Builder<RendicionMaquina>  $query
     */
    public static function aplicarRangoFecha(Builder $query, array $filtros): void
    {
        [$desde, $hasta] = self::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );

        if ($desde !== '') {
            $query->whereDate('rendicion_maquina.fecha', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate('rendicion_maquina.fecha', '<=', $hasta);
        }
    }

    /**
     * @param  Builder<RendicionMaquina>  $query
     */
    public static function aplicarTurnoCierre(Builder $query): void
    {
        $query->where('rendicion_maquina.turno', CierreRendicionMaquinaGrupoSupport::TURNO_CIERRE)
            ->where('rendicion_maquina.estado', RendicionMaquina::ESTADO_CONFIRMADA);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function normalizarRangoFechas(string $fechaDesde, string $fechaHasta): array
    {
        $desde = trim($fechaDesde);
        $hasta = trim($fechaHasta);

        if ($desde !== '' && $hasta === '') {
            $hasta = $desde;
        } elseif ($hasta !== '' && $desde === '') {
            $desde = $hasta;
        }

        return [$desde, $hasta];
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campoKey): array
    {
        if ($campoKey === 'estado_cierre') {
            return ['igual' => 'Igual a'];
        }

        return RendicionMaquinaListadoFiltros::operadoresParaCampo($campoKey);
    }
}
