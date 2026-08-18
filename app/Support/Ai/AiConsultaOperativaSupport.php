<?php

namespace App\Support\Ai;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Empresa;
use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Venta;
use App\Repositories\Compras\Proveedor_CuentacorrienteRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Stock\ArticuloRepositoryInterface;
use App\Repositories\Ventas\Cliente_CuentacorrienteRepository;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Support\Compras\ComprasKpisOperativosSupport;
use App\Support\Contable\CuentacontableSaldoMesSupport;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Stock\ArticuloSaldosDepositoSupport;
use App\Support\Stock\ArticuloUsoInsumoSupport;
use App\Support\Stock\MovimientosArticuloDepositoSupport;
use App\Support\Stock\RecuentoMovimientosArticuloSupport;
use Illuminate\Support\Facades\DB;

/**
 * Grounding de consultas operativas acotadas (Fase C): maestros + snapshots tipados.
 * Sin LLM ni embeddings: resuelve por código/ID y arma párrafos + links.
 */
final class AiConsultaOperativaSupport
{
    public const INTENT_ARTICULO_SALDO = 'articulo_saldo';

    public const INTENT_ARTICULO_KARDEX = 'articulo_kardex';

    public const INTENT_CLIENTE = 'cliente';

    public const INTENT_PROVEEDOR = 'proveedor';

    public const INTENT_PROVEEDOR_CTACTE = 'proveedor_ctacte';

    public const INTENT_CLIENTE_CTACTE = 'cliente_ctacte';

    public const INTENT_ORDENCOMPRA = 'ordencompra';

    public const INTENT_ARBOL_OC = 'arbol_oc';

    public const INTENT_SALDO_CUENTA = 'saldo_cuenta';

    public const INTENT_MAYOR_CUENTA = 'mayor_cuenta';

    public const INTENT_ASIENTO = 'asiento';

    public const INTENT_COMPROBANTE_PROVEEDOR = 'comprobante_proveedor';

    public const INTENT_FACTURA_VENTA = 'factura_venta';

    public const INTENT_PLAN_AGENTE = 'plan_agente';

    public const INTENT_CONSULTAR_MANUAL = 'consultar_manual';

    public const INTENT_PEDIDO_CONSUMO_SECTOR = 'pedido_consumo_sector';

    public const INTENT_COMPRAS_KPI_RESUMEN = 'compras_kpi_resumen';

    public const INTENT_COMPRAS_KPI_PROCESO = 'compras_kpi_proceso';

    public const INTENT_COMPRAS_KPI_PRODUCTIVIDAD = 'compras_kpi_productividad';

    public const INTENT_OC_PENDIENTES_FIRMA = 'oc_pendientes_firma';

    public const INTENT_OC_VENCIDAS_SIN_RECEPCION = 'oc_vencidas_sin_recepcion';

    public const INTENT_LEAD_TIME_OC_RECEPCION = 'lead_time_oc_recepcion';

    public const INTENT_TOP_PROVEEDORES_MONTO = 'top_proveedores_monto';

    public const INTENT_RQ_SIN_OC = 'rq_sin_oc';

    /** @return array<string, string> */
    public static function intentsEtiquetas(): array
    {
        return [
            self::INTENT_ARTICULO_SALDO => 'Saldo de artículo (SKU)',
            self::INTENT_ARTICULO_KARDEX => 'Kardex / movimientos de artículo',
            self::INTENT_CLIENTE => 'Ficha de cliente',
            self::INTENT_CLIENTE_CTACTE => 'Cuenta corriente de cliente',
            self::INTENT_PROVEEDOR => 'Ficha de proveedor',
            self::INTENT_PROVEEDOR_CTACTE => 'Cuenta corriente de proveedor',
            self::INTENT_ORDENCOMPRA => 'Estado de orden de compra',
            self::INTENT_ARBOL_OC => 'Árbol de aprobación de OC',
            self::INTENT_SALDO_CUENTA => 'Saldo de cuenta contable',
            self::INTENT_MAYOR_CUENTA => 'Mayor contable (cuenta / CC / OC / empresa / fechas)',
            self::INTENT_ASIENTO => 'Asiento contable',
            self::INTENT_COMPROBANTE_PROVEEDOR => 'Comprobante / factura de proveedor',
            self::INTENT_FACTURA_VENTA => 'Factura de venta',
            self::INTENT_PLAN_AGENTE => 'Plan agente (HITL ante desvío / deuda / firma)',
            self::INTENT_CONSULTAR_MANUAL => 'Manual / ayuda (RAG)',
            self::INTENT_PEDIDO_CONSUMO_SECTOR => 'Pedido por consumo (CC + depósito)',
            self::INTENT_COMPRAS_KPI_RESUMEN => 'KPI / resumen operativo de Compras',
            self::INTENT_COMPRAS_KPI_PROCESO => 'KPI proceso de Compras (ciclo / gestión / COM / % abiertas)',
            self::INTENT_COMPRAS_KPI_PRODUCTIVIDAD => 'KPI productividad de Compras (OC / ahorro por comprador)',
            self::INTENT_OC_PENDIENTES_FIRMA => 'OC pendientes de firma',
            self::INTENT_OC_VENCIDAS_SIN_RECEPCION => 'OC vencidas sin recepción',
            self::INTENT_LEAD_TIME_OC_RECEPCION => 'Lead time OC → recepción',
            self::INTENT_TOP_PROVEEDORES_MONTO => 'Top proveedores por monto',
            self::INTENT_RQ_SIN_OC => 'Requisiciones con líneas sin OC',
        ];
    }

    /**
     * Intents visibles/ejecutables según permisos del rol en sesión.
     * Además de ejecutar-consulta-ia (panel), cada intent exige permiso de módulo
     * y, en contable, consulta-ia-contable.
     *
     * @return array<string, string>
     */
    public static function intentsEtiquetasPermitidos(): array
    {
        $out = [];
        foreach (self::intentsEtiquetas() as $intent => $etiqueta) {
            if (self::usuarioPuedeIntent($intent)) {
                $out[$intent] = $etiqueta;
            }
        }

        return $out;
    }

