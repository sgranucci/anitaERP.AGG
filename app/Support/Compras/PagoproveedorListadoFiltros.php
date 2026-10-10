<?php

namespace App\Support\Compras;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoLienzoSupport;
use App\Support\Listado\ListadoQbeSupport;
use App\Support\Listado\ListadoRelacionCatalogo;
use App\Support\Listado\ListadoVisualSupport;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de órdenes de pago a proveedores.
 */
class PagoproveedorListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const MODO_QBE = 'qbe';

    /** Fichas de período, iguales a ingresos y egresos. Ayer queda en la consulta avanzada. */
    public const PERIODOS_FICHA = [
        '' => 'Todas las fechas',
        'hoy' => 'Hoy',
        'esta_semana' => 'Esta semana',
        'este_mes' => 'Este mes',
        'mes_anterior' => 'Mes anterior',
        'este_anio' => 'Este año',
    ];

    /** @var array<string, string> */
    public const OPERADORES_TEXTO_QBE = [
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
    public const OPERADORES_FECHA_QBE = ListadoQbeSupport::OPERADORES_FECHA;

    /** @var array<string, string> */
    public const OPERADORES_DECIMAL = ListadoQbeSupport::OPERADORES_DECIMAL;

    /** @var array<string, string> */
    public const OPERADORES_BOOLEANO = [
        'igual' => 'Es',
        'vacio' => 'Sin dato',
    ];

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'pagoproveedor.id', 'type' => 'entero', 'label' => 'ID'],
        'numerotransaccion' => ['column' => 'pagoproveedor.numerotransaccion', 'type' => 'texto', 'label' => 'Nro. transacción'],
        'proveedor' => ['column' => 'proveedor.nombre', 'type' => 'texto', 'label' => 'Proveedor'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'estado' => ['column' => 'pagoproveedor.estado', 'type' => 'texto', 'label' => 'Estado'],
        'fecha' => ['column' => 'pagoproveedor.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
        'detalle' => ['column' => 'pagoproveedor.detalle', 'type' => 'texto', 'label' => 'Detalle'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'proveedor.nombre',
        'pagoproveedor.detalle',
        'empresa.nombre',
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
        'mayor' => 'Desde',
        'menor' => 'Hasta',
    ];

    public static function resolverDesdeRequest(
        Request $request,
        ?string $busquedaRuta = null,
        ?int $empresaDefault = null
    ): array {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId,
                'empresa_scope' => $empresaScope,
                'mail' => self::normalizarMail((string) $request->input('mail', '')),
                '_limpiar' => true,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO, self::MODO_QBE], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'proveedor');
        if (! isset(self::CAMPOS[$campo]) && ! isset(self::camposQbeDisponibles()[$campo])) {
            $campo = 'proveedor';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');
        $qbe = ListadoQbeSupport::resolverDesdeRequest(
            $request,
            self::camposQbeDisponibles(),
            static fn (string $op, string $campoQbe): string => self::normalizarOperadorQbe($op, $campoQbe)
        );
        $graficoPedido = self::graficoDesdeRequest($request);
        $click = trim((string) $request->input('grafico_click', ''));
        $clickDimension = trim((string) $request->input('grafico_click_dimension', ''));
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
            $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'proveedor');
        }

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
            'periodo' => self::periodoDesdeRequest($request),
            'mail' => self::normalizarMail((string) $request->input('mail', '')),
            'qbe' => $qbe,
            'sort' => $sort,
            'agrupar' => $agrupar,
            'grafico' => $graficoPedido['grafico'],
            'graficos' => $graficoPedido['graficos'],
            'grafico_off' => $graficoPedido['grafico_off'],
            'grafico_click' => $click,
            'grafico_click_dimension' => $clickDimension,
            'formato' => PagoproveedorListadoAnalisisSupport::normalizarFormato($request->input('formato')),
            'calculadas' => PagoproveedorListadoAnalisisSupport::normalizarCalculadas($request->input('calculadas')),
            '_grafico_explicito' => $request->exists('grafico') || $request->exists('graficos') || $request->boolean('grafico_off'),
            '_formato_explicito' => $request->exists('formato'),
            '_calculadas_explicito' => $request->exists('calculadas'),
        ];
    }

    /**
     * Filtro externo del index: empresa (default primera asignada) o todas (`empresa_todas=1`).
     *
     * @return array{0:?int,1:string}  [empresa_id, empresa_scope]
     */
    private static function resolverEmpresaExterna(Request $request, ?int $empresaDefault): array
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

    public static function periodoDesdeRequest(Request $request): string
    {
        $periodo = (string) $request->input('filtro_periodo', '');

        return array_key_exists($periodo, self::PERIODOS_FICHA) ? $periodo : '';
    }

    /**
     * Criterios del panel / búsqueda rápida (sin el filtro externo de empresa).
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosTexto($filtros);
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'proveedor',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'periodo' => '',
            'mail' => '',
            'qbe' => ListadoQbeSupport::vacio(),
            'sort' => [],
            'agrupar' => [],
            'grafico' => PagoproveedorListadoAnalisisSupport::graficoVacio(),
            'graficos' => [],
            'grafico_off' => false,
            'grafico_click' => '',
            'grafico_click_dimension' => '',
            'formato' => [],
            'calculadas' => [],
        ];
    }

    /**
     * @return array{grafico: array{tipo: string, dimension: string, medida: string}, graficos: list<array{tipo: string, dimension: string, medida: string}>, grafico_off: bool}
     */
    private static function graficoDesdeRequest(Request $request): array
    {
        $listaExplicita = $request->exists('graficos');
        $graficos = ListadoLienzoSupport::normalizar(
            $request->input('graficos'),
            $request->input('grafico'),
            $listaExplicita,
            self::camposOrdenables()
        );
        $pidio = $listaExplicita || $request->exists('grafico');
        $apagado = $request->boolean('grafico_off') || ($pidio && $graficos === []);

        return [
            'graficos' => $apagado ? [] : $graficos,
            'grafico' => $apagado ? PagoproveedorListadoAnalisisSupport::graficoVacio() : ListadoLienzoSupport::primero($graficos),
            'grafico_off' => $apagado,
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);

        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'proveedor';
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
        if (($filtros['periodo'] ?? '') !== '') {
            $params['filtro_periodo'] = $filtros['periodo'];
        }
        $mail = self::normalizarMail((string) ($filtros['mail'] ?? ''));
        if ($mail !== '') {
            $params['mail'] = $mail;
        }

        return array_merge(
            $params,
            ListadoQbeSupport::paraQueryString((array) ($filtros['qbe'] ?? [])),
            ListadoOrdenamientoSupport::paraQueryString(
                ListadoOrdenamientoSupport::normalizar($filtros['sort'] ?? [], self::camposOrdenables())
            ),
            ListadoAgrupacionSupport::paraQueryString(
                ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], self::camposOrdenables())
            ),
            PagoproveedorListadoAnalisisSupport::paraQueryString($filtros)
        );
    }

    /**
     * Solo el filtro externo de empresa (para Limpiar texto sin perder empresa).
     *
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
     * Columnas del union `listado_op` que se pueden ordenar desde el encabezado.
     * Cuentas de caja y el ícono de mail se arman después de paginar: no entran.
     *
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposOrdenables(): array
    {
        return [
            'fecha' => ['column' => 'listado_op.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
            'op' => ['column' => 'listado_op.numerotransaccion', 'type' => 'entero', 'label' => 'OP'],
            'empresa' => ['column' => 'listado_op.nombreempresa', 'type' => 'texto', 'label' => 'Empresa'],
            'proveedor' => ['column' => 'listado_op.nombreproveedor', 'type' => 'texto', 'label' => 'Proveedor'],
            'detalle' => ['column' => 'listado_op.detalle', 'type' => 'texto', 'label' => 'Descripción'],
            'monto' => ['column' => 'listado_op.monto', 'type' => 'decimal', 'label' => 'Monto'],
            'estado' => ['column' => 'listado_op.estado', 'type' => 'texto', 'label' => 'Estado'],
            'moneda' => ['column' => 'listado_op.moneda_abrev', 'type' => 'texto', 'label' => 'Moneda'],
            'tipocomprobante' => ['column' => 'listado_op.tipocomprobante', 'type' => 'texto', 'label' => 'Tipo'],
            'id' => ['column' => 'listado_op.pk_id', 'type' => 'entero', 'label' => 'ID'],
            'origen' => ['column' => 'listado_op.origen', 'type' => 'texto', 'label' => 'Origen'],
        ];
    }

    /**
     * @param  Builder<\App\Models\Compras\Pagoproveedor>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('pagoproveedor.empresa_id', (int) $filtros['empresa_id']);
        }

        if (($filtros['fecha_desde'] ?? '') !== '') {
            $query->whereDate('pagoproveedor.fecha', '>=', $filtros['fecha_desde']);
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $query->whereDate('pagoproveedor.fecha', '<=', $filtros['fecha_hasta']);
        }

        if (! self::tieneCriteriosTextoParaBusqueda($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'proveedor', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    public static function tieneCriteriosTexto(array $filtros): bool
    {
        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== '') {
            return true;
        }
        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            return true;
        }
        if (ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))) {
            return true;
        }

        return self::tieneCriteriosTextoParaBusqueda($filtros);
    }

    /**
     * Criterios de texto/campo (sin rango de fechas, que se aplica aparte).
     */
    public static function tieneCriteriosTextoParaBusqueda(array $filtros): bool
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
     * @param  Builder<\App\Models\Compras\Pagoproveedor>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['pagoproveedor.numerotransaccion', 'pagoproveedor.detalle', 'proveedor.nombre'] as $col) {
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
                $q->orWhere('pagoproveedor.id', (int) $id);
            }
            foreach (['pagoproveedor.numerotransaccion', 'pagoproveedor.estado', 'pagoproveedor.detalle', 'proveedor.nombre', 'empresa.nombre'] as $col) {
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
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Pagoproveedor>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['proveedor'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($type === 'fecha') {
            self::aplicarFecha($query, (string) $def['column'], $operador, $valor, $valorHasta);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Compras\Pagoproveedor>  $query
     */
    private static function aplicarFecha(Builder $query, string $column, string $operador, string $valor, string $valorHasta): void
    {
        $desde = trim($valor);
        $hasta = trim($valorHasta);
        if ($desde === '' && $hasta === '') {
            return;
        }
        if ($desde !== '' && $hasta !== '') {
            $query->whereDate($column, '>=', $desde)->whereDate($column, '<=', $hasta);

            return;
        }
        if ($desde !== '') {
            $op = match ($operador) {
                'menor' => '<=',
                'mayor' => '>=',
                default => '=',
            };
            $query->whereDate($column, $op, $desde);
        }
    }

    /**
     * @param  Builder<\App\Models\Compras\Pagoproveedor>  $query
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
     * @param  Builder<\App\Models\Compras\Pagoproveedor>  $query
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

    public static function normalizarMail(string $mail): string
    {
        $mail = strtolower(trim($mail));

        return in_array($mail, ['enviado', 'no'], true) ? $mail : '';
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO),
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
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };
    }

    /**
     * Campos del QBE. Cuentas de caja y mail no están en el SQL del listado.
     *
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposQbeDisponibles(): array
    {
        $out = [];
        foreach (PagoproveedorListadoColumnas::catalogoActivo() as $key => $meta) {
            if (empty($meta['filterable']) || ($meta['source'] ?? '') === '') {
                continue;
            }
            $out[$key] = [
                'column' => (string) $meta['source'],
                'type' => (string) ($meta['type'] ?? 'texto'),
                'label' => (string) ($meta['label'] ?? $key),
            ];
        }

        // La factura no está en el UNION. La declara el catálogo de relaciones.
        foreach (ListadoRelacionCatalogo::campos(PagoproveedorListadoColumnas::RECURSO) as $key => $meta) {
            $out[$key] = $meta;
        }

        return $out;
    }

    /**
     * Empresa y mail (chips) no los pisa una vista guardada.
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
            'mail' => $base['mail'] ?? '',
            'fecha_desde' => $base['fecha_desde'] ?? '',
            'fecha_hasta' => $base['fecha_hasta'] ?? '',
            'periodo' => $base['periodo'] ?? '',
        ];
        $campos = self::camposOrdenables();
        $ordenVista = ListadoOrdenamientoSupport::normalizar($desdeVista['sort'] ?? $desdeVista['orden'] ?? [], $campos);
        $ordenBase = ListadoOrdenamientoSupport::normalizar($base['sort'] ?? [], $campos);
        $agruparVista = ListadoAgrupacionSupport::normalizar($desdeVista['agrupar'] ?? $desdeVista['group'] ?? [], $campos);
        $agruparBase = ListadoAgrupacionSupport::normalizar($base['agrupar'] ?? [], $campos);

        if (! empty($base['_limpiar'])) {
            unset($base['_limpiar'], $base['_qbe_explicito'], $base['_grafico_explicito'], $base['_formato_explicito'], $base['_calculadas_explicito']);

            return array_merge($base, $externos, [
                'modo' => self::MODO_TODOS,
                'valor' => '',
                'valor_hasta' => '',
                'busqueda' => '',
                'qbe' => ListadoQbeSupport::vacio(),
                'sort' => $ordenBase !== [] ? $ordenBase : $ordenVista,
                'agrupar' => $agruparBase !== [] ? $agruparBase : $agruparVista,
                'grafico' => PagoproveedorListadoAnalisisSupport::graficoVacio(),
                'graficos' => [],
                'grafico_off' => true,
                'grafico_click' => '',
                'grafico_click_dimension' => '',
                'formato' => [],
                'calculadas' => [],
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

            return self::mezclarAnalisis(array_merge($base, $externos), $desdeVista);
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

        return self::mezclarAnalisis(array_merge($base, $externos, [
            'modo' => $modo,
            'campo' => (string) ($desdeVista['campo'] ?? $base['campo'] ?? 'proveedor'),
            'operador' => (string) ($desdeVista['operador'] ?? $base['operador'] ?? 'contiene'),
            'valor' => (string) ($desdeVista['valor'] ?? ''),
            'valor_hasta' => (string) ($desdeVista['valor_hasta'] ?? ''),
            'busqueda' => (string) ($desdeVista['valor'] ?? $desdeVista['busqueda'] ?? ''),
            'qbe' => $qbe,
            'sort' => $ordenBase !== [] ? $ordenBase : $ordenVista,
            'agrupar' => $agruparBase !== [] ? $agruparBase : $agruparVista,
        ]), $desdeVista);
    }

    /**
     * Si la pantalla no mandó gráfico, formato o fórmula, se toman de la vista.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $desdeVista
     * @return array<string, mixed>
     */
    private static function mezclarAnalisis(array $base, array $desdeVista): array
    {
        if (! empty($base['_grafico_explicito']) || ! empty($base['grafico_off'])) {
            $graficos = is_array($base['graficos'] ?? null) ? $base['graficos'] : [];
            if ($graficos === [] || ($base['grafico']['tipo'] ?? '') === '') {
                $base['grafico'] = PagoproveedorListadoAnalisisSupport::graficoVacio();
                $base['graficos'] = [];
                $base['grafico_off'] = true;
            } else {
                $base['grafico_off'] = false;
                $base['grafico'] = ListadoLienzoSupport::primero($graficos);
            }
        } else {
            $guardados = is_array($desdeVista['graficos'] ?? null) ? $desdeVista['graficos'] : [];
            $base['graficos'] = ListadoLienzoSupport::normalizar($guardados, $desdeVista['grafico'] ?? [], $guardados !== [], self::camposOrdenables());
            $base['grafico'] = ListadoLienzoSupport::primero($base['graficos']);
            $base['grafico_off'] = $base['graficos'] === [];
        }
        if (empty($base['_formato_explicito'])) {
            $base['formato'] = PagoproveedorListadoAnalisisSupport::normalizarFormato($desdeVista['formato'] ?? []);
        }
        if (empty($base['_calculadas_explicito'])) {
            $base['calculadas'] = PagoproveedorListadoAnalisisSupport::normalizarCalculadas($desdeVista['calculadas'] ?? []);
        }
        unset($base['_grafico_explicito'], $base['_formato_explicito'], $base['_calculadas_explicito']);

        return $base;
    }

    /**
     * @param  array<string, mixed>  $qbe
     * @param  array<string, string>  $columnas  key de catálogo => columna o expresión de esa pata del UNION
     */
    public static function aplicarQbe(Builder $query, array $qbe, array $columnas, string $origenLiteral): void
    {
        $campos = self::camposQbeLeg($columnas);
        $norm = ListadoQbeSupport::normalizar(
            $qbe,
            $campos,
            static fn (string $op, string $campo): string => self::normalizarOperadorQbe($op, $campo)
        );

        ListadoQbeSupport::aplicar(
            $query,
            $norm,
            static function (Builder $q, array $criterio, string $boolean = 'and') use ($campos, $columnas, $origenLiteral): void {
                $formula = trim((string) ($criterio['formula'] ?? ''));
                if ($formula !== '') {
                    $compiled = ListadoQbeFormulaSupport::compilar($formula, $campos);
                    if ($compiled === null) {
                        return;
                    }
                    ListadoQbeFormulaSupport::aplicarComparacion(
                        $q,
                        $compiled,
                        (string) ($criterio['op'] ?? 'contiene'),
                        trim((string) ($criterio['valor'] ?? '')),
                        trim((string) ($criterio['valor_hasta'] ?? '')),
                        $boolean
                    );

                    return;
                }
                self::aplicarEnCampoQbe(
                    $q,
                    (string) ($criterio['campo'] ?? ''),
                    (string) ($criterio['op'] ?? 'contiene'),
                    trim((string) ($criterio['valor'] ?? '')),
                    trim((string) ($criterio['valor_hasta'] ?? '')),
                    $boolean,
                    $columnas,
                    $origenLiteral
                );
            }
        );
    }

    /**
     * @param  array<string, string>  $columnas
     * @return array<string, array{column: string, type: string, label: string}>
     */
    private static function camposQbeLeg(array $columnas): array
    {
        $out = self::camposQbeDisponibles();
        foreach ($out as $key => $meta) {
            if (isset($columnas[$key]) && $columnas[$key] !== '') {
                $out[$key]['column'] = $columnas[$key];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $columnas
     */
    private static function aplicarEnCampoQbe(
        Builder $query,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta,
        string $boolean,
        array $columnas,
        string $origenLiteral
    ): void {
        if ($boolean === ListadoQbeSupport::LOGIC_OR) {
            $query->orWhere(function (Builder $q) use ($campoKey, $operador, $valor, $valorHasta, $columnas, $origenLiteral) {
                self::aplicarEnCampoQbe($q, $campoKey, $operador, $valor, $valorHasta, 'and', $columnas, $origenLiteral);
            });

            return;
        }

        if ($campoKey === 'origen') {
            $query->whereRaw(self::origenCoincide($origenLiteral, $operador, $valor) ? '1 = 1' : '1 = 0');

            return;
        }

        if (ListadoRelacionCatalogo::aplica(PagoproveedorListadoColumnas::RECURSO, $campoKey)) {
            ListadoRelacionCatalogo::aplicar(
                $query,
                PagoproveedorListadoColumnas::RECURSO,
                $campoKey,
                $operador,
                $valor,
                $valorHasta,
                $origenLiteral
            );

            return;
        }

        $def = self::camposQbeDisponibles()[$campoKey] ?? null;
        $column = (string) ($columnas[$campoKey] ?? '');
        if ($def === null || $column === '') {
            return;
        }
        $type = (string) ($def['type'] ?? 'texto');

        if ($type === 'entero') {
            self::aplicarEnteroExpresion($query, $column, $operador, $valor, $valorHasta);

            return;
        }
        if ($type === 'fecha' && ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
            ListadoQbeSupport::aplicarFecha($query, $column, $operador, $valor, $valorHasta);

            return;
        }
        if ($type === 'decimal') {
            self::aplicarDecimalExpresion($query, $column, $operador, $valor, $valorHasta);

            return;
        }

        self::aplicarTextoExpresion($query, $column, $operador, $valor, $valorHasta, in_array($campoKey, ['proveedor', 'empresa', 'detalle'], true));
    }

    /**
     * Texto o importe de una columna de una relación. Lo usa el catálogo de factura aplicada.
     */
    public static function aplicarExpresionRelacion(
        Builder $query,
        string $tipo,
        string $column,
        string $operador,
        string $valor,
        string $hasta
    ): void {
        if ($tipo === 'decimal') {
            self::aplicarDecimalExpresion($query, $column, $operador, $valor, $hasta);

            return;
        }
        self::aplicarTextoExpresion($query, $column, $operador, $valor, $hasta, false);
    }

    /**
     * El clic en una barra se aplica después de fusionar la vista, para no pisar la consulta guardada.
     * La dimensión la manda el gráfico clickeado. Si no viene, se usa el primer gráfico.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function aplicarClickGrafico(array $filtros): array
    {
        $click = trim((string) ($filtros['grafico_click'] ?? ''));
        $dimension = trim((string) ($filtros['grafico_click_dimension'] ?? ''));
        if ($dimension === '' || ! isset(self::camposQbeDisponibles()[$dimension])) {
            $dimension = (string) ($filtros['grafico']['dimension'] ?? '');
        }
        if ($click === '' || $dimension === '' || ! isset(self::camposQbeDisponibles()[$dimension])) {
            return $filtros;
        }
        $filtros['grafico_click_dimension'] = $dimension;
        $filtros['qbe'] = ListadoQbeSupport::normalizar(
            ListadoVisualSupport::agregarCriterioIgual(is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : [], $dimension, $click),
            self::camposQbeDisponibles(),
            static fn (string $op, string $campo): string => self::normalizarOperadorQbe($op, $campo)
        );
        if (ListadoQbeSupport::tieneCriterios($filtros['qbe'])) {
            $filtros['modo'] = self::MODO_QBE;
        }

        return $filtros;
    }

    private static function origenCoincide(string $literal, string $operador, string $valor): bool
    {
        $etiqueta = $literal === PagoproveedorListadoFila::ORIGEN_IE_OPP ? 'ingresos y egresos' : 'orden de pago';
        $codigo = strtolower($literal);
        $buscado = mb_strtolower(trim($valor));
        if ($operador === 'vacio') {
            return false;
        }
        $hit = $buscado === ''
            ? false
            : str_contains($etiqueta, $buscado) || str_contains($codigo, $buscado) || str_contains($buscado, 'ie') && $literal === PagoproveedorListadoFila::ORIGEN_IE_OPP;

        return match ($operador) {
            'distinto', 'no_contiene' => ! $hit,
            default => $hit,
        };
    }

    private static function aplicarEnteroExpresion(Builder $query, string $column, string $operador, string $valor, string $hasta): void
    {
        if ($operador === 'vacio') {
            self::whereExpr($query, $column, 'IS NULL');

            return;
        }
        $n = filter_var($valor, FILTER_VALIDATE_INT);
        $n2 = filter_var($hasta, FILTER_VALIDATE_INT);
        if ($operador === 'entre') {
            if ($n !== false) {
                self::whereExpr($query, $column, '>=', (int) $n);
            }
            if ($n2 !== false) {
                self::whereExpr($query, $column, '<=', (int) $n2);
            }

            return;
        }
        if ($n === false) {
            return;
        }
        $op = match ($operador) {
            'mayor' => '>',
            'mayor_igual' => '>=',
            'menor' => '<',
            'menor_igual' => '<=',
            default => '=',
        };
        self::whereExpr($query, $column, $op, (int) $n);
    }

    private static function aplicarDecimalExpresion(Builder $query, string $column, string $operador, string $valor, string $hasta): void
    {
        if (ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
            ListadoQbeSupport::aplicarDecimal($query, $column, $operador, $valor, $hasta);

            return;
        }
        if ($operador === 'vacio') {
            self::whereExpr($query, $column, 'IS NULL');

            return;
        }
        $desde = ListadoQbeSupport::normalizarDecimal($valor);
        $hastaN = ListadoQbeSupport::normalizarDecimal($hasta);
        if ($operador === 'entre') {
            if ($desde !== null) {
                self::whereExpr($query, $column, '>=', $desde);
            }
            if ($hastaN !== null) {
                self::whereExpr($query, $column, '<=', $hastaN);
            }

            return;
        }
        if ($desde === null) {
            return;
        }
        $op = match ($operador) {
            'mayor' => '>',
            'menor' => '<',
            'menor_igual' => '<=',
            'igual' => '=',
            default => '>=',
        };
        self::whereExpr($query, $column, $op, $desde);
    }

    private static function aplicarTextoExpresion(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $hasta,
        bool $flexible
    ): void {
        if ($operador === 'vacio') {
            if (ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
                $query->where(function (Builder $w) use ($column) {
                    $w->whereNull($column)->orWhere($column, '');
                });
            } else {
                $query->whereRaw('('.$column.') IS NULL OR ('.$column.") = ''");
            }

            return;
        }
        if ($operador === 'entre') {
            if ($valor !== '') {
                self::whereExpr($query, $column, '>=', $valor);
            }
            if ($hasta !== '') {
                self::whereExpr($query, $column, '<=', $hasta);
            }

            return;
        }
        $escapado = addcslashes($valor, '%_\\');
        if (in_array($operador, ['igual', 'distinto', 'mayor', 'mayor_igual', 'menor', 'menor_igual'], true)) {
            $op = match ($operador) {
                'distinto' => '!=',
                'mayor' => '>',
                'mayor_igual' => '>=',
                'menor' => '<',
                'menor_igual' => '<=',
                default => '=',
            };
            self::whereExpr($query, $column, $op, $valor);

            return;
        }
        $like = match ($operador) {
            'empieza' => $escapado.'%',
            'termina' => '%'.$escapado,
            'no_contiene' => '%'.$escapado.'%',
            default => '%'.$escapado.'%',
        };
        $sqlOp = $operador === 'no_contiene' ? 'NOT LIKE' : 'LIKE';
        self::whereExpr($query, $column, $sqlOp, $like);
        if ($operador === 'contiene' && $flexible && ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
            CoincidenciaFlexibleTexto::aplicar($query, $column, $valor);
        }
    }

    private static function whereExpr(Builder $query, string $column, string $op, mixed $valor = null): void
    {
        if ($valor === null && $op === 'IS NULL') {
            if (ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
                $query->whereNull($column);
            } else {
                $query->whereRaw('('.$column.') IS NULL');
            }

            return;
        }
        if (ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
            $query->where($column, $op, $valor);

            return;
        }
        $query->whereRaw('('.$column.') '.$op.' ?', [$valor]);
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
