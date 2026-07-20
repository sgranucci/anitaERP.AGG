<?php

namespace App\Support\Caja\Flash;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FlashParametroListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'flash_parametro.id', 'type' => 'entero', 'label' => 'ID'],
        'periodo' => ['column' => 'flash_parametro.periodo', 'type' => 'texto', 'label' => 'Período'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'budget_total' => ['column' => 'flash_parametro.budget_total', 'type' => 'decimal', 'label' => 'Budget total'],
        'budget_slot' => ['column' => 'flash_parametro.budget_slot', 'type' => 'decimal', 'label' => 'Budget slots'],
        'budget_bingo' => ['column' => 'flash_parametro.budget_bingo', 'type' => 'decimal', 'label' => 'Budget bingo'],
        'budget_estac' => ['column' => 'flash_parametro.budget_estac', 'type' => 'decimal', 'label' => 'Budget estac.'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'empresa.nombre',
        'flash_parametro.periodo',
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
    public const OPERADORES_DECIMAL = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
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

        $campo = (string) $request->input('filtro_campo', 'periodo');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'periodo';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');
        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'periodo');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'empresas_asignadas' => [],
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
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

    /** @return array<string, mixed> */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'periodo',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => 0,
            'empresas_asignadas' => [],
        ];
    }

    /** @return array<string, string|int|bool> */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'periodo';
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
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Caja\Flash\FlashParametro>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarScopeEmpresasAsignadas(Builder $query, array $filtros): void
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('flash_parametro.empresa_id', $empresaId);

            return;
        }

        $asignadas = array_values(array_filter(
            array_map('intval', (array) ($filtros['empresas_asignadas'] ?? [])),
            fn (int $id) => $id > 0,
        ));

        if ($asignadas === []) {
            return;
        }

        $query->whereIn('flash_parametro.empresa_id', $asignadas);
    }

    /**
     * @param  Builder<\App\Models\Caja\Flash\FlashParametro>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'periodo', $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Flash\FlashParametro>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                $q->whereNull('flash_parametro.periodo')->orWhere('flash_parametro.periodo', '');
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        $periodo = self::normalizarPeriodoBusqueda($valor);
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $id, $operador, $periodo) {
            if ($id !== false) {
                $q->orWhere('flash_parametro.id', (int) $id);
            }
            if ($periodo !== null) {
                $q->orWhere('flash_parametro.periodo', $periodo);
            }
            foreach (['empresa.nombre', 'flash_parametro.periodo'] as $col) {
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
     * @param  Builder<\App\Models\Caja\Flash\FlashParametro>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campo, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campo] ?? self::CAMPOS['periodo'];
        $column = $def['column'];
        $type = $def['type'];

        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        if ($campo === 'periodo') {
            $periodo = self::normalizarPeriodoBusqueda($valor) ?? $valor;
            match ($operador) {
                'mayor' => $query->where($column, '>', $periodo),
                'menor' => $query->where($column, '<', $periodo),
                'distinto' => $query->where($column, '!=', $periodo),
                'empieza' => $query->where($column, 'like', $periodo.'%'),
                'termina' => $query->where($column, 'like', '%'.$periodo),
                'contiene' => $query->where($column, 'like', '%'.$periodo.'%'),
                default => $query->where($column, '=', $periodo),
            };

            return;
        }

        match ($type) {
            'entero' => self::aplicarEntero($query, $column, $operador, $valor),
            'decimal' => self::aplicarDecimal($query, $column, $operador, $valor),
            default => self::aplicarTexto($query, $column, $operador, $valor),
        };
    }

    /** @param  Builder<\App\Models\Caja\Flash\FlashParametro>  $query */
    private static function aplicarTexto(Builder $query, string $column, string $operador, string $valor): void
    {
        $like = self::patronLike($operador, $valor);
        $query->where(function ($q) use ($column, $like, $operador, $valor) {
            if ($operador === 'distinto') {
                $q->where($column, 'not like', $like);
            } else {
                $q->where($column, 'like', $like);
            }
            if ($operador === 'contiene' && self::usaCoincidenciaFlexibleEnColumna($column)) {
                CoincidenciaFlexibleTexto::aplicar(
                    $q,
                    $column,
                    $valor,
                    $operador !== 'distinto',
                    CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                );
            }
        });
    }

    /** @param  Builder<\App\Models\Caja\Flash\FlashParametro>  $query */
    private static function aplicarEntero(Builder $query, string $column, string $operador, string $valor): void
    {
        $num = filter_var($valor, FILTER_VALIDATE_INT);
        if ($num === false) {
            return;
        }
        match ($operador) {
            'mayor' => $query->where($column, '>', $num),
            'menor' => $query->where($column, '<', $num),
            'distinto' => $query->where($column, '!=', $num),
            default => $query->where($column, '=', $num),
        };
    }

    /** @param  Builder<\App\Models\Caja\Flash\FlashParametro>  $query */
    private static function aplicarDecimal(Builder $query, string $column, string $operador, string $valor): void
    {
        $num = (float) str_replace(',', '.', trim($valor));
        match ($operador) {
            'mayor' => $query->where($column, '>', $num),
            'menor' => $query->where($column, '<', $num),
            'distinto' => $query->where($column, '!=', $num),
            default => $query->where($column, '=', $num),
        };
    }

    private static function normalizarPeriodoBusqueda(string $valor): ?string
    {
        $valor = trim($valor);
        if (preg_match('/^\d{6}$/', $valor)) {
            return $valor;
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $valor, $m)) {
            return $m[1].$m[2];
        }
        if (preg_match('/^(\d{2})\/(\d{4})$/', $valor, $m)) {
            return $m[2].$m[1];
        }

        return null;
    }

    private static function patronLike(string $operador, string $valor): string
    {
        return match ($operador) {
            'empieza' => $valor.'%',
            'termina' => '%'.$valor,
            'igual', 'distinto' => $valor,
            default => '%'.$valor.'%',
        };
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    public static function operadoresParaCampo(string $campo): array
    {
        $type = self::CAMPOS[$campo]['type'] ?? 'texto';

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'decimal' => self::OPERADORES_DECIMAL,
            default => self::OPERADORES_TEXTO,
        };
    }

    private static function normalizarOperador(string $operador, string $campo): string
    {
        $ops = self::operadoresParaCampo($campo);

        return isset($ops[$operador]) ? $operador : array_key_first($ops);
    }
}
