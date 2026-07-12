<?php

namespace App\Support\Contable;

use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Support\Caja\Bingo\RendicionBingoCajaListadoFiltros;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class CierreRendicionBingoListadoFiltros
{
    public const MODO_TODOS = RendicionBingoCajaListadoFiltros::MODO_TODOS;

    public const MODO_CAMPO = RendicionBingoCajaListadoFiltros::MODO_CAMPO;

    public const ESTADO_TODOS = 'todos';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CERRADA = 'cerrada';

    /** @var array<string, array{column?: string, type: string, label: string, relation?: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'rendicion_bingo_caja.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo' => ['column' => 'rendicion_bingo_caja.codigo', 'type' => 'texto', 'label' => 'Ticket / código'],
        'empresa' => ['relation' => 'empresa', 'type' => 'texto', 'label' => 'Empresa'],
        'fecharendicion' => ['column' => 'rendicion_bingo_caja.fecharendicion', 'type' => 'fecha', 'label' => 'Fecha rendición'],
        'fecha_jornada' => ['column' => 'rendicion_bingo_caja.fecha_jornada', 'type' => 'fecha', 'label' => 'Fecha jornada'],
        'estado_cierre' => ['type' => 'estado_cierre', 'label' => 'Estado cierre contable'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = RendicionBingoCajaListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
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

        return RendicionBingoCajaListadoFiltros::tieneCriteriosUsuario($filtros);
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
        $params = RendicionBingoCajaListadoFiltros::paraQueryString($filtros);
        if (($filtros['estado_cierre'] ?? self::ESTADO_TODOS) !== self::ESTADO_TODOS) {
            $params['estado_cierre'] = $filtros['estado_cierre'];
        }

        return $params;
    }

    /**
     * @param  Builder<RendicionBingoCaja>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        self::aplicarEstadoCierre($query, $filtros);
        RendicionBingoCajaListadoFiltros::aplicar($query, $filtros);
    }

    /**
     * @param  Builder<RendicionBingoCaja>  $query
     */
    public static function aplicarEstadoCierre(Builder $query, array $filtros): void
    {
        $estado = (string) ($filtros['estado_cierre'] ?? self::ESTADO_TODOS);
        if ($estado === self::ESTADO_PENDIENTE) {
            $query->where(function ($w) {
                $w->whereNull('rendicion_bingo_caja.asiento_id')
                    ->orWhere('rendicion_bingo_caja.asiento_id', 0);
            });

            return;
        }
        if ($estado === self::ESTADO_CERRADA) {
            $query->whereNotNull('rendicion_bingo_caja.asiento_id')
                ->where('rendicion_bingo_caja.asiento_id', '>', 0);
        }
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campoKey): array
    {
        if ($campoKey === 'estado_cierre') {
            return ['igual' => 'Igual a'];
        }

        return RendicionBingoCajaListadoFiltros::operadoresParaCampo($campoKey);
    }
}
