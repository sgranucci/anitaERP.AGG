<?php

declare(strict_types=1);

namespace App\Support\Produccion;

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
 * Filtros del workbench de movimientos de OT.
 *
 * El estado de tareas (en curso / terminada / facturada / todas) queda
 * por encima del QBE y una vista guardada no lo pisa.
 */
class MovimientoOrdentrabajoListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const MODO_QBE = 'qbe';

    public const ESTADO_EN_CURSO = 'en_curso';

    public const ESTADO_TERMINADA = 'terminada';

    public const ESTADO_FACTURADA = 'facturada';

    public const ESTADO_TODAS = 'todas';

    /** @var list<string> */
    public const ESTADOS = [
        self::ESTADO_EN_CURSO,
        self::ESTADO_TERMINADA,
        self::ESTADO_FACTURADA,
        self::ESTADO_TODAS,
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'tarea.nombre',
        'operacion.nombre',
        'empleado.nombre',
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

    private const CAMPO_DEFAULT = 'empleado';

    /**
     * @return array<string, array{column: string, type: string, label: string, attr: string}>
     */
    public static function campos(): array
    {
        $out = [];
        foreach (MovimientoOrdentrabajoListadoColumnas::camposFiltrables() as $key => $meta) {
            $out[$key] = [
                'column' => $meta['column'],
                'type' => $meta['type'],
                'label' => $meta['label'],
                'attr' => $meta['attr'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{column: string, type: string, label: string, attr: string}>
     */
    public static function camposQbeDisponibles(): array
    {
        return self::campos();
    }

    /**
     * @return array<string, array{label: string, type: string, column: string, attr: string}>
     */
    public static function camposOrdenables(): array
    {
        return MovimientoOrdentrabajoListadoColumnas::camposOrdenables();
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $ordentrabajoId = null): array
    {
        $estado = self::normalizarEstado((string) $request->input('filtro_estado_tarea', self::ESTADO_EN_CURSO));
        $otId = $ordentrabajoId && $ordentrabajoId > 0
            ? $ordentrabajoId
            : (int) $request->input('ordentrabajo_id', 0);
        $externos = [
            'estado_tarea' => $estado,
            'ordentrabajo_id' => $otId > 0 ? $otId : null,
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

    public static function normalizarEstado(string $estado): string
    {
        return in_array($estado, self::ESTADOS, true) ? $estado : self::ESTADO_EN_CURSO;
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
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => self::CAMPO_DEFAULT,
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'estado_tarea' => self::ESTADO_EN_CURSO,
            'ordentrabajo_id' => null,
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
                $params['filtro_campo'] = $filtros['campo'] ?? self::CAMPO_DEFAULT;
                $params['filtro_operador'] = $filtros['operador'] ?? 'contiene';
            }
            if (! empty($filtros['valor'])) {
                $params['filtro_valor'] = $filtros['valor'];
            }
            if (! empty($filtros['valor_hasta'])) {
                $params['filtro_valor_hasta'] = $filtros['valor_hasta'];
            }
        }

        $estado = self::normalizarEstado((string) ($filtros['estado_tarea'] ?? self::ESTADO_EN_CURSO));
        $params['filtro_estado_tarea'] = $estado;
        if (! empty($filtros['ordentrabajo_id'])) {
            $params['ordentrabajo_id'] = (int) $filtros['ordentrabajo_id'];
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
            'estado_tarea' => self::normalizarEstado((string) ($base['estado_tarea'] ?? self::ESTADO_EN_CURSO)),
            'ordentrabajo_id' => ! empty($base['ordentrabajo_id']) ? (int) $base['ordentrabajo_id'] : null,
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
     * @param  Builder<\App\Models\Produccion\MovimientoOrdentrabajo>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['ordentrabajo_id'])) {
            $query->where('movimientoordentrabajo.ordentrabajo_id', (int) $filtros['ordentrabajo_id']);
        }

        self::aplicarEstadoTarea($query, self::normalizarEstado((string) ($filtros['estado_tarea'] ?? self::ESTADO_EN_CURSO)));

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
                (string) ($filtros['campo'] ?? self::CAMPO_DEFAULT),
                $operador,
                $valor,
                (string) ($filtros['valor_hasta'] ?? '')
            );

            return;
        }

        self::aplicarBusquedaGlobal($query, $valor);
    }

    /**
     * En curso: la OT no tiene Terminada ni Facturada.
     * Terminada: tiene Terminada y todavía no Facturada.
     * Facturada: tiene Facturada.
     * Todas: no agrega el antijoin (recorrido por id).
     *
     * @param  Builder<\App\Models\Produccion\MovimientoOrdentrabajo>  $query
     */
    public static function aplicarEstadoTarea(Builder $query, string $estado): void
    {
        if ($estado === self::ESTADO_TODAS) {
            return;
        }

        $terminada = (int) config('consprod.TAREA_TERMINADA');
        $facturada = (int) config('consprod.TAREA_FACTURADA');
        $cierre = DB::table('ordentrabajo_tarea')
            ->select('ordentrabajo_id')
            ->selectRaw('MAX(CASE WHEN tarea_id = ? THEN 1 ELSE 0 END) as tiene_terminada', [$terminada])
            ->selectRaw('MAX(CASE WHEN tarea_id = ? THEN 1 ELSE 0 END) as tiene_facturada', [$facturada])
            ->whereIn('tarea_id', [$terminada, $facturada])
            ->groupBy('ordentrabajo_id');

        $query->leftJoinSub($cierre, 'cierre_ot', 'cierre_ot.ordentrabajo_id', '=', 'movimientoordentrabajo.ordentrabajo_id');

        if ($estado === self::ESTADO_FACTURADA) {
            $query->where('cierre_ot.tiene_facturada', 1);

            return;
        }
        if ($estado === self::ESTADO_TERMINADA) {
            $query->where('cierre_ot.tiene_terminada', 1)
                ->where(function ($w) {
                    $w->whereNull('cierre_ot.tiene_facturada')
                        ->orWhere('cierre_ot.tiene_facturada', 0);
                });

            return;
        }

        $query->whereNull('cierre_ot.ordentrabajo_id');
    }

    /**
     * @param  Builder<\App\Models\Produccion\MovimientoOrdentrabajo>  $query
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

        $query->orderByDesc('movimientoordentrabajo.id');
    }

    /**
     * @param  Builder<\App\Models\Produccion\MovimientoOrdentrabajo>  $query
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
     * @param  Builder<\App\Models\Produccion\MovimientoOrdentrabajo>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $valor): void
    {
        if ($valor === '') {
            return;
        }

        $id = ctype_digit($valor) ? (int) $valor : null;
        $fecha = ListadoQbeSupport::normalizarFecha($valor);

        $query->where(function ($q) use ($valor, $id, $fecha) {
            if ($id !== null && $id > 0) {
                $q->orWhere('movimientoordentrabajo.id', $id);
                $otIds = DB::table('ordentrabajo')->where('codigo', $id)->pluck('id');
                if ($otIds->isNotEmpty()) {
                    $q->orWhereIn('movimientoordentrabajo.ordentrabajo_id', $otIds->all());
                }
            }
            foreach ([
                'empleado' => 'movimientoordentrabajo.empleado_id',
                'tarea' => 'movimientoordentrabajo.tarea_id',
                'operacion' => 'movimientoordentrabajo.operacion_id',
            ] as $tabla => $fk) {
                $ids = self::idsMaestroPorTexto($tabla, 'nombre', 'contiene', $valor);
                if ($ids !== []) {
                    $q->orWhereIn($fk, $ids);
                }
            }
            if ($fecha !== null) {
                $hasta = date('Y-m-d', strtotime($fecha.' +1 day'));
                $q->orWhere(function ($w) use ($fecha, $hasta) {
                    $w->where('movimientoordentrabajo.fecha', '>=', $fecha.' 00:00:00')
                        ->where('movimientoordentrabajo.fecha', '<', $hasta.' 00:00:00');
                });
            }
            $valorNorm = mb_strtolower($valor);
            foreach (\App\Traits\Produccion\MovimientoOrdenTrabajoTrait::$enumEstado as $cod => $nombre) {
                if ($valorNorm !== '' && mb_strtolower($nombre) === $valorNorm) {
                    $q->orWhere('movimientoordentrabajo.estado', $cod);
                }
            }
        });
    }

    /**
     * @param  Builder<\App\Models\Produccion\MovimientoOrdentrabajo>  $query
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

        self::aplicarTexto($query, $column, $operador, $valor, $valorHasta);
    }

    /**
     * @param  Builder<\App\Models\Produccion\MovimientoOrdentrabajo>  $query
     */
    private static function aplicarTexto(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta
    ): void {
        $maestro = self::maestroDeColumna($column);
        if ($column === 'movimientoordentrabajo.estado') {
            if ($operador === 'vacio') {
                $query->where(function ($q) use ($column) {
                    $q->whereNull($column)->orWhere($column, '');
                });

                return;
            }
            $codigos = [];
            $valorNorm = mb_strtolower(trim($valor));
            if ($valorNorm === '') {
                return;
            }
            foreach (\App\Traits\Produccion\MovimientoOrdenTrabajoTrait::$enumEstado as $cod => $nombre) {
                $nombreNorm = mb_strtolower($nombre);
                $codNorm = mb_strtolower((string) $cod);
                $coincide = $operador === 'igual'
                    ? $nombreNorm === $valorNorm || $codNorm === $valorNorm
                    : str_contains($nombreNorm, $valorNorm) || str_contains($codNorm, $valorNorm);
                if ($coincide) {
                    $codigos[] = $cod;
                }
            }
            if ($codigos === []) {
                $query->whereRaw('1 = 0');

                return;
            }
            if ($operador === 'no_contiene' || $operador === 'distinto') {
                $query->whereNotIn($column, $codigos);

                return;
            }
            $query->whereIn($column, $codigos);

            return;
        }
        if ($maestro !== null) {
            if ($operador === 'vacio') {
                $query->where(function ($q) use ($maestro) {
                    $q->whereNull($maestro['fk'])
                        ->orWhereIn($maestro['fk'], self::idsMaestroPorTexto($maestro['tabla'], $maestro['columna'], 'vacio', ''));
                });

                return;
            }
            if ($valor === '' && $operador !== 'entre') {
                return;
            }
            $ids = self::idsMaestroPorTexto($maestro['tabla'], $maestro['columna'], $operador, $valor, $valorHasta);
            if ($ids === []) {
                $query->whereRaw('1 = 0');

                return;
            }
            if ($operador === 'no_contiene' || $operador === 'distinto') {
                $query->whereNotIn($maestro['fk'], $ids);

                return;
            }
            $query->whereIn($maestro['fk'], $ids);

            return;
        }

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
            'distinto' => $query->where($column, '!=', $valor),
            'mayor' => $query->where($column, '>', $valor),
            'mayor_igual' => $query->where($column, '>=', $valor),
            'menor' => $query->where($column, '<', $valor),
            'menor_igual' => $query->where($column, '<=', $valor),
            'no_contiene' => $query->where($column, 'not like', $like),
            default => $query->where($column, 'like', $like),
        };
    }

    /**
     * @param  Builder<\App\Models\Produccion\MovimientoOrdentrabajo>  $query
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

    /**
     * @return array{tabla: string, columna: string, fk: string}|null
     */
    private static function maestroDeColumna(string $column): ?array
    {
        return match ($column) {
            'empleado.nombre' => ['tabla' => 'empleado', 'columna' => 'nombre', 'fk' => 'movimientoordentrabajo.empleado_id'],
            'tarea.nombre' => ['tabla' => 'tarea', 'columna' => 'nombre', 'fk' => 'movimientoordentrabajo.tarea_id'],
            'operacion.nombre' => ['tabla' => 'operacion', 'columna' => 'nombre', 'fk' => 'movimientoordentrabajo.operacion_id'],
            'ordentrabajo.codigo' => ['tabla' => 'ordentrabajo', 'columna' => 'codigo', 'fk' => 'movimientoordentrabajo.ordentrabajo_id'],
            default => null,
        };
    }

    /**
     * @return list<int>
     */
    private static function idsMaestroPorTexto(
        string $tabla,
        string $columna,
        string $operador,
        string $valor,
        string $valorHasta = ''
    ): array {
        $q = DB::table($tabla);
        $column = $tabla.'.'.$columna;
        if ($operador === 'vacio') {
            $q->where(function ($w) use ($column) {
                $w->whereNull($column)->orWhere($column, '');
            });
        } elseif ($operador === 'entre') {
            if (trim($valor) !== '') {
                $q->where($column, '>=', $valor);
            }
            if (trim($valorHasta) !== '') {
                $q->where($column, '<=', $valorHasta);
            }
        } elseif ($operador === 'igual' || $operador === 'distinto') {
            $q->where($column, '=', $valor);
        } elseif (in_array($operador, ['mayor', 'mayor_igual', 'menor', 'menor_igual'], true)) {
            $op = match ($operador) {
                'mayor' => '>',
                'mayor_igual' => '>=',
                'menor' => '<',
                default => '<=',
            };
            $q->where($column, $op, $valor);
        } else {
            $q->where(function ($w) use ($column, $operador, $valor) {
                $w->where($column, 'like', self::patronLike($operador, $valor));
                if ($operador === 'contiene' && in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $w,
                        $column,
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            });
        }

        return $q->pluck('id')->map(fn ($id) => (int) $id)->all();
    }
}