    /** ¿El rol actual puede ejecutar este intent vía IA? */
    public static function usuarioPuedeIntent(string $intent): bool
    {
        $intent = strtolower(trim($intent));
        if (! array_key_exists($intent, self::intentsEtiquetas())) {
            return false;
        }

        // Contable vía IA: permiso dedicado (además del del módulo)
        if (in_array($intent, [
            self::INTENT_MAYOR_CUENTA,
            self::INTENT_SALDO_CUENTA,
            self::INTENT_ASIENTO,
        ], true) && ! can('consulta-ia-contable', false)) {
            return false;
        }

        return match ($intent) {
            self::INTENT_ARTICULO_SALDO,
            self::INTENT_ARTICULO_KARDEX => can('listar-articulos', false) || can('editar-articulos', false),
            self::INTENT_CLIENTE => can('listar-clientes', false) || can('editar-cliente', false),
            self::INTENT_CLIENTE_CTACTE => can('listar-cuentacorriente-cliente', false)
                || can('listar-clientes', false)
                || can('editar-cliente', false),
            self::INTENT_PROVEEDOR => can('listar-proveedor', false) || can('editar-proveedor', false),
            self::INTENT_PROVEEDOR_CTACTE => can('listar-cuentacorriente-proveedor', false)
                || can('listar-proveedor', false)
                || can('editar-proveedor', false),
            self::INTENT_ORDENCOMPRA,
            self::INTENT_ARBOL_OC => can('listar-ordencompra', false) || can('editar-ordencompra', false),
            self::INTENT_SALDO_CUENTA => can('listar-mayor-plano-cuenta', false)
                || can('listar-cuentas-contables', false)
                || can('listar-sumas-saldos', false),
            self::INTENT_MAYOR_CUENTA => can('listar-mayor-plano-cuenta', false) || can('listar-asiento', false),
            self::INTENT_ASIENTO => can('listar-asiento', false) || can('editar-asiento', false),
            self::INTENT_COMPROBANTE_PROVEEDOR => can('listar-comprobante-proveedor', false)
                || can('editar-comprobante-proveedor', false),
            self::INTENT_FACTURA_VENTA => can('listar-factura', false) || can('editar-factura', false) || can('facturar', false),
            self::INTENT_PLAN_AGENTE => true,
            self::INTENT_CONSULTAR_MANUAL => AiManualRagSupport::habilitado(),
            self::INTENT_PEDIDO_CONSUMO_SECTOR => can('listar-articulos', false)
                || can('listar-requisicion', false)
                || can('crear-requisicion', false)
                || can('listar-requisicion-sala', false)
                || can('crear-requisicion-sala', false),
            self::INTENT_COMPRAS_KPI_RESUMEN,
            self::INTENT_COMPRAS_KPI_PROCESO,
            self::INTENT_COMPRAS_KPI_PRODUCTIVIDAD,
            self::INTENT_OC_PENDIENTES_FIRMA,
            self::INTENT_LEAD_TIME_OC_RECEPCION,
            self::INTENT_TOP_PROVEEDORES_MONTO => can('listar-ordencompra', false)
                || can('listar-proveedor', false)
                || can('listar-comprobante-proveedor', false)
                || can('listar-kpi-compras', false),
            self::INTENT_OC_VENCIDAS_SIN_RECEPCION => can('listar-ordencompra', false)
                || can('listar-recepcion-proveedor', false)
                || can('listar-kpi-compras', false),
            self::INTENT_RQ_SIN_OC => can('listar-requisicion', false)
                || can('listar-ordencompra', false)
                || can('listar-kpi-compras', false),
            default => false,
        };
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array{
     *   ok: bool,
     *   intent: string,
     *   score: float,
     *   parrafos: list<string>,
     *   links: list<array{etiqueta: string, url: string}>,
     *   datos: array<string,mixed>,
     *   tabla?: array{columnas: list<array{key: string, label: string}>, filas: list<array<string, string>>},
     *   error?: string
     * }
     */
    public static function consultar(string $intent, array $params = []): array
    {
        $intent = strtolower(trim($intent));
        if (! array_key_exists($intent, self::intentsEtiquetas())) {
            return self::fallo($intent, 'Intent no soportado. Elija una consulta de la lista.');
        }
        $omitirPermisoRol = ! empty($params['_mcp_bridge']);
        unset($params['_mcp_bridge']);
        if (! $omitirPermisoRol && ! self::usuarioPuedeIntent($intent)) {
            return self::fallo($intent, 'Sin permiso para esta consulta IA con su rol actual.');
        }

        return match ($intent) {
            self::INTENT_ARTICULO_SALDO => self::consultarArticuloSaldo($params),
            self::INTENT_ARTICULO_KARDEX => self::consultarArticuloKardex($params),
            self::INTENT_CLIENTE => self::consultarCliente($params),
            self::INTENT_CLIENTE_CTACTE => self::consultarClienteCtacte($params),
            self::INTENT_PROVEEDOR => self::consultarProveedor($params),
            self::INTENT_PROVEEDOR_CTACTE => self::consultarProveedorCtacte($params),
            self::INTENT_ORDENCOMPRA => self::consultarOrdencompra($params),
            self::INTENT_ARBOL_OC => self::consultarArbolOc($params),
            self::INTENT_SALDO_CUENTA => self::consultarSaldoCuenta($params),
            self::INTENT_MAYOR_CUENTA => self::consultarMayorCuenta($params),
            self::INTENT_ASIENTO => self::consultarAsiento($params),
            self::INTENT_COMPROBANTE_PROVEEDOR => self::consultarComprobanteProveedor($params),
            self::INTENT_FACTURA_VENTA => self::consultarFacturaVenta($params),
            self::INTENT_PLAN_AGENTE => AiAgenteOperativoSupport::proponerPlan(
                (string) ($params['evento'] ?? $params['valor'] ?? ''),
                $params
            ),
            self::INTENT_CONSULTAR_MANUAL => AiManualRagSupport::consultar($params),
            self::INTENT_PEDIDO_CONSUMO_SECTOR => self::consultarPedidoConsumoSector($params),
            self::INTENT_COMPRAS_KPI_RESUMEN => self::consultarComprasKpi(
                self::INTENT_COMPRAS_KPI_RESUMEN,
                fn () => ComprasKpisOperativosSupport::resumen($params)
            ),
            self::INTENT_COMPRAS_KPI_PROCESO => self::consultarComprasKpi(
                self::INTENT_COMPRAS_KPI_PROCESO,
                fn () => ComprasKpisOperativosSupport::kpisProceso($params)
            ),
            self::INTENT_COMPRAS_KPI_PRODUCTIVIDAD => self::consultarComprasKpi(
                self::INTENT_COMPRAS_KPI_PRODUCTIVIDAD,
                fn () => ComprasKpisOperativosSupport::kpisProductividad($params)
            ),
            self::INTENT_OC_PENDIENTES_FIRMA => self::consultarComprasKpi(
                self::INTENT_OC_PENDIENTES_FIRMA,
                fn () => ComprasKpisOperativosSupport::ocPendientesFirma($params)
            ),
            self::INTENT_OC_VENCIDAS_SIN_RECEPCION => self::consultarComprasKpi(
                self::INTENT_OC_VENCIDAS_SIN_RECEPCION,
                fn () => ComprasKpisOperativosSupport::ocVencidasSinRecepcion($params)
            ),
            self::INTENT_LEAD_TIME_OC_RECEPCION => self::consultarComprasKpi(
                self::INTENT_LEAD_TIME_OC_RECEPCION,
                fn () => ComprasKpisOperativosSupport::leadTimeOcRecepcion($params)
            ),
            self::INTENT_TOP_PROVEEDORES_MONTO => self::consultarComprasKpi(
                self::INTENT_TOP_PROVEEDORES_MONTO,
                fn () => ComprasKpisOperativosSupport::topProveedoresMonto($params)
            ),
            self::INTENT_RQ_SIN_OC => self::consultarComprasKpi(
                self::INTENT_RQ_SIN_OC,
                fn () => ComprasKpisOperativosSupport::rqSinOc($params)
            ),
            default => self::fallo($intent, 'Intent no implementado.'),
        };
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function consultarArticuloSaldo(array $params): array
    {
        if (! can('listar-articulos', false) && ! can('editar-articulos', false)) {
            return self::fallo(self::INTENT_ARTICULO_SALDO, 'Sin permiso para consultar artículos.');
        }
        if (! ArticuloSaldosDepositoSupport::puedeConsultar()) {
            return self::fallo(self::INTENT_ARTICULO_SALDO, 'Sin permiso para consultar saldos de depósito.');
        }

        $sku = trim((string) ($params['sku'] ?? $params['codigo'] ?? $params['valor'] ?? ''));
        if ($sku === '') {
            return self::fallo(self::INTENT_ARTICULO_SALDO, 'Indique el SKU o nombre del artículo.');
        }

        $soloInsumo = ! empty($params['solo_insumo']);
        $encontrado = self::resolverArticuloPorTexto($sku, $soloInsumo);
        if (! ($encontrado['ok'] ?? false)) {
            return self::fallo(self::INTENT_ARTICULO_SALDO, $encontrado['error'] ?? 'Artículo no encontrado.');
        }
        /** @var Articulo $articulo */
        $articulo = $encontrado['articulo'];

        $empresaId = isset($params['empresa_id']) && (int) $params['empresa_id'] > 0
            ? (int) $params['empresa_id']
            : null;
        $saldos = ArticuloSaldosDepositoSupport::listadoPorArticulo((int) $articulo->id, $empresaId);
        $parrafos = [
            'Artículo '.$articulo->sku.' — '.($articulo->descripcion ?? ''),
        ];
        if ($soloInsumo) {
            $parrafos[] = 'Filtro: solo insumos gastronomía.';
        }
        $parrafos[] = 'Unidad: '.($saldos['articulo']['unidad_medida_abreviatura'] ?? $saldos['articulo']['unidad_medida'] ?? '—');
        $parrafos[] = 'Saldo total (depósitos con movimiento): '.$saldos['total_fmt'];
        foreach (array_slice($saldos['filas'], 0, 8) as $fila) {
            $parrafos[] = 'Depósito '.$fila['codigo'].' '.$fila['nombre']
                .(! empty($saldos['mostrar_empresa']) ? ' ('.$fila['empresa_nombre'].')' : '')
                .': '.$fila['saldo_fmt'];
        }
        if (count($saldos['filas']) > 8) {
            $parrafos[] = '… y '.(count($saldos['filas']) - 8).' depósito(s) más.';
        }

        $links = [];
        if (can('editar-articulos', false) || can('listar-articulos', false)) {
            $links[] = [
                'etiqueta' => 'Abrir artículo',
                'url' => route('editar_articulo', ['id' => $articulo->id]).'?origen=modal_consulta&vista=consulta',
            ];
        }

        return [
            'ok' => true,
            'intent' => self::INTENT_ARTICULO_SALDO,
            'score' => 0.92,
            'parrafos' => $parrafos,
            'links' => $links,
            'datos' => [
                'articulo_id' => (int) $articulo->id,
                'sku' => (string) $articulo->sku,
                'total_saldo' => $saldos['total'],
                'depositos' => count($saldos['filas']),
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function consultarArticuloKardex(array $params): array
    {
        if (! can('listar-articulos', false) && ! can('editar-articulos', false)) {
            return self::fallo(self::INTENT_ARTICULO_KARDEX, 'Sin permiso para consultar artículos.');
        }
        if (! MovimientosArticuloDepositoSupport::puedeConsultar()) {
            return self::fallo(self::INTENT_ARTICULO_KARDEX, 'Sin permiso para consultar el kardex / movimientos.');
        }

        $texto = trim((string) ($params['sku'] ?? $params['descripcion'] ?? $params['valor'] ?? ''));
        if ($texto === '') {
            return self::fallo(self::INTENT_ARTICULO_KARDEX, 'Indique SKU o nombre del artículo (ej.: muzarella).');
        }

        $soloInsumo = ! empty($params['solo_insumo']);
        $encontrado = self::resolverArticuloPorTexto($texto, $soloInsumo);
        if (! ($encontrado['ok'] ?? false)) {
            return self::fallo(self::INTENT_ARTICULO_KARDEX, $encontrado['error'] ?? 'Artículo no encontrado.');
        }
        /** @var Articulo $articulo */
        $articulo = $encontrado['articulo'];

        $empresaId = isset($params['empresa_id']) && (int) $params['empresa_id'] > 0
            ? (int) $params['empresa_id']
            : null;
        $depositoId = self::resolverDepositoIdDesdeParams($params);
        $fechaDesde = trim((string) ($params['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($params['fecha_hasta'] ?? ''));
        $max = self::topeLineas($params, 60);

        try {
            $contexto = RecuentoMovimientosArticuloSupport::validarContexto((int) $articulo->id, $depositoId, $empresaId);
        } catch (\Throwable $e) {
            return self::fallo(self::INTENT_ARTICULO_KARDEX, $e->getMessage());
        }

        $modoTodos = (bool) ($contexto['modo_todos_depositos'] ?? true);
        $q = RecuentoMovimientosArticuloSupport::query((int) $articulo->id, $depositoId, $empresaId);
        if ($fechaDesde !== '') {
            $q->whereDate('am.fecha', '>=', $fechaDesde);
        }
        if ($fechaHasta !== '') {
            $q->whereDate('am.fecha', '<=', $fechaHasta);
        }
        $totalPeriodo = (clone $q)->count();
        $rows = $q->limit($max)->get()
            ->map(fn ($row) => RecuentoMovimientosArticuloSupport::enriquecerFila($row, $modoTodos));

        $columnas = [
            ['key' => 'fecha', 'label' => 'Fecha'],
            ['key' => 'tipo', 'label' => 'Tipo'],
            ['key' => 'concepto', 'label' => 'Concepto / comprobante'],
            ['key' => 'entrada', 'label' => 'Entrada'],
            ['key' => 'salida', 'label' => 'Salida'],
        ];
        if ($modoTodos) {
            $columnas[] = ['key' => 'deposito', 'label' => 'Depósito'];
        }
        $columnas[] = ['key' => 'empresa', 'label' => 'Empresa'];

        $filas = [];
        foreach ($rows as $row) {
            $filas[] = [
                'fecha' => $row->fecha ? date('d/m/Y', strtotime((string) $row->fecha)) : '—',
                'tipo' => (string) ($row->tipo ?? '—'),
                'concepto' => (string) ($row->concepto_display ?? $row->concepto ?? ''),
                'entrada' => (string) ($row->entrada_fmt ?? ''),
                'salida' => (string) ($row->salida_fmt ?? ''),
                'deposito' => (string) ($row->deposito_etiqueta ?? $row->deposito_nombre ?? ''),
                'empresa' => (string) ($row->empresa_nombre ?? ''),
            ];
        }

        $parrafos = [
            'Kardex '.$articulo->sku.' — '.($articulo->descripcion ?? ''),
        ];
        if ($soloInsumo) {
            $parrafos[] = 'Filtro: solo insumos gastronomía.';
        }
        $parrafos[] = 'Depósito: '.($contexto['deposito']['nombre'] ?? 'Todos');
        if ($fechaDesde !== '' || $fechaHasta !== '') {
            $parrafos[] = 'Período: '
                .($fechaDesde !== '' ? date('d/m/Y', strtotime($fechaDesde)) : '…')
                .' → '
                .($fechaHasta !== '' ? date('d/m/Y', strtotime($fechaHasta)) : '…');
        }
        $parrafos[] = 'Saldo actual: '.($contexto['saldo_fmt'] ?? '—')
            .' '.($contexto['articulo']['unidad_medida_abreviatura'] ?? '');
        $parrafos[] = 'Movimientos en alcance: '.$totalPeriodo
            .(
                $totalPeriodo > $rows->count()
                    ? ' (mostrando '.$rows->count().'; use Excel para más).'
                    : '.'
            );
        if ($rows->isEmpty()) {
            $parrafos[] = 'Sin movimientos en el alcance consultado.';
        }

        $links = [[
            'etiqueta' => 'Abrir kardex completo',
            'url' => route('recuento_movimientos_articulo', array_filter([
                'articulo_id' => $articulo->id,
                'deposito_id' => $depositoId > 0 ? $depositoId : 'todos',
                'empresa_id' => $empresaId,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ])),
        ]];

        return [
            'ok' => true,
            'intent' => self::INTENT_ARTICULO_KARDEX,
            'score' => 0.9,
            'parrafos' => $parrafos,
            'links' => $links,
            'tabla' => [
                'columnas' => $modoTodos
                    ? $columnas
                    : array_values(array_filter($columnas, static fn ($c) => ($c['key'] ?? '') !== 'deposito')),
                'filas' => $filas,
            ],
            'datos' => [
                'articulo_id' => (int) $articulo->id,
                'sku' => (string) $articulo->sku,
                'saldo' => $contexto['saldo'] ?? null,
                'movimientos' => $rows->count(),
                'movimientos_periodo' => $totalPeriodo,
                'deposito_id' => $depositoId,
                'fecha_desde' => $fechaDesde !== '' ? $fechaDesde : null,
                'fecha_hasta' => $fechaHasta !== '' ? $fechaHasta : null,
            ],
        ];
    }

    /**
     * Resuelve artículo por SKU exacto, descripción o parecido (typos: muzarella/mozarella/mozzarella).
     * Con $soloInsumo filtra uso «INSUMO GASTRONOMIA».
     *
     * @return array{ok: bool, articulo?: Articulo, error?: string}
     */
    private static function resolverArticuloPorTexto(string $texto, bool $soloInsumo = false): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return ['ok' => false, 'error' => 'Indique SKU o nombre del artículo.'];
        }

        $repo = app(ArticuloRepositoryInterface::class);
        $porSku = $repo->findPorSku($texto);
        if ($porSku instanceof Articulo) {
            if ($soloInsumo && ! ArticuloUsoInsumoSupport::esUsoInsumo((int) ($porSku->usoarticulo_id ?? 0))) {
                return [
                    'ok' => false,
                    'error' => 'El SKU «'.$texto.'» no es un insumo gastronomía. Quitá «insumo» o usá otro código.',
                ];
            }

            return ['ok' => true, 'articulo' => $porSku];
        }

        $variantes = self::variantesBusquedaArticulo($texto);
        $q = Articulo::query()->select('id', 'sku', 'descripcion', 'unidadmedida_id', 'usoarticulo_id');
        if ($soloInsumo) {
            $insumoId = ArticuloUsoInsumoSupport::idUsoInsumo();
            if ($insumoId === null || $insumoId <= 0) {
                return ['ok' => false, 'error' => 'No está configurado el uso de artículo «INSUMO GASTRONOMIA».'];
            }
            $q->where('usoarticulo_id', $insumoId);
        }

        $q->where(function ($w) use ($variantes, $texto) {
            foreach ($variantes as $v) {
                $w->orWhere('sku', 'like', '%'.$v.'%')
                    ->orWhere('descripcion', 'like', '%'.$v.'%');
            }
            CoincidenciaFlexibleTexto::aplicar(
                $w,
                'descripcion',
                $texto,
                true,
                CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
            );
        });

        $hits = $q->orderBy('sku')->limit(20)->get();
        if ($hits->isEmpty()) {
            $hits = self::buscarArticulosPorSimilitud($texto, $soloInsumo, 12);
        } else {
            // Reordenar por parecido tipográfico (muzarella ≈ mozzarella ≈ mozarella)
            $hits = self::ordenarPorSimilitud($hits, $texto);
        }

        if ($hits->isEmpty()) {
            $ambito = $soloInsumo ? 'insumo gastronomía' : 'artículo';

            return [
                'ok' => false,
                'error' => 'No se encontró '.$ambito.' parecido a «'.$texto.'». Probá SKU o más letras.',
            ];
        }

        $mejor = $hits->first();
        $scoreMejor = self::scoreSimilitudArticulo($texto, (string) $mejor->descripcion, (string) $mejor->sku);
        if ($hits->count() === 1 || $scoreMejor <= 2) {
            return ['ok' => true, 'articulo' => $mejor];
        }

        $segundo = $hits->get(1);
        $scoreSegundo = $segundo
            ? self::scoreSimilitudArticulo($texto, (string) $segundo->descripcion, (string) $segundo->sku)
            : 9999;
        // Ganador claro (tipografía + preferir nombre principal: «MOZZARELLA INSUMO» vs «PIZZETA…»)
        if ($scoreMejor <= 40 && $scoreSegundo >= $scoreMejor + 15) {
            return ['ok' => true, 'articulo' => $mejor];
        }

        $candidatos = $hits->take(5)->map(fn ($a) => $a->sku.' — '.$a->descripcion)->all();

        return [
            'ok' => false,
            'error' => 'Hay varios '.($soloInsumo ? 'insumos' : 'artículos').' parecidos. Aclare con SKU: '
                .implode('; ', $candidatos),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Articulo>
     */
    private static function buscarArticulosPorSimilitud(string $texto, bool $soloInsumo, int $limite)
    {
        $q = Articulo::query()->select('id', 'sku', 'descripcion', 'unidadmedida_id', 'usoarticulo_id');
        if ($soloInsumo) {
            $insumoId = ArticuloUsoInsumoSupport::idUsoInsumo();
            if ($insumoId === null || $insumoId <= 0) {
                return collect();
            }
            $q->where('usoarticulo_id', $insumoId);
        }

        // Acotar candidatos: fragmento normalizado (muzarella → muza / rela)
        $norm = self::normalizarTextoArticulo($texto);
        if (mb_strlen($norm) >= 4) {
            $pref = mb_substr($norm, 0, 4);
            $suf = mb_substr($norm, -4);
            $q->where(function ($w) use ($pref, $suf) {
                $w->whereRaw('LOWER(descripcion) LIKE ?', ['%'.$pref.'%'])
                    ->orWhereRaw('LOWER(descripcion) LIKE ?', ['%'.$suf.'%'])
                    ->orWhereRaw('LOWER(sku) LIKE ?', ['%'.$pref.'%']);
            });
        }

        $pool = $q->limit($soloInsumo ? 400 : 200)->get();

        return self::ordenarPorSimilitud($pool, $texto)->take($limite)->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Articulo>  $coleccion
     * @return \Illuminate\Support\Collection<int, Articulo>
     */
    private static function ordenarPorSimilitud($coleccion, string $texto)
    {
        return $coleccion->sortBy(function ($a) use ($texto) {
            return self::scoreSimilitudArticulo($texto, (string) $a->descripcion, (string) $a->sku);
        })->values();
    }

    /**
     * Menor = mejor. Distancia tipográfica + castigo si el término no es la primera palabra.
     * Así «muzarella» prioriza MOZZARELLA INSUMO frente a PIZZETA MUZZARELLA INSUMO.
     */
    private static function scoreSimilitudArticulo(string $busqueda, string $descripcion, string $sku): int
    {
        $dist = self::distanciaSimilitudArticulo($busqueda, $descripcion, $sku);
        $b = self::normalizarTextoArticulo($busqueda);
        if ($b === '') {
            return 9999;
        }

        $palabras = preg_split('/\s+/u', trim($descripcion)) ?: [];
        $primera = self::normalizarTextoArticulo((string) ($palabras[0] ?? ''));
        $esPrincipal = $primera !== '' && levenshtein($b, $primera) <= 2;
        $castigo = $esPrincipal ? 0 : 25;

        // «MOZZARELLA INSUMO» (2 tokens) gana a frases largas con el mismo queso
        if ($esPrincipal && count($palabras) <= 2) {
            $castigo -= 10;
        }

        return ($dist * 10) + $castigo;
    }

    private static function distanciaSimilitudArticulo(string $busqueda, string $descripcion, string $sku): int
    {
        $b = self::normalizarTextoArticulo($busqueda);
        if ($b === '') {
            return 999;
        }
        $dist = levenshtein($b, self::normalizarTextoArticulo($descripcion));
        // Palabras crudas (antes de colapsar espacios) — muzarella ≈ mozzarella / mozarella
        foreach (preg_split('/\s+/u', mb_strtolower(trim($descripcion), 'UTF-8')) ?: [] as $palabra) {
            $p = self::normalizarTextoArticulo($palabra);
            if ($p === '') {
                continue;
            }
            $dist = min($dist, levenshtein($b, $p));
            if (str_contains($p, $b) || str_contains($b, $p)) {
                $dist = min($dist, (int) abs(mb_strlen($p) - mb_strlen($b)));
            }
        }
        $dist = min($dist, levenshtein($b, self::normalizarTextoArticulo($sku)));

        return $dist;
    }

    private static function normalizarTextoArticulo(string $texto): string
    {
        $s = mb_strtolower(trim($texto), 'UTF-8');
        $s = strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
        $s = preg_replace('/[^a-z0-9]+/u', '', $s) ?? '';
        // mozzarella / mozarella / muzarella → forma colapsada comparable
        $s = preg_replace('/(.)\1+/u', '$1', $s) ?? $s;

        return $s;
    }

    /** @return list<string> */
    private static function variantesBusquedaArticulo(string $texto): array
    {
        $t = trim($texto);
        $out = [$t];
        $lower = mb_strtolower($t, 'UTF-8');
        $out[] = $lower;
        // Tipografías habituales del queso
        $formas = ['muzarella', 'muzarela', 'muzzarella', 'mozarella', 'mozarela', 'mozzarella', 'mozzarela'];
        foreach ($formas as $forma) {
            if (str_contains($lower, $forma) || self::normalizarTextoArticulo($lower) === self::normalizarTextoArticulo($forma)) {
                foreach ($formas as $alt) {
                    $out[] = str_replace($forma, $alt, $lower);
                    $out[] = $alt;
                }
            }
        }
        // Si escribió algo "parecido" a mozzarella por normalización, sumar todas las formas
        $norm = self::normalizarTextoArticulo($lower);
        if ($norm !== '' && levenshtein($norm, 'mozarela') <= 2) {
            array_push($out, ...$formas);
        }
        $colapsado = preg_replace('/(.)\1+/u', '$1', $lower);
        if (is_string($colapsado) && $colapsado !== '' && $colapsado !== $lower) {
            $out[] = $colapsado;
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function consultarCliente(array $params): array
    {
        if (! can('listar-clientes', false) && ! can('editar-cliente', false)) {
            return self::fallo(self::INTENT_CLIENTE, 'Sin permiso para consultar clientes.');
        }

        $valor = trim((string) ($params['codigo'] ?? $params['documento'] ?? $params['valor'] ?? ''));
        if ($valor === '') {
            return self::fallo(self::INTENT_CLIENTE, 'Indique código o documento del cliente.');
        }

        /** @var ClienteRepositoryInterface $repo */
        $repo = app(ClienteRepositoryInterface::class);
        try {
            if (preg_match('/^\d+$/', $valor) && strlen($valor) >= 7) {
                $cliente = $repo->findPorNumeroDocumento($valor)
                    ?? $repo->findPorCodigo($valor);
            } else {
                $cliente = $repo->findPorCodigo($valor);
            }
        } catch (\Throwable) {
            $cliente = null;
        }
        if (! $cliente instanceof Cliente) {
            return self::fallo(self::INTENT_CLIENTE, 'No se encontró cliente «'.$valor.'».');
        }

        $parrafos = [
            'Cliente '.($cliente->codigo ?? '').' — '.($cliente->nombre ?? ''),
            'Documento: '.($cliente->numerodocumento ?? '—'),
            'Estado: '.($cliente->estado ?? '—'),
        ];
        if (! empty($cliente->email)) {
            $parrafos[] = 'Email: '.$cliente->email;
        }

        $links = [];
        if (can('editar-cliente', false) || can('listar-clientes', false)) {
            $links[] = [
                'etiqueta' => 'Abrir cliente',
                'url' => route('editar_cliente', ['id' => $cliente->id]).'?origen=modal_consulta&vista=consulta',
            ];
        }

        return [
            'ok' => true,
            'intent' => self::INTENT_CLIENTE,
            'score' => 0.9,
            'parrafos' => $parrafos,
            'links' => $links,
            'datos' => [
                'cliente_id' => (int) $cliente->id,
                'codigo' => (string) ($cliente->codigo ?? ''),
                'numerodocumento' => (string) ($cliente->numerodocumento ?? ''),
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function consultarProveedor(array $params): array
    {
        if (! can('listar-proveedor', false) && ! can('editar-proveedor', false)) {
            return self::fallo(self::INTENT_PROVEEDOR, 'Sin permiso para consultar proveedores.');
        }

        $codigo = trim((string) ($params['codigo'] ?? $params['valor'] ?? ''));
        if ($codigo === '') {
            return self::fallo(self::INTENT_PROVEEDOR, 'Indique el código del proveedor.');
        }

        $proveedor = app(ProveedorRepositoryInterface::class)->findPorCodigo($codigo);
        if (! $proveedor instanceof Proveedor) {
            return self::fallo(self::INTENT_PROVEEDOR, 'No se encontró proveedor «'.$codigo.'».');
        }

        $parrafos = [
            'Proveedor '.($proveedor->codigo ?? '').' — '.($proveedor->nombre ?? ''),
            'Inscripción/CUIT: '.($proveedor->nroinscripcion ?? '—'),
            'Estado: '.($proveedor->estado ?? '—'),
        ];
        if (! empty($proveedor->email)) {
            $parrafos[] = 'Email: '.$proveedor->email;
        }

        $links = [];
        if (can('editar-proveedor', false) || can('listar-proveedor', false)) {
            $links[] = [
                'etiqueta' => 'Abrir proveedor',
                'url' => route('editar_proveedor', ['id' => $proveedor->id]).'?origen=modal_consulta&vista=consulta',
            ];
        }

        return [
            'ok' => true,
            'intent' => self::INTENT_PROVEEDOR,
            'score' => 0.9,
            'parrafos' => $parrafos,
            'links' => $links,
            'datos' => [
                'proveedor_id' => (int) $proveedor->id,
                'codigo' => (string) ($proveedor->codigo ?? ''),
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function consultarOrdencompra(array $params): array
    {
        if (! can('listar-ordencompra', false) && ! can('editar-ordencompra', false)) {
            return self::fallo(self::INTENT_ORDENCOMPRA, 'Sin permiso para consultar órdenes de compra.');
        }

        $numero = trim((string) ($params['numero'] ?? $params['codigo'] ?? $params['valor'] ?? ''));
        if ($numero === '') {
            return self::fallo(self::INTENT_ORDENCOMPRA, 'Indique el número de OC.');
        }

        $oc = Ordencompra::query()
            ->with(['empresas:id,nombre', 'proveedores:id,codigo,nombre', 'centrocostos:id,codigo,nombre'])
            ->where('numeroordencompra', $numero)
            ->orderByDesc('id')
            ->first();
        if (! $oc) {
            return self::fallo(self::INTENT_ORDENCOMPRA, 'No se encontró OC «'.$numero.'».');
        }

        $parrafos = [
            'OC '.$oc->numeroordencompra.' — estado: '.($oc->estadoordencompra ?? '—'),
            'Empresa: '.($oc->empresas->nombre ?? '—'),
            'Proveedor: '.trim(($oc->proveedores->codigo ?? '').' '.($oc->proveedores->nombre ?? '')) ?: '—',
            'Centro de costo: '.trim(($oc->centrocostos->codigo ?? '').' '.($oc->centrocostos->nombre ?? '')) ?: '—',
            'Fecha: '.($oc->fecha ? date('d/m/Y', strtotime((string) $oc->fecha)) : '—'),
        ];

        $links = [];
        $links[] = [
            'etiqueta' => 'Abrir OC',
            'url' => route('editar_ordencompra', ['id' => $oc->id]).'?origen=modal_consulta&vista=consulta',
        ];

        return [
            'ok' => true,
            'intent' => self::INTENT_ORDENCOMPRA,
            'score' => 0.91,
            'parrafos' => $parrafos,
            'links' => $links,
            'datos' => [
                'ordencompra_id' => (int) $oc->id,
                'numero' => (string) $oc->numeroordencompra,
                'estado' => (string) ($oc->estadoordencompra ?? ''),
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function consultarArbolOc(array $params): array
    {
        if (! can('listar-ordencompra', false) && ! can('editar-ordencompra', false)) {
            return self::fallo(self::INTENT_ARBOL_OC, 'Sin permiso para consultar OC / árbol.');
        }

        $numero = trim((string) ($params['numero'] ?? $params['codigo'] ?? $params['valor'] ?? ''));
        if ($numero === '') {
            return self::fallo(self::INTENT_ARBOL_OC, 'Indique el número de OC.');
        }

        $oc = Ordencompra::query()->where('numeroordencompra', $numero)->orderByDesc('id')->first();
        if (! $oc) {
            return self::fallo(self::INTENT_ARBOL_OC, 'No se encontró OC «'.$numero.'».');
        }

        /** @var ArbolaprobacionService $arbol */
        $arbol = app(ArbolaprobacionService::class);
        $pack = $arbol->movimientosOrdencompraConAvisoGrabacion((int) $oc->id);
        $panel = $pack['ai_contexto_arbol'] ?? null;
        $parrafos = [];
        $parrafos[] = 'OC '.$oc->numeroordencompra.' — estado: '.($oc->estadoordencompra ?? '—');
        $parrafos[] = 'Movimientos de árbol: '.count($pack['movimientos'] ?? []);
        if (! empty($pack['aviso_grabacion_pendiente'])) {
            $parrafos[] = 'Aviso: '.$pack['aviso_grabacion_pendiente'];
        }
        if (is_array($panel)) {
            foreach ($panel['ai_parrafos'] ?? [] as $p) {
                $parrafos[] = (string) $p;
            }
            if (empty($panel['ai_parrafos']) && ! empty($panel['ai_advertencias'])) {
                foreach ($panel['ai_advertencias'] as $a) {
                    $parrafos[] = (string) $a;
                }
            }
        } else {
            $parrafos[] = 'No hay contexto IA de árbol disponible (skill deshabilitada o sin movimiento pendiente).';
        }

        $links = [];
        $links[] = [
            'etiqueta' => 'Abrir OC (solapa árbol)',
            'url' => route('editar_ordencompra', ['id' => $oc->id]).'?origen=modal_consulta&vista=consulta',
        ];

        return [
            'ok' => true,
            'intent' => self::INTENT_ARBOL_OC,
            'score' => is_array($panel) && ($panel['ai_score'] ?? null) !== null
                ? (float) $panel['ai_score']
                : 0.8,
            'parrafos' => array_slice($parrafos, 0, 16),
            'links' => $links,
            'datos' => [
                'ordencompra_id' => (int) $oc->id,
                'numero' => (string) $oc->numeroordencompra,
                'ai_decision_id' => is_array($panel) ? ($panel['ai_decision_id'] ?? null) : null,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function consultarProveedorCtacte(array $params): array
    {
        if (! can('listar-cuentacorriente-proveedor', false)
            && ! can('listar-proveedor', false)
            && ! can('editar-proveedor', false)) {
            return self::fallo(self::INTENT_PROVEEDOR_CTACTE, 'Sin permiso para consultar cuenta corriente de proveedor.');
        }

        $codigo = trim((string) ($params['codigo'] ?? $params['valor'] ?? ''));
        if ($codigo === '') {
            return self::fallo(self::INTENT_PROVEEDOR_CTACTE, 'Indique el código del proveedor.');
        }

        $proveedor = app(ProveedorRepositoryInterface::class)->findPorCodigo($codigo);
        if (! $proveedor instanceof Proveedor) {
            return self::fallo(self::INTENT_PROVEEDOR_CTACTE, 'No se encontró proveedor «'.$codigo.'».');
        }

        /** @var Proveedor_CuentacorrienteRepositoryInterface $repoCt */
        $repoCt = app(Proveedor_CuentacorrienteRepositoryInterface::class);
        $saldo = $repoCt->calcularSaldoCuentaCorriente((int) $proveedor->id);
        $deuda = $repoCt->calcularTotalDeudaProveedor((int) $proveedor->id);
        $max = self::topeLineas($params, 60);
        $fechaDesde = trim((string) ($params['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($params['fecha_hasta'] ?? ''));
        $soloDeuda = ! empty($params['solo_deuda']);

        $q = Proveedor_Cuentacorriente::query()
            ->with([
                'comprobante_proveedores.tipotransaccion_compras:id,nombre,abreviatura',
                'comprobante_proveedores.ordencompras:id,numeroordencompra,estado',
                'monedas:id,abreviatura',
                'empresas:id,nombre',
            ])
            ->where('proveedor_id', (int) $proveedor->id);
        if ($fechaDesde !== '') {
            $q->whereDate('fecha', '>=', $fechaDesde);
        }
        if ($fechaHasta !== '') {
            $q->whereDate('fecha', '<=', $fechaHasta);
        }
        if ($soloDeuda) {
            $q->whereNotNull('comprobante_proveedor_id');
        }

        $totalPeriodo = (clone $q)->count();
        $importePeriodo = (float) (clone $q)->sum('total');
        $ultimas = $q->orderByDesc('fecha')->orderByDesc('id')->limit($max)->get();

        $columnas = [
            ['key' => 'fecha', 'label' => 'Fecha'],
            ['key' => 'vto', 'label' => 'Vto'],
            ['key' => 'tipo', 'label' => 'Tipo'],
            ['key' => 'comprobante', 'label' => 'Comprobante'],
            ['key' => 'oc', 'label' => 'OC'],
            ['key' => 'moneda', 'label' => 'Mon'],
            ['key' => 'total', 'label' => 'Total'],
            ['key' => 'empresa', 'label' => 'Empresa'],
        ];
        $filasTabla = [];
        foreach ($ultimas as $fila) {
            $comp = $fila->comprobante_proveedores;
            $oc = $comp?->ordencompras;
            $filasTabla[] = [
                'fecha' => $fila->fecha ? date('d/m/Y', strtotime((string) $fila->fecha)) : '—',
                'vto' => $fila->fechavencimiento
                    ? date('d/m/Y', strtotime((string) $fila->fechavencimiento))
                    : '',
                'tipo' => (string) ($comp?->tipotransaccion_compras?->abreviatura
                    ?? $comp?->tipotransaccion_compras?->nombre
                    ?? ''),
                'comprobante' => $comp
                    ? trim(($comp->letra ?? '').'-'.($comp->sucursal ?? '').'-'.($comp->numerocomprobante ?? ''))
                    : '',
                'oc' => $oc ? (string) ($oc->numeroordencompra ?? '') : '',
                'moneda' => (string) ($fila->monedas?->abreviatura ?? ''),
                'total' => number_format((float) $fila->total, 2, ',', '.'),
                'empresa' => (string) ($fila->empresas?->nombre ?? ''),
            ];
        }

        $parrafos = [
            'Proveedor '.$proveedor->codigo.' — '.($proveedor->nombre ?? ''),
            'Saldo cuenta corriente: '.number_format($saldo, 2, ',', '.'),
            'Deuda pendiente (aprox.): '.number_format($deuda, 2, ',', '.'),
        ];
        if ($fechaDesde !== '' || $fechaHasta !== '') {
            $parrafos[] = 'Período: '
                .($fechaDesde !== '' ? date('d/m/Y', strtotime($fechaDesde)) : '…')
                .' → '
                .($fechaHasta !== '' ? date('d/m/Y', strtotime($fechaHasta)) : '…')
                .' — '.$totalPeriodo.' mov. / suma '.number_format($importePeriodo, 2, ',', '.');
        } else {
            $parrafos[] = 'Últimos movimientos: '.$ultimas->count()
                .' de '.$totalPeriodo.' (join comprobante + OC + moneda + empresa).';
        }

        $links = [];
        if (can('listar-cuentacorriente-proveedor', false) || can('listar-proveedor', false)) {
            $links[] = [
                'etiqueta' => 'Abrir cuenta corriente',
                'url' => route('listar_cuentacorriente_proveedor', ['id' => $proveedor->id]),
            ];
        }

        return [
            'ok' => true,
            'intent' => self::INTENT_PROVEEDOR_CTACTE,
            'score' => 0.93,
            'parrafos' => $parrafos,
            'links' => $links,
            'tabla' => [
                'columnas' => $columnas,
                'filas' => $filasTabla,
            ],
            'datos' => [
                'proveedor_id' => (int) $proveedor->id,
                'codigo' => (string) ($proveedor->codigo ?? ''),
                'saldo' => $saldo,
                'deuda' => $deuda,
                'lineas_periodo' => $totalPeriodo,
                'importe_periodo' => $importePeriodo,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function consultarSaldoCuenta(array $params): array
    {
        if (! can('listar-mayor-plano-cuenta', false)
            && ! can('listar-cuentas-contables', false)
            && ! can('listar-sumas-saldos', false)) {
            return self::fallo(self::INTENT_SALDO_CUENTA, 'Sin permiso para consultar saldos contables.');
        }

        $empresaRes = AiResolucionMaestrosSupport::resolverEmpresa($params);
        if (! ($empresaRes['ok'] ?? false)) {
            return self::fallo(self::INTENT_SALDO_CUENTA, (string) ($empresaRes['error'] ?? 'Empresa no resuelta.'));
        }
        $empresaId = $empresaRes['empresa_id'] ?? null;

        $cuentaRes = AiResolucionMaestrosSupport::resolverCuenta($params, true, $empresaId);
        if (! ($cuentaRes['ok'] ?? false) || ! ($cuentaRes['cuenta'] ?? null)) {
            return self::fallo(self::INTENT_SALDO_CUENTA, (string) ($cuentaRes['error'] ?? 'Indique código o nombre de cuenta contable.'));
        }
        /** @var Cuentacontable $cuenta */
        $cuenta = $cuentaRes['cuenta'];
        $cuentasConsulta = AiResolucionMaestrosSupport::cuentasParaConsulta($cuenta);
        $cuentaIds = AiResolucionMaestrosSupport::idsCuentas($cuentasConsulta);
        [$desdeMes, $hastaMes] = self::resolverRangoAnioMes($params);

        $q = DB::table('cuentacontable_saldo_mes')
            ->whereIn('cuentacontable_id', $cuentaIds !== [] ? $cuentaIds : [(int) $cuenta->id])
            ->where('moneda_id', CuentacontableSaldoMesSupport::monedaLocalId());
        if ($empresaId) {
            $q->where('empresa_id', $empresaId);
        }
        if ($desdeMes !== null) {
            $q->where('anio_mes', '>=', $desdeMes);
        }
        if ($hastaMes !== null) {
            $q->where('anio_mes', '<=', $hastaMes);
        }

        $agg = $q->selectRaw('COALESCE(SUM(debe_local),0) as debe, COALESCE(SUM(haber_local),0) as haber, COALESCE(SUM(monto_local),0) as neto')->first();
        $debe = (float) ($agg->debe ?? 0);
        $haber = (float) ($agg->haber ?? 0);
        $neto = (float) ($agg->neto ?? 0);

        $periodoTxt = ($params['fecha_desde'] ?? null) || ($params['fecha_hasta'] ?? null)
            ? trim(($params['fecha_desde'] ?? '…').' → '.($params['fecha_hasta'] ?? '…'))
            : 'histórico (meses con saldo)';

        $parrafos = [
            'Cuenta '.$cuenta->codigo.' — '.($cuenta->nombre ?? ''),
        ];
        if (count($cuentasConsulta) > 1) {
            $parrafos[] = 'Cuenta de título/total: saldo consolidado de '.count($cuentasConsulta).' cuentas imputables.';
        }
        $parrafos[] = 'Período: '.$periodoTxt;
        $parrafos[] = 'Debe: '.number_format($debe, 2, ',', '.');
        $parrafos[] = 'Haber: '.number_format($haber, 2, ',', '.');
        $parrafos[] = 'Saldo neto (debe − haber): '.number_format($neto, 2, ',', '.');

        return [
            'ok' => true,
            'intent' => self::INTENT_SALDO_CUENTA,
            'score' => 0.9,
            'parrafos' => $parrafos,
            'links' => [],
            'datos' => [
                'cuentacontable_id' => (int) $cuenta->id,
                'cuenta_codigo' => (string) $cuenta->codigo,
                'cuentas_incluidas' => count($cuentasConsulta),
                'debe' => $debe,
                'haber' => $haber,
                'neto' => $neto,
            ],
        ];
    }

    /**
     * Mayor ERP con filtros combinables: cuenta, centro de costo, empresa, fechas y/o OC.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function consultarMayorCuenta(array $params): array
    {
        if (! can('listar-mayor-plano-cuenta', false) && ! can('listar-asiento', false)) {
            return self::fallo(self::INTENT_MAYOR_CUENTA, 'Sin permiso para consultar el mayor.');
        }

        $pidioEmpresa = ($params['empresa_id'] ?? null)
            || trim((string) ($params['empresa_codigo'] ?? '')) !== ''
            || trim((string) ($params['empresa_nombre'] ?? '')) !== '';
        $empresaRes = AiResolucionMaestrosSupport::resolverEmpresa($params);
        if ($pidioEmpresa && ! ($empresaRes['ok'] ?? false)) {
            return self::fallo(self::INTENT_MAYOR_CUENTA, (string) ($empresaRes['error'] ?? 'Empresa no resuelta.'));
        }
        $empresaId = ($empresaRes['ok'] ?? false) ? ($empresaRes['empresa_id'] ?? null) : null;

        $codigoCuentaRaw = trim((string) ($params['cuenta_codigo'] ?? $params['cuenta_nombre'] ?? ''));
        if ($codigoCuentaRaw === '' && empty($params['numero_oc']) && empty($params['ordencompra_id'])
            && empty($params['centrocosto_id']) && empty($params['centrocosto_codigo'])) {
            // Compat: "valor"/"codigo" solo si no hay filtros OC/CC (evita tomar nro OC como cuenta)
            $codigoCuentaRaw = trim((string) ($params['codigo'] ?? $params['valor'] ?? ''));
            if ($codigoCuentaRaw !== '') {
                $params['cuenta_codigo'] = $codigoCuentaRaw;
            }
        }

        $cuenta = null;
        $cuentasConsulta = [];
        $pidioCuenta = trim((string) ($params['cuenta_codigo'] ?? '')) !== ''
            || trim((string) ($params['cuenta_nombre'] ?? '')) !== ''
            || $codigoCuentaRaw !== '';
        if ($pidioCuenta) {
            $cuentaRes = AiResolucionMaestrosSupport::resolverCuenta($params, true, $empresaId);
            if (! ($cuentaRes['ok'] ?? false) || ! ($cuentaRes['cuenta'] ?? null)) {
                return self::fallo(self::INTENT_MAYOR_CUENTA, (string) ($cuentaRes['error'] ?? 'Cuenta no resuelta.'));
            }
            /** @var Cuentacontable $cuenta */
            $cuenta = $cuentaRes['cuenta'];
            $cuentasConsulta = AiResolucionMaestrosSupport::cuentasParaConsulta($cuenta);
        }

        $cc = self::resolverCentrocostoMayor($params);
        if (($params['centrocosto_id'] ?? null) || ($params['centrocosto_codigo'] ?? null)) {
            if (! $cc) {
                $ref = (string) ($params['centrocosto_codigo'] ?? $params['centrocosto_id'] ?? '');

                return self::fallo(self::INTENT_MAYOR_CUENTA, 'No se encontró el centro de costo «'.$ref.'».');
            }
        }

        $pidioOc = trim((string) ($params['numero_oc'] ?? '')) !== ''
            || (int) ($params['ordencompra_id'] ?? 0) > 0;
        $oc = self::resolverOrdencompraMayor($params);
        if ($pidioOc && ! $oc) {
            $ref = (string) ($params['numero_oc'] ?? $params['ordencompra_id'] ?? '');

            return self::fallo(self::INTENT_MAYOR_CUENTA, 'No se encontró la OC «'.$ref.'».');
        }

        if (! $cuenta && ! $oc && ! $cc) {
            return self::fallo(
                self::INTENT_MAYOR_CUENTA,
                'Indique al menos cuenta contable, centro de costo u orden de compra.'
            );
        }

        $fechaDesde = (string) ($params['fecha_desde'] ?? date('Y-m-01'));
        $fechaHasta = (string) ($params['fecha_hasta'] ?? date('Y-m-d'));
        $modoExport = ! empty($params['modo_export']);
        $tope = $modoExport
            ? AiConsultaOperativaSchemaSupport::MAX_LINEAS_EXPORT
            : AiConsultaOperativaSchemaSupport::MAX_LINEAS;
        $defaultMax = $modoExport ? min(200, $tope) : min(60, $tope);
        $max = (int) ($params['max_lineas'] ?? $defaultMax);
        $max = max(1, min($tope, $max > 0 ? $max : $defaultMax));
        $excluir = is_array($params['campos_excluir'] ?? null) ? $params['campos_excluir'] : [];
        $cruzar = (string) ($params['cruzar_con'] ?? '');
        $ordencompraId = $oc ? (int) $oc->id : 0;
        $cuentaIds = $cuentasConsulta !== []
            ? AiResolucionMaestrosSupport::idsCuentas($cuentasConsulta)
            : [];
        $multiCuenta = count($cuentaIds) > 1;

        $base = Asiento_Movimiento::query()
            ->whereHas('asientos', function ($aq) use ($fechaDesde, $fechaHasta, $empresaId) {
                $aq->whereBetween('fecha', [$fechaDesde, $fechaHasta]);
                if ($empresaId) {
                    $aq->where('empresa_id', $empresaId);
                }
            });
        if ($cuentaIds !== []) {
            $base->whereIn('cuentacontable_id', $cuentaIds);
        }
        if ($cc) {
            $base->where('centrocosto_id', (int) $cc->id);
        }
        if ($ordencompraId > 0) {
            $base->where(function ($q) use ($ordencompraId) {
                $q->whereHas('asientos', fn ($aq) => $aq->where('ordencompra_id', $ordencompraId))
                    ->orWhereHas(
                        'asientos.comprobante_proveedores',
                        fn ($cp) => $cp->where('ordencompra_id', $ordencompraId)
                    )
                    ->orWhereHas(
                        'comprobante_proveedores',
                        fn ($cp) => $cp->where('ordencompra_id', $ordencompraId)
                    );
            });
        }
        if ($cruzar === 'proveedor') {
            $base->whereNotNull('comprobante_proveedor_id');
        }

        // Totales del período completo (no solo la página visible)
        $totalesPeriodo = (clone $base)
            ->selectRaw(
                'COUNT(*) as lineas,'
                .' COALESCE(SUM(CASE WHEN monto > 0 THEN monto ELSE 0 END), 0) as debe,'
                .' COALESCE(SUM(CASE WHEN monto < 0 THEN ABS(monto) ELSE 0 END), 0) as haber'
            )
            ->first();
        $lineasPeriodo = (int) ($totalesPeriodo->lineas ?? 0);
        $debePeriodo = (float) ($totalesPeriodo->debe ?? 0);
        $haberPeriodo = (float) ($totalesPeriodo->haber ?? 0);

        $with = [
            'asientos:id,fecha,empresa_id,numeroasiento,ordencompra_id',
            'centrocostos:id,codigo,nombre',
            'comprobante_proveedores:id,proveedor_id,letra,sucursal,numerocomprobante,ordencompra_id',
            'comprobante_proveedores.proveedores:id,codigo,nombre',
        ];
        if (! $cuenta || $multiCuenta) {
            $with[] = 'cuentacontables:id,codigo,nombre';
        }

        $movs = (clone $base)
            ->with($with)
            ->orderBy(
                DB::raw('(select fecha from asiento where asiento.id = asiento_movimiento.asiento_id)')
            )
            ->orderBy('asiento_movimiento.id')
            ->limit($max)
            ->get();

        $mostrarCuenta = (! $cuenta || $multiCuenta) && ! in_array('cuenta', $excluir, true);
        $mostrarCc = $cc !== null || ! in_array('centrocosto', $excluir, true);
        $columnas = [];
        if (! in_array('fecha', $excluir, true)) {
            $columnas[] = ['key' => 'fecha', 'label' => 'Fecha'];
        }
        if (! in_array('asiento', $excluir, true)) {
            $columnas[] = ['key' => 'asiento', 'label' => 'Asiento'];
        }
        if ($mostrarCuenta) {
            $columnas[] = ['key' => 'cuenta', 'label' => 'Cuenta'];
        }
        if ($mostrarCc && ($cc || $movs->contains(fn ($m) => (int) ($m->centrocosto_id ?? 0) > 0))) {
            $columnas[] = ['key' => 'centrocosto', 'label' => 'CC'];
        }
        if (! in_array('debe', $excluir, true)) {
            $columnas[] = ['key' => 'debe', 'label' => 'Debe'];
        }
        if (! in_array('haber', $excluir, true)) {
            $columnas[] = ['key' => 'haber', 'label' => 'Haber'];
        }
        if (! in_array('detalle', $excluir, true)) {
            $columnas[] = ['key' => 'detalle', 'label' => 'Detalle'];
        }
        if (! in_array('proveedor', $excluir, true)) {
            $columnas[] = ['key' => 'proveedor', 'label' => 'Proveedor'];
        }

        $filas = [];
        $debePagina = 0.0;
        $haberPagina = 0.0;
        foreach ($movs as $mov) {
            $monto = (float) ($mov->monto ?? 0);
            $debe = $monto > 0 ? $monto : 0.0;
            $haber = $monto < 0 ? abs($monto) : 0.0;
            $debePagina += $debe;
            $haberPagina += $haber;
            $fila = [];
            foreach ($columnas as $col) {
                $fila[$col['key']] = match ($col['key']) {
                    'fecha' => $mov->asientos?->fecha
                        ? date('d/m/Y', strtotime((string) $mov->asientos->fecha))
                        : '—',
                    'asiento' => (string) ($mov->asientos->numeroasiento ?? $mov->asiento_id ?? '—'),
                    'cuenta' => trim(($mov->cuentacontables->codigo ?? '').' '.($mov->cuentacontables->nombre ?? '')) ?: '—',
                    'centrocosto' => trim(($mov->centrocostos->codigo ?? '').' '.($mov->centrocostos->nombre ?? '')) ?: '—',
                    'debe' => $modoExport
                        ? ($debe > 0 ? round($debe, 2) : '')
                        : ($debe > 0 ? number_format($debe, 2, ',', '.') : ''),
                    'haber' => $modoExport
                        ? ($haber > 0 ? round($haber, 2) : '')
                        : ($haber > 0 ? number_format($haber, 2, ',', '.') : ''),
                    'detalle' => (string) ($mov->observacion ?? ''),
                    'proveedor' => self::etiquetaProveedorMovimiento($mov),
                    default => '',
                };
            }
            $filas[] = $fila;
        }

        $parrafos = [];
        if ($cuenta) {
            $parrafos[] = 'Mayor cuenta '.$cuenta->codigo.' — '.($cuenta->nombre ?? '');
            if ($multiCuenta) {
                $parrafos[] = 'Cuenta de título/total: incluye '.count($cuentaIds).' cuentas imputables del rubro.';
            }
        } else {
            $parrafos[] = 'Mayor (todas las cuentas del filtro)';
        }
        $parrafos[] = 'Período: '.date('d/m/Y', strtotime($fechaDesde))
            .' → '.date('d/m/Y', strtotime($fechaHasta));
        if ($empresaId) {
            $empNombre = Empresa::query()->where('id', $empresaId)->value('nombre');
            $parrafos[] = 'Empresa: '.($empNombre ?: '#'.$empresaId);
        }
        if ($cc) {
            $parrafos[] = 'Centro de costo: '.$cc->codigo.' — '.($cc->nombre ?? '');
        }
        if ($oc) {
            $parrafos[] = 'Orden de compra: '.($oc->numeroordencompra ?? $oc->id);
        }
        if ($cruzar === 'proveedor') {
            $parrafos[] = 'Filtro: solo movimientos cruzados con comprobante de proveedor.';
        }
        if ($lineasPeriodo === 0) {
            $parrafos[] = 'Sin movimientos con esos filtros.';
        } else {
            $parrafos[] = 'Movimientos del período: '.$lineasPeriodo
                .' — Debe '.number_format($debePeriodo, 2, ',', '.')
                .' / Haber '.number_format($haberPeriodo, 2, ',', '.');
            if ($lineasPeriodo > $movs->count()) {
                $parrafos[] = 'Mostrando las primeras '.$movs->count()
                    .' líneas (orden cronológico). Use Excel para el detalle completo (hasta '
                    .AiConsultaOperativaSchemaSupport::MAX_LINEAS_EXPORT.').';
            }
        }

        return [
            'ok' => true,
            'intent' => self::INTENT_MAYOR_CUENTA,
            'score' => 0.88,
            'parrafos' => $parrafos,
            'links' => [],
            'tabla' => [
                'columnas' => $columnas,
                'filas' => $filas,
            ],
            'datos' => [
                'cuentacontable_id' => $cuenta ? (int) $cuenta->id : null,
                'cuenta_codigo' => $cuenta ? (string) $cuenta->codigo : null,
                'cuentas_incluidas' => $multiCuenta ? count($cuentaIds) : ($cuenta ? 1 : 0),
                'centrocosto_id' => $cc ? (int) $cc->id : null,
                'centrocosto_codigo' => $cc ? (string) $cc->codigo : null,
                'empresa_id' => $empresaId,
                'ordencompra_id' => $ordencompraId > 0 ? $ordencompraId : null,
                'numero_oc' => $oc ? (string) ($oc->numeroordencompra ?? '') : null,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
                'lineas_periodo' => $lineasPeriodo,
                'lineas_mostradas' => $movs->count(),
                'debe_periodo' => $debePeriodo,
                'haber_periodo' => $haberPeriodo,
                'debe' => $debePagina,
                'haber' => $haberPagina,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     */
    private static function resolverCentrocostoMayor(array $params): ?Centrocosto
    {
        $id = (int) ($params['centrocosto_id'] ?? 0);
        if ($id > 0) {
            return Centrocosto::query()->find($id);
        }
        $codigo = trim((string) ($params['centrocosto_codigo'] ?? ''));
        if ($codigo === '') {
            return null;
        }

        return Centrocosto::query()->where('codigo', $codigo)->first()
            ?? Centrocosto::query()->where('codigo', (int) $codigo)->first();
    }

    /**
     * @param  array<string,mixed>  $params
     */
    private static function resolverEmpresaIdMayor(array $params): ?int
    {
        $res = AiResolucionMaestrosSupport::resolverEmpresa($params);

        return ($res['ok'] ?? false) ? ($res['empresa_id'] ?? null) : null;
    }

    /**
     * @param  array<string,mixed>  $params
     */
    private static function resolverOrdencompraMayor(array $params): ?Ordencompra
    {
        $id = (int) ($params['ordencompra_id'] ?? 0);
        if ($id > 0) {
            return Ordencompra::query()->find($id);
        }
        $numero = trim((string) ($params['numero_oc'] ?? ''));
        if ($numero === '') {
            return null;
        }

        return Ordencompra::query()->where('numeroordencompra', $numero)->first()
            ?? (ctype_digit($numero) ? Ordencompra::query()->find((int) $numero) : null);
    }

    private static function etiquetaProveedorMovimiento(Asiento_Movimiento $mov): string
    {
        $comp = $mov->comprobante_proveedores;
        if (! $comp) {
            return '';
        }
        $prov = $comp->proveedores;
        if (! $prov) {
            return trim(($comp->letra ?? '').'-'.($comp->sucursal ?? '').'-'.($comp->numerocomprobante ?? ''));
        }

        return trim(($prov->codigo ?? '').' '.($prov->nombre ?? ''));
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function consultarClienteCtacte(array $params): array
    {
        if (! can('listar-cuentacorriente-cliente', false)
            && ! can('listar-clientes', false)
            && ! can('editar-cliente', false)) {
            return self::fallo(self::INTENT_CLIENTE_CTACTE, 'Sin permiso para consultar cuenta corriente de cliente.');
        }

        $valor = trim((string) ($params['codigo'] ?? $params['documento'] ?? $params['valor'] ?? ''));
        if ($valor === '') {
            return self::fallo(self::INTENT_CLIENTE_CTACTE, 'Indique código o documento del cliente.');
        }

        /** @var ClienteRepositoryInterface $repo */
        $repo = app(ClienteRepositoryInterface::class);
        try {
            $cliente = (preg_match('/^\d+$/', $valor) === 1 && strlen($valor) >= 7)
                ? ($repo->findPorNumeroDocumento($valor) ?? $repo->findPorCodigo($valor))
                : $repo->findPorCodigo($valor);
        } catch (\Throwable) {
            $cliente = null;
        }
        if (! $cliente instanceof Cliente) {
            return self::fallo(self::INTENT_CLIENTE_CTACTE, 'No se encontró cliente «'.$valor.'».');
        }

        /** @var Cliente_CuentacorrienteRepository $repoCt */
        $repoCt = app(Cliente_CuentacorrienteRepository::class);
        $saldo = $repoCt->calcularSaldoCuentaCorriente((int) $cliente->id);
        $deuda = $repoCt->calcularTotalDeudaCliente((int) $cliente->id);
        $max = self::topeLineas($params, 60);
        $fechaDesde = trim((string) ($params['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($params['fecha_hasta'] ?? ''));

        $q = Cliente_Cuentacorriente::query()
            ->with([
                'ventas.puntoventas:id,codigo',
                'ventas.tipotransacciones:id,abreviatura,nombre',
                'cobranzas:id,detalle',
                'monedas:id,abreviatura',
                'empresas:id,nombre',
            ])
            ->where('cliente_id', (int) $cliente->id);
        if ($fechaDesde !== '') {
            $q->whereDate('fecha', '>=', $fechaDesde);
        }
        if ($fechaHasta !== '') {
            $q->whereDate('fecha', '<=', $fechaHasta);
        }

        $totalPeriodo = (clone $q)->count();
        $importePeriodo = (float) (clone $q)->sum('total');
        $ultimas = $q->orderByDesc('fecha')->orderByDesc('id')->limit($max)->get();

        $columnas = [
            ['key' => 'fecha', 'label' => 'Fecha'],
            ['key' => 'vto', 'label' => 'Vto'],
            ['key' => 'tipo', 'label' => 'Tipo'],
            ['key' => 'comprobante', 'label' => 'Comprobante'],
            ['key' => 'cobranza', 'label' => 'Cobranza'],
            ['key' => 'moneda', 'label' => 'Mon'],
            ['key' => 'total', 'label' => 'Total'],
            ['key' => 'empresa', 'label' => 'Empresa'],
        ];
        $filasTabla = [];
        foreach ($ultimas as $fila) {
            $venta = $fila->ventas;
            $filasTabla[] = [
                'fecha' => $fila->fecha ? date('d/m/Y', strtotime((string) $fila->fecha)) : '—',
                'vto' => $fila->fechavencimiento
                    ? date('d/m/Y', strtotime((string) $fila->fechavencimiento))
                    : '',
                'tipo' => (string) ($venta?->tipotransacciones?->abreviatura
                    ?? $venta?->tipotransacciones?->nombre
                    ?? ($fila->cobranzas ? 'COB' : '')),
                'comprobante' => $venta
                    ? trim(($venta->puntoventas->codigo ?? '').'-'.($venta->numerocomprobante ?? $venta->codigo ?? ''))
                    : '',
                'cobranza' => (string) ($fila->cobranzas?->detalle ?? ''),
                'moneda' => (string) ($fila->monedas?->abreviatura ?? ''),
                'total' => number_format((float) $fila->total, 2, ',', '.'),
                'empresa' => (string) ($fila->empresas?->nombre ?? ''),
            ];
        }

        $parrafos = [
            'Cliente '.$cliente->codigo.' — '.($cliente->nombre ?? ''),
            'Saldo cuenta corriente: '.number_format($saldo, 2, ',', '.'),
            'Deuda pendiente (aprox.): '.number_format($deuda, 2, ',', '.'),
        ];
        if ($fechaDesde !== '' || $fechaHasta !== '') {
            $parrafos[] = 'Período: '
                .($fechaDesde !== '' ? date('d/m/Y', strtotime($fechaDesde)) : '…')
                .' → '
                .($fechaHasta !== '' ? date('d/m/Y', strtotime($fechaHasta)) : '…')
                .' — '.$totalPeriodo.' mov. / suma '.number_format($importePeriodo, 2, ',', '.');
        } else {
            $parrafos[] = 'Últimos movimientos: '.$ultimas->count()
                .' de '.$totalPeriodo.' (join venta/PV + cobranza + moneda + empresa).';
        }

        $links = [];
        if (can('listar-cuentacorriente-cliente', false) || can('listar-clientes', false)) {
            $links[] = [
                'etiqueta' => 'Abrir cuenta corriente',
                'url' => route('listar_cuentacorriente_cliente', ['id' => $cliente->id]),
            ];
        }

        return [
            'ok' => true,
            'intent' => self::INTENT_CLIENTE_CTACTE,
            'score' => 0.93,
            'parrafos' => $parrafos,
            'links' => $links,
            'tabla' => [
                'columnas' => $columnas,
                'filas' => $filasTabla,
            ],
            'datos' => [
                'cliente_id' => (int) $cliente->id,
                'codigo' => (string) ($cliente->codigo ?? ''),
                'saldo' => $saldo,
                'deuda' => $deuda,
                'lineas_periodo' => $totalPeriodo,
                'importe_periodo' => $importePeriodo,
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function consultarAsiento(array $params): array
    {
        if (! can('listar-asiento', false) && ! can('editar-asiento', false)) {
            return self::fallo(self::INTENT_ASIENTO, 'Sin permiso para consultar asientos.');
        }

        $numero = trim((string) ($params['numero'] ?? $params['valor'] ?? ''));
        if ($numero === '') {
            return self::fallo(self::INTENT_ASIENTO, 'Indique el número de asiento.');
        }

        $empresaId = self::resolverEmpresaIdMayor($params);

        $q = Asiento::query()
            ->with(['empresas:id,nombre', 'tipoasientos:id,nombre', 'asiento_movimientos.cuentacontables:id,codigo,nombre'])
            ->where('numeroasiento', $numero);
        if ($empresaId) {
            $q->where('empresa_id', $empresaId);
        }
        $asiento = $q->orderByDesc('id')->first();
        if (! $asiento) {
            return self::fallo(self::INTENT_ASIENTO, 'No se encontró asiento «'.$numero.'».');
        }

        $max = self::topeLineas($params, 12);
        $parrafos = [
            'Asiento '.$asiento->numeroasiento
                .' — '.($asiento->tipoasientos->nombre ?? '—')
                .' — '.($asiento->fecha ? date('d/m/Y', strtotime((string) $asiento->fecha)) : '—'),
            'Empresa: '.($asiento->empresas->nombre ?? '—'),
            'Estado aprobación: '.($asiento->estado_aprobacion ?? '—'),
        ];
        if (! empty($asiento->observacion)) {
            $parrafos[] = 'Obs.: '.$asiento->observacion;
        }
        $parrafos[] = 'Movimientos (máx. '.$max.'):';
        foreach ($asiento->asiento_movimientos->take($max) as $mov) {
            $monto = (float) ($mov->monto ?? 0);
            $lado = $monto >= 0 ? 'D' : 'H';
            $cta = $mov->cuentacontables;
            $parrafos[] = ($cta->codigo ?? '?').' '.($cta->nombre ?? '')
                .' '.$lado.' '.number_format(abs($monto), 2, ',', '.');
        }
        if ($asiento->asiento_movimientos->count() > $max) {
            $parrafos[] = '… y '.($asiento->asiento_movimientos->count() - $max).' línea(s) más.';
        }

        $links = [];
        if (can('editar-asiento', false) || can('listar-asiento', false)) {
            $links[] = [
                'etiqueta' => 'Abrir asiento',
                'url' => route('editar_asiento', ['id' => $asiento->id]).'?origen=modal_consulta&vista=consulta',
            ];
        }

        return [
            'ok' => true,
            'intent' => self::INTENT_ASIENTO,
            'score' => 0.91,
            'parrafos' => $parrafos,
            'links' => $links,
            'datos' => [
                'asiento_id' => (int) $asiento->id,
                'numeroasiento' => (string) $asiento->numeroasiento,
                'movimientos' => $asiento->asiento_movimientos->count(),
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function consultarComprobanteProveedor(array $params): array
    {
        if (! can('listar-comprobante-proveedor', false) && ! can('editar-comprobante-proveedor', false)) {
            return self::fallo(self::INTENT_COMPROBANTE_PROVEEDOR, 'Sin permiso para consultar comprobantes de proveedor.');
        }

        $valor = trim((string) ($params['numero'] ?? $params['codigo'] ?? $params['valor'] ?? ''));
        if ($valor === '') {
            return self::fallo(self::INTENT_COMPROBANTE_PROVEEDOR, 'Indique número o letra-sucursal-número del comprobante.');
        }

        $empresaId = self::resolverEmpresaIdMayor($params);
        $cp = self::buscarComprobanteProveedor($valor, $empresaId);
        if (! $cp) {
            return self::fallo(self::INTENT_COMPROBANTE_PROVEEDOR, 'No se encontró comprobante de proveedor «'.$valor.'».');
        }

        $codigo = self::codigoComprobanteProveedor($cp);
        $parrafos = [
            'Comprobante '.$codigo,
            'Proveedor: '.trim(($cp->proveedores->codigo ?? '').' '.($cp->proveedores->nombre ?? '')) ?: '—',
            'Empresa: '.($cp->empresas->nombre ?? '—'),
            'Fecha: '.($cp->fechacomprobante ? date('d/m/Y', strtotime((string) $cp->fechacomprobante)) : '—'),
            'Total: '.number_format((float) ($cp->total ?? 0), 2, ',', '.'),
            'Estado: '.($cp->estado ?? '—'),
        ];
        if (! empty($cp->ordencompras)) {
            $parrafos[] = 'OC: '.($cp->ordencompras->numeroordencompra ?? '—');
        }

        $links = [];
        if (can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false)) {
            $links[] = [
                'etiqueta' => 'Abrir comprobante',
                'url' => route('editar_comprobante_proveedor', ['id' => $cp->id]).'?origen=modal_consulta&vista=consulta',
            ];
        }

        return [
            'ok' => true,
            'intent' => self::INTENT_COMPROBANTE_PROVEEDOR,
            'score' => 0.9,
            'parrafos' => $parrafos,
            'links' => $links,
            'datos' => [
                'comprobante_proveedor_id' => (int) $cp->id,
                'codigo' => $codigo,
                'total' => (float) ($cp->total ?? 0),
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function consultarFacturaVenta(array $params): array
    {
        if (! can('listar-factura', false) && ! can('editar-factura', false) && ! can('facturar', false)) {
            return self::fallo(self::INTENT_FACTURA_VENTA, 'Sin permiso para consultar facturas de venta.');
        }

        $valor = trim((string) ($params['numero'] ?? $params['codigo'] ?? $params['valor'] ?? ''));
        if ($valor === '') {
            return self::fallo(self::INTENT_FACTURA_VENTA, 'Indique número o punto de venta-número de la factura.');
        }

        $empresaId = self::resolverEmpresaIdMayor($params);
        $venta = self::buscarFacturaVenta($valor, $empresaId);
        if (! $venta) {
            return self::fallo(self::INTENT_FACTURA_VENTA, 'No se encontró factura de venta «'.$valor.'».');
        }

        $codigo = self::codigoFacturaVenta($venta);
        $parrafos = [
            'Factura '.$codigo,
            'Cliente: '.trim(($venta->clientes->codigo ?? '').' '.($venta->clientes->nombre ?? '')) ?: '—',
            'Fecha: '.($venta->fecha ? date('d/m/Y', strtotime((string) $venta->fecha)) : '—'),
            'Total: '.number_format((float) ($venta->total ?? 0), 2, ',', '.'),
        ];
        if (isset($venta->estado)) {
            $parrafos[] = 'Estado: '.$venta->estado;
        }

        $links = [];
        if (can('editar-factura', false) || can('listar-factura', false)) {
            $links[] = [
                'etiqueta' => 'Abrir factura',
                'url' => route('editar_factura', ['id' => $venta->id]).'?origen=modal_consulta&vista=consulta',
            ];
        }

        return [
            'ok' => true,
            'intent' => self::INTENT_FACTURA_VENTA,
            'score' => 0.9,
            'parrafos' => $parrafos,
            'links' => $links,
            'datos' => [
                'venta_id' => (int) $venta->id,
                'codigo' => $codigo,
                'total' => (float) ($venta->total ?? 0),
            ],
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    private static function consultarPedidoConsumoSector(array $params): array
    {
        if (! self::usuarioPuedeIntent(self::INTENT_PEDIDO_CONSUMO_SECTOR)) {
            return self::fallo(self::INTENT_PEDIDO_CONSUMO_SECTOR, 'Sin permiso para planear pedidos por consumo.');
        }

        $resultado = PedidoConsumoSectorProyeccionSupport::proyectar($params);
        if (! ($resultado['ok'] ?? false)) {
            return self::fallo(
                self::INTENT_PEDIDO_CONSUMO_SECTOR,
                $resultado['error'] ?? 'No se pudo proyectar el pedido.'
            );
        }

        return [
            'ok' => true,
            'intent' => self::INTENT_PEDIDO_CONSUMO_SECTOR,
            'score' => (float) ($resultado['score'] ?? 0.88),
            'parrafos' => $resultado['parrafos'] ?? [],
            'links' => $resultado['links'] ?? [],
            'tabla' => $resultado['tabla'] ?? null,
            'datos' => $resultado['datos'] ?? [],
        ];
    }

    /**
     * @param  callable(): array<string,mixed>  $resolver
     * @return array<string,mixed>
     */
    private static function consultarComprasKpi(string $intent, callable $resolver): array
    {
        if (! self::usuarioPuedeIntent($intent)) {
            return self::fallo($intent, 'Sin permiso para KPIs / consultas de Compras.');
        }

        $resultado = $resolver();
        if (! ($resultado['ok'] ?? false)) {
            return self::fallo($intent, $resultado['error'] ?? 'No se pudo calcular el KPI.');
        }

        $out = [
            'ok' => true,
            'intent' => $intent,
            'score' => 0.9,
            'parrafos' => $resultado['parrafos'] ?? [],
            'links' => $resultado['links'] ?? [],
            'datos' => $resultado['datos'] ?? [],
        ];
        if (! empty($resultado['tabla'])) {
            $out['tabla'] = $resultado['tabla'];
        }

        return $out;
    }

    private static function buscarComprobanteProveedor(string $valor, ?int $empresaId): ?Comprobante_Proveedor
    {
        $query = Comprobante_Proveedor::query()
            ->with([
                'proveedores:id,codigo,nombre',
                'tipotransaccion_compras:id,abreviatura,nombre',
                'empresas:id,nombre',
                'ordencompras:id,numeroordencompra',
            ]);
        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        if (ctype_digit($valor)) {
            $query->where(function ($q) use ($valor) {
                $q->where('id', (int) $valor)
                    ->orWhere('numerocomprobante', (int) $valor);
            });
        } else {
            $partes = preg_split('/[\s\-\/]+/', $valor) ?: [];
            $query->where(function ($q) use ($valor, $partes) {
                $q->where('numerocomprobante', 'like', '%'.$valor.'%');
                if (count($partes) >= 3) {
                    $q->orWhere(function ($q2) use ($partes) {
                        $q2->where('letra', $partes[0])
                            ->where('sucursal', (int) $partes[1])
                            ->where('numerocomprobante', (int) $partes[2]);
                    });
                }
            });
        }

        return $query->orderByDesc('id')->first();
    }

    private static function buscarFacturaVenta(string $valor, ?int $empresaId): ?Venta
    {
        $query = Venta::query()
            ->with([
                'clientes:id,codigo,nombre',
                'tipotransacciones:id,abreviatura,nombre',
                'puntoventas:id,codigo,empresa_id',
            ]);
        if ($empresaId) {
            $query->whereHas('puntoventas', fn ($q) => $q->where('empresa_id', $empresaId));
        }

        if (ctype_digit($valor)) {
            $query->where(function ($q) use ($valor) {
                $q->where('id', (int) $valor)
                    ->orWhere('numerocomprobante', (int) $valor);
            });
        } else {
            $partes = preg_split('/[\s\-\/]+/', $valor) ?: [];
            $query->where(function ($q) use ($valor, $partes) {
                $q->where('numerocomprobante', 'like', '%'.$valor.'%');
                if (count($partes) >= 2) {
                    $nro = (int) end($partes);
                    $pv = (int) ($partes[count($partes) - 2] ?? 0);
                    $q->orWhere(function ($q2) use ($nro, $pv) {
                        $q2->where('numerocomprobante', $nro);
                        if ($pv > 0) {
                            $q2->whereHas('puntoventas', fn ($pq) => $pq->where('codigo', $pv));
                        }
                    });
                }
            });
        }

        return $query->orderByDesc('id')->first();
    }

    private static function codigoComprobanteProveedor(Comprobante_Proveedor $cp): string
    {
        $abrev = (string) ($cp->tipotransaccion_compras?->abreviatura ?? '');
        $suc = str_pad((string) ($cp->sucursal ?? 0), 4, '0', STR_PAD_LEFT);

        return trim($abrev.' '.$cp->letra.'-'.$suc.'-'.$cp->numerocomprobante);
    }

    private static function codigoFacturaVenta(Venta $venta): string
    {
        $abrev = (string) ($venta->tipotransacciones?->abreviatura ?? '');
        $pv = (string) ($venta->puntoventas?->codigo ?? '');

        return trim($abrev.' '.$pv.'-'.$venta->numerocomprobante);
    }

    /**
     * @param  array<string,mixed>  $params
     */
    private static function topeLineas(array $params, int $default): int
    {
        $modoExport = ! empty($params['modo_export']);
        $tope = $modoExport
            ? AiConsultaOperativaSchemaSupport::MAX_LINEAS_EXPORT
            : AiConsultaOperativaSchemaSupport::MAX_LINEAS;
        $max = (int) ($params['max_lineas'] ?? $default);

        return max(1, min($tope, $max > 0 ? $max : $default));
    }

    /**
     * @param  array<string,mixed>  $params
     */
    private static function resolverDepositoIdDesdeParams(array $params): int
    {
        if (isset($params['deposito_id']) && (int) $params['deposito_id'] > 0) {
            return (int) $params['deposito_id'];
        }
        $codigo = trim((string) ($params['deposito_codigo'] ?? $params['deposito'] ?? ''));
        if ($codigo === '') {
            return 0;
        }
        $dep = Depmae::query()->where('codigo', $codigo)->first();

        return $dep ? (int) $dep->id : 0;
    }

    private static function normalizarCodigoCuenta(string $codigo): string
    {
        return AiResolucionMaestrosSupport::normalizarCodigoCuenta($codigo);
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array{0: ?int, 1: ?int} anio_mes YYYYMM
     */
    private static function resolverRangoAnioMes(array $params): array
    {
        $desde = isset($params['fecha_desde']) ? (string) $params['fecha_desde'] : '';
        $hasta = isset($params['fecha_hasta']) ? (string) $params['fecha_hasta'] : '';
        $desdeMes = $desde !== '' && preg_match('/^\d{4}-\d{2}/', $desde)
            ? (int) str_replace('-', '', substr($desde, 0, 7))
            : null;
        $hastaMes = $hasta !== '' && preg_match('/^\d{4}-\d{2}/', $hasta)
            ? (int) str_replace('-', '', substr($hasta, 0, 7))
            : null;

        return [$desdeMes, $hastaMes];
    }

    /**
     * @return array<string,mixed>
     */
    private static function fallo(string $intent, string $mensaje): array
    {
        return [
            'ok' => false,
            'intent' => $intent,
            'score' => 0.0,
            'parrafos' => [],
            'links' => [],
            'datos' => [],
            'error' => $mensaje,
        ];
    }
}

