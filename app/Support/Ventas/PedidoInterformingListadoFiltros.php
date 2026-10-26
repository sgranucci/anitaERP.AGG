<?php

namespace App\Support\Ventas;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoQbeSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros inteligentes del index de pedidos INTERFORMING.
 */
class PedidoInterformingListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'pedido.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo' => ['column' => 'pedido.codigo', 'type' => 'texto', 'label' => 'Código'],
        'orden_compra' => ['column' => 'pedido.orden_compra', 'type' => 'texto', 'label' => 'O. Compra'],
        'cliente' => ['column' => 'cliente.nombre', 'type' => 'texto', 'label' => 'Cliente'],
        'cliente_codigo' => ['column' => 'cliente.codigo', 'type' => 'texto', 'label' => 'Cód. cliente'],
        'vendedor' => ['column' => 'vendedor.nombre', 'type' => 'texto', 'label' => 'Vendedor'],
        'transporte' => ['column' => 'transporte.nombre', 'type' => 'texto', 'label' => 'Expreso'],
        'estadopedido' => ['column' => 'pedido.estadopedido', 'type' => 'estado', 'label' => 'Estado'],
        'fecha' => ['column' => 'pedido.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
        'fechaentrega' => ['column' => 'pedido.fechaentrega', 'type' => 'fecha', 'label' => 'Fecha entrega'],
        'lugarentrega' => ['column' => 'pedido.lugarentrega', 'type' => 'texto', 'label' => 'Lugar entrega'],
        'leyenda' => ['column' => 'pedido.leyenda', 'type' => 'texto', 'label' => 'Leyenda'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'pedido.codigo',
        'pedido.orden_compra',
        'pedido.lugarentrega',
        'pedido.leyenda',
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

    /** @var array<string, string> */
    public const OPERADORES_QBE_TEXTO = [
        'contiene' => 'Contiene',
        'no_contiene' => 'No contiene',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Es igual a',
        'distinto' => 'Distinto de',
        'mayor' => 'Mayor (A–Z)',
        'mayor_igual' => 'Mayor o igual (A–Z)',
        'menor' => 'Menor (A–Z)',
        'menor_igual' => 'Menor o igual (A–Z)',
        'entre' => 'Entre (A–Z)',
        'vacio' => 'Está vacío',
    ];

    /** @var array<string, string> */
    public const OPERADORES_QBE_ENTERO = [
        'igual' => 'Es igual a',
        'mayor' => 'Mayor que',
        'mayor_igual' => 'Mayor o igual',
        'menor' => 'Menor que',
        'menor_igual' => 'Menor o igual',
        'entre' => 'Entre',
        'vacio' => 'Está vacío',
    ];

    public const TIPO_TODOS = 'TODOS';

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
        $camposQbe = self::camposQbeDisponibles();

        $out = [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'tipo' => self::resolverTipo($request),
            'qbe' => ListadoQbeSupport::resolverDesdeRequest(
                $request,
                $camposQbe,
                static fn (string $op, string $campoQbe): string => self::normalizarOperadorQbe($op, $campoQbe)
            ),
            'sort' => ListadoOrdenamientoSupport::resolverDesdeRequest($request, self::camposOrdenables()),
            'agrupar' => ListadoAgrupacionSupport::resolverDesdeRequest($request, self::camposOrdenables()),
        ];
        if ($request->filled('vista_id')) {
            $out['vista_id'] = (int) $request->input('vista_id');
        }
        if ($request->boolean('vista_estandar') || $request->input('vista_modo') === 'estandar') {
            $out['vista_estandar'] = 1;
        }
        if ($request->boolean('filtro_limpiar') || $request->boolean('limpiar_filtros')) {
            $out['_limpiar'] = true;
        }

        return $out;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))) {
            return true;
        }

        return self::tieneCriteriosTexto($filtros);
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

    /**
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, busqueda: string}
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
            'tipo' => self::TIPO_TODOS,
            'qbe' => ListadoQbeSupport::vacio(),
            'sort' => [],
            'agrupar' => [],
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
        if (! empty($filtros['busqueda_rapida'])) {
            $params['filtro_busqueda_rapida'] = '1';
        }
        $tipo = strtoupper(trim((string) ($filtros['tipo'] ?? self::TIPO_TODOS)));
        if (in_array($tipo, [PedidoInterformingNumeracionSupport::TIPO_VENTA, PedidoInterformingNumeracionSupport::TIPO_EXPORTACION], true)) {
            $params['tipo'] = $tipo;
        }
        $params = array_merge($params, ListadoQbeSupport::paraQueryString((array) ($filtros['qbe'] ?? [])));
        $params = array_merge($params, ListadoOrdenamientoSupport::paraQueryString((array) ($filtros['sort'] ?? [])));
        $params = array_merge($params, ListadoAgrupacionSupport::paraQueryString((array) ($filtros['agrupar'] ?? [])));
        if (! empty($filtros['vista_id'])) {
            $params['vista_id'] = (int) $filtros['vista_id'];
        }
        if (! empty($filtros['vista_estandar'])) {
            $params['vista_estandar'] = 1;
        }
        if (! empty($filtros['columnas'])) {
            $params['columnas'] = (string) $filtros['columnas'];
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Ventas\PedidoInterforming>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        self::aplicarTipo($query, $filtros);
        if (ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))) {
            self::aplicarQbe($query, (array) $filtros['qbe']);
        }
        if (! self::tieneCriteriosTexto($filtros)) {
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

    /**
     * @param  Builder<\App\Models\Ventas\PedidoInterforming>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['pedido.codigo', 'pedido.orden_compra', 'pedido.lugarentrega'] as $col) {
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
                $q->orWhere('pedido.id', (int) $id);
            }
            if ($estadoCodigo !== null) {
                $q->orWhere('pedido.estadopedido', $estadoCodigo);
            }
            foreach (['pedido.codigo', 'pedido.orden_compra', 'pedido.lugarentrega', 'pedido.leyenda', 'pedido.estadopedido'] as $col) {
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
     * @param  Builder<\App\Models\Ventas\PedidoInterforming>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['codigo'];
        $type = $def['type'];
        $column = (string) $def['column'];

        if ($type === 'entero') {
            self::aplicarEntero($query, 'pedido.id', $operador, $valor);

            return;
        }

        if ($type === 'fecha') {
            $colLocal = $campoKey === 'fechaentrega' ? 'pedido.fechaentrega' : 'pedido.fecha';
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
                    $q->whereNull('pedido.cliente_id')->orWhereDoesntHave('clientes');
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
                    $q->whereNull('pedido.vendedor_id')->orWhereDoesntHave('vendedores');
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
                    $q->whereNull('pedido.transporte_id')->orWhereDoesntHave('transportes');
                });

                return;
            }
            $query->whereHas('transportes', function ($t) use ($operador, $valor) {
                self::aplicarTexto($t, 'nombre', $operador, $valor);
            });

            return;
        }

        $colLocal = match ($campoKey) {
            'orden_compra' => 'pedido.orden_compra',
            'lugarentrega' => 'pedido.lugarentrega',
            'leyenda' => 'pedido.leyenda',
            default => 'pedido.codigo',
        };
        self::aplicarTexto($query, $colLocal, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Ventas\PedidoInterforming>  $query
     */
    private static function aplicarEstado(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                $q->whereNull('pedido.estadopedido')->orWhere('pedido.estadopedido', '');
            });

            return;
        }
        if ($valor === '') {
            return;
        }

        $codigo = self::resolverCodigoEstado($valor) ?? trim($valor);
        if ($operador === 'distinto') {
            $query->where('pedido.estadopedido', '!=', $codigo);

            return;
        }
        if ($operador === 'contiene') {
            $query->where(function ($q) use ($valor, $codigo) {
                $q->where('pedido.estadopedido', 'like', '%'.self::escapeLike($valor).'%');
                if ($codigo !== $valor) {
                    $q->orWhere('pedido.estadopedido', $codigo);
                }
            });

            return;
        }

        $query->where('pedido.estadopedido', $codigo);
    }

    private static function resolverCodigoEstado(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        $etiquetas = PedidoEstadosInterforming::etiquetasCabecera();
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
     * @param  Builder<\App\Models\Ventas\PedidoInterforming>  $query
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
     * @param  Builder<\App\Models\Ventas\PedidoInterforming>  $query
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
     * @param  Builder<\App\Models\Ventas\PedidoInterforming>  $query
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
        $tipo = strtoupper(trim((string) ($filtros['tipo'] ?? self::TIPO_TODOS)));
        if ($tipo === PedidoInterformingNumeracionSupport::TIPO_VENTA) {
            $partes[] = 'Tipo: PED (venta)';
        } elseif ($tipo === PedidoInterformingNumeracionSupport::TIPO_EXPORTACION) {
            $partes[] = 'Tipo: PEX (exportación)';
        }
        if (ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))) {
            $partes[] = 'Consulta avanzada';
        }
        if (self::tieneCriteriosTexto($filtros)) {
            $modo = $filtros['modo'] ?? self::MODO_TODOS;
            if ($modo === self::MODO_CAMPO) {
                $campo = $filtros['campo'] ?? 'codigo';
                $partes[] = 'Campo: '.(self::CAMPOS[$campo]['label'] ?? $campo);
            } elseif (trim((string) ($filtros['valor'] ?? '')) !== '') {
                $partes[] = 'Texto: '.$filtros['valor'];
            }
        }

        return implode(' · ', $partes);
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposQbeDisponibles(): array
    {
        $out = [];
        foreach (PedidoInterformingListadoColumnas::camposFiltrables() as $key => $meta) {
            $out[$key] = [
                'column' => $meta['column'],
                'type' => $meta['type'],
                'label' => $meta['label'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return PedidoInterformingListadoColumnas::camposOrdenables();
    }

    /**
     * El chip PED/PEX no lo pisa una vista guardada.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $desdeVista
     * @return array<string, mixed>
     */
    public static function fusionarDesdeVista(array $base, array $desdeVista): array
    {
        $tipo = self::tipoNormalizado((string) ($base['tipo'] ?? self::TIPO_TODOS));
        $campos = self::camposOrdenables();
        $ordenVista = ListadoOrdenamientoSupport::normalizar($desdeVista['sort'] ?? $desdeVista['orden'] ?? [], $campos);
        $ordenBase = ListadoOrdenamientoSupport::normalizar($base['sort'] ?? [], $campos);
        $agruparVista = ListadoAgrupacionSupport::normalizar($desdeVista['agrupar'] ?? $desdeVista['group'] ?? [], $campos);
        $agruparBase = ListadoAgrupacionSupport::normalizar($base['agrupar'] ?? [], $campos);

        if (! empty($base['_limpiar'])) {
            unset($base['_limpiar'], $base['_qbe_explicito']);

            return array_merge($base, [
                'modo' => self::MODO_TODOS,
                'valor' => '',
                'valor_hasta' => '',
                'busqueda' => '',
                'tipo' => $tipo,
                'qbe' => ListadoQbeSupport::vacio(),
                'sort' => $ordenBase !== [] ? $ordenBase : $ordenVista,
                'agrupar' => $agruparBase !== [] ? $agruparBase : $agruparVista,
            ]);
        }

        $qbeExplicito = ! empty($base['_qbe_explicito']);
        unset($base['_qbe_explicito']);

        if ($qbeExplicito || self::tieneCriteriosTexto($base) || $ordenBase !== [] || $agruparBase !== []) {
            if ($ordenBase === [] && $ordenVista !== []) {
                $base['sort'] = $ordenVista;
            }
            if ($agruparBase === [] && $agruparVista !== []) {
                $base['agrupar'] = $agruparVista;
            }
            $base['tipo'] = $tipo;

            return $base;
        }

        $qbe = ListadoQbeSupport::normalizar(
            $desdeVista['qbe'] ?? [],
            self::camposQbeDisponibles(),
            static fn (string $op, string $campo): string => self::normalizarOperadorQbe($op, $campo)
        );

        return array_merge($base, [
            'modo' => (string) ($desdeVista['modo'] ?? self::MODO_TODOS),
            'qbe' => $qbe,
            'tipo' => $tipo,
            'sort' => $ordenVista,
            'agrupar' => $agruparVista,
        ]);
    }

    /**
     * @param  Builder<\App\Models\Ventas\PedidoInterforming>  $query
     */
    public static function aplicarTipo(Builder $query, array $filtros): void
    {
        $tipo = self::tipoNormalizado((string) ($filtros['tipo'] ?? self::TIPO_TODOS));
        if ($tipo === self::TIPO_TODOS) {
            return;
        }
        $query->where('pedido.tipo_comprobante', $tipo);
    }

    /**
     * @param  Builder<\App\Models\Ventas\PedidoInterforming>  $query
     * @param  array<string, mixed>  $qbe
     */
    private static function aplicarQbe(Builder $query, array $qbe): void
    {
        $norm = ListadoQbeSupport::normalizar(
            $qbe,
            self::camposQbeDisponibles(),
            static fn (string $op, string $campo): string => self::normalizarOperadorQbe($op, $campo)
        );

        ListadoQbeSupport::aplicar(
            $query,
            $norm,
            static function (Builder $q, array $criterio, string $boolean = 'and'): void {
                $formula = trim((string) ($criterio['formula'] ?? ''));
                if ($formula !== '') {
                    $compiled = ListadoQbeFormulaSupport::compilar($formula, self::camposQbeDisponibles());
                    if ($compiled === null) {
                        return;
                    }
                    if ($boolean === ListadoQbeSupport::LOGIC_OR) {
                        $q->orWhere(function (Builder $inner) use ($compiled, $criterio) {
                            ListadoQbeFormulaSupport::aplicarComparacion(
                                $inner,
                                $compiled,
                                (string) ($criterio['op'] ?? 'contiene'),
                                trim((string) ($criterio['valor'] ?? '')),
                                trim((string) ($criterio['valor_hasta'] ?? ''))
                            );
                        });

                        return;
                    }
                    ListadoQbeFormulaSupport::aplicarComparacion(
                        $q,
                        $compiled,
                        (string) ($criterio['op'] ?? 'contiene'),
                        trim((string) ($criterio['valor'] ?? '')),
                        trim((string) ($criterio['valor_hasta'] ?? ''))
                    );

                    return;
                }
                self::aplicarCriterioQbe(
                    $q,
                    (string) ($criterio['campo'] ?? 'codigo'),
                    (string) ($criterio['op'] ?? 'contiene'),
                    trim((string) ($criterio['valor'] ?? '')),
                    trim((string) ($criterio['valor_hasta'] ?? '')),
                    $boolean
                );
            }
        );
    }

    /**
     * @param  Builder<\App\Models\Ventas\PedidoInterforming>  $query
     */
    private static function aplicarCriterioQbe(
        Builder $query,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta,
        string $boolean
    ): void {
        if ($boolean === ListadoQbeSupport::LOGIC_OR) {
            $query->orWhere(function (Builder $q) use ($campoKey, $operador, $valor, $valorHasta) {
                self::aplicarCriterioQbe($q, $campoKey, $operador, $valor, $valorHasta, 'and');
            });

            return;
        }

        $def = self::camposQbeDisponibles()[$campoKey] ?? null;
        if ($def === null) {
            return;
        }
        $type = (string) ($def['type'] ?? 'texto');
        $column = (string) $def['column'];

        if ($type === 'entero') {
            self::aplicarEnteroQbe($query, $column, $operador, $valor, $valorHasta);

            return;
        }
        if ($type === 'fecha') {
            ListadoQbeSupport::aplicarFecha($query, $column, $operador, $valor, $valorHasta);

            return;
        }
        self::aplicarTextoQbe($query, $column, $operador, $valor, $valorHasta);
    }

    /**
     * @param  Builder<\App\Models\Ventas\PedidoInterforming>  $query
     */
    private static function aplicarTextoQbe(Builder $query, string $column, string $operador, string $valor, string $valorHasta): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }
        if ($operador === 'entre') {
            if ($valor !== '') {
                $query->where($column, '>=', $valor);
            }
            if ($valorHasta !== '') {
                $query->where($column, '<=', $valorHasta);
            }

            return;
        }
        if ($valor === '') {
            return;
        }
        $like = '%'.self::escapeLike($valor).'%';
        match ($operador) {
            'empieza' => $query->where($column, 'like', self::escapeLike($valor).'%'),
            'termina' => $query->where($column, 'like', '%'.self::escapeLike($valor)),
            'igual' => $query->where($column, '=', $valor),
            'distinto' => $query->where($column, '!=', $valor),
            'mayor' => $query->where($column, '>', $valor),
            'mayor_igual' => $query->where($column, '>=', $valor),
            'menor' => $query->where($column, '<', $valor),
            'menor_igual' => $query->where($column, '<=', $valor),
            'no_contiene' => $query->where(function ($q) use ($column, $like) {
                $q->whereNull($column)->orWhere($column, '')->orWhere($column, 'not like', $like);
            }),
            default => $query->where(function ($q) use ($column, $valor, $like) {
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
            }),
        };
    }

    /**
     * @param  Builder<\App\Models\Ventas\PedidoInterforming>  $query
     */
    private static function aplicarEnteroQbe(Builder $query, string $column, string $operador, string $valor, string $valorHasta): void
    {
        if ($operador === 'vacio') {
            $query->whereNull($column);

            return;
        }
        if ($operador === 'entre') {
            $desde = filter_var($valor, FILTER_VALIDATE_INT);
            $hasta = filter_var($valorHasta, FILTER_VALIDATE_INT);
            if ($desde !== false) {
                $query->where($column, '>=', (int) $desde);
            }
            if ($hasta !== false) {
                $query->where($column, '<=', (int) $hasta);
            }

            return;
        }
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false) {
            return;
        }
        $id = (int) $id;
        match ($operador) {
            'mayor' => $query->where($column, '>', $id),
            'mayor_igual' => $query->where($column, '>=', $id),
            'menor' => $query->where($column, '<', $id),
            'menor_igual' => $query->where($column, '<=', $id),
            default => $query->where($column, '=', $id),
        };
    }

    private static function resolverTipo(Request $request): string
    {
        if ($request->boolean('tipo_todos')) {
            return self::TIPO_TODOS;
        }

        return self::tipoNormalizado((string) $request->input('tipo', self::TIPO_TODOS));
    }

    private static function tipoNormalizado(string $tipo): string
    {
        $tipo = strtoupper(trim($tipo));

        return in_array($tipo, [
            PedidoInterformingNumeracionSupport::TIPO_VENTA,
            PedidoInterformingNumeracionSupport::TIPO_EXPORTACION,
        ], true) ? $tipo : self::TIPO_TODOS;
    }

    private static function normalizarOperadorQbe(string $operador, string $campoKey): string
    {
        $type = self::camposQbeDisponibles()[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_QBE_ENTERO),
            'fecha' => array_keys(ListadoQbeSupport::OPERADORES_FECHA),
            default => array_keys(self::OPERADORES_QBE_TEXTO),
        };
        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }
}
