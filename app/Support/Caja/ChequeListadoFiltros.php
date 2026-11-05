<?php

namespace App\Support\Caja;

use App\Models\Compras\Proveedor;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de cheques (index).
 */
class ChequeListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const MODO_QBE = 'qbe';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'cheque.id', 'type' => 'entero', 'label' => 'ID'],
        'numerocheque' => ['column' => 'cheque.numerocheque', 'type' => 'texto', 'label' => 'Número'],
        'nro_interno_anita' => ['column' => 'cheque.nro_interno_anita', 'type' => 'entero', 'label' => 'Int. Anita'],
        'origen' => ['column' => 'cheque.origen', 'type' => 'texto', 'label' => 'Origen'],
        'estado' => ['column' => 'cheque.estado', 'type' => 'texto', 'label' => 'Estado'],
        'banco' => ['column' => 'banco.nombre', 'type' => 'texto', 'label' => 'Banco'],
        'cuentacaja' => ['column' => 'cuentacaja.nombre', 'type' => 'texto', 'label' => 'Cuenta de caja'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'cliente' => ['column' => 'cliente.nombre', 'type' => 'texto', 'label' => 'Cliente'],
        'proveedor_endoso' => ['column' => 'prov_pago.nombre', 'type' => 'texto', 'label' => 'Proveedor endoso'],
        'monto' => ['column' => 'cheque.monto', 'type' => 'decimal', 'label' => 'Monto'],
        'entregado' => ['column' => 'cheque.entregado', 'type' => 'texto', 'label' => 'Entregado'],
        'anombrede' => ['column' => 'cheque.anombrede', 'type' => 'texto', 'label' => 'A nombre de'],
        'beneficiario' => ['column' => 'cheque.entregado', 'type' => 'texto', 'label' => 'Beneficiario'],
        'banco_cta' => ['column' => 'banco.nombre', 'type' => 'texto', 'label' => 'Banco / Cta'],
        'tipo' => ['column' => 'cheque.negociable', 'type' => 'texto', 'label' => 'Tipo'],
        'fechaemision' => ['column' => 'cheque.fechaemision', 'type' => 'fecha', 'label' => 'Emisión'],
        'fechapago' => ['column' => 'cheque.fechapago', 'type' => 'fecha', 'label' => 'Pago'],
    ];

    /** @var array<string, array{column: string, label: string}> */
    public const ORDENES = [
        'fechapago' => ['column' => 'cheque.fechapago', 'label' => 'Fecha de cheque'],
        'fechaemision' => ['column' => 'cheque.fechaemision', 'label' => 'Fecha de emisión'],
        'numerocheque' => ['column' => 'cheque.numerocheque', 'label' => 'Número'],
        'monto' => ['column' => 'cheque.monto', 'label' => 'Monto'],
        'id' => ['column' => 'cheque.id', 'label' => 'ID'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'cheque.numerocheque',
        'cheque.entregado',
        'cheque.anombrede',
        'banco.nombre',
        'empresa.nombre',
        'cliente.nombre',
        'proveedor.nombre',
        'proveedor.codigo',
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
    public const OPERADORES_DECIMAL = ListadoQbeSupport::OPERADORES_DECIMAL;

    /** @var array<string, string> */
    public const OPERADORES_FECHA = ListadoQbeSupport::OPERADORES_FECHA;

    /** @var array<string, string> */
    public const OPERADORES_BOOLEANO = [];

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposQbeDisponibles(): array
    {
        return self::CAMPOS;
    }

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId,
                'empresa_scope' => $empresaScope,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO, self::MODO_QBE], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'numerocheque');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'numerocheque';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        $qbe = ListadoQbeSupport::resolverDesdeRequest(
            $request,
            self::CAMPOS,
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
            $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'numerocheque');
        }

        $cartera = $request->boolean('cartera');
        $endosado = $request->boolean('endosado');
        $paraDepositar = $request->boolean('para_depositar');
        $paraDepositarHasta = trim((string) $request->input('para_depositar_hasta', ''));
        if ($paraDepositarHasta !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $paraDepositarHasta)) {
            $paraDepositarHasta = '';
        }
        if ($paraDepositar && $paraDepositarHasta === '') {
            $paraDepositarHasta = date('Y-m-d');
        }
        [$orden, $ordenDir] = self::resolverOrden($request);
        if ($sort !== [] && isset(self::ORDENES[$sort[0]['campo']])) {
            $orden = $sort[0]['campo'];
            $ordenDir = $sort[0]['dir'];
        }

        $origen = strtoupper(trim((string) $request->input('origen', '')));
        if (! in_array($origen, ['E', 'R'], true)) {
            $origen = '';
        }
        // Conserva espacio (DIFERIDO) si viene en el query.
        $estadoRaw = $request->input('estado');
        $estado = $estadoRaw === null ? '' : (string) $estadoRaw;
        if ($cartera || $paraDepositar || $endosado) {
            $origen = '';
            $estado = '';
        }
        if ($paraDepositar) {
            $cartera = false;
            $endosado = false;
        }
        if ($cartera) {
            $endosado = false;
        }
        if ($endosado) {
            $cartera = false;
            $paraDepositar = false;
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
            'cartera' => $cartera,
            'endosado' => $endosado,
            'para_depositar' => $paraDepositar,
            'para_depositar_hasta' => $paraDepositarHasta,
            'origen' => $origen,
            'estado' => $estado,
            'orden' => $orden,
            'orden_dir' => $ordenDir,
            'qbe' => $qbe,
            'sort' => $sort,
            'agrupar' => $agrupar,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function resolverOrden(Request $request): array
    {
        $orden = (string) $request->input('orden', 'fechapago');
        if (! isset(self::ORDENES[$orden])) {
            $orden = 'fechapago';
        }
        $dir = strtolower((string) $request->input('orden_dir', 'desc'));
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        return [$orden, $dir];
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
     * Criterios del panel / búsqueda rápida (sin filtros externos de empresa / cartera / origen).
     */
    public static function tieneCriteriosTexto(array $filtros): bool
    {
        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }

        if (($filtros['modo'] ?? '') === self::MODO_QBE && ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? [])) {
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

        if (! empty($filtros['cartera'])) {
            return true;
        }

        if (! empty($filtros['endosado'])) {
            return true;
        }

        if (! empty($filtros['para_depositar'])) {
            return true;
        }

        if (trim((string) ($filtros['origen'] ?? '')) !== '') {
            return true;
        }

        if (array_key_exists('estado', $filtros) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
            return true;
        }

        return false;
    }

    /**
     * @return array{
     *   modo: string,
     *   campo: string,
     *   operador: string,
     *   valor: string,
     *   valor_hasta: string,
     *   busqueda: string,
     *   empresa_id: ?int,
     *   empresa_scope: string,
     *   cartera: bool,
     *   para_depositar: bool,
     *   origen: string,
     *   estado: string,
     *   orden: string,
     *   orden_dir: string
     * }
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'numerocheque',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'cartera' => false,
            'endosado' => false,
            'para_depositar' => false,
            'para_depositar_hasta' => '',
            'origen' => '',
            'estado' => '',
            'orden' => 'fechapago',
            'orden_dir' => 'desc',
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

        if (! empty($filtros['cartera'])) {
            $params['cartera'] = 1;
        }
        if (! empty($filtros['endosado'])) {
            $params['endosado'] = 1;
        }
        if (! empty($filtros['para_depositar'])) {
            $params['para_depositar'] = 1;
            $hasta = (string) ($filtros['para_depositar_hasta'] ?? '');
            if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
                $params['para_depositar_hasta'] = $hasta;
            }
        }
        if (($filtros['origen'] ?? '') !== '') {
            $params['origen'] = $filtros['origen'];
        }
        if (array_key_exists('estado', $filtros) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
            $params['estado'] = $filtros['estado'];
        }
        $params = array_merge($params, self::paraQueryStringOrden($filtros));

        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'numerocheque';
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
        if (($filtros['modo'] ?? '') === self::MODO_QBE) {
            $params['filtro_modo'] = self::MODO_QBE;
            $params = array_merge($params, ListadoQbeSupport::paraQueryString(
                ListadoQbeSupport::normalizar(
                    $filtros['qbe'] ?? [],
                    self::CAMPOS,
                    static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
                )
            ));
        }
        if (($filtros['sort'] ?? []) !== []) {
            $params = array_merge($params, ListadoOrdenamientoSupport::paraQueryString($filtros['sort']));
        }
        if (($filtros['agrupar'] ?? []) !== []) {
            $params = array_merge($params, ListadoAgrupacionSupport::paraQueryString($filtros['agrupar']));
        }

        return $params;
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
     * Empresa + quick filters (cartera / origen / estado) para limpiar solo el texto.
     *
     * @return array<string, string|int|bool>
     */
    public static function paraQueryStringExternos(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);

        if (! empty($filtros['cartera'])) {
            $params['cartera'] = 1;
        }
        if (! empty($filtros['endosado'])) {
            $params['endosado'] = 1;
        }
        if (! empty($filtros['para_depositar'])) {
            $params['para_depositar'] = 1;
            $hasta = (string) ($filtros['para_depositar_hasta'] ?? '');
            if ($hasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
                $params['para_depositar_hasta'] = $hasta;
            }
        }
        if (($filtros['origen'] ?? '') !== '') {
            $params['origen'] = $filtros['origen'];
        }
        if (array_key_exists('estado', $filtros) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
            $params['estado'] = $filtros['estado'];
        }
        $params = array_merge($params, self::paraQueryStringOrden($filtros));

        return $params;
    }

    /**
     * @return array<string, string>
     */
    public static function paraQueryStringOrden(array $filtros): array
    {
        $orden = (string) ($filtros['orden'] ?? 'fechapago');
        $dir = (string) ($filtros['orden_dir'] ?? 'desc');
        if (! isset(self::ORDENES[$orden])) {
            $orden = 'fechapago';
        }
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }
        if ($orden === 'fechapago' && $dir === 'desc') {
            return [];
        }

        return [
            'orden' => $orden,
            'orden_dir' => $dir,
        ];
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    public static function aplicarOrden(Builder $query, array $filtros): void
    {
        $sort = ListadoOrdenamientoSupport::normalizar($filtros['sort'] ?? [], self::camposOrdenables());
        if ($sort !== []) {
            ListadoAgrupacionSupport::aplicarOrdenPrefijo(
                $query,
                ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], self::camposOrdenables()),
                self::camposOrdenables()
            );
            ListadoOrdenamientoSupport::aplicar($query, $sort, self::camposOrdenables(), [
                'campo' => 'id',
                'dir' => ListadoOrdenamientoSupport::DIR_DESC,
            ]);

            return;
        }

        $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], self::camposOrdenables());
        if ($agrupar !== []) {
            ListadoAgrupacionSupport::aplicarOrdenPrefijo($query, $agrupar, self::camposOrdenables());
        }

        $orden = (string) ($filtros['orden'] ?? 'fechapago');
        if (! isset(self::ORDENES[$orden])) {
            $orden = 'fechapago';
        }
        $dir = (string) ($filtros['orden_dir'] ?? 'desc');
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $query->orderBy(self::ORDENES[$orden]['column'], $dir);
        if ($orden !== 'id') {
            $query->orderBy('cheque.id', 'desc');
        }
    }

    /**
     * Joins de maestros solo si un filtro, el orden o un corte leen esa tabla.
     * La búsqueda global resuelve los ids en un subselect y no los necesita.
     *
     * @param  array<string, mixed>  $filtros
     * @return list<string>
     */
    public static function tablasJoin(array $filtros): array
    {
        $mapa = [
            'banco' => ['banco'],
            'banco_cta' => ['banco', 'cuentacaja'],
            'cuentacaja' => ['cuentacaja'],
            'empresa' => ['empresa'],
            'cliente' => ['cliente'],
            'moneda' => ['moneda'],
        ];
        $tablas = [];
        foreach (self::camposQueLeenMaestro($filtros) as $campo) {
            foreach ($mapa[$campo] ?? [] as $tabla) {
                $tablas[$tabla] = $tabla;
            }
        }

        return array_values($tablas);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<string>
     */
    private static function camposQueLeenMaestro(array $filtros): array
    {
        $campos = [];
        $modo = (string) ($filtros['modo'] ?? self::MODO_TODOS);
        if ($modo === self::MODO_CAMPO && ($filtros['campo'] ?? '') !== '') {
            $campos[] = (string) $filtros['campo'];
        }
        if ($modo === self::MODO_QBE) {
            $campos = array_merge($campos, self::camposEnQbe(is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : []));
        }
        foreach ((array) ($filtros['agrupar'] ?? []) as $campo) {
            $campos[] = (string) $campo;
        }
        foreach ((array) ($filtros['sort'] ?? []) as $item) {
            if (is_array($item) && isset($item['campo'])) {
                $campos[] = (string) $item['campo'];
            }
        }

        return $campos;
    }

    /**
     * @param  array<mixed>  $nodo
     * @return list<string>
     */
    private static function camposEnQbe(array $nodo): array
    {
        $campos = [];
        if (isset($nodo['campo']) && is_string($nodo['campo'])) {
            $campos[] = $nodo['campo'];
        }
        foreach ($nodo as $valor) {
            if (is_array($valor)) {
                $campos = array_merge($campos, self::camposEnQbe($valor));
            }
        }

        return $campos;
    }

    /**
     * El join al proveedor solo hace falta para agrupar u ordenar por esa columna.
     * La búsqueda resuelve los ids aparte, sin arrastrar el join en cada fila.
     *
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    public static function aplicarJoinProveedorEndoso(Builder $query, array $filtros): void
    {
        if (! self::necesitaJoinProveedorEndoso($filtros)) {
            return;
        }

        $query->leftJoin('pagoproveedor as pp_endoso', 'pp_endoso.id', '=', 'cheque.pagoproveedor_id')
            ->leftJoin('proveedor as prov_pago', 'prov_pago.id', '=', 'pp_endoso.proveedor_id')
            ->leftJoin('proveedor as prov_cheque', 'prov_cheque.id', '=', 'cheque.proveedor_id');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private static function necesitaJoinProveedorEndoso(array $filtros): bool
    {
        foreach ((array) ($filtros['agrupar'] ?? []) as $campo) {
            if ($campo === 'proveedor_endoso') {
                return true;
            }
        }
        foreach ((array) ($filtros['sort'] ?? []) as $item) {
            if (is_array($item) && ($item['campo'] ?? '') === 'proveedor_endoso') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('cheque.empresa_id', (int) $filtros['empresa_id']);
        }

        if (! empty($filtros['para_depositar'])) {
            $hasta = (string) ($filtros['para_depositar_hasta'] ?? '');
            if ($hasta === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
                $hasta = date('Y-m-d');
            }
            self::aplicarSoloParaDepositar($query, $hasta);
        } elseif (! empty($filtros['cartera'])) {
            $query->where('cheque.origen', 'R')
                ->whereNull('cheque.pagoproveedor_id')
                ->whereNull('cheque.proveedor_id')
                ->where(function ($q) {
                    $q->whereIn('cheque.estado', [' ', 'N'])
                        ->orWhereNull('cheque.estado')
                        ->orWhere('cheque.estado', '');
                });
        } elseif (! empty($filtros['endosado'])) {
            self::restringirEndosados($query);
        } else {
            if (($filtros['origen'] ?? '') !== '') {
                $query->where('cheque.origen', $filtros['origen']);
            }
            if (array_key_exists('estado', $filtros) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
                $query->where('cheque.estado', $filtros['estado']);
            }
        }

        if (! self::tieneCriteriosTexto($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_QBE) {
            ListadoQbeSupport::aplicar(
                $query,
                ListadoQbeSupport::normalizar(
                    $filtros['qbe'] ?? [],
                    self::CAMPOS,
                    static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
                ),
                static function (Builder $q, array $criterio): void {
                    self::aplicarEnCampo(
                        $q,
                        (string) ($criterio['campo'] ?? 'numerocheque'),
                        (string) ($criterio['op'] ?? 'contiene'),
                        (string) ($criterio['valor'] ?? ''),
                        (string) ($criterio['valor_hasta'] ?? '')
                    );
                }
            );

            return;
        }

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'numerocheque', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * CHT en cartera con fechapago ≤ fecha de corte (default hoy).
     *
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    public static function aplicarSoloParaDepositar(Builder $query, ?string $hastaYmd = null): void
    {
        $hasta = $hastaYmd && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hastaYmd)
            ? $hastaYmd
            : date('Y-m-d');

        $query->where('cheque.origen', 'R')
            ->whereNull('cheque.pagoproveedor_id')
            ->whereNull('cheque.fecha_deposito')
            ->whereDate('cheque.fechapago', '<=', $hasta)
            ->where(function ($q) {
                $q->whereNull('cheque.estado')
                    ->orWhereNotIn('cheque.estado', ['R', 'A', '*']);
            })
            ->where(function ($q) {
                $q->whereNull('cheque.nro_caucion')
                    ->orWhere('cheque.nro_caucion', '')
                    ->orWhere('cheque.nro_caucion', '0');
            });
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['cheque.numerocheque', 'cheque.entregado', 'cheque.anombrede'] as $col) {
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
                $q->orWhere('cheque.id', (int) $id)
                    ->orWhere('cheque.nro_interno_anita', (int) $id);
            }
            $textCols = [
                'cheque.numerocheque',
                'cheque.origen',
                'cheque.estado',
                'cheque.entregado',
                'cheque.anombrede',
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
            self::orWhereFkPorTexto($q, 'cheque.banco_id', 'banco', ['nombre'], $operador, $valor);
            self::orWhereFkPorTexto($q, 'cheque.empresa_id', 'empresa', ['nombre'], $operador, $valor);
            self::orWhereFkPorTexto($q, 'cheque.cliente_id', 'cliente', ['nombre', 'codigo'], $operador, $valor);
            self::orWhereFkPorTexto($q, 'cheque.moneda_id', 'moneda', ['nombre', 'abreviatura'], $operador, $valor);
            self::aplicarProveedorEndoso($q, $operador, $valor, true);
            self::aplicarCoincidenciaOrigenEstado($q, $valor, $operador);
            if (is_numeric(str_replace([',', '.'], ['', '.'], $valor))) {
                $monto = (float) str_replace(',', '.', $valor);
                $q->orWhere('cheque.monto', $monto);
            }
        });
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    private static function aplicarCoincidenciaOrigenEstado(Builder $query, string $valor, string $operador): void
    {
        if ($operador !== 'contiene') {
            return;
        }

        $valorNorm = mb_strtolower($valor);
        if (str_contains($valorNorm, 'emit')) {
            $query->orWhere('cheque.origen', 'E');
        }
        if (str_contains($valorNorm, 'recib') || str_contains($valorNorm, 'cartera')) {
            $query->orWhere('cheque.origen', 'R');
        }
        if (str_contains($valorNorm, 'rechaz')) {
            $query->orWhere('cheque.estado', 'R');
        }
        if (str_contains($valorNorm, 'anul')) {
            $query->orWhere('cheque.estado', 'A');
        }
        if (str_contains($valorNorm, 'debit')) {
            $query->orWhere('cheque.estado', '*');
        }
        if (str_contains($valorNorm, 'difer')) {
            $query->orWhere(function ($q) {
                $q->where('cheque.estado', ' ')
                    ->whereNull('cheque.pagoproveedor_id')
                    ->whereNull('cheque.proveedor_id');
            });
        }
        if (str_contains($valorNorm, 'endos')) {
            $query->orWhere(function ($q) {
                self::restringirEndosados($q);
            });
        }
    }

    /**
     * Nombre o código de un maestro: se resuelve en su tabla y el cheque
     * se filtra por el id. Así el LIKE no obliga a unir esa tabla con todo el listado.
     *
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     * @param  list<string>  $columnas
     */
    private static function orWhereFkPorTexto(Builder $query, string $fk, string $tabla, array $columnas, string $operador, string $valor): void
    {
        if ($operador === 'vacio' || trim($valor) === '') {
            return;
        }

        $query->orWhereIn($fk, function ($sub) use ($tabla, $columnas, $operador, $valor) {
            $sub->select($tabla.'.id')->from($tabla)->where(function ($w) use ($tabla, $columnas, $operador, $valor) {
                foreach ($columnas as $col) {
                    $full = $tabla.'.'.$col;
                    if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($full)) {
                        continue;
                    }
                    if ($operador === 'igual') {
                        $w->orWhere($full, '=', $valor);
                        continue;
                    }
                    if ($operador === 'distinto') {
                        $w->orWhere($full, '!=', $valor);
                        continue;
                    }
                    $w->orWhere($full, 'like', self::patronLike($operador, $valor));
                    if ($operador === 'contiene' && self::usaCoincidenciaFlexibleEnColumna($full)) {
                        CoincidenciaFlexibleTexto::aplicar(
                            $w,
                            $full,
                            $valor,
                            true,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                        );
                    }
                }
            });
        });
    }

    /**
     * Proveedor del endoso: primero se buscan los ids en la tabla chica
     * y después se filtra el cheque por esos ids.
     *
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    private static function aplicarProveedorEndoso(Builder $query, string $operador, string $valor, bool $comoOr): void
    {
        if ($operador === 'vacio') {
            $callback = function ($q) {
                $q->whereNull('cheque.proveedor_id')->whereNull('cheque.pagoproveedor_id');
            };
            if ($comoOr) {
                $query->orWhere($callback);
            } else {
                $query->where($callback);
            }

            return;
        }

        $ids = self::idsProveedorCoincidentes($operador, $valor);
        if ($ids === []) {
            if (! $comoOr) {
                $query->whereRaw('0 = 1');
            }

            return;
        }

        $callback = function ($q) use ($ids) {
            $q->whereIn('cheque.proveedor_id', $ids)
                ->orWhereIn('cheque.pagoproveedor_id', function ($sub) use ($ids) {
                    $sub->select('id')->from('pagoproveedor')->whereIn('proveedor_id', $ids);
                });
        };
        if ($comoOr) {
            $query->orWhere($callback);
        } else {
            $query->where($callback);
        }
    }

    /**
     * @return list<int>
     */
    private static function idsProveedorCoincidentes(string $operador, string $valor): array
    {
        if ($operador === 'vacio' || trim($valor) === '') {
            return [];
        }

        $q = Proveedor::query()->withoutGlobalScopes()->select('proveedor.id');
        self::aplicarTextoEnColumnas($q, ['proveedor.nombre', 'proveedor.codigo'], $operador, $valor);

        return $q->pluck('id')->map(static fn ($id) => (int) $id)->all();
    }

    /**
     * Recibido que ya se entregó a un proveedor.
     *
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    private static function restringirEndosados(Builder $query): void
    {
        $query->where('cheque.origen', 'R')
            ->where(function ($q) {
                $q->whereNotNull('cheque.pagoproveedor_id')
                    ->orWhereNotNull('cheque.proveedor_id');
            });
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['numerocheque'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($type === 'decimal') {
            ListadoQbeSupport::aplicarDecimal($query, (string) $def['column'], $operador, $valor, $valorHasta);

            return;
        }

        if ($type === 'fecha') {
            ListadoQbeSupport::aplicarFecha($query, (string) $def['column'], $operador, $valor, $valorHasta);

            return;
        }

        if ($campoKey === 'banco_cta') {
            self::aplicarTextoEnColumnas($query, ['banco.nombre', 'cuentacaja.nombre'], $operador, $valor);

            return;
        }

        if ($campoKey === 'beneficiario') {
            self::aplicarTextoEnColumnas($query, ['cheque.entregado', 'cheque.anombrede'], $operador, $valor);

            return;
        }

        if ($campoKey === 'origen') {
            self::aplicarOrigen($query, $operador, $valor);

            return;
        }

        if ($campoKey === 'estado') {
            self::aplicarEstado($query, $operador, $valor);

            return;
        }

        if ($campoKey === 'proveedor_endoso') {
            self::aplicarProveedorEndoso($query, $operador, $valor, false);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    private static function aplicarOrigen(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                $q->whereNull('cheque.origen')->orWhere('cheque.origen', '');
            });

            return;
        }
        if ($valor === '') {
            return;
        }

        $valorNorm = mb_strtolower(trim($valor));
        $codigo = match (true) {
            str_contains($valorNorm, 'emit') => 'E',
            str_contains($valorNorm, 'recib') => 'R',
            in_array(mb_strtoupper($valor), ['E', 'R'], true) => mb_strtoupper($valor),
            default => null,
        };

        if ($codigo !== null && in_array($operador, ['contiene', 'igual', 'empieza', 'termina'], true)) {
            $query->where('cheque.origen', $codigo);

            return;
        }

        self::aplicarTexto($query, 'cheque.origen', $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Cheque>  $query
     */
    private static function aplicarEstado(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                $q->whereNull('cheque.estado')->orWhere('cheque.estado', '');
            });

            return;
        }
        if ($valor === '') {
            return;
        }

        $valorNorm = mb_strtolower(trim($valor));
        if (str_contains($valorNorm, 'endos')) {
            self::restringirEndosados($query);

            return;
        }

        $codigo = match (true) {
            str_contains($valorNorm, 'difer') => ' ',
            str_contains($valorNorm, 'debit') => '*',
            str_contains($valorNorm, 'cierr') => 'C',
            str_contains($valorNorm, 'anul') => 'A',
            str_contains($valorNorm, 'rechaz') => 'R',
            str_contains($valorNorm, 'no_pres') || str_contains($valorNorm, 'no present') => 'N',
            in_array(mb_strtoupper($valor), [' ', '*', 'C', 'A', 'R', 'N'], true) => mb_strtoupper($valor),
            default => null,
        };

        if ($codigo !== null && in_array($operador, ['contiene', 'igual', 'empieza', 'termina'], true)) {
            $query->where('cheque.estado', $codigo);
            if ($codigo === ' ' && str_contains($valorNorm, 'difer')) {
                $query->whereNull('cheque.pagoproveedor_id')
                    ->whereNull('cheque.proveedor_id');
            }

            return;
        }

        self::aplicarTexto($query, 'cheque.estado', $operador, $valor);
    }

    private static function aplicarTexto(QueryBuilder $query, string $column, string $operador, string $valor): void
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
     * @param  Builder<\App\Models\Caja\Cheque>  $query
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
     * @param  list<string>  $columnas
     */
    private static function aplicarTextoEnColumnas(QueryBuilder $query, array $columnas, string $operador, string $valor): void
    {
        $columnas = array_values(array_filter(
            $columnas,
            static fn (string $col): bool => ListadoOrdenamientoSupport::esColumnaSqlSegura($col)
        ));
        if ($columnas === []) {
            return;
        }

        $query->where(function ($q) use ($columnas, $operador, $valor) {
            foreach ($columnas as $i => $col) {
                $metodo = $i === 0 ? 'where' : 'orWhere';
                $q->{$metodo}(function ($w) use ($col, $operador, $valor) {
                    self::aplicarTexto($w, $col, $operador, $valor);
                });
            }
        });
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
            'decimal' => array_keys(self::OPERADORES_DECIMAL),
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
            'decimal' => self::OPERADORES_DECIMAL,
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };
    }

    /**
     * @return array<string, array{column: string, type: string, label: string, attr: string}>
     */
    public static function camposOrdenables(): array
    {
        $out = [];
        foreach (self::CAMPOS as $key => $meta) {
            $out[$key] = [
                'column' => $meta['column'],
                'type' => $meta['type'],
                'label' => $meta['label'],
                'attr' => $key,
            ];
        }

        return $out;
    }

    /**
     * La vista rápida (empresa, cartera, origen, estado) manda sobre la vista guardada.
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
            'cartera' => $base['cartera'] ?? false,
            'endosado' => $base['endosado'] ?? false,
            'para_depositar' => $base['para_depositar'] ?? false,
            'para_depositar_hasta' => $base['para_depositar_hasta'] ?? '',
            'origen' => $base['origen'] ?? '',
            'estado' => $base['estado'] ?? '',
        ];

        $qbeExplicito = ! empty($base['_qbe_explicito']);
        unset($base['_qbe_explicito']);

        if ($qbeExplicito || self::tieneCriteriosTexto($base) || ($base['sort'] ?? []) !== [] || ($base['agrupar'] ?? []) !== []) {
            if (($base['sort'] ?? []) === [] && ! empty($desdeVista['sort']) && is_array($desdeVista['sort'])) {
                $base['sort'] = $desdeVista['sort'];
            }
            if (($base['agrupar'] ?? []) === [] && ! empty($desdeVista['agrupar']) && is_array($desdeVista['agrupar'])) {
                $base['agrupar'] = $desdeVista['agrupar'];
            }

            return array_merge($base, $externos);
        }

        $qbe = ListadoQbeSupport::normalizar(
            $desdeVista['qbe'] ?? [],
            self::CAMPOS,
            static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
        );
        $modo = (string) ($desdeVista['modo'] ?? self::MODO_TODOS);
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $modo = self::MODO_QBE;
        }

        return array_merge($base, $externos, [
            'modo' => $modo,
            'qbe' => $qbe,
            'sort' => is_array($desdeVista['sort'] ?? null) ? $desdeVista['sort'] : ($base['sort'] ?? []),
            'agrupar' => is_array($desdeVista['agrupar'] ?? null) ? $desdeVista['agrupar'] : ($base['agrupar'] ?? []),
        ]);
    }
}
