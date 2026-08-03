<?php

namespace App\Support\Sueldos;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de novedades de liquidación (index paginado).
 */
class NovedadSueldosListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'novedad_sueldos.id', 'type' => 'entero', 'label' => 'ID'],
        'concepto_codigo' => ['column' => 'novedad_sueldos.concepto_codigo', 'type' => 'entero', 'label' => 'Concepto'],
        'legajo' => ['column' => 'empleado_sueldos.legajo', 'type' => 'entero', 'label' => 'Legajo'],
        'empleado' => ['column' => 'empleado_sueldos.nombre', 'type' => 'texto', 'label' => 'Empleado'],
        'estado' => ['column' => 'novedad_sueldos.estado', 'type' => 'texto', 'label' => 'Estado'],
        'origen' => ['column' => 'novedad_sueldos.origen', 'type' => 'texto', 'label' => 'Origen'],
        'periodo' => ['column' => 'novedad_sueldos.periodo', 'type' => 'entero', 'label' => 'Período'],
        'liquidacion' => ['column' => 'liquidacion_sueldos.numero', 'type' => 'entero', 'label' => 'Corrida'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'empleado_sueldos.nombre',
        'concepto_sueldos.descripcion',
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

    private const CAMPO_DEFAULT = 'empleado';

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

        $campo = (string) $request->input('filtro_campo', self::CAMPO_DEFAULT);
        if (! isset(self::CAMPOS[$campo])) {
            $campo = self::CAMPO_DEFAULT;
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : self::CAMPO_DEFAULT);

        $liquidacionId = (int) $request->input('liquidacion_id', 0);
        $empleadoId = (int) $request->input('empleado_id', 0);

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'liquidacion_id' => $liquidacionId > 0 ? $liquidacionId : null,
            'empleado_id' => $empleadoId > 0 ? $empleadoId : null,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (! empty($filtros['liquidacion_id']) || ! empty($filtros['empleado_id'])) {
            return true;
        }
        if (($filtros['operador'] ?? '') === 'vacio') {
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

    /**
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, busqueda: string, liquidacion_id: ?int, empleado_id: ?int}
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => self::CAMPO_DEFAULT,
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'liquidacion_id' => null,
            'empleado_id' => null,
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? self::CAMPO_DEFAULT;
            $params['filtro_operador'] = $filtros['operador'] ?? 'contiene';
        } elseif (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            $params['filtro_operador'] = $filtros['operador'];
        }
        if (! empty($filtros['valor'])) {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if (! empty($filtros['liquidacion_id'])) {
            $params['liquidacion_id'] = (int) $filtros['liquidacion_id'];
        }
        if (! empty($filtros['empleado_id'])) {
            $params['empleado_id'] = (int) $filtros['empleado_id'];
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Novedad_Sueldos>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['liquidacion_id'])) {
            $query->where('novedad_sueldos.liquidacion_id', (int) $filtros['liquidacion_id']);
        }
        if (! empty($filtros['empleado_id'])) {
            $query->where('novedad_sueldos.empleado_id', (int) $filtros['empleado_id']);
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? self::CAMPO_DEFAULT, $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Novedad_Sueldos>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                $q->whereNull('empleado_sueldos.nombre')->orWhere('empleado_sueldos.nombre', '');
            });

            return;
        }
        if ($valor === '') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = '%'.self::escapeLike($valor).'%';

        $query->where(function ($q) use ($valor, $like, $id) {
            if ($id !== false) {
                $q->orWhere('novedad_sueldos.id', (int) $id)
                    ->orWhere('novedad_sueldos.concepto_codigo', (int) $id)
                    ->orWhere('empleado_sueldos.legajo', (int) $id)
                    ->orWhere('liquidacion_sueldos.numero', (int) $id)
                    ->orWhere('novedad_sueldos.periodo', (int) $id);
            }
            $q->orWhere('empleado_sueldos.nombre', 'like', $like)
                ->orWhere('concepto_sueldos.descripcion', 'like', $like)
                ->orWhere('novedad_sueldos.estado', 'like', $like)
                ->orWhere('novedad_sueldos.origen', 'like', $like);
            CoincidenciaFlexibleTexto::aplicar(
                $q,
                'empleado_sueldos.nombre',
                $valor,
                true,
                CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
            );
            CoincidenciaFlexibleTexto::aplicar(
                $q,
                'concepto_sueldos.descripcion',
                $valor,
                true,
                CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
            );
        });
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Novedad_Sueldos>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS[self::CAMPO_DEFAULT];

        if ($def['type'] === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Novedad_Sueldos>  $query
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
                $query->where($column, '<>', $valor);
                break;
            default:
                $query->where(function ($q) use ($column, $valor) {
                    $q->where($column, 'like', '%'.self::escapeLike($valor).'%');
                    if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                        CoincidenciaFlexibleTexto::aplicar(
                            $q,
                            $column,
                            $valor,
                            true,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                        );
                    }
                });
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Novedad_Sueldos>  $query
     */
    private static function aplicarEntero(Builder $query, string $column, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->whereNull($column);

            return;
        }
        if ($valor === '' || filter_var($valor, FILTER_VALIDATE_INT) === false) {
            return;
        }
        $n = (int) $valor;
        switch ($operador) {
            case 'mayor':
                $query->where($column, '>', $n);
                break;
            case 'menor':
                $query->where($column, '<', $n);
                break;
            default:
                $query->where($column, '=', $n);
                break;
        }
    }

    public static function operadoresParaCampo(string $campo): array
    {
        $def = self::CAMPOS[$campo] ?? null;
        if ($def && ($def['type'] ?? '') === 'entero') {
            return self::OPERADORES_ENTERO;
        }

        return self::OPERADORES_TEXTO;
    }

    private static function normalizarOperador(string $operador, string $campo): string
    {
        $ops = self::operadoresParaCampo($campo);
        if (! isset($ops[$operador])) {
            return array_key_first($ops) ?: 'contiene';
        }

        return $operador;
    }

    private static function escapeLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }
}
