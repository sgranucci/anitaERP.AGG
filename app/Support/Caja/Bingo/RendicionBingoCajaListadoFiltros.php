<?php

namespace App\Support\Caja\Bingo;

use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de rendiciones bingo en caja (index / exportaciones).
 */
class RendicionBingoCajaListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column?: string, type: string, label: string, relation?: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'rendicion_bingo_caja.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo' => ['column' => 'rendicion_bingo_caja.codigo', 'type' => 'texto', 'label' => 'Ticket / código'],
        'empresa' => ['relation' => 'empresa', 'type' => 'texto', 'label' => 'Empresa'],
        'turno_operativo_id' => ['column' => 'rendicion_bingo_caja.turno_operativo_bingo_id', 'type' => 'entero', 'label' => 'Turno operativo'],
        'terminal' => ['relation' => 'turnoOperativo', 'type' => 'texto', 'label' => 'Terminal'],
        'fecharendicion' => ['column' => 'rendicion_bingo_caja.fecharendicion', 'type' => 'fecha', 'label' => 'Fecha rendición'],
        'fecha_jornada' => ['column' => 'rendicion_bingo_caja.fecha_jornada', 'type' => 'fecha', 'label' => 'Fecha jornada'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'rendicion_bingo_caja.codigo',
        'empresa.nombre',
    ];

    /** @var array<string, string> */
    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene (en cualquier parte)',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Igual a',
        'distinto' => 'Distinto de',
        'vacio' => 'Vacío',
    ];

    /** @var array<string, string> */
    public const OPERADORES_ENTERO = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
    ];

    /** @var array<string, string> */
    public const OPERADORES_FECHA = [
        'igual' => 'Igual a',
        'desde' => 'Desde (≥)',
        'hasta' => 'Hasta (≤)',
        'entre' => 'Entre',
        'vacio' => 'Sin fecha',
    ];

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return self::filtrosVacios();
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'codigo');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'codigo';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'codigo');

        $fechaDesde = trim((string) $request->input('fecha_jornada_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_jornada_hasta', ''));

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'fecha_jornada_desde' => $fechaDesde !== '' ? $fechaDesde : '',
            'fecha_jornada_hasta' => $fechaHasta !== '' ? $fechaHasta : '',
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'empresas_asignadas' => [],
        ];
    }

    /**
     * Criterios elegidos por el usuario (toolbar, aviso «Limpiar filtros»).
     * No incluye empresa asignada por defecto ni el alcance de empresas en sesión.
     */
    public static function tieneCriteriosUsuario(array $filtros): bool
    {
        if (trim((string) ($filtros['fecha_jornada_desde'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_jornada_hasta'] ?? '')) !== '') {
            return true;
        }

        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }

        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($filtros['valor_hasta'] ?? '')) !== '') {
            return true;
        }

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            return true;
        }

        if (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            return true;
        }

        return false;
    }

    /**
     * Si hay criterios de búsqueda/fecha para aplicar en la consulta (incluye filtros de usuario).
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosUsuario($filtros);
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'codigo',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'fecha_jornada_desde' => '',
            'fecha_jornada_hasta' => '',
            'empresa_id' => 0,
            'empresas_asignadas' => [],
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringModoValor($filtros);

        if (! empty($filtros['fecha_jornada_desde'])) {
            $params['fecha_jornada_desde'] = $filtros['fecha_jornada_desde'];
        }
        if (! empty($filtros['fecha_jornada_hasta'])) {
            $params['fecha_jornada_hasta'] = $filtros['fecha_jornada_hasta'];
        }
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }

        return $params;
    }

    /**
     * Limita el listado a las empresas del usuario (sesión). Si hay empresa_id en filtros, acota a esa.
     *
     * @param  Builder<RendicionBingoCaja>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarScopeEmpresasAsignadas(Builder $query, array $filtros): void
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('rendicion_bingo_caja.empresa_id', $empresaId);

            return;
        }

        /** @var list<int> $asignadas */
        $asignadas = array_values(array_filter(
            array_map('intval', (array) ($filtros['empresas_asignadas'] ?? [])),
            fn (int $id) => $id > 0,
        ));

        if ($asignadas === []) {
            return;
        }

        $query->whereIn('rendicion_bingo_caja.empresa_id', $asignadas);
    }

    /**
     * @return array<string, string|int|bool>
     */
    private static function paraQueryStringModoValor(array $filtros): array
    {
        $params = [];
        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'codigo';
            $params['filtro_operador'] = $filtros['operador'] ?? 'contiene';
        } elseif (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            $params['filtro_operador'] = $filtros['operador'];
        }
        if (! empty($filtros['valor'])) {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if (! empty($filtros['valor_hasta'])) {
            $params['filtro_valor_hasta'] = $filtros['valor_hasta'];
        }

        return $params;
    }

    /**
     * @param  Builder<RendicionBingoCaja>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        self::aplicarRangoFechaJornada($query, $filtros);

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'codigo', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<RendicionBingoCaja>  $query
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

        if ($desde !== '') {
            $query->whereDate('rendicion_bingo_caja.fecha_jornada', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate('rendicion_bingo_caja.fecha_jornada', '<=', $hasta);
        }
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
     * @param  Builder<RendicionBingoCaja>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->whereRaw('0 = 1');

            return;
        }

        if ($valor === '') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $id, $operador) {
            if ($id !== false) {
                $q->orWhere('rendicion_bingo_caja.id', (int) $id)
                    ->orWhere('rendicion_bingo_caja.turno_operativo_bingo_id', (int) $id);
            }

            $q->orWhere('rendicion_bingo_caja.codigo', 'like', $like);
            if ($operador === 'contiene') {
                CoincidenciaFlexibleTexto::aplicar($q, 'rendicion_bingo_caja.codigo', $valor, true);
            }

            $q->orWhereHas('empresa', fn ($e) => self::aplicarTextoEnSubquery($e, 'nombre', $operador, $valor))
                ->orWhereHas('turnoOperativo', fn ($t) => self::aplicarTextoEnSubquery($t, 'identificador_pc', $operador, $valor));
        });
    }

    /**
     * @param  Builder<RendicionBingoCaja>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['codigo'];
        $type = $def['type'];

        if (isset($def['relation'])) {
            self::aplicarEnRelacion($query, (string) $def['relation'], $operador, $valor);

            return;
        }

        $column = (string) ($def['column'] ?? '');

        if ($type === 'entero') {
            self::aplicarEntero($query, $column, $operador, $valor);

            return;
        }

        if ($type === 'fecha') {
            self::aplicarFechaColumna($query, $column, $operador, $valor, $valorHasta);

            return;
        }

        self::aplicarTexto($query, $column, $operador, $valor);
    }

    /**
     * @param  Builder<RendicionBingoCaja>  $query
     */
    private static function aplicarEnRelacion(Builder $query, string $relation, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->whereDoesntHave($relation);

            return;
        }

        if ($valor === '') {
            return;
        }

        $query->whereHas($relation, function ($sub) use ($relation, $operador, $valor) {
            if ($relation === 'turnoOperativo') {
                self::aplicarTextoEnSubquery($sub, 'identificador_pc', $operador, $valor);
            } else {
                self::aplicarTextoEnSubquery($sub, 'nombre', $operador, $valor);
            }
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function aplicarTextoEnSubquery(Builder $query, string $column, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        $like = self::patronLike($operador, $valor);
        $query->where(function ($q) use ($column, $like, $valor, $operador) {
            $q->where($column, 'like', $like);
            if ($operador === 'contiene' && $column === 'nombre') {
                CoincidenciaFlexibleTexto::aplicar($q, $column, $valor, false);
            }
        });
    }

    /**
     * @param  Builder<RendicionBingoCaja>  $query
     */
    private static function aplicarTexto(Builder $query, string $column, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }
        if ($valor === '') {
            return;
        }

        switch ($operador) {
            case 'empieza':
                $query->where($column, 'like', self::escapeLike($valor).'%');
                break;
            case 'termina':
                $query->where($column, 'like', '%'.self::escapeLike($valor));
                break;
            case 'igual':
                $query->where($column, '=', $valor);
                break;
            case 'distinto':
                $query->where($column, '!=', $valor);
                break;
            case 'contiene':
            default:
                $query->where(function ($q) use ($column, $valor) {
                    $like = '%'.self::escapeLike($valor).'%';
                    $q->where($column, 'like', $like);
                    if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                        CoincidenciaFlexibleTexto::aplicar($q, $column, $valor, false);
                    }
                });
                break;
        }
    }

    /**
     * @param  Builder<RendicionBingoCaja>  $query
     */
    private static function aplicarEntero(Builder $query, string $column, string $operador, string $valor): void
    {
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false) {
            return;
        }
        $id = (int) $id;
        switch ($operador) {
            case 'mayor':
                $query->where($column, '>', $id);
                break;
            case 'menor':
                $query->where($column, '<', $id);
                break;
            default:
                $query->where($column, '=', $id);
                break;
        }
    }

    /**
     * @param  Builder<RendicionBingoCaja>  $query
     */
    private static function aplicarFechaColumna(Builder $query, string $column, string $operador, string $valor, string $valorHasta): void
    {
        if ($operador === 'vacio') {
            $query->whereNull($column);

            return;
        }

        $desde = self::parsearFecha($valor);
        $hasta = self::parsearFecha($valorHasta);

        switch ($operador) {
            case 'desde':
                if ($desde) {
                    $query->whereDate($column, '>=', $desde);
                }
                break;
            case 'hasta':
                if ($desde) {
                    $query->whereDate($column, '<=', $desde);
                }
                break;
            case 'entre':
                if ($desde && $hasta) {
                    $query->whereDate($column, '>=', $desde)->whereDate($column, '<=', $hasta);
                } elseif ($desde) {
                    $query->whereDate($column, '>=', $desde);
                } elseif ($hasta) {
                    $query->whereDate($column, '<=', $hasta);
                }
                break;
            default:
                if ($desde) {
                    $query->whereDate($column, '=', $desde);
                }
                break;
        }
    }

    private static function parsearFecha(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $valor)->format('Y-m-d');
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    private static function patronLike(string $operador, string $valor): string
    {
        $v = self::escapeLike($valor);

        return match ($operador) {
            'empieza' => $v.'%',
            'termina' => '%'.$v,
            'igual' => $v,
            default => '%'.$v.'%',
        };
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO),
            'fecha' => array_keys(self::OPERADORES_FECHA),
            default => array_keys(self::OPERADORES_TEXTO),
        };

        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };
    }
}
