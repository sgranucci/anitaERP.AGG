<?php

namespace App\Support\Contable;

use App\Models\Caja\RendicionMaquinavendingCaja;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado contable de cierre de rendiciones vending.
 */
class CierreRendicionMaquinavendingListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const ESTADO_TODOS = 'todos';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CERRADA = 'cerrada';

    /** @var array<string, array{column?: string, type: string, label: string, relation?: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'rendicion_maquinavending_caja.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo' => ['column' => 'rendicion_maquinavending_caja.codigo', 'type' => 'texto', 'label' => 'Ticket / código'],
        'empresa' => ['relation' => 'empresa', 'type' => 'texto', 'label' => 'Empresa'],
        'caja' => ['relation' => 'caja', 'type' => 'texto', 'label' => 'Caja'],
        'puntoventa_cae' => ['relation' => 'puntoventaCae', 'type' => 'texto', 'label' => 'PV CAE'],
        'maquinavending' => ['relation' => 'maquinavending', 'type' => 'texto', 'label' => 'Máquina'],
        'fecharendicion' => ['column' => 'rendicion_maquinavending_caja.fecharendicion', 'type' => 'fecha', 'label' => 'Fecha rendición'],
        'fecha_jornada' => ['type' => 'fecha_jornada', 'label' => 'Fecha jornada'],
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
                'empresa_id' => $empresaId ?? '',
                'empresa_scope' => $empresaScope,
            ]);
        }

        $estado = (string) $request->input('estado_cierre', self::ESTADO_TODOS);
        if (! in_array($estado, [self::ESTADO_TODOS, self::ESTADO_PENDIENTE, self::ESTADO_CERRADA], true)) {
            $estado = self::ESTADO_TODOS;
        }

        return [
            'empresa_id' => $empresaId ?? '',
            'empresa_scope' => $empresaScope,
            'valor' => FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta),
            'filtro_busqueda_rapida' => $request->input('filtro_busqueda_rapida'),
            'modo' => $request->input('filtro_modo', self::MODO_TODOS),
            'campo' => $request->input('filtro_campo', 'codigo'),
            'operador' => $request->input('filtro_operador', 'contiene'),
            'valor_hasta' => $request->input('filtro_valor_hasta'),
            'fecha_jornada_desde' => trim((string) $request->input('fecha_jornada_desde', '')),
            'fecha_jornada_hasta' => trim((string) $request->input('fecha_jornada_hasta', '')),
            'estado_cierre' => $estado,
        ];
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
        return [
            'empresa_id' => '',
            'empresa_scope' => 'una',
            'valor' => '',
            'filtro_busqueda_rapida' => '',
            'modo' => self::MODO_TODOS,
            'campo' => 'codigo',
            'operador' => 'contiene',
            'valor_hasta' => '',
            'fecha_jornada_desde' => '',
            'fecha_jornada_hasta' => '',
            'estado_cierre' => self::ESTADO_TODOS,
        ];
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
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            return true;
        }

        return false;
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

        $resto = array_filter([
            'filtro_valor' => $filtros['valor'] ?? null,
            'filtro_busqueda_rapida' => $filtros['filtro_busqueda_rapida'] ?? null,
            'filtro_modo' => $filtros['modo'] ?? null,
            'filtro_campo' => $filtros['campo'] ?? null,
            'filtro_operador' => $filtros['operador'] ?? null,
            'filtro_valor_hasta' => $filtros['valor_hasta'] ?? null,
            'fecha_jornada_desde' => $filtros['fecha_jornada_desde'] ?? null,
            'fecha_jornada_hasta' => $filtros['fecha_jornada_hasta'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $params = array_merge($params, $resto);

        if (($filtros['estado_cierre'] ?? self::ESTADO_TODOS) !== self::ESTADO_TODOS) {
            $params['estado_cierre'] = $filtros['estado_cierre'];
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
     * @param  Builder<RendicionMaquinavendingCaja>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        self::aplicarEstadoCierre($query, $filtros);
        self::aplicarRangoFechaJornada($query, $filtros);
        self::aplicarBusqueda($query, $filtros);
    }

    /**
     * @param  Builder<RendicionMaquinavendingCaja>  $query
     */
    public static function aplicarEstadoCierre(Builder $query, array $filtros): void
    {
        $estado = (string) ($filtros['estado_cierre'] ?? self::ESTADO_TODOS);
        if ($estado === self::ESTADO_PENDIENTE) {
            $query->where(function ($w) {
                $w->where(function ($q) {
                    $q->whereNull('rendicion_maquinavending_caja.asiento_id')
                        ->orWhere('rendicion_maquinavending_caja.asiento_id', 0);
                })->where(function ($q) {
                    $q->whereNull('rendicion_maquinavending_caja.cierre_contable_legacy')
                        ->orWhere('rendicion_maquinavending_caja.cierre_contable_legacy', false);
                });
            });

            return;
        }
        if ($estado === self::ESTADO_CERRADA) {
            $query->where(function ($w) {
                $w->whereNotNull('rendicion_maquinavending_caja.asiento_id')
                    ->where('rendicion_maquinavending_caja.asiento_id', '>', 0)
                    ->orWhere('rendicion_maquinavending_caja.cierre_contable_legacy', true);
            });
        }
    }

    /**
     * @param  Builder<RendicionMaquinavendingCaja>  $query
     */
    public static function aplicarRangoFechaJornada(Builder $query, array $filtros): void
    {
        [$desde, $hasta] = self::normalizarRangoFechas(
            (string) ($filtros['fecha_jornada_desde'] ?? ''),
            (string) ($filtros['fecha_jornada_hasta'] ?? ''),
        );

        if ($desde === '' && $hasta === '') {
            return;
        }

        $query->where(function ($w) use ($desde, $hasta) {
            $w->whereHas('maquinavendingRendicion', function ($mr) use ($desde, $hasta) {
                if ($desde !== '') {
                    $mr->whereDate('fecha_jornada', '>=', $desde);
                }
                if ($hasta !== '') {
                    $mr->whereDate('fecha_jornada', '<=', $hasta);
                }
            })->orWhere(function ($q) use ($desde, $hasta) {
                $q->whereDoesntHave('maquinavendingRendicion');
                if ($desde !== '') {
                    $q->whereDate('fecharendicion', '>=', $desde);
                }
                if ($hasta !== '') {
                    $q->whereDate('fecharendicion', '<=', $hasta);
                }
            });
        });
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
     * @param  Builder<RendicionMaquinavendingCaja>  $query
     */
    private static function aplicarBusqueda(Builder $query, array $filtros): void
    {
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_CAMPO) {
            return;
        }

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_TODOS && $valor !== '') {
            $query->where(function ($q) use ($valor) {
                $id = filter_var($valor, FILTER_VALIDATE_INT);
                if ($id !== false) {
                    $q->orWhere('rendicion_maquinavending_caja.id', (int) $id);
                }
                $q->orWhere('rendicion_maquinavending_caja.codigo', 'like', '%'.$valor.'%')
                    ->orWhereHas('empresa', fn ($e) => $e->where('nombre', 'like', '%'.$valor.'%'))
                    ->orWhereHas('puntoventaCae', fn ($pv) => $pv->where('codigo', 'like', '%'.$valor.'%')
                        ->orWhere('nombre', 'like', '%'.$valor.'%'))
                    ->orWhereHas('maquinavending', fn ($m) => $m->where('nombre', 'like', '%'.$valor.'%'));
            });

            return;
        }

        if ($valor !== '') {
            $campo = (string) ($filtros['campo'] ?? 'codigo');
            if ($campo === 'codigo') {
                $query->where('rendicion_maquinavending_caja.codigo', 'like', '%'.$valor.'%');
            } elseif ($campo === 'id') {
                $query->where('rendicion_maquinavending_caja.id', (int) $valor);
            }
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

        return ['contiene' => 'Contiene', 'igual' => 'Igual a'];
    }
}
