<?php

namespace App\Support\Compras;

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
 * Filtros del listado de comprobantes de proveedor (index / exportaciones).
 * Incluye filtro externo de empresa (como cuentas de caja).
 */
class ComprobanteProveedorListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const MODO_QBE = 'qbe';

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

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'comprobante_proveedor.id', 'type' => 'entero', 'label' => 'ID'],
        'nombreempresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'nombreproveedor' => ['column' => 'proveedor.nombre', 'type' => 'texto', 'label' => 'Proveedor'],
        'nombretipotransaccion' => ['column' => 'tipotransaccion_compra.nombre', 'type' => 'texto', 'label' => 'Tipo de comprobante'],
        'letra' => ['column' => 'comprobante_proveedor.letra', 'type' => 'texto', 'label' => 'Letra'],
        'sucursal' => ['column' => 'comprobante_proveedor.sucursal', 'type' => 'entero', 'label' => 'Sucursal'],
        'numerocomprobante' => ['column' => 'comprobante_proveedor.numerocomprobante', 'type' => 'entero', 'label' => 'Número comprobante'],
        'numeroordencompra' => ['column' => 'ordencompra.numeroordencompra', 'type' => 'entero', 'label' => 'Nº orden de compra'],
        'fechacomprobante' => ['column' => 'comprobante_proveedor.fechacomprobante', 'type' => 'fecha', 'label' => 'Fecha comprobante'],
        'fechaiva' => ['column' => 'comprobante_proveedor.fechaiva', 'type' => 'fecha', 'label' => 'Fecha IVA / contabilización'],
        'total' => ['column' => 'comprobante_proveedor.total', 'type' => 'texto', 'label' => 'Total'],
        'estado' => ['column' => 'comprobante_proveedor.estado', 'type' => 'texto', 'label' => 'Estado'],
        'origen_entrada' => ['column' => 'comprobante_proveedor.origen_entrada', 'type' => 'texto', 'label' => 'Origen'],
        'modo_carga' => ['column' => 'comprobante_proveedor.modo_carga', 'type' => 'texto', 'label' => 'Modo carga'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'empresa.nombre',
        'proveedor.nombre',
        'comprobante_proveedor.proveedor_nombre_eventual',
        'tipotransaccion_compra.nombre',
        'comprobante_proveedor.estado',
        'comprobante_proveedor.origen_entrada',
        'comprobante_proveedor.modo_carga',
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

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);
        $estado = self::resolverEstadoExterno($request);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId,
                'empresa_scope' => $empresaScope,
                'estado' => $estado,
                '_limpiar' => true,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO, self::MODO_QBE], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'nombreproveedor');
        if (! isset(self::CAMPOS[$campo]) && ! isset(self::camposQbeDisponibles()[$campo])) {
            $campo = 'nombreproveedor';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');
        $qbe = ListadoQbeSupport::resolverDesdeRequest(
            $request,
            self::camposQbeDisponibles(),
            static fn (string $op, string $campoQbe): string => self::normalizarOperadorQbe($op, $campoQbe)
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
            $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'nombreproveedor');
        }

        $out = [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'estado' => $estado,
            'qbe' => $qbe,
            'sort' => $sort,
            'agrupar' => $agrupar,
        ];
        if ($request->filled('vista_id')) {
            $out['vista_id'] = (int) $request->input('vista_id');
        }
        if ($request->boolean('vista_estandar') || $request->input('vista_modo') === 'estandar') {
            $out['vista_estandar'] = 1;
        }
        $columnas = trim((string) $request->input('columnas', ''));
        if ($columnas !== '') {
            $out['columnas'] = $columnas;
        }

        return $out;
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposQbeDisponibles(): array
    {
        $out = [];
        foreach (ComprobanteProveedorListadoColumnas::camposFiltrables() as $key => $meta) {
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
        return ComprobanteProveedorListadoColumnas::camposOrdenables();
    }

    /**
     * @return array{0:?int,1:string}
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

    private static function resolverEstadoExterno(Request $request): string
    {
        if ($request->boolean('estado_todas') || $request->input('estado_scope') === ComprobanteProveedorEstados::FILTRO_TODOS) {
            return ComprobanteProveedorEstados::FILTRO_TODOS;
        }

        $estado = trim((string) $request->input('estado', ComprobanteProveedorEstados::FILTRO_TODOS));
        if (! ComprobanteProveedorEstados::esFiltroListadoValido($estado)) {
            return ComprobanteProveedorEstados::FILTRO_TODOS;
        }

        return $estado;
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
        return self::tieneCriteriosTexto($filtros);
    }

    /**
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, busqueda: string, empresa_id: ?int, empresa_scope: string, estado: string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'nombreproveedor',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'estado' => ComprobanteProveedorEstados::FILTRO_TODOS,
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
        $params = self::paraQueryStringExternos($filtros);

        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'nombreproveedor';
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
     * Empresa + estado (chips externos). Limpiar texto no los pierde.
     *
     * @return array<string, int|string>
     */
    public static function paraQueryStringExternos(array $filtros): array
    {
        return array_merge(self::paraQueryStringEmpresa($filtros), self::paraQueryStringEstado($filtros));
    }

    /**
     * @return array<string, string>
     */
    public static function paraQueryStringEstado(array $filtros): array
    {
        $estado = (string) ($filtros['estado'] ?? ComprobanteProveedorEstados::FILTRO_TODOS);
        if ($estado === '' || $estado === ComprobanteProveedorEstados::FILTRO_TODOS) {
            return [];
        }

        return ['estado' => $estado];
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('comprobante_proveedor.empresa_id', (int) $filtros['empresa_id']);
        }

        $estado = (string) ($filtros['estado'] ?? ComprobanteProveedorEstados::FILTRO_TODOS);
        if ($estado === ComprobanteProveedorEstados::FILTRO_ERROR_ANITA) {
            $query->where('comprobante_proveedor.anita_sync_estado', ComprobanteProveedorAnitaSyncEstado::ERROR);
        } elseif ($estado !== '' && $estado !== ComprobanteProveedorEstados::FILTRO_TODOS) {
            $query->where('comprobante_proveedor.estado', $estado);
        }

        if (! self::tieneCriteriosTexto($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_QBE) {
            self::aplicarQbe($query, (array) ($filtros['qbe'] ?? []));

            return;
        }

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'nombreproveedor', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio' || $valor === '') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $id, $operador) {
            if ($id !== false) {
                $q->orWhere('comprobante_proveedor.id', (int) $id)
                    ->orWhere('comprobante_proveedor.sucursal', (int) $id)
                    ->orWhere('comprobante_proveedor.numerocomprobante', (int) $id)
                    ->orWhere('ordencompra.numeroordencompra', (int) $id);
            }

            foreach (self::columnasTextoBusquedaGlobal() as $col) {
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

            $fecha = self::parsearFecha($valor);
            if ($fecha) {
                $q->orWhereDate('comprobante_proveedor.fechacomprobante', '=', $fecha)
                    ->orWhereDate('comprobante_proveedor.fechaiva', '=', $fecha);
            }
        });
    }

    /** @return list<string> */
    private static function columnasTextoBusquedaGlobal(): array
    {
        return [
            'empresa.nombre',
            'proveedor.nombre',
            'tipotransaccion_compra.nombre',
            'comprobante_proveedor.letra',
            'comprobante_proveedor.total',
            'comprobante_proveedor.estado',
            'comprobante_proveedor.origen_entrada',
            'comprobante_proveedor.modo_carga',
        ];
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['nombreproveedor'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($type === 'fecha') {
            self::aplicarFechaColumna($query, (string) $def['column'], $operador, $valor, $valorHasta);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
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
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
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
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
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
     * Empresa y estado (chips) no los pisa una vista guardada.
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
            'estado' => $base['estado'] ?? ComprobanteProveedorEstados::FILTRO_TODOS,
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
            self::camposQbeDisponibles(),
            static fn (string $op, string $campo): string => self::normalizarOperadorQbe($op, $campo)
        );
        $modo = (string) ($desdeVista['modo'] ?? self::MODO_TODOS);
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $modo = self::MODO_QBE;
        }

        return array_merge($base, $externos, [
            'modo' => $modo,
            'campo' => (string) ($desdeVista['campo'] ?? $base['campo'] ?? 'nombreproveedor'),
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
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
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
                'campo' => 'fechaiva',
                'dir' => ListadoOrdenamientoSupport::DIR_DESC,
            ]);

            return;
        }

        $query->orderByDesc('comprobante_proveedor.fechaiva')
            ->orderByDesc('comprobante_proveedor.id');
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
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
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
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
            $query->orWhere(function (Builder $q) use ($campoKey, $operador, $valor, $valorHasta) {
                self::aplicarEnCampoQbe($q, $campoKey, $operador, $valor, $valorHasta);
            });

            return;
        }

        $def = self::camposQbeDisponibles()[$campoKey] ?? null;
        if ($def === null) {
            return;
        }
        $type = $def['type'] ?? 'texto';
        $column = (string) $def['column'];

        if ($campoKey === 'proveedor') {
            self::aplicarProveedor($query, $operador, $valor, $valorHasta);

            return;
        }
        if ($campoKey === 'tipo') {
            self::aplicarTipoCompra($query, $operador, $valor, $valorHasta);

            return;
        }
        if (in_array($campoKey, ['origen', 'estado', 'modo_carga'], true) && in_array($operador, ['contiene', 'igual'], true)) {
            $codigos = self::codigosPorEtiqueta($campoKey, $valor);
            if ($codigos !== []) {
                $query->whereIn($column, $codigos);

                return;
            }
        }
        if ($type === 'entero') {
            if ($operador === 'vacio') {
                $query->whereNull($column);

                return;
            }
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
     * @return list<string>
     */
    private static function codigosPorEtiqueta(string $campoKey, string $valor): array
    {
        $valor = trim($valor);
        if ($valor === '') {
            return [];
        }
        $mapa = match ($campoKey) {
            'origen' => array_map(
                static fn (string $cod): array => [$cod, ComprobanteProveedorOrigenEntrada::etiqueta($cod)],
                ComprobanteProveedorOrigenEntrada::todos()
            ),
            'estado' => array_map(
                static fn (string $cod): array => [$cod, ComprobanteProveedorEstados::etiqueta($cod)],
                ComprobanteProveedorEstados::todos()
            ),
            'modo_carga' => array_map(
                static fn (string $cod): array => [$cod, ComprobanteProveedorModoCarga::etiqueta($cod)],
                ComprobanteProveedorModoCarga::todos()
            ),
            default => [],
        };
        $codigos = [];
        foreach ($mapa as [$cod, $etiqueta]) {
            if (stripos($cod, $valor) !== false || stripos($etiqueta, $valor) !== false) {
                $codigos[] = $cod;
            }
        }

        return $codigos;
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    private static function aplicarProveedor(Builder $query, string $operador, string $valor, string $valorHasta): void
    {
        $query->where(function (Builder $q) use ($operador, $valor, $valorHasta) {
            self::aplicarTextoQbe($q, 'proveedor.nombre', $operador, $valor, $valorHasta);
            $q->orWhere(function (Builder $inner) use ($operador, $valor, $valorHasta) {
                self::aplicarTextoQbe($inner, 'comprobante_proveedor.proveedor_nombre_eventual', $operador, $valor, $valorHasta);
            });
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    private static function aplicarTipoCompra(Builder $query, string $operador, string $valor, string $valorHasta): void
    {
        $query->where(function (Builder $q) use ($operador, $valor, $valorHasta) {
            self::aplicarTextoQbe($q, 'tipotransaccion_compra.nombre', $operador, $valor, $valorHasta);
            $q->orWhere(function (Builder $inner) use ($operador, $valor, $valorHasta) {
                self::aplicarTextoQbe($inner, 'tipotransaccion_compra.abreviatura', $operador, $valor, $valorHasta);
            });
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    private static function aplicarTextoQbe(
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
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    private static function aplicarEnteroQbe(
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

    private static function normalizarOperadorQbe(string $operador, string $campoKey): string
    {
        $type = self::camposQbeDisponibles()[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO_QBE),
            'fecha' => array_keys(self::OPERADORES_FECHA_QBE),
            'decimal' => array_keys(self::OPERADORES_DECIMAL),
            default => array_keys(self::OPERADORES_TEXTO_QBE),
        };

        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }
}
