<?php

namespace App\Support\Contable;

use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Support\Caja\RendicionEstacionamientoCajaListadoFiltros;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado contable de cierre de rendiciones estacionamiento.
 */
class CierreRendicionEstacionamientoListadoFiltros
{
    public const MODO_TODOS = RendicionEstacionamientoCajaListadoFiltros::MODO_TODOS;

    public const MODO_CAMPO = RendicionEstacionamientoCajaListadoFiltros::MODO_CAMPO;

    public const ESTADO_TODOS = 'todos';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CERRADA = 'cerrada';

    /** @var array<string, array{column?: string, type: string, label: string, relation?: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'rendicion_estacionamiento_caja.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo' => ['column' => 'rendicion_estacionamiento_caja.codigo', 'type' => 'texto', 'label' => 'Ticket / código'],
        'empresa' => ['relation' => 'empresa', 'type' => 'texto', 'label' => 'Empresa'],
        'caja' => ['relation' => 'caja', 'type' => 'texto', 'label' => 'Caja'],
        'puntoventa_cae' => ['relation' => 'puntoventaCae', 'type' => 'texto', 'label' => 'PV CAE'],
        'turno_operativo_id' => ['column' => 'rendicion_estacionamiento_caja.turno_operativo_estacionamiento_id', 'type' => 'entero', 'label' => 'Turno operativo'],
        'fecharendicion' => ['column' => 'rendicion_estacionamiento_caja.fecharendicion', 'type' => 'fecha', 'label' => 'Fecha rendición'],
        'fecha_jornada' => ['type' => 'fecha_jornada', 'label' => 'Fecha jornada'],
        'estado_cierre' => ['type' => 'estado_cierre', 'label' => 'Estado cierre contable'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = RendicionEstacionamientoCajaListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
        $estado = (string) $request->input('estado_cierre', self::ESTADO_TODOS);
        if (! in_array($estado, [self::ESTADO_TODOS, self::ESTADO_PENDIENTE, self::ESTADO_CERRADA], true)) {
            $estado = self::ESTADO_TODOS;
        }
        $filtros['estado_cierre'] = $estado;

        return $filtros;
    }

    public static function tieneCriteriosUsuario(array $filtros): bool
    {
        if (($filtros['estado_cierre'] ?? self::ESTADO_TODOS) !== self::ESTADO_TODOS) {
            return true;
        }

        if (trim((string) ($filtros['fecha_jornada_desde'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_jornada_hasta'] ?? '')) !== '') {
            return true;
        }

        return RendicionEstacionamientoCajaListadoFiltros::tieneCriteriosUsuario($filtros);
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
        $params = RendicionEstacionamientoCajaListadoFiltros::paraQueryString($filtros);
        if (($filtros['estado_cierre'] ?? self::ESTADO_TODOS) !== self::ESTADO_TODOS) {
            $params['estado_cierre'] = $filtros['estado_cierre'];
        }

        return $params;
    }

    /**
     * @param  Builder<RendicionEstacionamientoCaja>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        self::aplicarEstadoCierre($query, $filtros);
        RendicionEstacionamientoCajaListadoFiltros::aplicar($query, $filtros);
    }

    /**
     * Solo rendiciones de turno (cierre por PV).
     *
     * @param  Builder<RendicionEstacionamientoCaja>  $query
     */
    public static function aplicarScopeTurno(Builder $query): void
    {
        $query->where(function ($w) {
            $w->where('rendicion_estacionamiento_caja.tipo', RendicionEstacionamientoCaja::TIPO_TURNO)
                ->orWhereNull('rendicion_estacionamiento_caja.tipo')
                ->orWhere('rendicion_estacionamiento_caja.tipo', '');
        });
    }

    /**
     * @param  Builder<RendicionEstacionamientoCaja>  $query
     */
    public static function aplicarEstadoCierre(Builder $query, array $filtros): void
    {
        $estado = (string) ($filtros['estado_cierre'] ?? self::ESTADO_TODOS);
        if ($estado === self::ESTADO_PENDIENTE) {
            $query->where(function ($w) {
                $w->where(function ($q) {
                    $q->whereNull('rendicion_estacionamiento_caja.asiento_id')
                        ->orWhere('rendicion_estacionamiento_caja.asiento_id', 0);
                })->where(function ($q) {
                    $q->whereNull('rendicion_estacionamiento_caja.cierre_contable_legacy')
                        ->orWhere('rendicion_estacionamiento_caja.cierre_contable_legacy', false);
                });
            });

            return;
        }
        if ($estado === self::ESTADO_CERRADA) {
            $query->where(function ($w) {
                $w->whereNotNull('rendicion_estacionamiento_caja.asiento_id')
                    ->where('rendicion_estacionamiento_caja.asiento_id', '>', 0)
                    ->orWhere('rendicion_estacionamiento_caja.cierre_contable_legacy', true);
            });
        }
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campoKey): array
    {
        if ($campoKey === 'estado_cierre') {
            return [
                'igual' => 'Igual a',
            ];
        }

        return RendicionEstacionamientoCajaListadoFiltros::operadoresParaCampo($campoKey);
    }
}
