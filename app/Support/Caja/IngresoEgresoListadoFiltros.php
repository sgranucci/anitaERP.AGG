<?php

namespace App\Support\Caja;

use App\Models\Compras\Pagoproveedor;
use App\Support\Compras\PagoproveedorFacturaRelacion;
use App\Support\Compras\PagoproveedorListadoFiltros;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\ListadoRelacionCatalogo;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoQbeSupport;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de ingresos y egresos de caja (index).
 */
class IngresoEgresoListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const MODO_QBE = 'qbe';

    /** Fichas de período (una sola). Ayer queda solo en la consulta avanzada. */
    public const PERIODOS_FICHA = [
        '' => 'Todas las fechas',
        'hoy' => 'Hoy',
        'esta_semana' => 'Esta semana',
        'este_mes' => 'Este mes',
        'mes_anterior' => 'Mes anterior',
        'este_anio' => 'Este año',
    ];

    /** @var array<string, string> */
    public const OPERADORES_FECHA = ListadoQbeSupport::OPERADORES_FECHA;

    /** @var array<string, string> */
    public const OPERADORES_ENTERO_QBE = [
        'igual' => 'Es igual a',
        'mayor' => 'Mayor que',
        'mayor_igual' => 'Mayor o igual',
        'menor' => 'Menor que',
        'menor_igual' => 'Menor o igual',
        'entre' => 'Entre',
        'vacio' => 'Está vacío',
    ];

    /** @var array<string, string> */
    public const OPERADORES_BOOLEANO = [
        'igual' => 'Es',
        'vacio' => 'Sin dato',
    ];

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'caja_movimiento.id', 'type' => 'entero', 'label' => 'ID'],
        'numero' => ['column' => 'caja_movimiento.numerotransaccion', 'type' => 'entero', 'label' => 'Número'],
        'fecha' => ['column' => 'caja_movimiento.fecha', 'type' => 'texto', 'label' => 'Fecha'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'tipotransaccion' => ['column' => 'tipotransaccion_caja.nombre', 'type' => 'texto', 'label' => 'Tipo de transacción'],
        'abreviatura' => ['column' => 'tipotransaccion_caja.abreviatura', 'type' => 'texto', 'label' => 'Abreviatura tipo'],
        'concepto' => ['column' => 'conceptogasto.nombre', 'type' => 'texto', 'label' => 'Concepto'],
        'detalle' => ['column' => 'caja_movimiento.detalle', 'type' => 'texto', 'label' => 'Detalle'],
        'ordenservicio' => ['column' => 'caja_movimiento.ordenservicio_id', 'type' => 'entero', 'label' => 'Orden de servicio'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'empresa.nombre',
        'tipotransaccion_caja.nombre',
        'conceptogasto.nombre',
        'caja_movimiento.detalle',
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

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId,
                'empresa_scope' => $empresaScope,
                'tipos' => self::tiposDesdeRequest($request),
                'periodo' => self::periodoDesdeRequest($request),
                'mail' => PagoproveedorListadoFiltros::normalizarMail((string) $request->input('mail', '')),
                '_limpiar' => true,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        if ($valor === '' && $request->has('busqueda') && ! $request->has('filtro_valor')) {
            $valor = trim((string) $request->input('busqueda', ''));
        }
        if ($valor === '' && is_string($busquedaRuta) && $busquedaRuta !== '') {
            $valor = trim($busquedaRuta);
        }

        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO, self::MODO_QBE], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'detalle');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'detalle';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        $qbe = ListadoQbeSupport::normalizar(
            $request->input('qbe', []),
            self::camposQbe(),
            static fn (string $op, string $campoQbe): string => self::normalizarOperadorQbe($op, $campoQbe)
        );
        $sort = ListadoOrdenamientoSupport::normalizar($request->input('sort', []), self::camposOrdenables());
        $agrupar = ListadoAgrupacionSupport::normalizar($request->input('group', []), self::camposOrdenables());

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
            $qbe = ListadoQbeSupport::vacio();
        } elseif (ListadoQbeSupport::tieneCriterios($qbe)) {
            $modo = self::MODO_QBE;
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'detalle');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'solicitudpago_id' => max(0, (int) $request->input('solicitudpago_id', 0)) ?: null,
            'tipos' => self::tiposDesdeRequest($request),
            'periodo' => self::periodoDesdeRequest($request),
            'mail' => PagoproveedorListadoFiltros::normalizarMail((string) $request->input('mail', '')),
            'qbe' => $qbe,
            'sort' => $sort,
            'agrupar' => $agrupar,
        ];
    }

    /**
     * @return list<int>
     */
    public static function tiposDesdeRequest(Request $request): array
    {
        $raw = $request->input('tipos', $request->input('filtro_tipos', []));
        if (is_string($raw)) {
            $raw = preg_split('/\s*,\s*/', $raw) ?: [];
        }
        $tipos = [];
        foreach ((array) $raw as $id) {
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $tipos, true)) {
                $tipos[] = $id;
            }
        }

        return $tipos;
    }

    public static function periodoDesdeRequest(Request $request): string
    {
        $periodo = (string) $request->input('filtro_periodo', '');

        return array_key_exists($periodo, self::PERIODOS_FICHA) ? $periodo : '';
    }

    /**
     * @return array{0:?int,1:string}
     */
    public static function resolverEmpresaExterna(Request $request, ?int $empresaDefault): array
    {
        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            return [null, 'todas'];
        }
        if ($request->filled('empresa_id')) {
            return [(int) $request->input('empresa_id'), 'una'];
        }
        if ($empresaDefault !== null && $empresaDefault > 0) {
            return [$empresaDefault, 'una'];
        }

        return [null, 'todas'];
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

        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            return true;
        }

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            return true;
        }

        if (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            return true;
        }

        if (ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))) {
            return true;
        }

        return false;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (! empty($filtros['solicitudpago_id'])) {
            return true;
        }
        if (($filtros['tipos'] ?? []) !== []) {
            return true;
        }
        if (($filtros['periodo'] ?? '') !== '') {
            return true;
        }
        if (PagoproveedorListadoFiltros::normalizarMail((string) ($filtros['mail'] ?? '')) !== '') {
            return true;
        }

        return self::tieneCriteriosTexto($filtros);
    }

    public static function filtrosVacios(?int $empresaDefault = null): array
    {
        $base = [
            'modo' => self::MODO_TODOS,
            'campo' => 'detalle',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'busqueda_rapida' => false,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'solicitudpago_id' => null,
            'empresa_id' => null,
            'empresa_scope' => 'todas',
            'tipos' => [],
            'periodo' => '',
            'mail' => '',
            'qbe' => ListadoQbeSupport::vacio(),
            'sort' => [],
            'agrupar' => [],
        ];

        if ($empresaDefault !== null && $empresaDefault > 0) {
            $base['empresa_id'] = $empresaDefault;
            $base['empresa_scope'] = 'una';
        }

        return $base;
    }

    /**
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);

        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'detalle';
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
        if (! empty($filtros['fecha_desde'])) {
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (! empty($filtros['solicitudpago_id'])) {
            $params['solicitudpago_id'] = (int) $filtros['solicitudpago_id'];
        }
        if (($filtros['tipos'] ?? []) !== []) {
            $params['tipos'] = array_values($filtros['tipos']);
        }
        if (($filtros['periodo'] ?? '') !== '') {
            $params['filtro_periodo'] = $filtros['periodo'];
        }
        $mail = PagoproveedorListadoFiltros::normalizarMail((string) ($filtros['mail'] ?? ''));
        if ($mail !== '') {
            $params['mail'] = $mail;
        }
        foreach (self::normalizarCalculadas($filtros['calculadas'] ?? []) as $i => $calc) {
            $params['calculadas'][$i] = [
                'etiqueta' => $calc['etiqueta'],
                'formula' => $calc['formula'],
            ];
        }

        $params = array_merge($params, ListadoQbeSupport::paraQueryString((array) ($filtros['qbe'] ?? [])));
        $params = array_merge($params, ListadoOrdenamientoSupport::paraQueryString((array) ($filtros['sort'] ?? [])));
        $params = array_merge($params, ListadoAgrupacionSupport::paraQueryString((array) ($filtros['agrupar'] ?? [])));

        return $params;
    }

    /**
     * @return array<string, int>
     */
    public static function paraQueryStringEmpresa(array $filtros): array
    {
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            return ['empresa_todas' => 1];
        }
        if (! empty($filtros['empresa_id'])) {
            return ['empresa_id' => (int) $filtros['empresa_id']];
        }

        return [];
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('caja_movimiento.empresa_id', (int) $filtros['empresa_id']);
        }

        if (! empty($filtros['solicitudpago_id'])) {
            $query->where('caja_movimiento.solicitudpago_id', (int) $filtros['solicitudpago_id']);
        }

        if (($filtros['fecha_desde'] ?? '') !== '') {
            $query->whereDate('caja_movimiento.fecha', '>=', $filtros['fecha_desde']);
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $query->whereDate('caja_movimiento.fecha', '<=', $filtros['fecha_hasta']);
        }

        $tipos = array_values(array_filter(array_map('intval', (array) ($filtros['tipos'] ?? []))));
        if ($tipos !== []) {
            $query->whereIn('caja_movimiento.tipotransaccion_caja_id', $tipos);
        }

        $rango = ListadoQbeSupport::rangoPeriodo((string) ($filtros['periodo'] ?? ''));
        if ($rango !== null) {
            $hastaExclusivo = (new DateTimeImmutable($rango[1]))->modify('+1 day')->format('Y-m-d');
            $query->where('caja_movimiento.fecha', '>=', $rango[0].' 00:00:00');
            $query->where('caja_movimiento.fecha', '<', $hastaExclusivo.' 00:00:00');
        }

        self::aplicarMailPagoProveedor($query, $filtros);

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        if ($modo === self::MODO_QBE) {
            self::aplicarQbe($query, (array) ($filtros['qbe'] ?? []));

            return;
        }

        if (! self::tieneCriteriosTextoParaBusqueda($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'detalle', $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * Criterios de texto/campo (sin rango de fechas, que ya se aplicó aparte).
     */
    private static function tieneCriteriosTextoParaBusqueda(array $filtros): bool
    {
        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO
            && ($filtros['operador'] ?? 'contiene') !== 'contiene') {
            return true;
        }

        return false;
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['caja_movimiento.detalle', 'conceptogasto.nombre'] as $col) {
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
        $esIguassu = config('app.empresa') === 'Iguassu Travel';

        $query->where(function ($q) use ($valor, $like, $id, $operador, $esIguassu) {
            if ($id !== false) {
                $q->orWhere('caja_movimiento.id', (int) $id)
                    ->orWhere('caja_movimiento.numerotransaccion', (int) $id);
                if ($esIguassu) {
                    $q->orWhere('caja_movimiento.ordenservicio_id', (int) $id);
                }
            }

            $textCols = [
                'empresa.nombre',
                'tipotransaccion_caja.nombre',
                'tipotransaccion_caja.abreviatura',
                'caja_movimiento.detalle',
                'conceptogasto.nombre',
                'caja_movimiento.fecha',
            ];
            foreach ($textCols as $col) {
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

            // Abreviaturas cortas (OP → OPP): también match exacto case-insensitive
            if ($operador === 'contiene' || $operador === 'igual' || $operador === 'empieza') {
                $q->orWhereRaw('UPPER(tipotransaccion_caja.abreviatura) = ?', [strtoupper($valor)]);
                $q->orWhereRaw('UPPER(tipotransaccion_caja.abreviatura) LIKE ?', [strtoupper(self::escapeLike($valor)).'%']);
            }

            if ($operador === 'igual' || $operador === 'contiene') {
                $q->orWhere('caja_movimiento.numerotransaccion', $valor);
            }
        });
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        // Tipo: nombre o abreviatura (OP / OPP / "Orden de pago")
        if ($campoKey === 'tipotransaccion') {
            if ($operador === 'vacio') {
                $query->where(function ($q) {
                    $q->where(function ($w) {
                        $w->whereNull('tipotransaccion_caja.nombre')->orWhere('tipotransaccion_caja.nombre', '');
                    })->where(function ($w) {
                        $w->whereNull('tipotransaccion_caja.abreviatura')->orWhere('tipotransaccion_caja.abreviatura', '');
                    });
                });

                return;
            }
            if ($valor === '') {
                return;
            }

            $query->where(function ($q) use ($operador, $valor) {
                self::aplicarTexto($q, 'tipotransaccion_caja.nombre', $operador, $valor);
                $q->orWhere(function ($w) use ($operador, $valor) {
                    self::aplicarTexto($w, 'tipotransaccion_caja.abreviatura', $operador, $valor);
                });
            });

            return;
        }

        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['detalle'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
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
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
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

    /**
     * Mail al proveedor solo en OPP y OPA que tienen orden de pago.
     *
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     * @param  array<string, mixed>  $filtros
     */
    private static function aplicarMailPagoProveedor(Builder $query, array $filtros): void
    {
        $mail = PagoproveedorListadoFiltros::normalizarMail((string) ($filtros['mail'] ?? ''));
        if ($mail === '') {
            return;
        }

        $query->whereRaw('UPPER(TRIM(tipotransaccion_caja.abreviatura)) IN (?, ?)', ['OPP', 'OPA'])
            ->whereNotNull('caja_movimiento.pagoproveedor_id');

        $prefijo = Pagoproveedor::PREFIJO_OBSERVACION_ENVIO_CORREO.'%';
        $existe = function ($q) use ($prefijo) {
            $q->selectRaw('1')
                ->from('pagoproveedor_estado as pe_mail')
                ->whereColumn('pe_mail.pagoproveedor_id', 'caja_movimiento.pagoproveedor_id')
                ->where('pe_mail.observacion', 'like', $prefijo);
        };

        if ($mail === 'enviado') {
            $query->whereExists($existe);

            return;
        }

        $query->whereNotExists($existe);
    }

    /**
     * Campos visibles en el panel (oculta OS fuera de Iguassu).
     *
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposParaVista(): array
    {
        $campos = self::CAMPOS;
        if (config('app.empresa') !== 'Iguassu Travel') {
            unset($campos['ordenservicio']);
        }

        return $campos;
    }

    /**
     * @return array<string, array{label: string, type: string, column: string, group?: string}>
     */
    public static function camposQbe(): array
    {
        return array_merge(
            IngresoEgresoListadoColumnas::camposFiltrables(),
            PagoproveedorFacturaRelacion::campos()
        );
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return IngresoEgresoListadoColumnas::camposOrdenables();
    }

    /**
     * Ejes del gráfico más las medidas que se calculan en memoria (ya en pesos).
     *
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposVisual(): array
    {
        $out = self::camposOrdenables();
        $out['ingresos'] = [
            'label' => 'Ingresos',
            'type' => 'decimal',
            'column' => '',
        ];
        $out['egresos'] = [
            'label' => 'Egresos',
            'type' => 'decimal',
            'column' => '',
        ];

        return $out;
    }

    public static function camposQbeDisponibles(): array
    {
        return self::camposQbe();
    }

    /**
     * Campos que una fórmula puede mostrar. Ingresos y egresos en pesos no están en el SQL.
     *
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposFormula(): array
    {
        $out = [];
        foreach (self::camposQbe() as $key => $meta) {
            $column = (string) ($meta['column'] ?? '');
            if ($column === '' || ! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
                continue;
            }
            $out[$key] = $meta;
        }

        return $out;
    }

    /**
     * @return list<array{etiqueta: string, formula: string, valida: bool}>
     */
    public static function normalizarCalculadas(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $campos = self::camposFormula();
        $out = [];
        foreach ($raw as $fila) {
            if (count($out) >= 2 || ! is_array($fila)) {
                continue;
            }
            $etiqueta = trim((string) ($fila['etiqueta'] ?? ''));
            $formula = trim((string) ($fila['formula'] ?? ''));
            if ($etiqueta === '' || $formula === '') {
                continue;
            }
            $out[] = [
                'etiqueta' => mb_substr($etiqueta, 0, 40),
                'formula' => mb_substr($formula, 0, ListadoQbeFormulaSupport::MAX_LEN),
                'valida' => ListadoQbeFormulaSupport::compilar($formula, $campos) !== null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>|null  $vistaJson
     * @return array<string, mixed>
     */
    public static function mezclarCalculadas(array $filtros, Request $request, ?array $vistaJson): array
    {
        if ($request->exists('calculadas')) {
            $filtros['calculadas'] = self::normalizarCalculadas($request->input('calculadas'));
        } elseif (is_array($vistaJson)) {
            $filtros['calculadas'] = self::normalizarCalculadas($vistaJson['calculadas'] ?? []);
        } else {
            $filtros['calculadas'] = self::normalizarCalculadas($filtros['calculadas'] ?? []);
        }

        return $filtros;
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarCalculadas(Builder $query, array $filtros): void
    {
        $campos = self::camposFormula();
        foreach (self::normalizarCalculadas($filtros['calculadas'] ?? []) as $i => $calc) {
            if (! $calc['valida']) {
                continue;
            }
            $compiled = ListadoQbeFormulaSupport::compilar($calc['formula'], $campos);
            if ($compiled === null) {
                continue;
            }
            $query->selectRaw('('.$compiled['sql'].') as calc_'.$i, $compiled['bindings']);
        }
        $limite = (int) ($filtros['_mail_limite'] ?? 0);
        if ($limite > 0) {
            $query->limit($limite);
        }
    }

    /**
     * La vista no pisa fichas de empresa, tipo, período, mail ni el filtro de solicitud de pago.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $desdeVista
     * @return array<string, mixed>
     */
    public static function fusionarDesdeVista(array $base, array $desdeVista): array
    {
        $externos = [
            'empresa_id' => $base['empresa_id'] ?? null,
            'empresa_scope' => $base['empresa_scope'] ?? 'una',
            'tipos' => $base['tipos'] ?? [],
            'periodo' => $base['periodo'] ?? '',
            'mail' => PagoproveedorListadoFiltros::normalizarMail((string) ($base['mail'] ?? '')),
            'solicitudpago_id' => $base['solicitudpago_id'] ?? null,
            'fecha_desde' => $base['fecha_desde'] ?? '',
            'fecha_hasta' => $base['fecha_hasta'] ?? '',
        ];
        $campos = self::camposOrdenables();
        $ordenVista = ListadoOrdenamientoSupport::normalizar($desdeVista['sort'] ?? ($desdeVista['orden'] ?? []), $campos);
        $ordenBase = ListadoOrdenamientoSupport::normalizar($base['sort'] ?? [], $campos);
        $agruparVista = ListadoAgrupacionSupport::normalizar($desdeVista['agrupar'] ?? ($desdeVista['group'] ?? []), $campos);
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
            self::camposQbe(),
            static fn (string $op, string $campo): string => self::normalizarOperadorQbe($op, $campo)
        );
        $modo = (string) ($desdeVista['modo'] ?? self::MODO_TODOS);
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $modo = self::MODO_QBE;
        }

        return array_merge($base, $externos, [
            'modo' => $modo,
            'valor' => (string) ($desdeVista['valor'] ?? ''),
            'valor_hasta' => (string) ($desdeVista['valor_hasta'] ?? ''),
            'busqueda' => (string) ($desdeVista['valor'] ?? ($desdeVista['busqueda'] ?? '')),
            'qbe' => $qbe,
            'sort' => $ordenBase !== [] ? $ordenBase : $ordenVista,
            'agrupar' => $agruparBase !== [] ? $agruparBase : $agruparVista,
        ]);
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
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

        $query->orderBy('caja_movimiento.id', 'desc');
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     * @param  array<string, mixed>  $qbe
     */
    private static function aplicarQbe(Builder $query, array $qbe): void
    {
        $norm = ListadoQbeSupport::normalizar(
            $qbe,
            self::camposQbe(),
            static fn (string $op, string $campo): string => self::normalizarOperadorQbe($op, $campo)
        );

        ListadoQbeSupport::aplicar(
            $query,
            $norm,
            static function (Builder $q, array $criterio, string $boolean = 'and'): void {
                self::aplicarCriterioQbe($q, $criterio, $boolean);
            }
        );
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     * @param  array<string, mixed>  $criterio
     */
    private static function aplicarCriterioQbe(Builder $query, array $criterio, string $boolean): void
    {
        if ($boolean === ListadoQbeSupport::LOGIC_OR) {
            $query->orWhere(function (Builder $inner) use ($criterio) {
                self::aplicarCriterioQbe($inner, $criterio, ListadoQbeSupport::LOGIC_AND);
            });

            return;
        }

        $formula = trim((string) ($criterio['formula'] ?? ''));
        if ($formula !== '') {
            $compiled = ListadoQbeFormulaSupport::compilar($formula, self::camposQbe());
            if ($compiled === null) {
                return;
            }
            ListadoQbeFormulaSupport::aplicarComparacion(
                $query,
                $compiled,
                (string) ($criterio['op'] ?? 'contiene'),
                trim((string) ($criterio['valor'] ?? '')),
                trim((string) ($criterio['valor_hasta'] ?? ''))
            );

            return;
        }

        $campo = (string) ($criterio['campo'] ?? '');
        if (ListadoRelacionCatalogo::aplica(IngresoEgresoListadoColumnas::RECURSO, $campo)) {
            ListadoRelacionCatalogo::aplicar(
                $query,
                IngresoEgresoListadoColumnas::RECURSO,
                $campo,
                (string) ($criterio['op'] ?? 'contiene'),
                trim((string) ($criterio['valor'] ?? '')),
                trim((string) ($criterio['valor_hasta'] ?? '')),
                ''
            );

            return;
        }
        $def = self::camposQbe()[$campo] ?? null;
        if ($def === null) {
            return;
        }
        $operador = (string) ($criterio['op'] ?? 'contiene');
        $valor = trim((string) ($criterio['valor'] ?? ''));
        $hasta = trim((string) ($criterio['valor_hasta'] ?? ''));
        $column = (string) $def['column'];
        $type = (string) ($def['type'] ?? 'texto');

        if ($campo === 'tipo') {
            if ($operador === 'vacio') {
                $query->where(function ($q) {
                    $q->where(function ($w) {
                        $w->whereNull('tipotransaccion_caja.nombre')->orWhere('tipotransaccion_caja.nombre', '');
                    })->where(function ($w) {
                        $w->whereNull('tipotransaccion_caja.abreviatura')->orWhere('tipotransaccion_caja.abreviatura', '');
                    });
                });

                return;
            }
            if ($valor === '') {
                return;
            }
            $query->where(function ($q) use ($operador, $valor) {
                self::aplicarTexto($q, 'tipotransaccion_caja.nombre', $operador, $valor);
                $q->orWhere(function ($w) use ($operador, $valor) {
                    self::aplicarTexto($w, 'tipotransaccion_caja.abreviatura', $operador, $valor);
                });
            });

            return;
        }

        if ($type === 'fecha') {
            ListadoQbeSupport::aplicarFecha($query, $column, $operador, $valor, $hasta);

            return;
        }
        if ($type === 'entero') {
            if ($operador === 'vacio') {
                $query->whereNull($column);

                return;
            }
            ListadoQbeSupport::aplicarDecimal($query, $column, $operador, $valor, $hasta);

            return;
        }

        self::aplicarTexto($query, $column, $operador, $valor);
    }

    private static function normalizarOperadorQbe(string $operador, string $campoKey): string
    {
        $type = self::camposQbe()[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO_QBE),
            'fecha' => array_keys(self::OPERADORES_FECHA),
            'decimal' => array_keys(ListadoQbeSupport::OPERADORES_DECIMAL),
            default => array_keys(self::OPERADORES_TEXTO),
        };
        $operador = strtolower(trim($operador));
        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }
}
