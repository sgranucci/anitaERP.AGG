<?php

namespace App\Support\Ventas;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros inteligentes del index de remitos (El Bierzo / AGG).
 */
class RemitoListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'remito.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo' => ['column' => 'remito.codigo', 'type' => 'texto', 'label' => 'Código'],
        'cliente' => ['column' => 'cliente.nombre', 'type' => 'texto', 'label' => 'Cliente'],
        'cliente_codigo' => ['column' => 'cliente.codigo', 'type' => 'texto', 'label' => 'Cód. cliente'],
        'vendedor' => ['column' => 'vendedor.nombre', 'type' => 'texto', 'label' => 'Vendedor'],
        'transporte' => ['column' => 'transporte.nombre', 'type' => 'texto', 'label' => 'Reparto'],
        'estadoremito' => ['column' => 'remito.estadoremito', 'type' => 'estado', 'label' => 'Estado'],
        'fecha' => ['column' => 'remito.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
        'lugarentrega' => ['column' => 'remito.lugarentrega', 'type' => 'texto', 'label' => 'Lugar entrega'],
        'leyenda' => ['column' => 'remito.leyenda', 'type' => 'texto', 'label' => 'Leyenda'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'remito.codigo',
        'remito.lugarentrega',
        'remito.leyenda',
        'cliente.nombre',
        'vendedor.nombre',
        'transporte.nombre',
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

    /** @var array<string, string> */
    public const OPERADORES_ESTADO = [
        'igual' => 'Igual a',
        'distinto' => 'Distinto de',
        'contiene' => 'Contiene',
        'vacio' => 'Vacío',
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

        return array_merge([
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
        ], ListadoRepartoFechaEntregaSupport::resolverDesdeRequest($request));
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (ListadoRepartoFechaEntregaSupport::tieneCriteriosNoDefault($filtros)) {
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
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return array_merge([
            'modo' => self::MODO_TODOS,
            'campo' => 'codigo',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
        ], ListadoRepartoFechaEntregaSupport::vaciosConHoy());
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

        return array_merge($params, ListadoRepartoFechaEntregaSupport::paraQueryString($filtros));
    }

    /**
     * @param  Builder<\App\Models\Ventas\Remito>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        ListadoRepartoFechaEntregaSupport::aplicar($query, $filtros, 'remito.fechaentrega');

        if (! self::tieneCriteriosInteligentes($filtros)) {
            return;
        }

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

    private static function tieneCriteriosInteligentes(array $filtros): bool
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
     * @param  Builder<\App\Models\Ventas\Remito>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['remito.codigo', 'remito.lugarentrega'] as $col) {
                    $q->orWhere(function ($w) use ($col) {
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
        $estadoCodigo = self::resolverCodigoEstado($valor);

        $query->where(function ($q) use ($valor, $like, $id, $operador, $estadoCodigo) {
            if ($id !== false) {
                $q->orWhere('remito.id', (int) $id);
            }
            if ($estadoCodigo !== null) {
                $q->orWhere('remito.estadoremito', $estadoCodigo);
            }
            foreach (['remito.codigo', 'remito.lugarentrega', 'remito.leyenda', 'remito.estadoremito'] as $col) {
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
            $q->orWhereHas('clientes', function ($c) use ($like, $valor, $operador) {
                $c->where(function ($w) use ($like, $valor, $operador) {
                    $w->where('codigo', 'like', $like)->orWhere('nombre', 'like', $like);
                    if ($operador === 'contiene') {
                        CoincidenciaFlexibleTexto::aplicar(
                            $w,
                            'nombre',
                            $valor,
                            true,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                        );
                    }
                });
            });
            $q->orWhereHas('vendedores', function ($v) use ($like, $valor, $operador) {
                $v->where('nombre', 'like', $like);
                if ($operador === 'contiene') {
                    CoincidenciaFlexibleTexto::aplicar(
                        $v,
                        'nombre',
                        $valor,
                        false,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            });
            $q->orWhereHas('transportes', function ($t) use ($like) {
                $t->where('nombre', 'like', $like)->orWhere('codigo', 'like', $like);
            });
        });
    }

    /**
     * @param  Builder<\App\Models\Ventas\Remito>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['codigo'];
        $type = $def['type'];
        $column = (string) $def['column'];

        if ($type === 'entero') {
            self::aplicarEntero($query, 'remito.id', $operador, $valor);

            return;
        }

        if ($type === 'fecha') {
            $colLocal = 'remito.fecha';
            self::aplicarFechaColumna($query, $colLocal, $operador, $valor, $valorHasta);

            return;
        }

        if ($type === 'estado') {
            self::aplicarEstado($query, $operador, $valor);

            return;
        }

        if ($campoKey === 'cliente' || $campoKey === 'cliente_codigo') {
            $attr = $campoKey === 'cliente_codigo' ? 'codigo' : 'nombre';
            if ($operador === 'vacio') {
                $query->where(function ($q) {
                    $q->whereNull('cliente_id')->orWhereDoesntHave('clientes');
                });

                return;
            }
            $query->whereHas('clientes', function ($c) use ($attr, $operador, $valor) {
                self::aplicarTexto($c, $attr, $operador, $valor);
            });

            return;
        }

        if ($campoKey === 'vendedor') {
            if ($operador === 'vacio') {
                $query->where(function ($q) {
                    $q->whereNull('vendedor_id')->orWhereDoesntHave('vendedores');
                });

                return;
            }
            $query->whereHas('vendedores', function ($v) use ($operador, $valor) {
                self::aplicarTexto($v, 'nombre', $operador, $valor);
            });

            return;
        }

        if ($campoKey === 'transporte') {
            if ($operador === 'vacio') {
                $query->where(function ($q) {
                    $q->whereNull('transporte_id')->orWhereDoesntHave('transportes');
                });

                return;
            }
            $query->whereHas('transportes', function ($t) use ($operador, $valor) {
                self::aplicarTexto($t, 'nombre', $operador, $valor);
            });

            return;
        }

        $colLocal = match ($campoKey) {
            'lugarentrega' => 'remito.lugarentrega',
            'leyenda' => 'remito.leyenda',
            default => 'remito.codigo',
        };
        self::aplicarTexto($query, $colLocal, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Ventas\Remito>  $query
     */
    private static function aplicarEstado(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                $q->whereNull('remito.estadoremito')->orWhere('remito.estadoremito', '');
            });

            return;
        }
        if ($valor === '') {
            return;
        }

        $codigo = self::resolverCodigoEstado($valor) ?? trim($valor);
        if ($operador === 'distinto') {
            $query->where('remito.estadoremito', '!=', $codigo);

            return;
        }
        if ($operador === 'contiene') {
            $query->where(function ($q) use ($valor, $codigo) {
                $q->where('remito.estadoremito', 'like', '%'.self::escapeLike($valor).'%');
                if ($codigo !== $valor) {
                    $q->orWhere('remito.estadoremito', $codigo);
                }
            });

            return;
        }

        $query->where('remito.estadoremito', $codigo);
    }

    /** @return array<string, string> valor almacenado => etiqueta */
    public static function etiquetasEstado(): array
    {
        return [
            'Pendiente' => 'Pendiente',
            'Facturado' => 'Facturado',
            'Suspendido' => 'Suspendido',
        ];
    }

    private static function resolverCodigoEstado(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        $etiquetas = self::etiquetasEstado();
        if (isset($etiquetas[$valor])) {
            return $valor;
        }
        $norm = mb_strtolower($valor);
        foreach ($etiquetas as $cod => $lbl) {
            if (mb_strtolower($lbl) === $norm || str_contains(mb_strtolower($lbl), $norm)) {
                return (string) $cod;
            }
        }

        return null;
    }

    /**
     * @param  Builder<\App\Models\Ventas\Remito>  $query
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
     * @param  Builder<\App\Models\Ventas\Remito>  $query
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
     * @param  Builder<\App\Models\Ventas\Remito>  $query
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
            'fecha' => array_keys(self::OPERADORES_FECHA),
            'estado' => array_keys(self::OPERADORES_ESTADO),
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
            'estado' => self::OPERADORES_ESTADO,
            default => self::OPERADORES_TEXTO,
        };
    }

    public static function subtituloFiltros(array $filtros): string
    {
        $partes = [];
        $ext = ListadoRepartoFechaEntregaSupport::subtitulo($filtros);
        if ($ext !== '') {
            $partes[] = $ext;
        }

        if (self::tieneCriteriosInteligentes($filtros)) {
            $modo = $filtros['modo'] ?? self::MODO_TODOS;
            if ($modo === self::MODO_CAMPO) {
                $campo = $filtros['campo'] ?? 'codigo';
                $partes[] = 'Campo: '.(self::CAMPOS[$campo]['label'] ?? $campo);
            } else {
                $partes[] = 'Cualquier campo';
            }
            $partes[] = 'Condición: '.($filtros['operador'] ?? 'contiene');
            if (trim((string) ($filtros['valor'] ?? '')) !== '') {
                $partes[] = 'Valor: '.$filtros['valor'];
            }
            if (trim((string) ($filtros['valor_hasta'] ?? '')) !== '') {
                $partes[] = 'Hasta: '.$filtros['valor_hasta'];
            }
        }

        return implode(' · ', $partes);
    }
}
