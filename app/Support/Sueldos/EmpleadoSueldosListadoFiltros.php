<?php

namespace App\Support\Sueldos;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de empleados de sueldos (index paginado).
 */
class EmpleadoSueldosListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'empleado_sueldos.id', 'type' => 'entero', 'label' => 'ID'],
        'legajo' => ['column' => 'empleado_sueldos.legajo', 'type' => 'entero', 'label' => 'Legajo'],
        'nombre' => ['column' => 'empleado_sueldos.nombre', 'type' => 'texto', 'label' => 'Nombre'],
        'cuil' => ['column' => 'empleado_sueldos.cuil', 'type' => 'texto', 'label' => 'CUIL'],
        'documento' => ['column' => 'empleado_sueldos.documento', 'type' => 'texto', 'label' => 'Documento'],
        'estado' => ['column' => 'empleado_sueldos.estado', 'type' => 'texto', 'label' => 'Estado'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'empleado_sueldos.nombre',
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

    private const CAMPO_DEFAULT = 'nombre';

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$estado, $empresaId, $empresaScope] = self::resolverExterno($request, $empresaDefault);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'estado' => $estado,
                'empresa_id' => $empresaId,
                'empresa_scope' => $empresaScope,
            ]);
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

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'estado' => $estado,
        ];
    }

    /**
     * Filtros externos especiales del index: estado (default Activo) y empresa (default primera asignada).
     * Marcadores para expandir: filtro_estado=TODOS y empresa_todas=1.
     *
     * @return array{0:string,1:?int,2:string}  [estado, empresa_id, empresa_scope]
     */
    private static function resolverExterno(Request $request, ?int $empresaDefault): array
    {
        $estadoInput = $request->input('filtro_estado', null);
        if ($estadoInput === 'TODOS' || $request->boolean('estado_todos')) {
            $estado = '';
        } elseif (in_array($estadoInput, [EmpleadoEstados::PROVISORIO, EmpleadoEstados::ACTIVO, EmpleadoEstados::BAJA], true)) {
            $estado = (string) $estadoInput;
        } else {
            $estado = EmpleadoEstados::ACTIVO;
        }

        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            return [$estado, null, 'todas'];
        }
        if ($request->filled('empresa_id')) {
            return [$estado, (int) $request->input('empresa_id'), 'una'];
        }
        if ($empresaDefault !== null && $empresaDefault > 0) {
            return [$estado, $empresaDefault, 'una'];
        }

        return [$estado, null, 'todas'];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            return true;
        }
        if (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            return true;
        }
        if (($filtros['estado'] ?? EmpleadoEstados::ACTIVO) !== EmpleadoEstados::ACTIVO) {
            return true;
        }
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
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
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'estado' => EmpleadoEstados::ACTIVO,
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
        $estado = $filtros['estado'] ?? EmpleadoEstados::ACTIVO;
        if ($estado === '') {
            $params['filtro_estado'] = 'TODOS';
        } elseif ($estado !== EmpleadoEstados::ACTIVO) {
            $params['filtro_estado'] = $estado;
        }
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            $params['empresa_todas'] = 1;
        } elseif (! empty($filtros['empresa_id'])) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Empleado_Sueldos>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('empleado_sueldos.empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['estado'])) {
            $query->where('empleado_sueldos.estado', $filtros['estado']);
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = $filtros['operador'] ?? 'contiene';
        if ($valor === '' && $operador !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? self::CAMPO_DEFAULT, $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Empleado_Sueldos>  $query
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
                $q->orWhere('empleado_sueldos.id', (int) $id);
                $q->orWhere('empleado_sueldos.legajo', (int) $id);
            }
            $q->orWhere('empleado_sueldos.nombre', 'like', $like);
            $q->orWhere('empleado_sueldos.cuil', 'like', $like);
            $q->orWhere('empleado_sueldos.documento', 'like', $like);
            CoincidenciaFlexibleTexto::aplicar(
                $q,
                'empleado_sueldos.nombre',
                $valor,
                true,
                CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
            );
        });
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Empleado_Sueldos>  $query
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
     * @param  Builder<\App\Models\Sueldos\Empleado_Sueldos>  $query
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
                    $q->where($column, 'like', '%'.self::escapeLike($valor).'%');
                    if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                        CoincidenciaFlexibleTexto::aplicar(
                            $q,
                            $column,
                            $valor,
                            false,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                        );
                    }
                });
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Empleado_Sueldos>  $query
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
            case 'igual':
            default:
                $query->where($column, '=', $id);
                break;
        }
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
            default => self::OPERADORES_TEXTO,
        };
    }
}
