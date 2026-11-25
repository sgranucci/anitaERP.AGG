<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoFiltrosMemoriaSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoQbeSupport;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class OrdencompraLegajoBandejaFiltros
{
    private const SESSION_FILTROS = 'filtros_legajo_bandeja';

    public const VISTA_PENDIENTES = 'pendientes';

    public const VISTA_ESTADOS = 'estados';

    public const VISTA_CXP = 'cxp';

    public const VISTA_PAGOS = 'pagos';

    public const VISTA_ARCHIVADOS = 'archivados';

    public const VISTA_HISTORICO = 'historico';

    public const TAB_GASTRONOMIA = 'gastronomia';

    public const TAB_RESTO = 'resto';

    public const TAB_TODOS = 'todos';

    public const ATAJO_SIN_FACTURA = 'sin_factura';

    public const ATAJO_SIN_COM = 'sin_com';

    public const ATAJO_COM_SIN_ASIGNAR = 'com_sin_asignar';

    public const ATAJO_PENDIENTE_ENTREGA = 'pendiente_entrega';

    public const ATAJO_LISTO_CARGAR = 'listo_cargar';

    public const ATAJO_FC_CARGADA = 'fc_cargada';

    public const ATAJO_CON_PAGO = 'con_pago';

    public const MODO_TODOS = OrdencompraListadoFiltros::MODO_TODOS;

    public const MODO_CAMPO = OrdencompraListadoFiltros::MODO_CAMPO;

    public const MODO_QBE = OrdencompraListadoFiltros::MODO_QBE;

    /** @var array<string, string> */
    public const OPERADORES_TEXTO_QBE = OrdencompraListadoFiltros::OPERADORES_TEXTO_QBE;

    /** @var array<string, string> */
    public const OPERADORES_ENTERO_QBE = OrdencompraListadoFiltros::OPERADORES_ENTERO_QBE;

    /** @var array<string, string> */
    public const OPERADORES_FECHA_QBE = OrdencompraListadoFiltros::OPERADORES_FECHA_QBE;

    /** @var array<string, string> */
    public const OPERADORES_DECIMAL = OrdencompraListadoFiltros::OPERADORES_DECIMAL;

    /** @var array<string, string> */
    public const OPERADORES_BOOLEANO = OrdencompraListadoFiltros::OPERADORES_BOOLEANO;

    /** @var list<string> */
    public const VISTAS = [
        self::VISTA_PENDIENTES,
        self::VISTA_ESTADOS,
        self::VISTA_CXP,
        self::VISTA_PAGOS,
        self::VISTA_ARCHIVADOS,
        self::VISTA_HISTORICO,
    ];

    /** @var list<string> */
    public const ATAJOS = [
        self::ATAJO_SIN_FACTURA,
        self::ATAJO_SIN_COM,
        self::ATAJO_COM_SIN_ASIGNAR,
        self::ATAJO_PENDIENTE_ENTREGA,
        self::ATAJO_LISTO_CARGAR,
        self::ATAJO_FC_CARGADA,
        self::ATAJO_CON_PAGO,
    ];

    /**
     * Default de bandeja para usuarios con sector Cuentas a pagar.
     *
     * @return array<string, scalar>
     */
    public static function defaultsCuentasAPagarQuery(): array
    {
        return [
            'vista' => self::VISTA_CXP,
            'tab' => self::TAB_TODOS,
            'empresa_todas' => 1,
            'atajo' => self::ATAJO_LISTO_CARGAR,
        ];
    }

    public static function requestTraeContexto(Request $request): bool
    {
        if (QueryRetornoListado::requestTraeContextoIndex($request)) {
            return true;
        }

        foreach (['atajo', 'nro_oc', 'nro_factura', 'nro_com', 'nro_op', 'nro_cliente', 'tab', 'vista_id', 'vista_estandar', 'qbe', 'sort', 'group'] as $key) {
            if ($request->query->has($key) || $request->request->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string|int|bool>  $filtrosQuery
     */
    public static function persistir(array $filtrosQuery): void
    {
        ListadoFiltrosMemoriaSupport::persistir(self::SESSION_FILTROS, $filtrosQuery);
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function guardados(): array
    {
        return ListadoFiltrosMemoriaSupport::restaurar(self::SESSION_FILTROS);
    }

    public static function olvidar(): void
    {
        ListadoFiltrosMemoriaSupport::olvidar(self::SESSION_FILTROS);
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?int $empresaDefault = null): array
    {
        $vista = (string) $request->query('vista', self::VISTA_PENDIENTES);
        if (! in_array($vista, self::VISTAS, true)) {
            $vista = self::VISTA_PENDIENTES;
        }
        $tab = (string) $request->query('tab', self::TAB_TODOS);
        if (! in_array($tab, [self::TAB_GASTRONOMIA, self::TAB_RESTO, self::TAB_TODOS], true)) {
            $tab = self::TAB_TODOS;
        }
        $atajo = trim((string) $request->query('atajo', ''));
        if ($atajo !== '' && ! in_array($atajo, self::ATAJOS, true)) {
            $atajo = '';
        }
        // Cargar factura es trabajo de CxP. Pendientes+Listo para cargar dejaba
        // vacía la grilla: el envío ya sacó el legajo de COMPRAS.
        if ($atajo === self::ATAJO_LISTO_CARGAR && $vista === self::VISTA_PENDIENTES) {
            $vista = self::VISTA_CXP;
        }
        // Pendiente de entrega: el legajo puede estar en CxP o Pagos; mirar el circuito activo.
        if ($atajo === self::ATAJO_PENDIENTE_ENTREGA && $vista === self::VISTA_PENDIENTES) {
            $vista = self::VISTA_ESTADOS;
        }

        $listado = OrdencompraListadoFiltros::resolverDesdeRequest($request, null, $empresaDefault);

        // QBE / orden / agrupar con el catálogo de la bandeja (no el de OC).
        $qbe = ListadoQbeSupport::resolverDesdeRequest(
            $request,
            self::camposQbeDisponibles(),
            static fn (string $op, string $campoQbe): string => self::normalizarOperadorQbe($op, $campoQbe)
        );
        $sort = ListadoOrdenamientoSupport::resolverDesdeRequest($request, self::camposOrdenables());
        $agrupar = ListadoAgrupacionSupport::resolverDesdeRequest($request, self::camposOrdenables());
        if ($request->boolean('filtro_busqueda_rapida')) {
            $qbe = ListadoQbeSupport::vacio();
            $listado['modo'] = self::MODO_TODOS;
        } elseif (ListadoQbeSupport::tieneCriterios($qbe)) {
            $listado['modo'] = self::MODO_QBE;
        }
        $listado['qbe'] = $qbe;
        $listado['sort'] = $sort;
        $listado['agrupar'] = $agrupar;

        return array_merge($listado, [
            'vista' => $vista,
            'tab' => $tab,
            'atajo' => $atajo,
            'nro_oc' => trim((string) $request->query('nro_oc', '')),
            'nro_factura' => trim((string) $request->query('nro_factura', '')),
            'nro_com' => trim((string) $request->query('nro_com', '')),
            'nro_op' => trim((string) $request->query('nro_op', '')),
            'nro_cliente' => trim((string) $request->query('nro_cliente', '')),
        ]);
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposQbeDisponibles(): array
    {
        $out = [];
        foreach (OrdencompraLegajoBandejaListadoColumnas::camposFiltrables() as $key => $meta) {
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
        return OrdencompraLegajoBandejaListadoColumnas::camposOrdenables();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, scalar>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = OrdencompraListadoFiltros::paraQueryString($filtros);
        $params['vista'] = $filtros['vista'] ?? self::VISTA_PENDIENTES;
        $params['tab'] = $filtros['tab'] ?? self::TAB_TODOS;
        if (! empty($filtros['atajo'])) {
            $params['atajo'] = $filtros['atajo'];
        }
        if (! empty($filtros['nro_oc'])) {
            $params['nro_oc'] = $filtros['nro_oc'];
        }
        if (! empty($filtros['nro_factura'])) {
            $params['nro_factura'] = $filtros['nro_factura'];
        }
        if (! empty($filtros['nro_com'])) {
            $params['nro_com'] = $filtros['nro_com'];
        }
        if (! empty($filtros['nro_op'])) {
            $params['nro_op'] = $filtros['nro_op'];
        }
        if (! empty($filtros['nro_cliente'])) {
            $params['nro_cliente'] = $filtros['nro_cliente'];
        }

        // Reescribir qbe/sort/agrupar con el catálogo de bandeja (por si OC usó otras claves).
        $params = array_merge(
            $params,
            ListadoQbeSupport::paraQueryString(is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : []),
            ListadoOrdenamientoSupport::paraQueryString(is_array($filtros['sort'] ?? null) ? $filtros['sort'] : []),
            ListadoAgrupacionSupport::paraQueryString(is_array($filtros['agrupar'] ?? null) ? $filtros['agrupar'] : [])
        );
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
     * Chips fijos (empresa, presupuesto, estado bandeja, tab, atajo). Limpiar QBE no los pierde.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, scalar>
     */
    public static function paraQueryStringExternos(array $filtros): array
    {
        $params = self::paraQueryStringEmpresaYVista($filtros);
        if (! empty($filtros['presupuesto'])) {
            $params['presupuesto'] = $filtros['presupuesto'];
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, scalar>
     */
    public static function paraQueryStringEmpresaYVista(array $filtros): array
    {
        $params = OrdencompraListadoFiltros::paraQueryStringEmpresa($filtros);
        $params['vista'] = $filtros['vista'] ?? self::VISTA_PENDIENTES;
        $params['tab'] = $filtros['tab'] ?? self::TAB_TODOS;
        if (! empty($filtros['atajo'])) {
            $params['atajo'] = $filtros['atajo'];
        }

        return $params;
    }

    /**
     * Vista guardada no pisa chips fijos de bandeja (vista/tab/atajo/empresa/presupuesto/nros).
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $desdeVista
     * @return array<string, mixed>
     */
    public static function fusionarDesdeVista(array $base, array $desdeVista): array
    {
        $externos = [
            'vista' => $base['vista'] ?? self::VISTA_PENDIENTES,
            'tab' => $base['tab'] ?? self::TAB_TODOS,
            'atajo' => $base['atajo'] ?? '',
            'empresa_id' => $base['empresa_id'] ?? null,
            'empresa_scope' => $base['empresa_scope'] ?? 'una',
            'presupuesto' => $base['presupuesto'] ?? '',
            'nro_oc' => $base['nro_oc'] ?? '',
            'nro_factura' => $base['nro_factura'] ?? '',
            'nro_com' => $base['nro_com'] ?? '',
            'nro_op' => $base['nro_op'] ?? '',
            'nro_cliente' => $base['nro_cliente'] ?? '',
        ];

        $campos = self::camposOrdenables();
        $ordenVista = ListadoOrdenamientoSupport::normalizar($desdeVista['sort'] ?? $desdeVista['orden'] ?? [], $campos);
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

        if ($qbeExplicito || OrdencompraListadoFiltros::tieneCriteriosTexto($base) || $ordenBase !== [] || $agruparBase !== []) {
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
            self::camposQbeDisponibles(),
            static fn (string $op, string $campo): string => self::normalizarOperadorQbe($op, $campo)
        );
        $modo = (string) ($desdeVista['modo'] ?? self::MODO_TODOS);
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $modo = self::MODO_QBE;
        }

        return array_merge($base, $externos, [
            'modo' => $modo,
            'campo' => (string) ($desdeVista['campo'] ?? $base['campo'] ?? 'proveedor'),
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
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
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
                'campo' => 'fecha',
                'dir' => ListadoOrdenamientoSupport::DIR_DESC,
            ]);

            return;
        }

        $vista = (string) ($filtros['vista'] ?? self::VISTA_PENDIENTES);
        if ($vista === self::VISTA_CXP) {
            $query->reorder()->orderBy('ordencompra.id');

            return;
        }

        $query->reorder()
            ->orderByDesc('ordencompra.fecha')
            ->orderByDesc('ordencompra.id');
    }

    /**
     * Aplica solo el QBE de la bandeja (AND con chips fijos).
     *
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarQbeSiCorresponde(Builder $query, array $filtros): bool
    {
        $qbe = is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : [];
        if (! ListadoQbeSupport::tieneCriterios($qbe)) {
            return false;
        }

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
                        $q->orWhere(function (Builder $inner) use ($compiled, $criterio): void {
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

                self::aplicarEnCampoQbe(
                    $q,
                    (string) ($criterio['campo'] ?? 'proveedor'),
                    (string) ($criterio['op'] ?? 'contiene'),
                    trim((string) ($criterio['valor'] ?? '')),
                    trim((string) ($criterio['valor_hasta'] ?? '')),
                    $boolean
                );
            }
        );

        return true;
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     */
    private static function aplicarEnCampoQbe(
        Builder $query,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta = '',
        string $boolean = 'and'
    ): void {
        if ($boolean === ListadoQbeSupport::LOGIC_OR) {
            $query->orWhere(function (Builder $q) use ($campoKey, $operador, $valor, $valorHasta): void {
                self::aplicarEnCampoQbe($q, $campoKey, $operador, $valor, $valorHasta);
            });

            return;
        }

        $def = self::camposQbeDisponibles()[$campoKey] ?? null;
        if ($def === null) {
            return;
        }
        $type = (string) ($def['type'] ?? 'texto');
        $column = (string) $def['column'];
        if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
            return;
        }

        if ($type === 'entero') {
            self::aplicarEnteroQbe($query, $column, $operador, $valor, $valorHasta);

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

        self::aplicarTextoQbe($query, $column, $operador, $valor, $valorHasta);
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     */
    private static function aplicarTextoQbe(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta = ''
    ): void {
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column): void {
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
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
        switch ($operador) {
            case 'empieza':
                $query->where($column, 'like', $escaped.'%');
                break;
            case 'termina':
                $query->where($column, 'like', '%'.$escaped);
                break;
            case 'igual':
                $query->where($column, '=', $valor);
                break;
            case 'distinto':
                $query->where($column, '!=', $valor);
                break;
            case 'mayor':
            case 'mayor_igual':
            case 'menor':
            case 'menor_igual':
                $opSql = match ($operador) {
                    'mayor' => '>',
                    'mayor_igual' => '>=',
                    'menor' => '<',
                    default => '<=',
                };
                $query->where($column, $opSql, $valor);
                break;
            case 'no_contiene':
                $query->where(function ($q) use ($column, $escaped): void {
                    $q->whereNull($column)->orWhere($column, 'not like', '%'.$escaped.'%');
                });
                break;
            case 'contiene':
            default:
                $query->where(function ($q) use ($column, $valor, $escaped): void {
                    $q->where($column, 'like', '%'.$escaped.'%');
                    CoincidenciaFlexibleTexto::aplicar($q, $column, $valor, false);
                });
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     */
    private static function aplicarEnteroQbe(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta = ''
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
            case 'distinto':
                $query->where($column, '!=', $id);
                break;
            case 'igual':
            default:
                $query->where($column, '=', $id);
                break;
        }
    }

    private static function normalizarOperadorQbe(string $operador, string $campoKey): string
    {
        $type = self::camposQbeDisponibles()[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO_QBE),
            'fecha' => array_keys(self::OPERADORES_FECHA_QBE),
            'decimal' => array_keys(self::OPERADORES_DECIMAL),
            'booleano' => array_keys(self::OPERADORES_BOOLEANO),
            default => array_keys(self::OPERADORES_TEXTO_QBE),
        };

        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }
}
