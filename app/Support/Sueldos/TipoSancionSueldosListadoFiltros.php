<?php

namespace App\Support\Sueldos;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TipoSancionSueldosListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'tipo_sancion_sueldos.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo' => ['column' => 'tipo_sancion_sueldos.codigo', 'type' => 'entero', 'label' => 'Código'],
        'nombre' => ['column' => 'tipo_sancion_sueldos.nombre', 'type' => 'texto', 'label' => 'Nombre'],
        'clase' => ['column' => 'tipo_sancion_sueldos.clase', 'type' => 'texto', 'label' => 'Clase'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'tipo_sancion_sueldos.nombre',
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

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
        ];
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

        return ($filtros['operador'] ?? 'contiene') !== 'contiene';
    }

    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => self::CAMPO_DEFAULT,
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
        ];
    }

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

        return $params;
    }

    public static function aplicar(Builder $query, array $filtros): void
    {
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

    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                $q->whereNull('tipo_sancion_sueldos.nombre')->orWhere('tipo_sancion_sueldos.nombre', '');
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
                $q->orWhere('tipo_sancion_sueldos.id', (int) $id);
                $q->orWhere('tipo_sancion_sueldos.codigo', (int) $id);
            }
            $q->orWhere('tipo_sancion_sueldos.nombre', 'like', $like);
            $q->orWhere('tipo_sancion_sueldos.clase', 'like', $like);
            CoincidenciaFlexibleTexto::aplicar(
                $q,
                'tipo_sancion_sueldos.nombre',
                $valor,
                true,
                CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
            );
        });
    }

    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS[self::CAMPO_DEFAULT];
        if ($def['type'] === 'entero') {
            $id = filter_var($valor, FILTER_VALIDATE_INT);
            if ($id === false) {
                return;
            }
            $id = (int) $id;
            match ($operador) {
                'mayor' => $query->where($def['column'], '>', $id),
                'menor' => $query->where($def['column'], '<', $id),
                default => $query->where($def['column'], '=', $id),
            };

            return;
        }
        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

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

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';
        $permitidos = $type === 'entero' ? array_keys(self::OPERADORES_ENTERO) : array_keys(self::OPERADORES_TEXTO);

        return in_array($operador, $permitidos, true) ? $operador : ($permitidos[0] ?? 'contiene');
    }

    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';

        return $type === 'entero' ? self::OPERADORES_ENTERO : self::OPERADORES_TEXTO;
    }
}
