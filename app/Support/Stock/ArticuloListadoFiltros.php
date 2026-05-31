<?php

namespace App\Support\Stock;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de artículos (index / exportaciones).
 */
class ArticuloListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'articulo.id', 'type' => 'entero', 'label' => 'ID'],
        'sku' => ['column' => 'articulo.sku', 'type' => 'texto', 'label' => 'SKU'],
        'codigobarra' => ['column' => 'articulo.codigobarra', 'type' => 'texto', 'label' => 'Cód. barra'],
        'descripcion' => ['column' => 'articulo.descripcion', 'type' => 'texto', 'label' => 'Descripción'],
        'unidadmedida' => ['column' => 'unidadmedida.nombre', 'type' => 'texto', 'label' => 'Unidad de medida'],
        'categoria' => ['column' => 'categoria.nombre', 'type' => 'texto', 'label' => 'Categoría'],
        'tipoarticulo' => ['column' => 'tipoarticulo.nombre', 'type' => 'texto', 'label' => 'Tipo de artículo'],
        'usoarticulo' => ['column' => 'usoarticulo.nombre', 'type' => 'texto', 'label' => 'Uso'],
        'numeroparte' => ['column' => 'articulo.numeroparte', 'type' => 'texto', 'label' => 'Nro. parte'],
        'ubicacionparte' => ['column' => 'articulo.ubicacionparte', 'type' => 'texto', 'label' => 'Ubic. parte'],
        'nofactura' => ['column' => 'articulo.nofactura', 'type' => 'texto', 'label' => 'Facturable (0/1)'],
        'estado' => ['column' => 'articulo.estado', 'type' => 'texto', 'label' => 'Estado'],
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

    /**
     * Columnas donde aplica coincidencia flexible (tolerancia a errores de tipeo).
     *
     * @var list<string>
     */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'articulo.sku',
        'articulo.codigobarra',
        'articulo.descripcion',
        'unidadmedida.nombre',
        'articulo.ubicacionparte',
        'articulo.numeroparte',
        'categoria.nombre',
        'tipoarticulo.nombre',
        'usoarticulo.nombre',
    ];

    /** @var array<string, string> */
    public const OPERADORES_ENTERO = [
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

        $campo = (string) $request->input('filtro_campo', 'descripcion');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'descripcion';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'descripcion');

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
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, busqueda: string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'descripcion',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
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
            $params['filtro_campo'] = $filtros['campo'] ?? 'descripcion';
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
     * @param  Builder<\App\Models\Stock\Articulo>  $query
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
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'descripcion', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['articulo.sku', 'articulo.descripcion', 'articulo.estado'] as $col) {
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
                $q->orWhere('articulo.id', (int) $id);
            }
            $textCols = [
                'articulo.sku',
                'articulo.codigobarra',
                'articulo.descripcion',
                'unidadmedida.nombre',
                'articulo.ubicacionparte',
                'articulo.numeroparte',
                'categoria.nombre',
                'tipoarticulo.nombre',
                'usoarticulo.nombre',
                'articulo.nofactura',
                'articulo.estado',
            ];
            foreach ($textCols as $col) {
                $q->orWhereRaw(self::expresionTextoMinusculas($col).' LIKE ?', [self::normalizarTextoBusqueda($like)]);
                if ($operador === 'contiene' && self::usaCoincidenciaFlexibleEnColumna($col)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $col,
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
                    );
                }
            }
        });
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['descripcion'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
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
                self::whereTextoInsensitive($query, $column, self::escapeLike($valor).'%');
                break;
            case 'termina':
                self::whereTextoInsensitive($query, $column, '%'.self::escapeLike($valor));
                break;
            case 'igual':
                $query->whereRaw(self::expresionTextoMinusculas($column).' = ?', [self::normalizarTextoBusqueda($valor)]);
                break;
            case 'distinto':
                $query->whereRaw(self::expresionTextoMinusculas($column).' != ?', [self::normalizarTextoBusqueda($valor)]);
                break;
            case 'contiene':
            default:
                $query->where(function ($q) use ($column, $valor) {
                    self::whereTextoInsensitive($q, $column, '%'.self::escapeLike($valor).'%');
                    if (self::usaCoincidenciaFlexibleEnColumna($column)) {
                        CoincidenciaFlexibleTexto::aplicar(
                            $q,
                            $column,
                            $valor,
                            true,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
                        );
                    }
                });
                break;
        }
    }

    /**
     * LIKE insensible a mayúsculas (ej. «lab» coincide con SKU «LAB001»).
     *
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    private static function whereTextoInsensitive(Builder $query, string $column, string $patronLike): void
    {
        $query->whereRaw(self::expresionTextoMinusculas($column).' LIKE ?', [self::normalizarTextoBusqueda($patronLike)]);
    }

    private static function expresionTextoMinusculas(string $column): string
    {
        return 'LOWER('.$column.')';
    }

    private static function normalizarTextoBusqueda(string $valor): string
    {
        return mb_strtolower($valor, 'UTF-8');
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
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
