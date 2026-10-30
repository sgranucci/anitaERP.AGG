<?php

declare(strict_types=1);

namespace App\Support\Stock;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Filtros del workbench de fórmulas de artículos.
 *
 * Un número solo (61) coincide con el número de fórmula o el id, no con
 * cualquier texto que contenga esas cifras.
 *
 * Externos (AND, no los pisa una vista): estado de la fórmula, estado de los
 * artículos de venta asignados (igual que el reporte de recetas) y opcionales.
 */
class FormulaArticuloListadoFiltros
{
    public const SESSION_FILTROS = 'formula_articulo_listado_filtros';

    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const MODO_QBE = 'qbe';

    public const ESTADO_TODAS = '';

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'art_padre.descripcion',
        'formula_articulo.detalle',
        'art_padre.sku',
    ];

    /** @var array<string, string> */
    public const OPERADORES_TEXTO = [
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
    public const OPERADORES_ENTERO = [
        'igual' => 'Es igual a',
        'mayor' => 'Mayor que',
        'mayor_igual' => 'Mayor o igual',
        'menor' => 'Menor que',
        'menor_igual' => 'Menor o igual',
        'entre' => 'Entre',
        'vacio' => 'Está vacío',
    ];

    /** @var array<string, string> */
    public const OPERADORES_FECHA = ListadoQbeSupport::OPERADORES_FECHA;

    /** @var array<string, string> */
    public const OPERADORES_DECIMAL = ListadoQbeSupport::OPERADORES_DECIMAL;

    /** @var array<string, string> */
    public const OPERADORES_BOOLEANO = [
        'igual' => 'Es',
        'vacio' => 'Sin dato',
    ];

    /** @var list<string> */
    public const ESTADOS = ['ACTIVA', 'INACTIVA', 'BORRADOR'];

    public const ARTICULOS_TODOS = '';

    public const ARTICULOS_ACTIVOS = 'activos';

    public const ARTICULOS_INACTIVOS = 'inactivos';

    public const ARTICULOS_SIN_ASIGNAR = 'sin_asignar';

    /** @var list<string> */
    public const ARTICULOS = [
        self::ARTICULOS_ACTIVOS,
        self::ARTICULOS_INACTIVOS,
        self::ARTICULOS_SIN_ASIGNAR,
    ];

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        $out = [];
        foreach (FormulaArticuloListadoColumnas::camposFiltrables() as $key => $meta) {
            $out[$key] = [
                'column' => $meta['column'],
                'type' => $meta['type'],
                'label' => $meta['label'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposQbeDisponibles(): array
    {
        return self::campos();
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return FormulaArticuloListadoColumnas::camposOrdenables();
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $estado = self::normalizarEstado($request->input('filtro_estado'));
        $articulos = self::normalizarArticulos($request->input('filtro_articulos'));
        $conOpcionales = self::normalizarOpcionales($request->input('con_opcionales'));
        $externos = [
            'estado' => $estado,
            'articulos' => $articulos,
            'con_opcionales' => $conOpcionales,
        ];

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), $externos, [
                '_limpiar' => true,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida') || ($request->has('busqueda') && ! $request->has('filtro_valor') && $valor !== '');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO, self::MODO_QBE], true)) {
            $modo = self::MODO_TODOS;
        }

        $campoDefault = self::campoDefault();
        $campo = (string) $request->input('filtro_campo', $campoDefault);
        if (! isset(self::campos()[$campo])) {
            $campo = $campoDefault;
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');
        $qbe = ListadoQbeSupport::resolverDesdeRequest(
            $request,
            self::campos(),
            static fn (string $op, string $campoQbe): string => self::normalizarOperador($op, $campoQbe)
        );
        $sort = ListadoOrdenamientoSupport::resolverDesdeRequest($request, self::camposOrdenables());
        $agrupar = ListadoAgrupacionSupport::resolverDesdeRequest($request, self::camposOrdenables());

        if ($busquedaRapida || ($request->has('busqueda') && ! $request->has('qbe') && ! $request->has('filtro_modo'))) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
            $qbe = ListadoQbeSupport::vacio();
        } elseif (ListadoQbeSupport::tieneCriterios($qbe)) {
            $modo = self::MODO_QBE;
        }

        if ($modo !== self::MODO_QBE) {
            $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : $campoDefault);
        }

        return array_merge($externos, [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'qbe' => $qbe,
            'sort' => $sort,
            'agrupar' => $agrupar,
        ]);
    }

    public static function campoDefault(): string
    {
        return FormulaArticuloNumero::mostrarCodigo() ? 'numero' : 'id';
    }

    public static function normalizarEstado(mixed $estado): string
    {
        $estado = is_string($estado) ? strtoupper(trim($estado)) : '';

        return in_array($estado, self::ESTADOS, true) ? $estado : self::ESTADO_TODAS;
    }

    public static function normalizarArticulos(mixed $valor): string
    {
        $valor = is_string($valor) ? strtolower(trim($valor)) : '';

        return in_array($valor, self::ARTICULOS, true) ? $valor : self::ARTICULOS_TODOS;
    }

    public static function etiquetaArticulos(string $valor): string
    {
        return match (self::normalizarArticulos($valor)) {
            self::ARTICULOS_ACTIVOS => 'Artículos de venta activos',
            self::ARTICULOS_INACTIVOS => 'Artículos de venta inactivos',
            self::ARTICULOS_SIN_ASIGNAR => 'Sin artículo de venta',
            default => '',
        };
    }

    public static function normalizarOpcionales(mixed $valor): string
    {
        $valor = is_string($valor) ? strtolower(trim($valor)) : '';

        return in_array($valor, ['si', 'no'], true) ? $valor : '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosTexto(array $filtros): bool
    {
        if (($filtros['modo'] ?? '') === self::MODO_QBE && ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))) {
            return true;
        }

        return trim((string) ($filtros['valor'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (self::tieneCriteriosTexto($filtros)) {
            return true;
        }
        if (self::normalizarEstado($filtros['estado'] ?? '') !== self::ESTADO_TODAS) {
            return true;
        }
        if (self::normalizarArticulos($filtros['articulos'] ?? '') !== self::ARTICULOS_TODOS) {
            return true;
        }
        if (self::normalizarOpcionales($filtros['con_opcionales'] ?? '') !== '') {
            return true;
        }

        return ListadoOrdenamientoSupport::normalizar($filtros['sort'] ?? [], self::camposOrdenables()) !== []
            || ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], self::camposOrdenables()) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => self::campoDefault(),
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'busqueda_rapida' => false,
            'estado' => self::ESTADO_TODAS,
            'articulos' => self::ARTICULOS_TODOS,
            'con_opcionales' => '',
            'qbe' => ListadoQbeSupport::vacio(),
            'sort' => [],
            'agrupar' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        $modo = $filtros['modo'] ?? self::MODO_TODOS;

        if ($modo === self::MODO_QBE) {
            $params['filtro_modo'] = self::MODO_QBE;
            $params = array_merge($params, ListadoQbeSupport::paraQueryString(
                ListadoQbeSupport::normalizar(
                    $filtros['qbe'] ?? [],
                    self::campos(),
                    static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
                )
            ));
        } else {
            if ($modo !== self::MODO_TODOS) {
                $params['filtro_modo'] = $modo;
            }
            if ($modo === self::MODO_CAMPO) {
                $params['filtro_campo'] = $filtros['campo'] ?? self::campoDefault();
                $params['filtro_operador'] = $filtros['operador'] ?? 'contiene';
            }
            if (! empty($filtros['valor'])) {
                $params['filtro_valor'] = $filtros['valor'];
            }
            if (! empty($filtros['valor_hasta'])) {
                $params['filtro_valor_hasta'] = $filtros['valor_hasta'];
            }
        }

        $estado = self::normalizarEstado($filtros['estado'] ?? '');
        if ($estado !== self::ESTADO_TODAS) {
            $params['filtro_estado'] = $estado;
        }
        $articulos = self::normalizarArticulos($filtros['articulos'] ?? '');
        if ($articulos !== self::ARTICULOS_TODOS) {
            $params['filtro_articulos'] = $articulos;
        }
        $opc = self::normalizarOpcionales($filtros['con_opcionales'] ?? '');
        if ($opc !== '') {
            $params['con_opcionales'] = $opc;
        }

        return array_merge(
            $params,
            ListadoOrdenamientoSupport::paraQueryString(
                ListadoOrdenamientoSupport::normalizar($filtros['sort'] ?? [], self::camposOrdenables())
            ),
            ListadoAgrupacionSupport::paraQueryString(
                ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], self::camposOrdenables())
            )
        );
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $desdeVista
     * @return array<string, mixed>
     */
    public static function fusionarDesdeVista(array $base, array $desdeVista): array
    {
        $externos = [
            'estado' => self::normalizarEstado($base['estado'] ?? ''),
            'articulos' => self::normalizarArticulos($base['articulos'] ?? ''),
            'con_opcionales' => self::normalizarOpcionales($base['con_opcionales'] ?? ''),
        ];

        $campos = self::camposOrdenables();
        $ordenVista = ListadoOrdenamientoSupport::normalizar(
            ($desdeVista['orden'] ?? []) !== [] ? $desdeVista['orden'] : ($desdeVista['sort'] ?? []),
            $campos
        );
        $ordenBase = ListadoOrdenamientoSupport::normalizar($base['sort'] ?? [], $campos);
        $agruparVista = ListadoAgrupacionSupport::normalizar($desdeVista['agrupar'] ?? $desdeVista['group'] ?? [], $campos);
        $agruparBase = ListadoAgrupacionSupport::normalizar($base['agrupar'] ?? [], $campos);

        if (! empty($base['_limpiar'])) {
            unset($base['_limpiar'], $base['_qbe_explicito']);

            return array_merge($base, $externos, [
                'modo' => self::MODO_TODOS,
                'valor' => '',
                'valor_hasta' => '',
                'busqueda' => '',
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

            return array_merge($base, $externos);
        }

        $qbe = ListadoQbeSupport::normalizar(
            $desdeVista['qbe'] ?? [],
            self::campos(),
            static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
        );
        $modo = (string) ($desdeVista['modo'] ?? self::MODO_TODOS);
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $modo = self::MODO_QBE;
        }

        return array_merge($base, $externos, [
            'modo' => $modo,
            'campo' => (string) ($desdeVista['campo'] ?? $base['campo'] ?? self::campoDefault()),
            'operador' => (string) ($desdeVista['operador'] ?? $base['operador'] ?? 'contiene'),
            'valor' => (string) ($desdeVista['valor'] ?? ''),
            'valor_hasta' => (string) ($desdeVista['valor_hasta'] ?? ''),
            'busqueda' => (string) ($desdeVista['valor'] ?? $desdeVista['busqueda'] ?? ''),
            'qbe' => $qbe,
            'sort' => $ordenBase !== [] ? $ordenBase : $ordenVista,
            'agrupar' => $agruparBase !== [] ? $agruparBase : $agruparVista,
        ]);
    }

    /**
     * @param  Builder<\App\Models\Stock\Formula_Articulo>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $estado = self::normalizarEstado($filtros['estado'] ?? '');
        if ($estado !== self::ESTADO_TODAS) {
            $query->where('formula_articulo.estado', $estado);
        }

        self::aplicarAsignacionArticulo($query, self::normalizarArticulos($filtros['articulos'] ?? ''));

        $opc = self::normalizarOpcionales($filtros['con_opcionales'] ?? '');
        if ($opc === 'si') {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('formula_articulo_hijo')
                    ->whereColumn('formula_articulo_hijo.formula_articulo_id', 'formula_articulo.id')
                    ->where('formula_articulo_hijo.esopcional', 1);
            });
        } elseif ($opc === 'no') {
            $query->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('formula_articulo_hijo')
                    ->whereColumn('formula_articulo_hijo.formula_articulo_id', 'formula_articulo.id')
                    ->where('formula_articulo_hijo.esopcional', 1);
            });
        }

        if (! self::tieneCriteriosTexto($filtros)) {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        if ($modo === self::MODO_QBE) {
            self::aplicarQbe($query, (array) ($filtros['qbe'] ?? []));

            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = (string) ($filtros['operador'] ?? 'contiene');
        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo(
                $query,
                (string) ($filtros['campo'] ?? self::campoDefault()),
                $operador,
                $valor,
                (string) ($filtros['valor_hasta'] ?? '')
            );

            return;
        }

        self::aplicarBusquedaGlobal($query, $valor);
    }

    /**
     * @param  Builder<\App\Models\Stock\Formula_Articulo>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarOrden(Builder $query, array $filtros): void
    {
        $campos = self::camposOrdenables();
        $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
        $sort = ListadoOrdenamientoSupport::normalizar($filtros['sort'] ?? [], $campos);
        if ($agrupar !== []) {
            ListadoAgrupacionSupport::aplicarOrdenPrefijo($query, $agrupar, $campos);
        }
        if ($sort !== []) {
            ListadoOrdenamientoSupport::aplicar($query, $sort, $campos, [
                'campo' => 'id',
                'dir' => ListadoOrdenamientoSupport::DIR_DESC,
            ]);

            return;
        }

        $query->orderByDesc('formula_articulo.id');
    }

    public static function requestTraeFiltros(Request $request): bool
    {
        foreach ([
            'filtro_valor',
            'busqueda',
            'qbe',
            'filtro_modo',
            'filtro_limpiar',
            'limpiar_filtros',
            'vista_id',
            'vista_estandar',
            'vista_modo',
            'con_opcionales',
            'filtro_estado',
            'filtro_articulos',
            'sort',
            'group',
            'aplicar_qbe',
            'filtro_busqueda_rapida',
            'columnas',
            'quitar_orden',
        ] as $key) {
            if ($request->query->has($key) || $request->request->has($key)) {
                return true;
            }
        }

        return (int) $request->query('page', 0) > 1;
    }

    /**
     * @param  array<string, mixed>  $filtrosQuery
     */
    public static function persistir(array $filtrosQuery): void
    {
        unset($filtrosQuery['filtro_limpiar'], $filtrosQuery['limpiar_filtros']);
        if ($filtrosQuery === []) {
            session()->forget(self::SESSION_FILTROS);

            return;
        }

        session([self::SESSION_FILTROS => $filtrosQuery]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function guardados(): array
    {
        $guardados = session(self::SESSION_FILTROS, []);

        return is_array($guardados) ? $guardados : [];
    }

    public static function olvidar(): void
    {
        session()->forget(self::SESSION_FILTROS);
    }

    /**
     * @param  Builder<\App\Models\Stock\Formula_Articulo>  $query
     * @param  array{entre_grupos?: string, grupos?: list}|list  $qbe
     */
    private static function aplicarQbe(Builder $query, array $qbe): void
    {
        $norm = ListadoQbeSupport::normalizar(
            $qbe,
            self::campos(),
            static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
        );

        ListadoQbeSupport::aplicar(
            $query,
            $norm,
            static function (Builder $q, array $criterio, string $boolean = 'and'): void {
                $formula = trim((string) ($criterio['formula'] ?? ''));
                if ($formula !== '') {
                    $compiled = ListadoQbeFormulaSupport::compilar($formula, self::campos());
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
                self::aplicarEnCampo(
                    $q,
                    (string) ($criterio['campo'] ?? self::campoDefault()),
                    (string) ($criterio['op'] ?? 'contiene'),
                    trim((string) ($criterio['valor'] ?? '')),
                    trim((string) ($criterio['valor_hasta'] ?? '')),
                    $boolean
                );
            }
        );
    }

    /**
     * Artículo de venta asignado: articulo.formula o formula_articulo.articulo_id.
     * Activo = al menos uno no inactivo. Inactivo = hay asignados y ninguno activo.
     * Sin asignar = no hay artículo de venta. Los insumos de las líneas no cuentan.
     *
     * @param  Builder<\App\Models\Stock\Formula_Articulo>  $query
     */
    private static function aplicarAsignacionArticulo(Builder $query, string $modo): void
    {
        if ($modo === self::ARTICULOS_TODOS) {
            return;
        }

        if ($modo === self::ARTICULOS_SIN_ASIGNAR) {
            $query->whereNotExists(function ($sub) {
                self::subqueryArticulosAsignados($sub);
            });

            return;
        }

        if ($modo === self::ARTICULOS_ACTIVOS) {
            $query->whereExists(function ($sub) {
                self::subqueryArticulosAsignados($sub, true);
            });

            return;
        }

        $query->whereExists(function ($sub) {
            self::subqueryArticulosAsignados($sub);
        })->whereNotExists(function ($sub) {
            self::subqueryArticulosAsignados($sub, true);
        });
    }

    private static function subqueryArticulosAsignados($sub, bool $soloActivos = false): void
    {
        $sub->select(DB::raw(1))
            ->from('articulo as art_asig')
            ->where(function ($w) {
                $w->whereColumn('art_asig.formula', 'formula_articulo.id')
                    ->orWhereColumn('art_asig.id', 'formula_articulo.articulo_id');
            });
        if ($soloActivos) {
            self::whereArticuloVentaActivo($sub);
        }
    }

    private static function whereArticuloVentaActivo($sub): void
    {
        if (ArticuloEstadoCanalSupport::uiFerliActiva()) {
            $sub->where(function ($w) {
                $w->whereRaw("UPPER(TRIM(COALESCE(art_asig.estado_fabrica, ''))) <> 'INACTIVO'")
                    ->orWhereRaw("UPPER(TRIM(COALESCE(art_asig.estado_local, ''))) <> 'INACTIVO'");
            });

            return;
        }

        $sub->whereRaw("UPPER(TRIM(COALESCE(art_asig.estado, ''))) <> 'INACTIVO'");
    }

    /**
     * @param  Builder<\App\Models\Stock\Formula_Articulo>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $valor): void
    {
        if ($valor === '') {
            return;
        }

        if (ctype_digit($valor)) {
            self::aplicarNumeroExacto($query, $valor);

            return;
        }

        $like = '%'.addcslashes($valor, '%_\\').'%';
        $query->where(function ($q) use ($valor, $like) {
            foreach ([
                'formula_articulo.codigo',
                'formula_articulo.detalle',
                'formula_articulo.estado',
                'art_padre.sku',
                'art_padre.descripcion',
                'usuario.nombre',
                'empresa.nombre',
            ] as $columna) {
                $q->orWhere($columna, 'like', $like);
            }
            foreach (self::COLUMNAS_COINCIDENCIA_FLEXIBLE as $columna) {
                $min = $columna === 'art_padre.sku'
                    ? CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
                    : CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT;
                CoincidenciaFlexibleTexto::aplicar($q, $columna, $valor, true, $min);
            }
            $q->orWhereExists(function ($sub) use ($like) {
                $sub->select(DB::raw(1))
                    ->from('formula_articulo_hijo')
                    ->join('articulo as art_item', 'art_item.id', '=', 'formula_articulo_hijo.articulo_id')
                    ->whereColumn('formula_articulo_hijo.formula_articulo_id', 'formula_articulo.id')
                    ->where(function ($w) use ($like) {
                        $w->where('art_item.sku', 'like', $like)
                            ->orWhere('art_item.descripcion', 'like', $like);
                    });
            });
        });
    }

    /**
     * Número de fórmula (código visible) o id interno. No usa LIKE.
     *
     * @param  Builder<\App\Models\Stock\Formula_Articulo>  $query
     */
    private static function aplicarNumeroExacto(Builder $query, string $valor): void
    {
        $valor = trim($valor);
        if ($valor === '' || ! ctype_digit($valor)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $id = (int) $valor;
        $sinCeros = ltrim($valor, '0');
        if ($sinCeros === '') {
            $sinCeros = '0';
        }

        $query->where(function ($q) use ($valor, $sinCeros, $id) {
            $q->where('formula_articulo.codigo', $valor);
            if ($sinCeros !== $valor) {
                $q->orWhere('formula_articulo.codigo', $sinCeros);
            }
            if ($id > 0) {
                $q->orWhere('formula_articulo.id', $id);
            }
        });
    }

    /**
     * @param  Builder<\App\Models\Stock\Formula_Articulo>  $query
     */
    private static function aplicarEnCampo(
        Builder $query,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta = '',
        string $boolean = 'and'
    ): void {
        if ($boolean === ListadoQbeSupport::LOGIC_OR) {
            $query->orWhere(function (Builder $q) use ($campoKey, $operador, $valor, $valorHasta) {
                self::aplicarEnCampo($q, $campoKey, $operador, $valor, $valorHasta);
            });

            return;
        }

        if ($campoKey === 'numero' && $operador === 'igual') {
            self::aplicarNumeroExacto($query, $valor);

            return;
        }
        if (in_array($campoKey, ['item_sku', 'item_descripcion'], true)) {
            self::aplicarItem($query, $campoKey, $operador, $valor, $valorHasta);

            return;
        }

        $meta = self::campos()[$campoKey] ?? null;
        if ($meta === null) {
            return;
        }
        $column = (string) $meta['column'];
        if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
            return;
        }
        $type = (string) ($meta['type'] ?? 'texto');
        if ($type === 'entero') {
            self::aplicarEntero($query, $column, $operador, $valor, $valorHasta);

            return;
        }
        if ($type === 'fecha') {
            ListadoQbeSupport::aplicarFecha($query, $column, $operador, $valor, $valorHasta);

            return;
        }
        if ($type === 'decimal') {
            ListadoQbeSupport::aplicarDecimal($query, $column, $operador, $valor, $valorHasta);

            return;
        }

        self::aplicarTexto($query, $column, $operador, $valor, $valorHasta);
    }

    /**
     * @param  Builder<\App\Models\Stock\Formula_Articulo>  $query
     */
    private static function aplicarItem(
        Builder $query,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta
    ): void {
        $columna = $campoKey === 'item_sku' ? 'art_item.sku' : 'art_item.descripcion';
        if ($operador === 'vacio') {
            $query->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('formula_articulo_hijo')
                    ->whereColumn('formula_articulo_hijo.formula_articulo_id', 'formula_articulo.id')
                    ->whereNotNull('formula_articulo_hijo.articulo_id');
            });

            return;
        }
        if ($valor === '' && $operador !== 'entre') {
            return;
        }

        $query->whereExists(function ($sub) use ($columna, $operador, $valor, $valorHasta) {
            $sub->select(DB::raw(1))
                ->from('formula_articulo_hijo')
                ->join('articulo as art_item', 'art_item.id', '=', 'formula_articulo_hijo.articulo_id')
                ->whereColumn('formula_articulo_hijo.formula_articulo_id', 'formula_articulo.id');
            self::aplicarTexto($sub, $columna, $operador, $valor, $valorHasta);
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Query\Builder  $query
     */
    private static function aplicarTexto(
        $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta
    ): void {
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }
        if ($operador === 'entre') {
            if (trim($valor) !== '') {
                $query->where($column, '>=', $valor);
            }
            if (trim($valorHasta) !== '') {
                $query->where($column, '<=', $valorHasta);
            }

            return;
        }
        if ($valor === '') {
            return;
        }

        $like = self::patronLike($operador, $valor);
        match ($operador) {
            'igual' => $query->where($column, '=', $valor),
            'distinto' => $query->where(function ($q) use ($column, $valor) {
                $q->where($column, '!=', $valor)->orWhereNull($column);
            }),
            'mayor' => $query->where($column, '>', $valor),
            'mayor_igual' => $query->where($column, '>=', $valor),
            'menor' => $query->where($column, '<', $valor),
            'menor_igual' => $query->where($column, '<=', $valor),
            'no_contiene' => $query->where(function ($q) use ($column, $like) {
                $q->where($column, 'not like', $like)->orWhereNull($column);
            }),
            'contiene' => $query->where(function ($q) use ($column, $like, $valor) {
                $q->where($column, 'like', $like);
                if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    $min = $column === 'art_padre.sku'
                        ? CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
                        : CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT;
                    CoincidenciaFlexibleTexto::aplicar($q, $column, $valor, true, $min);
                }
            }),
            default => $query->where($column, 'like', $like),
        };
    }

    /**
     * @param  Builder<\App\Models\Stock\Formula_Articulo>  $query
     */
    private static function aplicarEntero(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta
    ): void {
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
            $query->whereRaw('1 = 0');

            return;
        }
        $op = match ($operador) {
            'mayor' => '>',
            'mayor_igual' => '>=',
            'menor' => '<',
            'menor_igual' => '<=',
            default => '=',
        };
        $query->where($column, $op, (int) $id);
    }

    private static function patronLike(string $operador, string $valor): string
    {
        $v = addcslashes($valor, '%_\\');

        return match ($operador) {
            'empieza' => $v.'%',
            'termina' => '%'.$v,
            'igual' => $v,
            default => '%'.$v.'%',
        };
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $type = self::campos()[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO),
            'fecha' => array_keys(self::OPERADORES_FECHA),
            'decimal' => array_keys(self::OPERADORES_DECIMAL),
            default => array_keys(self::OPERADORES_TEXTO),
        };

        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }
}
