<?php

namespace App\Support\Caja;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de cajas físicas (index).
 * Chip externo: sala (Crown) — vacío / todas = sin filtrar por sala.
 */
class CajaListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const PREFERENCIAS_CLAVE = 'caja_fisica_listado';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'caja.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo' => ['column' => 'caja.codigo', 'type' => 'texto', 'label' => 'Código'],
        'nombre' => ['column' => 'caja.nombre', 'type' => 'texto', 'label' => 'Nombre'],
        'sala' => ['column' => 'sala.nombre', 'type' => 'texto', 'label' => 'Sala'],
        'sala_codigo' => ['column' => 'sala.codigo', 'type' => 'texto', 'label' => 'Código sala'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'caja.nombre',
        'sala.nombre',
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

    /**
     * @param  list<int>  $salaIdsPermitidas
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(
        Request $request,
        ?string $busquedaRuta = null,
        array $salaIdsPermitidas = [],
        ?int $salaDefault = null,
    ): array {
        [$salaId, $salaScope] = self::resolverSalaExterna($request, $salaIdsPermitidas, $salaDefault);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'sala_id' => $salaId,
                'sala_scope' => $salaScope,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
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

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'nombre');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'sala_id' => $salaId,
            'sala_scope' => $salaScope,
        ];
    }

    /**
     * @param  list<int>  $salaIdsPermitidas
     * @return array{0:?int,1:string}
     */
    private static function resolverSalaExterna(
        Request $request,
        array $salaIdsPermitidas,
        ?int $salaDefault,
    ): array {
        if ($request->boolean('sala_todas') || $request->input('sala_scope') === 'todas') {
            return [null, 'todas'];
        }
        if ($request->boolean('sala_sin') || $request->input('sala_scope') === 'sin') {
            return [null, 'sin'];
        }
        if ($request->filled('sala_id')) {
            $id = (int) $request->input('sala_id');
            if ($id > 0 && ($salaIdsPermitidas === [] || in_array($id, $salaIdsPermitidas, true))) {
                return [$id, 'una'];
            }
        }
        if ($salaDefault !== null && $salaDefault > 0) {
            if ($salaIdsPermitidas === [] || in_array($salaDefault, $salaIdsPermitidas, true)) {
                return [$salaDefault, 'una'];
            }
        }

        return [null, 'todas'];
    }

    public static function tieneCriteriosTexto(array $filtros): bool
    {
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

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosTexto($filtros);
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'nombre',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'sala_id' => null,
            'sala_scope' => 'todas',
        ];
    }

    /**
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringSala($filtros);

        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'nombre';
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
     * @return array<string, int|string>
     */
    public static function paraQueryStringSala(array $filtros): array
    {
        $scope = $filtros['sala_scope'] ?? 'todas';
        if ($scope === 'todas') {
            return ['sala_todas' => 1];
        }
        if ($scope === 'sin') {
            return ['sala_sin' => 1];
        }
        if (! empty($filtros['sala_id'])) {
            return ['sala_id' => (int) $filtros['sala_id']];
        }

        return ['sala_todas' => 1];
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $scope = $filtros['sala_scope'] ?? 'todas';
        if ($scope === 'sin') {
            $query->whereNull('caja.sala_id');
        } elseif ($scope === 'una' && ! empty($filtros['sala_id'])) {
            $query->where('caja.sala_id', (int) $filtros['sala_id']);
        }

        if (! self::tieneCriteriosTexto($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'nombre', $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['caja.nombre', 'caja.codigo'] as $col) {
                    $q->where(function ($w) use ($col) {
                        $w->whereNull($col)->orWhere($col, '');
                    });
                }
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $id, $operador) {
            if ($id !== false) {
                $q->orWhere('caja.id', (int) $id);
            }
            foreach (['caja.nombre', 'caja.codigo', 'sala.nombre', 'sala.codigo'] as $col) {
                $q->orWhere($col, 'like', $like);
                if ($operador === 'contiene' && self::usaCoincidenciaFlexibleEnColumna($col)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $col,
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            }
        });
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['nombre'];
        if (($def['type'] ?? '') === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja>  $query
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
                    if (self::usaCoincidenciaFlexibleEnColumna($column)) {
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
     * @param  Builder<\App\Models\Caja\Caja>  $query
     */
    private static function aplicarEntero(Builder $query, string $column, string $operador, string $valor): void
    {
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false) {
            return;
        }
        $id = (int) $id;
        match ($operador) {
            'mayor' => $query->where($column, '>', $id),
            'menor' => $query->where($column, '<', $id),
            default => $query->where($column, '=', $id),
        };
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
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
