<?php

namespace App\Support\Caja;

use App\Models\Caja\CajeroCrown;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CajeroCrownListadoFiltros
{
    public const FILTRO_ACTIVOS = 'activos';

    public const FILTRO_INACTIVOS = 'inactivos';

    public const FILTRO_TODOS = 'todos';

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $salaId = (int) $request->input('sala_id', 0);
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            $vacios = self::filtrosVacios();
            $vacios['sala_id'] = $salaId;

            return $vacios;
        }

        $estado = strtolower(trim((string) $request->input('filtro_estado', self::FILTRO_ACTIVOS)));
        if (! in_array($estado, [self::FILTRO_ACTIVOS, self::FILTRO_INACTIVOS, self::FILTRO_TODOS], true)) {
            $estado = self::FILTRO_ACTIVOS;
        }

        return [
            'valor' => FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta),
            'sala_id' => $salaId,
            'filtro_estado' => $estado,
            'qbe' => ListadoQbeSupport::resolverDesdeRequest(
                $request,
                self::camposQbe(),
                static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
            ),
            'sort' => ListadoOrdenamientoSupport::resolverDesdeRequest($request, self::camposOrdenables()),
            'agrupar' => ListadoAgrupacionSupport::normalizar($request->input('group', []), self::camposOrdenables()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'valor' => '',
            'sala_id' => 0,
            'filtro_estado' => self::FILTRO_ACTIVOS,
            'qbe' => ListadoQbeSupport::vacio(),
            'sort' => [],
            'agrupar' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        if (($filtros['filtro_estado'] ?? self::FILTRO_ACTIVOS) !== self::FILTRO_ACTIVOS) {
            return true;
        }

        return ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))
            || ($filtros['agrupar'] ?? []) !== [];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if ((int) ($filtros['sala_id'] ?? 0) > 0) {
            $params['sala_id'] = (int) $filtros['sala_id'];
        }
        $estado = (string) ($filtros['filtro_estado'] ?? self::FILTRO_ACTIVOS);
        if ($estado !== self::FILTRO_ACTIVOS) {
            $params['filtro_estado'] = $estado;
        }

        return array_merge(
            $params,
            ListadoQbeSupport::paraQueryString((array) ($filtros['qbe'] ?? [])),
            ListadoOrdenamientoSupport::paraQueryString((array) ($filtros['sort'] ?? [])),
            ListadoAgrupacionSupport::paraQueryString((array) ($filtros['agrupar'] ?? []))
        );
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposQbe(): array
    {
        return CajeroCrownListadoColumnas::camposFiltrables();
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return CajeroCrownListadoColumnas::camposOrdenables();
    }

    public static function normalizarOperador(string $op, string $campo): string
    {
        $tipo = self::camposQbe()[$campo]['type'] ?? 'texto';
        $validos = match ($tipo) {
            'entero' => ['igual', 'mayor', 'menor', 'mayor_igual', 'menor_igual', 'entre'],
            'bool' => ['igual'],
            default => ['contiene', 'igual', 'empieza'],
        };

        return in_array($op, $validos, true) ? $op : match ($tipo) {
            'entero' => 'igual',
            'bool' => 'igual',
            default => 'contiene',
        };
    }

    /**
     * @param  Builder<CajeroCrown>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $salaId = (int) ($filtros['sala_id'] ?? 0);
        if ($salaId > 0) {
            $query->where('cajero_crown.sala_id', $salaId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $estado = (string) ($filtros['filtro_estado'] ?? self::FILTRO_ACTIVOS);
        if ($estado === self::FILTRO_ACTIVOS) {
            $query->where('cajero_crown.activo', true);
        } elseif ($estado === self::FILTRO_INACTIVOS) {
            $query->where('cajero_crown.activo', false);
        }

        self::aplicarJoins($query, $filtros);
        self::aplicarQbe($query, (array) ($filtros['qbe'] ?? []));

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '') {
            $query->where(function (Builder $q) use ($valor) {
                if (ctype_digit($valor)) {
                    $num = (int) $valor;
                    $q->orWhere('cajero_crown.codigo', $num)
                        ->orWhere('cajero_crown.legajo', $num);
                }
                $q->orWhere('cajero_crown.nombre', 'like', '%'.$valor.'%');
                CoincidenciaFlexibleTexto::aplicar($q, 'cajero_crown.nombre', $valor);
                $q->orWhere('usuario.nombre', 'like', '%'.$valor.'%')
                    ->orWhere('usuario.usuario', 'like', '%'.$valor.'%')
                    ->orWhere('caja.codigo', 'like', '%'.$valor.'%')
                    ->orWhere('caja.nombre', 'like', '%'.$valor.'%');
            });
        }

        ListadoOrdenamientoSupport::aplicar(
            $query,
            (array) ($filtros['sort'] ?? []),
            self::camposOrdenables(),
            ['campo' => 'codigo', 'dir' => 'asc']
        );
        $query->orderBy('cajero_crown.codigo')->orderBy('cajero_crown.id');
    }

    /**
     * @param  Builder<CajeroCrown>  $query
     * @param  array<string, mixed>  $filtros
     */
    private static function aplicarJoins(Builder $query, array $filtros): void
    {
        $necesitaUsuario = self::necesitaColumna($filtros, 'usuario');
        $necesitaCaja = self::necesitaColumna($filtros, 'caja');
        $necesitaMozo = self::necesitaColumna($filtros, 'mozo');
        if ($necesitaUsuario) {
            $query->leftJoin('usuario', 'usuario.id', '=', 'cajero_crown.usuario_id');
        }
        if ($necesitaCaja) {
            $query->leftJoin('caja', 'caja.id', '=', 'cajero_crown.caja_id');
        }
        if ($necesitaMozo) {
            $query->leftJoin('mozo_gastronomia', 'mozo_gastronomia.id', '=', 'cajero_crown.mozo_gastronomia_id');
        }
        if ($necesitaUsuario || $necesitaCaja || $necesitaMozo) {
            $query->select('cajero_crown.*')->distinct();
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private static function necesitaColumna(array $filtros, string $key): bool
    {
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '' && in_array($key, ['usuario', 'caja'], true)) {
            return true;
        }
        foreach ((array) ($filtros['sort'] ?? []) as $criterio) {
            if (($criterio['campo'] ?? '') === $key) {
                return true;
            }
        }
        foreach ((array) (($filtros['qbe']['grupos'] ?? []) ?: []) as $grupo) {
            foreach ((array) ($grupo['criterios'] ?? []) as $criterio) {
                if (($criterio['campo'] ?? '') === $key) {
                    return true;
                }
            }
        }
        foreach ((array) ($filtros['agrupar'] ?? []) as $campo) {
            if ($campo === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Builder<CajeroCrown>  $query
     * @param  array<string, mixed>  $qbe
     */
    private static function aplicarQbe(Builder $query, array $qbe): void
    {
        if (! ListadoQbeSupport::tieneCriterios($qbe)) {
            return;
        }
        ListadoQbeSupport::aplicar($query, $qbe, static function (Builder $q, array $criterio, string $boolean = 'and'): void {
            if ($boolean === ListadoQbeSupport::LOGIC_OR) {
                $q->orWhere(function (Builder $inner) use ($criterio) {
                    self::aplicarCriterio($inner, $criterio);
                });

                return;
            }
            self::aplicarCriterio($q, $criterio);
        });
    }

    /**
     * @param  Builder<CajeroCrown>  $query
     * @param  array<string, mixed>  $criterio
     */
    private static function aplicarCriterio(Builder $query, array $criterio): void
    {
        $campo = (string) ($criterio['campo'] ?? '');
        $def = self::camposQbe()[$campo] ?? null;
        if ($def === null) {
            return;
        }
        $op = (string) ($criterio['op'] ?? 'igual');
        $valor = trim((string) ($criterio['valor'] ?? ''));
        $hasta = trim((string) ($criterio['valor_hasta'] ?? ''));
        $tipo = (string) ($def['type'] ?? 'texto');
        $column = (string) $def['column'];

        if ($tipo === 'entero') {
            if ($valor === '' && $op !== 'vacio') {
                return;
            }
            $numero = (int) $valor;
            $tope = $hasta !== '' ? (int) $hasta : $numero;
            match ($op) {
                'mayor' => $query->where($column, '>', $numero),
                'menor' => $query->where($column, '<', $numero),
                'mayor_igual' => $query->where($column, '>=', $numero),
                'menor_igual' => $query->where($column, '<=', $numero),
                'entre' => $query->whereBetween($column, [min($numero, $tope), max($numero, $tope)]),
                default => $query->where($column, $numero),
            };

            return;
        }

        if ($tipo === 'bool') {
            $bool = in_array(strtolower($valor), ['1', 'si', 'sí', 'true', 'activo'], true);
            $query->where($column, $bool);

            return;
        }

        if ($valor === '') {
            return;
        }
        match ($op) {
            'igual' => $query->where($column, $valor),
            'empieza' => $query->where($column, 'like', $valor.'%'),
            default => $query->where(function (Builder $q) use ($column, $valor) {
                $q->where($column, 'like', '%'.$valor.'%');
                CoincidenciaFlexibleTexto::aplicar($q, $column, $valor);
            }),
        };
    }
}
