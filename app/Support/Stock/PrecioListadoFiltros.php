<?php

namespace App\Support\Stock;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de precios de venta (index / exportaciones).
 */
class PrecioListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'precio.id', 'type' => 'entero', 'label' => 'ID'],
        'sku' => ['column' => 'articulo.sku', 'type' => 'texto', 'label' => 'SKU'],
        'articulo' => ['column' => 'articulo.descripcion', 'type' => 'texto', 'label' => 'Descripción artículo'],
        'categoria' => ['column' => 'categoria.nombre', 'type' => 'texto', 'label' => 'Categoría'],
        'listaprecio' => ['column' => 'listaprecio.nombre', 'type' => 'texto', 'label' => 'Lista de precios'],
        'fechavigencia' => ['column' => 'precio.fechavigencia', 'type' => 'fecha', 'label' => 'Fecha vigencia'],
        'moneda' => ['column' => 'moneda.nombre', 'type' => 'texto', 'label' => 'Moneda'],
        'precio' => ['column' => 'precio.precio', 'type' => 'decimal', 'label' => 'Precio'],
        'precioanterior' => ['column' => 'precio.precioanterior', 'type' => 'decimal', 'label' => 'Precio anterior'],
        'usuario' => ['column' => 'usuario.nombre', 'type' => 'texto', 'label' => 'Usuario'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'articulo.sku',
        'articulo.descripcion',
        'articulo.detalle',
        'categoria.nombre',
        'listaprecio.nombre',
        'moneda.nombre',
        'usuario.nombre',
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

        $campo = (string) $request->input('filtro_campo', 'sku');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'sku';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'sku');

        $fechaVigencia = $request->filled('fecha_vigencia')
            ? Carbon::parse($request->fecha_vigencia)->format('Y-m-d')
            : Carbon::today()->format('Y-m-d');

        $listaprecioId = $request->input('listaprecio_id');
        if ($listaprecioId !== null && $listaprecioId !== '') {
            $listaprecioId = (int) $listaprecioId;
        } else {
            $listaprecioId = null;
        }

        $ocultarPrecioCero = $request->has('ocultar_precio_cero')
            ? (int) $request->input('ocultar_precio_cero') === 1
            : true;

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'fecha_vigencia' => $fechaVigencia,
            'listaprecio_id' => $listaprecioId,
            'ocultar_precio_cero' => $ocultarPrecioCero,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (! empty($filtros['listaprecio_id'])) {
            return true;
        }

        if (($filtros['ocultar_precio_cero'] ?? true) === false) {
            return true;
        }

        return self::tieneCriteriosInteligentesAplicados($filtros);
    }

    public static function tieneCriteriosInteligentesAplicados(array $filtros): bool
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

        if (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            return true;
        }

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO
            && trim((string) ($filtros['valor'] ?? '')) !== '') {
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
            'campo' => 'sku',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'fecha_vigencia' => Carbon::today()->format('Y-m-d'),
            'listaprecio_id' => null,
            'ocultar_precio_cero' => true,
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
            $params['filtro_campo'] = $filtros['campo'] ?? 'sku';
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

        $params['fecha_vigencia'] = $filtros['fecha_vigencia'] ?? Carbon::today()->format('Y-m-d');
        if (! empty($filtros['listaprecio_id'])) {
            $params['listaprecio_id'] = (int) $filtros['listaprecio_id'];
        }
        $params['ocultar_precio_cero'] = ($filtros['ocultar_precio_cero'] ?? true) ? 1 : 0;

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Stock\Precio>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! self::tieneCriteriosInteligentesAplicados($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'sku', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Stock\Precio>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['articulo.sku', 'articulo.descripcion', 'listaprecio.nombre'] as $col) {
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
                $q->orWhere('precio.id', (int) $id);
            }

            $textCols = [
                'articulo.sku',
                'articulo.descripcion',
                'articulo.detalle',
                'categoria.nombre',
                'listaprecio.nombre',
                'moneda.nombre',
                'usuario.nombre',
            ];

            foreach ($textCols as $col) {
                $q->orWhere($col, 'like', $like);
                if ($operador === 'contiene' && self::usaCoincidenciaFlexibleEnColumna($col)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $col,
                        $valor,
                        true,
                        self::longitudMinimaFlexible($col)
                    );
                }
            }

            if (is_numeric(str_replace(',', '.', $valor))) {
                $num = (float) str_replace(',', '.', $valor);
                $q->orWhere('precio.precio', '=', $num);
                $q->orWhere('precio.precioanterior', '=', $num);
            }
        });
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    private static function longitudMinimaFlexible(string $column): int
    {
        if (in_array($column, ['articulo.sku', 'articulo.descripcion', 'articulo.detalle'], true)) {
            return CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO;
        }

        return CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT;
    }

    /**
     * @param  Builder<\App\Models\Stock\Precio>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        if ($campoKey === 'articulo') {
            self::aplicarTextoArticulo($query, $operador, $valor);

            return;
        }

        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['sku'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($type === 'decimal') {
            self::aplicarDecimal($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($type === 'fecha') {
            self::aplicarFechaColumna($query, (string) $def['column'], $operador, $valor, $valorHasta);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Stock\Precio>  $query
     */
    private static function aplicarTextoArticulo(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                $q->where(function ($w) {
                    $w->whereNull('articulo.descripcion')->orWhere('articulo.descripcion', '');
                })->where(function ($w) {
                    $w->whereNull('articulo.detalle')->orWhere('articulo.detalle', '');
                });
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        $query->where(function ($q) use ($operador, $valor) {
            foreach (['articulo.descripcion', 'articulo.detalle', 'articulo.sku'] as $col) {
                self::aplicarTextoEnColumna($q, $col, $operador, $valor, true);
            }
        });
    }

    /**
     * @param  Builder<\App\Models\Stock\Precio>  $query
     */
    private static function aplicarTexto(Builder $query, string $column, string $operador, string $valor): void
    {
        $query->where(function ($q) use ($column, $operador, $valor) {
            self::aplicarTextoEnColumna($q, $column, $operador, $valor, false);
        });
    }

    /**
     * @param  Builder<\App\Models\Stock\Precio>  $query
     */
    private static function aplicarTextoEnColumna(Builder $query, string $column, string $operador, string $valor, bool $orWhere): void
    {
        $callback = function ($q) use ($column, $operador, $valor) {
            if ($operador === 'vacio') {
                $q->where(function ($w) use ($column) {
                    $w->whereNull($column)->orWhere($column, '');
                });

                return;
            }

            if ($valor === '') {
                return;
            }

            switch ($operador) {
                case 'empieza':
                    $q->where($column, 'like', self::escapeLike($valor).'%');
                    break;
                case 'termina':
                    $q->where($column, 'like', '%'.self::escapeLike($valor));
                    break;
                case 'igual':
                    $q->where($column, '=', $valor);
                    break;
                case 'distinto':
                    $q->where($column, '!=', $valor);
                    break;
                case 'contiene':
                default:
                    $like = '%'.self::escapeLike($valor).'%';
                    $q->where($column, 'like', $like);
                    if (self::usaCoincidenciaFlexibleEnColumna($column)) {
                        CoincidenciaFlexibleTexto::aplicar(
                            $q,
                            $column,
                            $valor,
                            false,
                            self::longitudMinimaFlexible($column)
                        );
                    }
                    break;
            }
        };

        if ($orWhere) {
            $query->orWhere($callback);
        } else {
            $callback($query);
        }
    }

    /**
     * @param  Builder<\App\Models\Stock\Precio>  $query
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

    /**
     * @param  Builder<\App\Models\Stock\Precio>  $query
     */
    private static function aplicarDecimal(Builder $query, string $column, string $operador, string $valor): void
    {
        $normalizado = str_replace(',', '.', trim($valor));
        if (! is_numeric($normalizado)) {
            return;
        }
        $num = (float) $normalizado;
        switch ($operador) {
            case 'mayor':
                $query->where($column, '>', $num);
                break;
            case 'menor':
                $query->where($column, '<', $num);
                break;
            case 'igual':
            default:
                $query->where($column, '=', $num);
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Stock\Precio>  $query
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
            case 'igual':
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
            'decimal' => array_keys(self::OPERADORES_DECIMAL),
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
            'decimal' => self::OPERADORES_DECIMAL,
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };
    }
}
