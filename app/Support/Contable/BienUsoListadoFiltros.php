<?php

namespace App\Support\Contable;

use App\Models\Contable\BienUso;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del workbench de bienes de uso (módulo estándar AGG+Crown).
 *
 * Externos (AND, no los pisa una vista): estado, empresa, centro de costo.
 */
class BienUsoListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const MODO_QBE = 'qbe';

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'bien_uso.uid',
        'bien_uso.hostname',
        'bien_uso.modelo',
        'bien_uso.vendor',
        'bien_uso.tema',
        'bien_uso.numero_serie',
        'bien_uso.observaciones',
        'bien_uso.descripcion',
        'bien_uso.marca',
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

    private const CAMPO_DEFAULT = 'uid';

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        $out = [];
        foreach (BienUsoListadoColumnas::camposFiltrables() as $key => $meta) {
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
        return BienUsoListadoColumnas::camposOrdenables();
    }

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$estado, $empresaId, $empresaScope, $centrocostoId] = self::resolverExterno($request, $empresaDefault);
        $externos = [
            'estado' => $estado,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'centrocosto_id' => $centrocostoId,
        ];

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), $externos, [
                '_limpiar' => true,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO, self::MODO_QBE], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', self::CAMPO_DEFAULT);
        if (! isset(self::campos()[$campo])) {
            $campo = self::CAMPO_DEFAULT;
            if (! isset(self::campos()[$campo])) {
                $campo = array_key_first(self::campos()) ?: 'id';
            }
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');
        $qbe = ListadoQbeSupport::resolverDesdeRequest(
            $request,
            self::campos(),
            static fn (string $op, string $campoQbe): string => self::normalizarOperador($op, $campoQbe)
        );
        $sort = ListadoOrdenamientoSupport::resolverDesdeRequest($request, self::camposOrdenables());
        $agrupar = ListadoAgrupacionSupport::resolverDesdeRequest($request, self::camposOrdenables());

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
            $qbe = ListadoQbeSupport::vacio();
        } elseif (ListadoQbeSupport::tieneCriterios($qbe)) {
            $modo = self::MODO_QBE;
        }

        if ($modo !== self::MODO_QBE) {
            $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : self::CAMPO_DEFAULT);
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

    /**
     * @return array{0: string, 1: ?int, 2: string, 3: ?int}
     */
    private static function resolverExterno(Request $request, ?int $empresaDefault): array
    {
        $estadoInput = $request->input('filtro_estado', null);
        if ($estadoInput === 'TODOS' || $request->boolean('estado_todos')) {
            $estado = '';
        } elseif (in_array($estadoInput, ['A', 'I', 'B'], true)) {
            $estado = (string) $estadoInput;
        } else {
            $estado = 'A';
        }

        $centrocostoId = filter_var($request->input('filtro_centrocosto_id'), FILTER_VALIDATE_INT);
        $cc = $centrocostoId !== false && $centrocostoId > 0 ? (int) $centrocostoId : null;

        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            return [$estado, null, 'todas', $cc];
        }
        if ($request->filled('empresa_id')) {
            return [$estado, (int) $request->input('empresa_id'), 'una', $cc];
        }
        if ($empresaDefault !== null && $empresaDefault > 0) {
            return [$estado, $empresaDefault, 'una', $cc];
        }

        return [$estado, null, 'todas', $cc];
    }

    public static function tieneCriteriosTexto(array $filtros): bool
    {
        if (ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))) {
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

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (self::tieneCriteriosTexto($filtros)) {
            return true;
        }
        if (($filtros['estado'] ?? 'A') !== 'A') {
            return true;
        }
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            return true;
        }
        if (! empty($filtros['centrocosto_id'])) {
            return true;
        }

        return false;
    }

    /** @return array<string, mixed> */
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
            'estado' => 'A',
            'centrocosto_id' => null,
            'qbe' => ListadoQbeSupport::vacio(),
            'sort' => [],
            'agrupar' => [],
        ];
    }

    /** @return array<string, mixed> */
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
                $params['filtro_campo'] = $filtros['campo'] ?? self::CAMPO_DEFAULT;
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
        }

        $estado = $filtros['estado'] ?? 'A';
        if ($estado === '') {
            $params['filtro_estado'] = 'TODOS';
        } elseif ($estado !== 'A') {
            $params['filtro_estado'] = $estado;
        }
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            $params['empresa_todas'] = 1;
        } elseif (! empty($filtros['empresa_id'])) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (! empty($filtros['centrocosto_id'])) {
            $params['filtro_centrocosto_id'] = (int) $filtros['centrocosto_id'];
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
            'estado' => $base['estado'] ?? 'A',
            'empresa_id' => $base['empresa_id'] ?? null,
            'empresa_scope' => $base['empresa_scope'] ?? 'una',
            'centrocosto_id' => $base['centrocosto_id'] ?? null,
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
            'campo' => (string) ($desdeVista['campo'] ?? $base['campo'] ?? self::CAMPO_DEFAULT),
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
     * @param  Builder<\App\Models\Contable\BienUso>  $query
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
                'campo' => isset($campos['codigo_bien']) ? 'codigo_bien' : (isset($campos['uid']) ? 'uid' : 'id'),
                'dir' => ListadoOrdenamientoSupport::DIR_ASC,
            ]);

            return;
        }

        $query->orderByRaw('COALESCE(bien_uso.uid, bien_uso.hostname, bien_uso.id)');
    }

    /**
     * @param  Builder<\App\Models\Contable\BienUso>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('bien_uso.empresa_id', (int) $filtros['empresa_id']);
        }
        if (($filtros['estado'] ?? '') !== '') {
            $query->where('bien_uso.estado', $filtros['estado']);
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
        $operador = $filtros['operador'] ?? 'contiene';
        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo(
                $query,
                $filtros['campo'] ?? self::CAMPO_DEFAULT,
                $operador,
                $valor,
                (string) ($filtros['valor_hasta'] ?? '')
            );

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Contable\BienUso>  $query
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
                    (string) ($criterio['campo'] ?? self::CAMPO_DEFAULT),
                    (string) ($criterio['op'] ?? 'contiene'),
                    trim((string) ($criterio['valor'] ?? '')),
                    trim((string) ($criterio['valor_hasta'] ?? '')),
                    $boolean
                );
            }
        );
    }

    /**
     * @param  Builder<\App\Models\Contable\BienUso>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['bien_uso.hostname', 'bien_uso.modelo', 'bien_uso.numero_serie', 'bien_uso.descripcion'] as $col) {
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
                $q->orWhere('bien_uso.id', (int) $id);
                $q->orWhere('bien_uso.codigo_inventario', (int) $id);
                if (\Illuminate\Support\Facades\Schema::hasColumn('bien_uso', 'codigo_bien')) {
                    $q->orWhere('bien_uso.codigo_bien', (int) $id);
                }
            }

            $textCols = array_values(array_filter([
                'bien_uso.uid',
                'bien_uso.hostname',
                'bien_uso.ip',
                'bien_uso.modelo',
                'bien_uso.vendor',
                'bien_uso.tema',
                'bien_uso.numero_serie',
                'bien_uso.observaciones',
                \Illuminate\Support\Facades\Schema::hasColumn('bien_uso', 'descripcion') ? 'bien_uso.descripcion' : null,
                \Illuminate\Support\Facades\Schema::hasColumn('bien_uso', 'marca') ? 'bien_uso.marca' : null,
                'empresa.nombre',
                'centrocosto.codigo',
                'centrocosto.nombre',
            ]));
            foreach ($textCols as $col) {
                $q->orWhere($col, 'like', $like);
                if ($operador === 'contiene' && in_array($col, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $col,
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            }

            self::aplicarCoincidenciaEnum($q, $valor, $operador);
        });
    }

    /**
     * @param  Builder<\App\Models\Contable\BienUso>  $query
     */
    private static function aplicarCoincidenciaEnum(Builder $query, string $valor, string $operador): void
    {
        if ($operador !== 'contiene') {
            return;
        }

        $valorNorm = mb_strtolower(trim($valor));

        foreach (BienUso::$enumEstado as $item) {
            if (str_contains(mb_strtolower($item['nombre']), $valorNorm)) {
                $query->orWhere('bien_uso.estado', $item['valor']);
            }
        }
        foreach (BienUso::$enumTipoBien as $item) {
            if (str_contains(mb_strtolower($item['nombre']), $valorNorm)) {
                $query->orWhere('bien_uso.tipo_bien', $item['valor']);
            }
        }
    }

    /**
     * @param  Builder<\App\Models\Contable\BienUso>  $query
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

        $campos = self::campos();
        $def = $campos[$campoKey] ?? $campos[self::CAMPO_DEFAULT] ?? ['column' => 'bien_uso.id', 'type' => 'entero'];
        $type = $def['type'] ?? 'texto';
        $column = (string) $def['column'];

        if ($type === 'entero') {
            if ($operador === 'vacio') {
                $query->whereNull($column);

                return;
            }
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
     * @param  Builder<\App\Models\Contable\BienUso>  $query
     */
    private static function aplicarTexto(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta = ''
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
            case 'mayor':
                $query->where($column, '>', $valor);
                break;
            case 'mayor_igual':
                $query->where($column, '>=', $valor);
                break;
            case 'menor':
                $query->where($column, '<', $valor);
                break;
            case 'menor_igual':
                $query->where($column, '<=', $valor);
                break;
            case 'no_contiene':
                $query->where(function ($q) use ($column, $valor) {
                    $like = '%'.self::escapeLike($valor).'%';
                    $q->whereNull($column)->orWhere($column, '')->orWhere($column, 'not like', $like);
                });
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
     * @param  Builder<\App\Models\Contable\BienUso>  $query
     */
    private static function aplicarEntero(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta = ''
    ): void {
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
        switch ($operador) {
            case 'mayor':
                $query->where($column, '>', $id);
                break;
            case 'mayor_igual':
                $query->where($column, '>=', $id);
                break;
            case 'menor':
                $query->where($column, '<', $id);
                break;
            case 'menor_igual':
                $query->where($column, '<=', $id);
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
        $type = self::campos()[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO),
            'fecha' => array_keys(self::OPERADORES_FECHA),
            'decimal' => array_keys(self::OPERADORES_DECIMAL),
            'booleano' => array_keys(self::OPERADORES_BOOLEANO),
            default => array_keys(self::OPERADORES_TEXTO),
        };

        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }

    /** @return array<string, string> */
    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::campos()[$campoKey]['type'] ?? 'texto';

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'fecha' => self::OPERADORES_FECHA,
            'decimal' => self::OPERADORES_DECIMAL,
            'booleano' => self::OPERADORES_BOOLEANO,
            default => self::OPERADORES_TEXTO,
        };
    }
}
