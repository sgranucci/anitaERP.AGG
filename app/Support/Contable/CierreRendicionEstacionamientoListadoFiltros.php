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

    /** Index/export: un asiento por fecha jornada + PV (varias rendiciones/turnos juntas). */
    public const VISTA_AGRUPADO = 'agrupado';

    /** Index/export: una fila por turno/rendición (misma fecha+PV pueden verse varias filas). */
    public const VISTA_POR_TURNO = 'por_turno';

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

        $vista = (string) $request->input('vista', self::VISTA_AGRUPADO);
        if (! in_array($vista, [self::VISTA_AGRUPADO, self::VISTA_POR_TURNO], true)) {
            $vista = self::VISTA_AGRUPADO;
        }
        $filtros['vista'] = $vista;

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
        $vista = (string) ($filtros['vista'] ?? self::VISTA_AGRUPADO);
        if ($vista !== self::VISTA_AGRUPADO) {
            $params['vista'] = $vista;
        }

        return $params;
    }

    public static function esVistaPorTurno(array $filtros): bool
    {
        return ($filtros['vista'] ?? self::VISTA_AGRUPADO) === self::VISTA_POR_TURNO;
    }

    /**
     * Texto de cabecera para PDF/Excel/CSV (empresa, fechas, estado, vista, búsqueda).
     */
    public static function textoCabeceraExport(array $filtros): string
    {
        $partes = [];
        $partes[] = 'Generado '.now()->format('d/m/Y H:i');

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $nombreEmpresa = (string) (\App\Models\Configuracion\Empresa::query()
                ->whereKey($empresaId)
                ->value('nombre') ?? '');
            $partes[] = 'Empresa: '.($nombreEmpresa !== '' ? $nombreEmpresa : '#'.$empresaId);
        } else {
            $partes[] = 'Empresa: todas (asignadas al usuario)';
        }

        $desde = trim((string) ($filtros['fecha_jornada_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_jornada_hasta'] ?? ''));
        if ($desde !== '' || $hasta !== '') {
            $fmtDesde = $desde !== '' ? \Carbon\Carbon::parse($desde)->format('d/m/Y') : '…';
            $fmtHasta = $hasta !== '' ? \Carbon\Carbon::parse($hasta)->format('d/m/Y') : '…';
            $partes[] = 'Jornada: '.$fmtDesde.' al '.$fmtHasta;
        } else {
            $partes[] = 'Jornada: sin rango de fechas';
        }

        $vista = self::esVistaPorTurno($filtros) ? 'Por turno' : 'Unificado (PV + fecha)';
        $partes[] = 'Vista: '.$vista;

        $estado = (string) ($filtros['estado_cierre'] ?? self::ESTADO_TODOS);
        $estadoLabel = match ($estado) {
            self::ESTADO_PENDIENTE => 'Pendiente',
            self::ESTADO_CERRADA => 'Cerrada',
            default => 'Todos',
        };
        $partes[] = 'Estado cierre: '.$estadoLabel;

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '') {
            $modo = (string) ($filtros['modo'] ?? self::MODO_TODOS);
            if ($modo === self::MODO_CAMPO) {
                $campo = (string) ($filtros['campo'] ?? '');
                $labelCampo = self::CAMPOS[$campo]['label'] ?? $campo;
                $operador = (string) ($filtros['operador'] ?? '');
                $partes[] = 'Búsqueda: '.$labelCampo.' '.$operador.' "'.$valor.'"';
            } else {
                $partes[] = 'Búsqueda: "'.$valor.'"';
            }
        }

        return implode(' — ', $partes);
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
