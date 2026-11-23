<?php

namespace App\Support\Compras;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoQbeSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Filtros del listado de órdenes de compra (index / exportaciones).
 */
class OrdencompraListadoFiltros
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

    /** @var array<string, string> */
    public const OPERADORES_BOOLEANO = [
        'igual' => 'Es',
        'vacio' => 'Sin dato',
    ];

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'ordencompra.id', 'type' => 'entero', 'label' => 'ID'],
        'numeroordencompra' => ['column' => 'ordencompra.numeroordencompra', 'type' => 'entero', 'label' => 'Número'],
        'nombreusuario' => ['column' => 'usuario.nombre', 'type' => 'texto', 'label' => 'Solicitante'],
        'fecha' => ['column' => 'ordencompra.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
        'fechaentrega' => ['column' => 'ordencompra.fechaentrega', 'type' => 'fecha', 'label' => 'Fecha entrega'],
        'nombreempresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'nombrecentrocosto' => ['column' => 'centrocosto.nombre', 'type' => 'texto', 'label' => 'Centro costo'],
        'codigocentrocosto' => ['column' => 'centrocosto.codigo', 'type' => 'texto', 'label' => 'Cód. centro costo'],
        'nombreproveedor' => ['column' => 'proveedor.nombre', 'type' => 'texto', 'label' => 'Proveedor'],
        'codigoproveedor' => ['column' => 'proveedor.codigo', 'type' => 'texto', 'label' => 'Cód. proveedor'],
        'nombresector' => ['column' => 'sector_legajocompra.nombre', 'type' => 'texto', 'label' => 'Sector legajo'],
        'estadoordencompra' => ['column' => 'ordencompra.estadoordencompra', 'type' => 'texto', 'label' => 'Estado'],
        'nombrecondicioncompra' => ['column' => 'condicioncompra.nombre', 'type' => 'texto', 'label' => 'Condición compra'],
        'numerorequisicion' => ['column' => 'requisicion.numerorequisicion', 'type' => 'entero', 'label' => 'Nº requisición'],
        'tratamiento' => ['column' => 'ordencompra.tratamiento', 'type' => 'texto', 'label' => 'Tratamiento'],
        'motivotratamiento' => ['column' => 'requisicion.motivotratamiento', 'type' => 'texto', 'label' => 'Motivo tratamiento'],
        'contrataciondirecta' => ['column' => 'requisicion.contrataciondirecta', 'type' => 'texto', 'label' => 'Contratación directa'],
        'comentario' => ['column' => 'ordencompra.comentario', 'type' => 'texto', 'label' => 'Comentario'],
        'detalle' => ['column' => 'ordencompra.detalle', 'type' => 'texto', 'label' => 'Detalle cabecera'],
        'nroinscripcion' => ['column' => 'requisicion.nroinscripcion', 'type' => 'texto', 'label' => 'Nro inscripción'],
        'recepcion_numero' => ['column' => 'oc_recep.recepcion_numero', 'type' => 'texto', 'label' => 'Recepción'],
        'factura_numero' => ['column' => 'oc_fac.factura_numero', 'type' => 'texto', 'label' => 'Factura'],
        'pago_numero' => ['column' => 'oc_pago.pago_numero', 'type' => 'texto', 'label' => 'Orden de pago'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'empresa.nombre',
        'centrocosto.nombre',
        'centrocosto.codigo',
        'proveedor.nombre',
        'proveedor.codigo',
        'usuario.nombre',
        'sector_legajocompra.nombre',
        'condicioncompra.nombre',
        'ordencompra.comentario',
        'ordencompra.detalle',
        'requisicion.motivotratamiento',
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

    public static function resolverDesdeRequest(
        Request $request,
        ?string $busquedaRuta = null,
        ?int $empresaDefault = null,
        ?int $salaDefault = null
    ): array {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);
        [$salaId, $salaScope] = self::resolverSalaExterna($request, $salaDefault);
        $presupuesto = self::normalizarPresupuestoExterno($request->input('presupuesto'));

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId,
                'empresa_scope' => $empresaScope,
                'sala_id' => $salaId,
                'sala_scope' => $salaScope,
                'presupuesto' => $presupuesto,
                'estado' => self::normalizarEstadoExterno($request->input('estado')),
                '_limpiar' => true,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO, self::MODO_QBE], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'numeroordencompra');
        if (! isset(self::CAMPOS[$campo]) && ! isset(self::camposQbeDisponibles()[$campo])) {
            $campo = 'numeroordencompra';
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
            // «Cualquier campo» compara texto. Si se valida contra el número de OC,
            // «contiene» no es un operador entero y termina en «igual» (LIKE sin %).
            $campoOperador = $modo === self::MODO_CAMPO ? $campo : 'comentario';
            $operador = self::normalizarOperador($operador, $campoOperador);
            if ($modo === self::MODO_TODOS && $operador === 'igual' && $valor !== '' && ! ctype_digit($valor)) {
                $operador = 'contiene';
            }
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
            'sala_id' => $salaId,
            'sala_scope' => $salaScope,
            'presupuesto' => $presupuesto,
            'estado' => self::normalizarEstadoExterno($request->input('estado')),
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
     * Campos del QBE y del diseñador: el catálogo de columnas del workbench.
     *
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposQbeDisponibles(): array
    {
        $out = [];
        foreach (OrdencompraListadoColumnas::camposFiltrables() as $key => $meta) {
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
        return OrdencompraListadoColumnas::camposOrdenables();
    }

    /**
     * Crown: sala (default primera) o todas (`sala_todas=1`). Fuera de Crown no filtra.
     *
     * @return array{0:?int,1:string}
     */
    private static function resolverSalaExterna(Request $request, ?int $salaDefault): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return [null, 'todas'];
        }
        if ($request->boolean('sala_todas') || $request->input('sala_scope') === 'todas') {
            return [null, 'todas'];
        }
        if ($request->filled('sala_id')) {
            return [(int) $request->input('sala_id'), 'una'];
        }
        if ($salaDefault !== null && $salaDefault > 0) {
            return [$salaDefault, 'una'];
        }

        return [null, 'todas'];
    }

    /**
     * Chip de partida de presupuesto. Solo aplica si el flag `oc_pedir_partida_capex` está activo.
     */
    public static function normalizarPresupuestoExterno($valor): string
    {
        if (! OrdencompraUiConfigSupport::pedirPartidaCapex()) {
            return '';
        }
        $valor = strtolower(trim((string) $valor));

        return in_array($valor, ['con', 'sin'], true) ? $valor : '';
    }

    public static function normalizarEstadoExterno($estado): string
    {
        $valor = strtoupper(trim((string) $estado));
        if ($valor === '' || ! OrdencompraEstados::esNombreValido($valor)) {
            return '';
        }

        return $valor;
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

    /**
     * Criterios del panel / búsqueda rápida (sin el filtro externo de empresa).
     */
    public static function tieneCriteriosTexto(array $filtros): bool
    {
        if (ListadoQbeSupport::tieneCriterios(is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : [])) {
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
            'campo' => 'numeroordencompra',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'sala_id' => null,
            'sala_scope' => 'todas',
            'presupuesto' => '',
            'estado' => '',
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
        $params = self::paraQueryStringEmpresa($filtros);

        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'numeroordencompra';
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

        $params = array_merge($params, ListadoQbeSupport::paraQueryString(
            is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : []
        ));
        $params = array_merge($params, ListadoOrdenamientoSupport::paraQueryString(
            is_array($filtros['sort'] ?? null) ? $filtros['sort'] : []
        ));
        $params = array_merge($params, ListadoAgrupacionSupport::paraQueryString(
            is_array($filtros['agrupar'] ?? null) ? $filtros['agrupar'] : []
        ));
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
     * Chips externos (sala, empresa, presupuesto, estado) + la vista abierta.
     * Limpiar el texto no los pierde.
     *
     * @return array<string, int|string>
     */
    public static function paraQueryStringExternos(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);
        if (! empty($filtros['vista_id'])) {
            $params['vista_id'] = (int) $filtros['vista_id'];
        } elseif (! empty($filtros['vista_estandar'])) {
            $params['vista_estandar'] = 1;
        }

        return $params;
    }

    /**
     * Solo el filtro externo de empresa (para Limpiar texto sin perder empresa).
     *
     * @return array<string, int|string>
     */
    public static function paraQueryStringEmpresa(array $filtros): array
    {
        $params = [];
        if (EntornoEmpresaSupport::esCrown()) {
            if (($filtros['sala_scope'] ?? 'todas') === 'todas') {
                $params['sala_todas'] = 1;
            } elseif (! empty($filtros['sala_id'])) {
                $params['sala_id'] = (int) $filtros['sala_id'];
            }
        }
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            $params['empresa_todas'] = 1;
        } elseif (! empty($filtros['empresa_id'])) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }

        $presupuesto = self::normalizarPresupuestoExterno($filtros['presupuesto'] ?? '');
        if ($presupuesto !== '') {
            $params['presupuesto'] = $presupuesto;
        }

        $estado = self::normalizarEstadoExterno($filtros['estado'] ?? '');
        if ($estado !== '') {
            $params['estado'] = $estado;
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (EntornoEmpresaSupport::esCrown()
            && ! empty($filtros['sala_id'])
            && ($filtros['sala_scope'] ?? 'una') !== 'todas') {
            $query->where('ordencompra.sala_id', (int) $filtros['sala_id']);
        }
        if (! empty($filtros['empresa_id'])) {
            $query->where('ordencompra.empresa_id', (int) $filtros['empresa_id']);
        }

        $presupuesto = self::normalizarPresupuestoExterno($filtros['presupuesto'] ?? '');
        if ($presupuesto === 'con') {
            $query->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('ordencompra_articulo')
                    ->whereColumn('ordencompra_articulo.ordencompra_id', 'ordencompra.id')
                    ->whereNotNull('ordencompra_articulo.partidagasto_id')
                    ->where('ordencompra_articulo.partidagasto_id', '>', 0);
            });
        } elseif ($presupuesto === 'sin') {
            $query->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('ordencompra_articulo')
                    ->whereColumn('ordencompra_articulo.ordencompra_id', 'ordencompra.id')
                    ->whereNotNull('ordencompra_articulo.partidagasto_id')
                    ->where('ordencompra_articulo.partidagasto_id', '>', 0);
            });
        }

        $estado = self::normalizarEstadoExterno($filtros['estado'] ?? '');
        if ($estado !== '') {
            $query->where('ordencompra.estadoordencompra', $estado);
        }

        $qbe = is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : [];
        $tieneQbe = ListadoQbeSupport::tieneCriterios($qbe);
        if ($tieneQbe) {
            self::aplicarQbe($query, $qbe);
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        if ($tieneQbe && $modo === self::MODO_QBE) {
            // La consulta avanzada reemplaza a la búsqueda rápida / panel.
            return;
        }

        if (! self::tieneCriteriosTexto($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'numeroordencompra', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            return;
        }

        if ($valor === '') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $id, $operador) {
            if ($id !== false) {
                $q->orWhere('ordencompra.id', (int) $id)
                    ->orWhere('ordencompra.numeroordencompra', (int) $id)
                    ->orWhere('requisicion.numerorequisicion', (int) $id);
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

            if (OrdencompraUiConfigSupport::solicitanteEditable()) {
                $q->orWhereIn('ordencompra.solicitante_usuario_id', function ($sub) use ($like) {
                    $sub->select('id')->from('usuario')->where('nombre', 'like', $like);
                });
            }

            $fecha = self::parsearFecha($valor);
            if ($fecha) {
                $q->orWhereDate('ordencompra.fecha', '=', $fecha)
                    ->orWhereDate('ordencompra.fechaentrega', '=', $fecha);
            }
        });
    }

    /** @return list<string> */
    private static function columnasTextoBusquedaGlobal(): array
    {
        $cols = [
            'ordencompra.numeroordencompra',
            'ordencompra.comentario',
            'ordencompra.detalle',
            'ordencompra.estadoordencompra',
            'ordencompra.tratamiento',
            'proveedor.nombre',
            'proveedor.codigo',
            'empresa.nombre',
            'centrocosto.nombre',
            'centrocosto.codigo',
            'usuario.nombre',
            'sector_legajocompra.nombre',
            'condicioncompra.nombre',
            'requisicion.numerorequisicion',
            'requisicion.motivotratamiento',
            'requisicion.contrataciondirecta',
        ];

        if (Schema::hasColumn('requisicion', 'nroinscripcion')) {
            $cols[] = 'requisicion.nroinscripcion';
        }

        return $cols;
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        if ($campoKey === 'nroinscripcion' && ! Schema::hasColumn('requisicion', 'nroinscripcion')) {
            return;
        }

        if (isset(OrdencompraCircuitoRelacion::campos()[$campoKey])) {
            OrdencompraCircuitoRelacion::aplicarFiltro($query, $campoKey, $operador, $valor, $valorHasta);

            return;
        }

        $def = self::CAMPOS[$campoKey]
            ?? self::camposQbeDisponibles()[$campoKey]
            ?? self::CAMPOS['numeroordencompra'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($type === 'fecha') {
            self::aplicarFechaColumna($query, (string) $def['column'], $operador, $valor, $valorHasta);

            return;
        }

        if ($type === 'decimal') {
            ListadoQbeSupport::aplicarDecimal($query, (string) $def['column'], $operador, $valor, $valorHasta);

            return;
        }

        if ($campoKey === 'nombreusuario' && OrdencompraUiConfigSupport::solicitanteEditable() && $operador !== 'vacio') {
            self::aplicarTextoSolicitante($query, $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * El nombre que se ve en Solicitante puede ser el elegido, no solo quien cargó.
     *
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     */
    private static function aplicarTextoSolicitante(Builder $query, string $operador, string $valor): void
    {
        if ($valor === '') {
            return;
        }

        $query->where(function ($q) use ($operador, $valor) {
            self::aplicarTexto($q, 'usuario.nombre', $operador, $valor);
            if ($operador === 'distinto') {
                return;
            }

            $like = $operador === 'igual' ? self::escapeLike($valor) : self::patronLike($operador, $valor);
            $q->orWhereIn('ordencompra.solicitante_usuario_id', function ($sub) use ($operador, $valor, $like) {
                $sub->select('id')->from('usuario');
                if ($operador === 'igual') {
                    $sub->where('nombre', '=', $valor);
                } else {
                    $sub->where('nombre', 'like', $like);
                }
            });
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
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
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
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
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
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
     * Los chips externos (sala, empresa, presupuesto, estado) no los pisa una vista guardada.
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
            'sala_id' => $base['sala_id'] ?? null,
            'sala_scope' => $base['sala_scope'] ?? 'todas',
            'presupuesto' => $base['presupuesto'] ?? '',
            'estado' => $base['estado'] ?? '',
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
            'campo' => (string) ($desdeVista['campo'] ?? $base['campo'] ?? 'numeroordencompra'),
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
     * Orden de la grilla. Sin criterios: fecha más reciente primero, ID como desempate.
     *
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

        $query->orderByDesc('ordencompra.fecha')
            ->orderByDesc('ordencompra.id');
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
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
                    (string) ($criterio['campo'] ?? 'numeroordencompra'),
                    (string) ($criterio['op'] ?? 'contiene'),
                    trim((string) ($criterio['valor'] ?? '')),
                    trim((string) ($criterio['valor_hasta'] ?? '')),
                    $boolean
                );
            }
        );
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

        if ($campoKey === 'nroinscripcion' && ! Schema::hasColumn('requisicion', 'nroinscripcion')) {
            return;
        }

        if (isset(OrdencompraCircuitoRelacion::campos()[$campoKey])) {
            OrdencompraCircuitoRelacion::aplicarFiltro($query, $campoKey, $operador, $valor, $valorHasta);

            return;
        }

        $def = self::camposQbeDisponibles()[$campoKey] ?? null;
        if ($def === null) {
            return;
        }
        $type = (string) ($def['type'] ?? 'texto');
        $column = (string) $def['column'];

        if ($campoKey === 'nombreusuario'
            && OrdencompraUiConfigSupport::solicitanteEditable()
            && $operador !== 'vacio') {
            self::aplicarTextoSolicitanteQbe($query, $operador, $valor, $valorHasta);

            return;
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
        if ($type === 'booleano') {
            self::aplicarBooleanoQbe($query, $column, $operador, $valor);

            return;
        }

        self::aplicarTextoQbe($query, $column, $operador, $valor, $valorHasta);
    }

    /**
     * El nombre que se ve en Solicitante puede ser el elegido, no solo quien cargó.
     *
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     */
    private static function aplicarTextoSolicitanteQbe(
        Builder $query,
        string $operador,
        string $valor,
        string $valorHasta
    ): void {
        $query->where(function (Builder $q) use ($operador, $valor, $valorHasta): void {
            self::aplicarTextoQbe($q, 'usuario.nombre', $operador, $valor, $valorHasta);
            $q->orWhereIn('ordencompra.solicitante_usuario_id', function ($sub) use ($operador, $valor, $valorHasta): void {
                $sub->select('id')->from('usuario');
                self::aplicarTextoQbe($sub, 'nombre', $operador, $valor, $valorHasta);
            });
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>|\Illuminate\Database\Query\Builder  $query
     */
    private static function aplicarTextoQbe(
        $query,
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
                $query->where(function ($q) use ($column, $valor): void {
                    $like = '%'.self::escapeLike($valor).'%';
                    $q->whereNull($column)->orWhere($column, '')->orWhere($column, 'not like', $like);
                });
                break;
            case 'contiene':
            default:
                $query->where(function ($q) use ($column, $valor): void {
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
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
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

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     */
    private static function aplicarBooleanoQbe(Builder $query, string $column, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column): void {
                $q->whereNull($column)->orWhere($column, 0);
            });

            return;
        }

        $truthy = in_array(mb_strtolower(trim($valor)), ['1', 'si', 'sí', 'true', 's', 'yes'], true);
        $query->where($column, $truthy ? 1 : 0);
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
