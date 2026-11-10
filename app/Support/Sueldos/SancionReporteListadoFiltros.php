<?php

namespace App\Support\Sueldos;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros de la consulta de sanciones.
 *
 * Empresas, fechas, apellido, legajo, estado, tipo y motivo quedan por encima
 * del QBE y se combinan con AND. Una vista guardada no los pisa.
 */
class SancionReporteListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const MODO_QBE = 'qbe';

    public const PREFERENCIAS_CLAVE = 'sancion-reporte-sueldos';

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'empleado_sueldos.nombre',
        'tipo_sancion_sueldos.nombre',
        'motivo_sancion_sueldos.nombre',
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

    private const CAMPO_DEFAULT = 'nombre';

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        $out = [];
        foreach (SancionReporteListadoColumnas::camposFiltrables() as $key => $meta) {
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
        return SancionReporteListadoColumnas::camposOrdenables();
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $externos = self::resolverExterno($request);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_ids' => [],
                'consolidar_empresas' => true,
                '_limpiar' => true,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, null);
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

    /**
     * @return array<string, mixed>
     */
    private static function resolverExterno(Request $request): array
    {
        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($empresaIds === [] && (int) $request->input('empresa_id', 0) > 0) {
            $empresaIds = [(int) $request->input('empresa_id')];
        }

        $envio = $request->exists('fecha_desde') || $request->exists('fecha_hasta') || $request->has('empresa_ids') || $request->filled('empresa_id');
        $fechaDesde = $envio
            ? trim((string) $request->input('fecha_desde', ''))
            : date('Y-01-01');
        $fechaHasta = $envio
            ? trim((string) $request->input('fecha_hasta', ''))
            : date('Y-m-d');

        $estado = trim((string) $request->input('estado', ''));
        if ($estado !== '' && ! isset(EmpleadoSancionSupport::ESTADOS[$estado])) {
            $estado = '';
        }

        $consolidar = $request->exists('consolidar_empresas')
            ? $request->boolean('consolidar_empresas')
            : true;

        return [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $consolidar,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'apellido' => trim((string) $request->input('apellido', '')),
            'tipo_sancion_id' => (int) $request->input('tipo_sancion_id', 0) ?: null,
            'motivo_sancion_id' => (int) $request->input('motivo_sancion_id', 0) ?: null,
            'estado' => $estado,
            'legajo_desde' => trim((string) $request->input('legajo_desde', '')),
            'legajo_hasta' => trim((string) $request->input('legajo_hasta', '')),
            'incluir_comentario' => $request->boolean('incluir_comentario'),
        ];
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

        return false;
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
            'empresa_ids' => [],
            'consolidar_empresas' => true,
            'fecha_desde' => date('Y-01-01'),
            'fecha_hasta' => date('Y-m-d'),
            'apellido' => '',
            'tipo_sancion_id' => null,
            'motivo_sancion_id' => null,
            'estado' => '',
            'legajo_desde' => '',
            'legajo_hasta' => '',
            'incluir_comentario' => false,
            'qbe' => ListadoQbeSupport::vacio(),
            'sort' => [],
            'agrupar' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros, bool $consultar = false): array
    {
        $params = [
            'fecha_desde' => $filtros['fecha_desde'] ?? '',
            'fecha_hasta' => $filtros['fecha_hasta'] ?? '',
            'consolidar_empresas' => ! empty($filtros['consolidar_empresas']) ? 1 : 0,
        ];
        foreach ($filtros['empresa_ids'] ?? [] as $empresaId) {
            $params['empresa_ids'][] = (int) $empresaId;
        }
        if (($filtros['apellido'] ?? '') !== '') {
            $params['apellido'] = $filtros['apellido'];
        }
        if (! empty($filtros['tipo_sancion_id'])) {
            $params['tipo_sancion_id'] = (int) $filtros['tipo_sancion_id'];
        }
        if (! empty($filtros['motivo_sancion_id'])) {
            $params['motivo_sancion_id'] = (int) $filtros['motivo_sancion_id'];
        }
        if (($filtros['estado'] ?? '') !== '') {
            $params['estado'] = $filtros['estado'];
        }
        if (($filtros['legajo_desde'] ?? '') !== '') {
            $params['legajo_desde'] = $filtros['legajo_desde'];
        }
        if (($filtros['legajo_hasta'] ?? '') !== '') {
            $params['legajo_hasta'] = $filtros['legajo_hasta'];
        }
        if (! empty($filtros['incluir_comentario'])) {
            $params['incluir_comentario'] = 1;
        }

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

        if ($consultar) {
            $params['consultar'] = 1;
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
     * Los filtros de pantalla mandan sobre la vista guardada.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $desdeVista
     * @return array<string, mixed>
     */
    public static function fusionarDesdeVista(array $base, array $desdeVista): array
    {
        $externos = [
            'empresa_ids' => $base['empresa_ids'] ?? [],
            'consolidar_empresas' => $base['consolidar_empresas'] ?? true,
            'fecha_desde' => $base['fecha_desde'] ?? '',
            'fecha_hasta' => $base['fecha_hasta'] ?? '',
            'apellido' => $base['apellido'] ?? '',
            'tipo_sancion_id' => $base['tipo_sancion_id'] ?? null,
            'motivo_sancion_id' => $base['motivo_sancion_id'] ?? null,
            'estado' => $base['estado'] ?? '',
            'legajo_desde' => $base['legajo_desde'] ?? '',
            'legajo_hasta' => $base['legajo_hasta'] ?? '',
            'incluir_comentario' => $base['incluir_comentario'] ?? false,
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
     * Consolidar apagado separa el listado por empresa.
     *
     * @param  array<string, mixed>  $filtros
     * @return list<string>
     */
    public static function agruparEfectivo(array $filtros): array
    {
        $campos = self::camposOrdenables();
        $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
        if (! empty($filtros['consolidar_empresas']) || in_array('empresa', $agrupar, true)) {
            return $agrupar;
        }
        array_unshift($agrupar, 'empresa');

        return array_slice($agrupar, 0, ListadoAgrupacionSupport::MAX_NIVELES);
    }

    public static function subtitulo(array $filtros): string
    {
        $partes = [];
        if (($filtros['fecha_desde'] ?? '') !== '') {
            $partes[] = 'Desde '.$filtros['fecha_desde'];
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $partes[] = 'Hasta '.$filtros['fecha_hasta'];
        }
        if (($filtros['apellido'] ?? '') !== '') {
            $partes[] = 'Apellido: '.$filtros['apellido'];
        }
        if (($filtros['estado'] ?? '') !== '') {
            $partes[] = 'Estado: '.EmpleadoSancionSupport::etiquetaEstado($filtros['estado']);
        }
        $empresas = $filtros['empresa_ids'] ?? [];
        if (is_array($empresas) && count($empresas) > 1) {
            $partes[] = count($empresas).' empresas';
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Empleado_Sancion_Sueldos>  $query
     */
    public static function aplicarOrden(Builder $query, array $filtros): void
    {
        $campos = self::camposOrdenables();
        $agrupar = self::agruparEfectivo($filtros);
        $sort = ListadoOrdenamientoSupport::normalizar($filtros['sort'] ?? [], $campos);
        if ($agrupar !== []) {
            ListadoAgrupacionSupport::aplicarOrdenPrefijo($query, $agrupar, $campos);
        }
        if ($sort !== []) {
            ListadoOrdenamientoSupport::aplicar($query, $sort, $campos, [
                'campo' => 'fecha_hecho',
                'dir' => ListadoOrdenamientoSupport::DIR_DESC,
            ]);

            return;
        }

        $query->orderBy('empleado_sueldos.legajo')
            ->orderBy('empleado_sancion_sueldos.fecha_hecho')
            ->orderBy('empleado_sancion_sueldos.id');
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Empleado_Sancion_Sueldos>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $empresaIds = array_values(array_filter(array_map('intval', $filtros['empresa_ids'] ?? [])));
        if ($empresaIds !== []) {
            $query->whereIn('empleado_sueldos.empresa_id', $empresaIds);
        }
        if (($filtros['fecha_desde'] ?? '') !== '') {
            $query->whereDate('empleado_sancion_sueldos.fecha_hecho', '>=', $filtros['fecha_desde']);
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $query->whereDate('empleado_sancion_sueldos.fecha_hecho', '<=', $filtros['fecha_hasta']);
        }
        if (! empty($filtros['tipo_sancion_id'])) {
            $query->where('empleado_sancion_sueldos.tipo_sancion_id', (int) $filtros['tipo_sancion_id']);
        }
        if (! empty($filtros['motivo_sancion_id'])) {
            $query->where('empleado_sancion_sueldos.motivo_sancion_id', (int) $filtros['motivo_sancion_id']);
        }
        if (($filtros['estado'] ?? '') !== '') {
            $query->where('empleado_sancion_sueldos.estado', $filtros['estado']);
        }
        if (($filtros['legajo_desde'] ?? '') !== '') {
            $query->where('empleado_sueldos.legajo', '>=', (int) $filtros['legajo_desde']);
        }
        if (($filtros['legajo_hasta'] ?? '') !== '') {
            $query->where('empleado_sueldos.legajo', '<=', (int) $filtros['legajo_hasta']);
        }
        $apellido = trim((string) ($filtros['apellido'] ?? ''));
        if ($apellido !== '') {
            $like = '%'.self::escapeLike($apellido).'%';
            $query->where(function ($q) use ($apellido, $like) {
                $q->where('empleado_sueldos.nombre', 'like', $like);
                CoincidenciaFlexibleTexto::aplicar(
                    $q,
                    'empleado_sueldos.nombre',
                    $apellido,
                    true,
                    CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                );
            });
        }
        if (! empty($filtros['incluir_comentario'])) {
            $query->whereNotNull('empleado_sancion_sueldos.comentario')
                ->where('empleado_sancion_sueldos.comentario', '!=', '');
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
            self::aplicarEnCampo($query, $filtros['campo'] ?? self::CAMPO_DEFAULT, $operador, $valor, (string) ($filtros['valor_hasta'] ?? ''));

            return;
        }

        self::aplicarBusquedaGlobal($query, $valor);
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Empleado_Sancion_Sueldos>  $query
     * @param  array<string, mixed>  $qbe
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
     * @param  Builder<\App\Models\Sueldos\Empleado_Sancion_Sueldos>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $valor): void
    {
        if ($valor === '') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = '%'.self::escapeLike($valor).'%';
        $query->where(function ($q) use ($valor, $like, $id) {
            if ($id !== false) {
                $q->orWhere('empleado_sueldos.legajo', (int) $id);
                $q->orWhere('empleado_sancion_sueldos.nro_interno', (int) $id);
            }
            $q->orWhere('empleado_sueldos.nombre', 'like', $like);
            $q->orWhere('tipo_sancion_sueldos.nombre', 'like', $like);
            $q->orWhere('motivo_sancion_sueldos.nombre', 'like', $like);
            CoincidenciaFlexibleTexto::aplicar(
                $q,
                'empleado_sueldos.nombre',
                $valor,
                true,
                CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
            );
        });
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Empleado_Sancion_Sueldos>  $query
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

        if ($campoKey === 'estado') {
            self::aplicarEstado($query, $operador, $valor);

            return;
        }

        $def = self::campos()[$campoKey] ?? self::campos()[self::CAMPO_DEFAULT];
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
     * @param  Builder<\App\Models\Sueldos\Empleado_Sancion_Sueldos>  $query
     */
    private static function aplicarEstado(Builder $query, string $operador, string $valor): void
    {
        $column = 'empleado_sancion_sueldos.estado';
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        $codigos = self::codigosEstado($operador, $valor);
        if ($codigos === []) {
            self::aplicarTexto($query, $column, $operador, $valor);

            return;
        }
        if (in_array($operador, ['distinto', 'no_contiene'], true)) {
            $query->whereNotIn($column, $codigos);

            return;
        }
        $query->whereIn($column, $codigos);
    }

    /**
     * @return list<string>
     */
    private static function codigosEstado(string $operador, string $valor): array
    {
        $needle = mb_strtolower(trim($valor));
        if ($needle === '') {
            return [];
        }
        $codigos = [];
        foreach (EmpleadoSancionSupport::ESTADOS as $codigo => $etiqueta) {
            $etiquetaMin = mb_strtolower($etiqueta);
            $codigoMin = mb_strtolower($codigo);
            $coincide = match ($operador) {
                'igual' => $etiquetaMin === $needle || $codigoMin === $needle,
                'empieza' => str_starts_with($etiquetaMin, $needle) || str_starts_with($codigoMin, $needle),
                'termina' => str_ends_with($etiquetaMin, $needle) || str_ends_with($codigoMin, $needle),
                'distinto', 'no_contiene', 'contiene' => str_contains($etiquetaMin, $needle) || str_contains($codigoMin, $needle),
                default => str_contains($etiquetaMin, $needle) || str_contains($codigoMin, $needle),
            };
            if ($coincide) {
                $codigos[] = $codigo;
            }
        }

        return $codigos;
    }

    /**
     * @param  Builder<\App\Models\Sueldos\Empleado_Sancion_Sueldos>  $query
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
     * @param  Builder<\App\Models\Sueldos\Empleado_Sancion_Sueldos>  $query
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
            default => array_keys(self::OPERADORES_TEXTO),
        };

        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }
}
