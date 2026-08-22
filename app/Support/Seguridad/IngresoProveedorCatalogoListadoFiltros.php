<?php

namespace App\Support\Seguridad;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class IngresoProveedorCatalogoListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'id', 'type' => 'entero', 'label' => 'ID'],
        'codigo' => ['column' => 'codigo', 'type' => 'texto', 'label' => 'Código'],
        'nombre' => ['column' => 'nombre', 'type' => 'texto', 'label' => 'Nombre'],
        'activo' => ['column' => 'activo', 'type' => 'texto', 'label' => 'Activo'],
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

    public static function resolverDesdeRequest(Request $request): array
    {
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return self::filtrosVacios();
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');
        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }
        $campo = (string) $request->input('filtro_campo', 'nombre');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'nombre';
        }
        $operador = (string) $request->input('filtro_operador', 'contiene');
        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }
        $permitidos = ($campo === 'id' && $modo === self::MODO_CAMPO)
            ? array_keys(self::OPERADORES_ENTERO)
            : array_keys(self::OPERADORES_TEXTO);
        if (! in_array($operador, $permitidos, true)) {
            $operador = $permitidos[0];
        }

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

    public static function tieneCriteriosTexto(array $filtros): bool
    {
        return self::tieneCriteriosAplicados($filtros);
    }

    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'nombre',
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
            $params['filtro_campo'] = $filtros['campo'] ?? 'nombre';
            $params['filtro_operador'] = $filtros['operador'] ?? 'contiene';
        }
        if (! empty($filtros['valor'])) {
            $params['filtro_valor'] = $filtros['valor'];
        }

        return $params;
    }

    public static function aplicar(Builder $query, array $filtros): void
    {
        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = $filtros['operador'] ?? 'contiene';
        if ($valor === '' && $operador !== 'vacio') {
            return;
        }

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            $campo = $filtros['campo'] ?? 'nombre';
            $col = self::CAMPOS[$campo]['column'] ?? 'nombre';
            $type = self::CAMPOS[$campo]['type'] ?? 'texto';
            if ($type === 'entero') {
                $id = filter_var($valor, FILTER_VALIDATE_INT);
                if ($id === false) {
                    return;
                }
                $id = (int) $id;
                match ($operador) {
                    'mayor' => $query->where($col, '>', $id),
                    'menor' => $query->where($col, '<', $id),
                    default => $query->where($col, '=', $id),
                };

                return;
            }
            self::aplicarTexto($query, $col, $operador, $valor);

            return;
        }

        $query->where(function ($q) use ($valor) {
            $id = filter_var($valor, FILTER_VALIDATE_INT);
            if ($id !== false) {
                $q->orWhere('id', (int) $id);
            }
            foreach (['codigo', 'nombre'] as $col) {
                $q->orWhere($col, 'like', '%'.addcslashes($valor, '%_\\').'%');
                CoincidenciaFlexibleTexto::aplicar(
                    $q,
                    $col,
                    $valor,
                    true,
                    CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                );
            }
        });
    }

    private static function aplicarTexto(Builder $query, string $column, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }
        $esc = addcslashes($valor, '%_\\');
        match ($operador) {
            'empieza' => $query->where($column, 'like', $esc.'%'),
            'termina' => $query->where($column, 'like', '%'.$esc),
            'igual' => $query->where($column, '=', $valor),
            'distinto' => $query->where($column, '!=', $valor),
            default => $query->where($column, 'like', '%'.$esc.'%'),
        };
    }

    /** @return array<string, string> */
    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';

        return $type === 'entero' ? self::OPERADORES_ENTERO : self::OPERADORES_TEXTO;
    }
}
