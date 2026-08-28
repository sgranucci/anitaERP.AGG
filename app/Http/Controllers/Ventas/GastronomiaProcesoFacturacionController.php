<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Usocuentacaja;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Articulo;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\CuentaGastronomiaLinea;
use App\Models\Ventas\DescuentoGastronomia;
use App\Models\Ventas\MesaGastronomia;
use App\Models\Ventas\MozoGastronomia;
use App\Support\Caja\CotizacionTesoreriaConsultaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use App\Services\Ventas\Gastronomia\GastronomiaCobranzaService;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Services\Ventas\Gastronomia\GastronomiaFacturaEmisionService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Services\Ventas\Gastronomia\GastronomiaTicketTarjetaCanjeService;
use App\Services\Ventas\Gastronomia\GastronomiaCategoriafidelidadCanjeService;
use App\Services\Ventas\Gastronomia\GastronomiaTicketCanjePremioService;
use App\Services\Ventas\Gastronomia\GastronomiaPreflightEmisionService;
use App\Services\Ventas\Gastronomia\GastronomiaEmisionDiagnosticoService;
use App\Services\Ventas\Gastronomia\GastronomiaTicketDiagnosticoService;
use App\Services\Ventas\Gastronomia\GastronomiaFormulaOpcionalesService;
use App\Services\Ventas\Gastronomia\GastronomiaTurnoOperativoService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryOrdenesExternasService;
use App\Services\Stock\PrecioService;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Support\Ventas\GastronomiaCuentacajaCanjeTarjeta;
use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\GastronomiaCuentacajaIconoSupport;
use App\Support\Ventas\GastronomiaCuentacajaSoloAutomaticaSupport;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use App\Support\Ventas\Gastronomia\GastronomiaAnularCuentaPendienteClaveSupport;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class GastronomiaProcesoFacturacionController extends Controller
{
    public function __construct(
        private readonly GastronomiaCuentaService $cuentaService,
        private readonly GastronomiaFacturaEmisionService $facturaEmisionService,
        private readonly GastronomiaFormulaOpcionalesService $opcionalesService,
        private readonly GastronomiaJornadaService $jornadaService,
        private readonly GastronomiaTurnoOperativoService $turnoOperativoService,
        private readonly WaitryOrdenesExternasService $waitryOrdenesExternasService,
        private readonly GastronomiaTicketTarjetaCanjeService $ticketTarjetaCanjeService,
        private readonly GastronomiaTicketCanjePremioService $ticketCanjePremioService,
        private readonly GastronomiaCategoriafidelidadCanjeService $categoriafidelidadCanjeService,
        private readonly GastronomiaEmisionDiagnosticoService $emisionDiagnosticoService,
        private readonly GastronomiaTicketDiagnosticoService $ticketDiagnosticoService,
    ) {}

    public function apiDiagnosticoEmision(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        try {
            $medicion = $this->emisionDiagnosticoService->medirNumeracion($cfg);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'identificador_pc' => GastronomiaIdentificadorPc::resolver($request),
            'medicion' => $medicion,
            'interpretacion' => [
                'cuello_botella_probable' => $this->interpretarCuelloBotella($medicion),
            ],
        ]);
    }

    public function apiDiagnosticoTicket(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $opciones = ['cfg_id' => (int) $cfg->id];
        if ($request->filled('venta_id')) {
            $opciones['venta_id'] = (int) $request->get('venta_id');
        }
        if ($request->boolean('imprimir')) {
            $opciones['imprimir'] = true;
        }

        try {
            $medicion = $this->ticketDiagnosticoService->medir($opciones);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => empty($medicion['errores'] ?? []),
            'identificador_pc' => GastronomiaIdentificadorPc::resolver($request),
            'medicion' => $medicion,
        ]);
    }

    /**
     * @param  array<string, mixed>  $medicion
     */
    private function interpretarCuelloBotella(array $medicion): string
    {
        $lat = $medicion['latencias_ms'] ?? [];
        $anitaTotal = (float) ($lat['anita_max_ven_nro_total'] ?? 0);
        $erp = (float) ($lat['erp_max_numerocomprobante'] ?? 0);

        if ($anitaTotal > 500 && $erp < 50) {
            return 'La consulta del último número en Anita ('.$anitaTotal.' ms) es mucho más lenta que ERP local ('.$erp.' ms). '
                .'CAEA: numeración ERP con lock por PV (CaeaEmisionNumeracionSupport).';
        }

        if ($anitaTotal < 200) {
            return 'Anita responde rápido para numeración ('.$anitaTotal.' ms). Si la factura tarda, revise grabaAnita (venta/stkmov), ticket térmico o log con GASTRONOMIA_EMISION_PROFILE=true.';
        }

        return 'Numeración Anita '.$anitaTotal.' ms vs ERP '.$erp.' ms. Active GASTRONOMIA_EMISION_PROFILE=true y facture una cuenta para ver el desglose completo en storage/logs/laravel.log.';
    }

    public function index(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cfg = $this->cfgPv($request);
        $empresaId = $cfg ? (int) $cfg->empresa_id : null;

        $cfg?->loadMissing(['tipotransaccion', 'listaprecio']);
        $tt = $cfg?->tipotransaccion;
        $tipoFacturaId = (int) ($cfg?->tipotransaccion_id ?? 0) ?: (int) config('gastronomia.tipotransaccion_factura_id', 0);
        $cfgTipotransaccionNombre = $tt
            ? trim($tt->abreviatura.' — '.$tt->nombre)
            : ($tipoFacturaId > 0 ? 'ID '.$tipoFacturaId.' (solo env)' : null);

        $listaprecioId = $cfg ? (int) ($cfg->listaprecio_id ?? config('precio.listaprecio_default_id', 1)) : (int) config('precio.listaprecio_default_id', 1);

        return view('ventas.gastronomia.proceso_facturacion.index', [
            'empresa_id' => $empresaId,
            'empresa_nombre' => $cfg?->empresa?->nombre,
            'listaprecio_id' => $listaprecioId,
            'listaprecio_nombre' => $cfg?->listaprecio?->nombre,
            'cfg_tipotransaccion_nombre' => $cfgTipotransaccionNombre,
            'prefijo_sku' => (string) config('gastronomia.sku_catalogo_prefijo', 'V'),
            'sku_catalogo_digitos_sufijo' => max(0, (int) config('gastronomia.sku_catalogo_digitos_sufijo', 0)),
            'tiene_cfg_pv' => $cfg !== null,
            'ubicacion_id' => $cfg?->ubicacion_id,
            'salida_factura_id' => $cfg?->salida_factura_id,
            'identificador_pc_actual' => GastronomiaIdentificadorPc::resolver($request),
            'usocuentacaja_gastronomia_id' => $this->usoCuentacajaGastronomiaId(),
            'wsfe_receptor_cf_umbral_monto' => ParametroSistemaSupport::topeConsumidorFinal(),
            'wsfe_forzar_modo_caea' => \App\Support\Ventas\ArcaWsfeEmisionResiliencia::forzarModoCaea(),
            'wsfe_failover_automatico' => \App\Support\Ventas\ArcaWsfeEmisionResiliencia::failoverAutomaticoActivo(),
            'modo_seleccion_preferido' => $this->leerPreferenciaModoSeleccion($cfg) ?? 'mesa',
            'waitry_habilitado_terminal' => $cfg?->waitryHabilitadoEnTerminal() ?? false,
            'cuentas_libres_habilitadas' => (bool) config('gastronomia.cuentas_libres_habilitadas', true),
            'cubiertos_obligatorio_al_abrir' => (bool) config('gastronomia.cubiertos_obligatorio_al_abrir', true),
            'cubiertos_default_al_abrir' => max(0, (int) config('gastronomia.cubiertos_default_al_abrir', 1)),
            'mozo_obligatorio_al_abrir' => (bool) config('gastronomia.mozo_obligatorio_al_abrir', true),
            'jornada' => $empresaId > 0
                ? $this->jornadaService->estadoParaEmpresa($empresaId)
                : null,
            'jornada_obligatoria' => (bool) config('gastronomia.jornada_obligatoria', true),
            'requiere_habilitacion_turno' => GastronomiaTurnoOperativoService::requiereHabilitacionTurno(),
            'turno_operativo' => $cfg && $empresaId > 0
                ? $this->turnoOperativoService->estadoParaTerminal(
                    $cfg,
                    GastronomiaIdentificadorPc::resolver($request),
                )
                : null,
            'url_habilitacion_turno' => route('gastronomia_habilitacion_turno'),
            'exige_clave_anular_cuenta_pendiente' => GastronomiaAnularCuentaPendienteClaveSupport::activoParaEmpresa($empresaId),
        ]);
    }

    public function apiConfig(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            $body = $cfg->getData(true);
            $body['ok'] = false;

            return response()->json($body, 422);
        }

        return response()->json([
            'ok' => true,
            'empresa_id' => (int) $cfg->empresa_id,
            'empresa_nombre' => $cfg->empresa->nombre ?? null,
            'identificador_pc' => GastronomiaIdentificadorPc::resolver($request),
            'ubicacion_id' => $cfg->ubicacion_id,
            'puntoventa_cae_id' => $cfg->puntoventa_cae_id,
            'puntoventa_caea_id' => $cfg->puntoventa_caea_id,
            'salida_factura_id' => $cfg->salida_factura_id,
            'salida_comanda_id' => $cfg->salida_comanda_id,
            'listaprecio_id' => $cfg->listaprecio_id,
            'listaprecio_nombre' => $cfg->listaprecio->nombre ?? null,
            'tipotransaccion_id' => $cfg->tipotransaccion_id,
            'tipotransaccion_nombre' => $cfg->tipotransaccion
                ? trim($cfg->tipotransaccion->abreviatura.' — '.$cfg->tipotransaccion->nombre)
                : null,
            'usocuentacaja_gastronomia_id' => $this->usoCuentacajaGastronomiaId(),
            'cuentacaja_efectivo' => $this->resolverCuentacajaEfectivo($cfg),
            'cuentacaja_efectivo_id' => GastronomiaCuentacajaEfectivo::idParaEmpresa((int) $cfg->empresa_id),
            'cuentacaja_efectivo_error' => GastronomiaCuentacajaEfectivo::mensajeErrorResolucion((int) $cfg->empresa_id),
            'cuentacaja_canje_tarjeta' => GastronomiaCuentacajaCanjeTarjeta::cuentaParaEmpresa((int) $cfg->empresa_id),
            'cuentacaja_canje_tarjeta_error' => GastronomiaCuentacajaCanjeTarjeta::mensajeErrorResolucion((int) $cfg->empresa_id),
            'cuentacaja_totem' => GastronomiaCuentacajaTotem::cuentaParaEmpresa((int) $cfg->empresa_id),
            'cuentacaja_totem_error' => GastronomiaCuentacajaTotem::mensajeErrorResolucion((int) $cfg->empresa_id),
            'cuentacaja_totem_codigo' => GastronomiaCuentacajaTotem::codigo(),
            'waitry_tipo_pago_cuentacaja' => WaitryMedioPagoCuentacajaSupport::mediosConfiguradosParaEmpresa((int) $cfg->empresa_id),
            'ticket_tarjeta_vencimiento_dias' => (int) config('gastronomia.ticket_tarjeta_vencimiento_dias', 30),
            'ticket_tarjeta_tolerancia_excedente' => (float) config('gastronomia.ticket_tarjeta_tolerancia_excedente_factura', 5.),
            'canje_premio_descuento_codigo' => (string) config('gastronomia.canje_premio_descuento_codigo', '10'),
            'canje_premio_cliente_codigo' => (string) config('gastronomia.canje_premio_cliente_codigo', '500'),
            'wigos_habilitado' => (bool) config('wigos.habilitado', false),
            'wigos_account_info_habilitado' => (bool) config('wigos.account_info_habilitado', false),
            'canje_fidelidad_descuento_codigo' => (string) config('gastronomia.canje_fidelidad_descuento_codigo', '10'),
            'canje_fidelidad_cliente_codigo' => (string) config('gastronomia.canje_fidelidad_cliente_codigo', '500'),
            'receptor_cf_nombre' => trim((string) config('arca_wsfe.receptor.consumidor_final_razon_social', 'CONSUMIDOR FINAL')),
            'tipotransaccion_caja_id' => GastronomiaCobranzaService::resolverTipotransaccionCajaId($cfg),
            'cobranza_config_error' => GastronomiaCobranzaService::mensajeConfigCobranzaFaltante($cfg),
            'modo_seleccion_preferido' => $this->leerPreferenciaModoSeleccion($cfg) ?? 'mesa',
            'cuentas_libres_habilitadas' => (bool) config('gastronomia.cuentas_libres_habilitadas', true),
            'cubiertos_obligatorio_al_abrir' => (bool) config('gastronomia.cubiertos_obligatorio_al_abrir', true),
            'cubiertos_default_al_abrir' => max(0, (int) config('gastronomia.cubiertos_default_al_abrir', 1)),
            'mozo_obligatorio_al_abrir' => (bool) config('gastronomia.mozo_obligatorio_al_abrir', true),
            'jornada' => $this->jornadaService->estadoParaEmpresa((int) $cfg->empresa_id),
            'jornada_obligatoria' => (bool) config('gastronomia.jornada_obligatoria', true),
            'requiere_habilitacion_turno' => GastronomiaTurnoOperativoService::requiereHabilitacionTurno(),
            'turno_operativo' => $this->turnoOperativoService->estadoParaTerminal(
                $cfg,
                GastronomiaIdentificadorPc::resolver($request),
            ),
            'url_habilitacion_turno' => route('gastronomia_habilitacion_turno'),
            'waitry_habilitado' => $cfg->waitryHabilitadoEnTerminal(),
            'waitry_get_orders_minutos_atras' => max(0, (int) config('waitry.get_orders_minutos_atras', 20)),
            'waitry_get_orders_cache_segundos' => max(0, (int) config('waitry.get_orders_cache_segundos', 15)),
            'exige_clave_anular_cuenta_pendiente' => GastronomiaAnularCuentaPendienteClaveSupport::activoParaEmpresa((int) $cfg->empresa_id),
        ]);
    }

    public function apiWaitryOrdenesPendientes(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        if (! $cfg->waitryHabilitadoEnTerminal()) {
            return response()->json(['ok' => false, 'error' => 'Integración Waitry deshabilitada para esta terminal.'], 422);
        }

        $desde = $request->get('from');
        $hasta = $request->get('to');
        $resultado = $this->waitryOrdenesExternasService->listarOrdenesPendientes(
            (int) $cfg->empresa_id,
            is_string($desde) ? $desde : null,
            is_string($hasta) ? $hasta : null,
            $request->boolean('refresh'),
        );

        if (! ($resultado['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => $resultado['error'] ?? 'No se pudieron obtener las órdenes Waitry.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'ordenes' => $resultado['ordenes'] ?? [],
            'filtro' => $resultado['filtro'] ?? null,
        ]);
    }

    public function apiWaitryImportarOrden(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $rawWaitryId = $request->input('waitry_order_id');
        if ($rawWaitryId !== null && $rawWaitryId !== '') {
            $request->merge(['waitry_order_id' => trim((string) $rawWaitryId)]);
        }

        $request->validate([
            'waitry_order_id' => ['required', 'min:1', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'cuenta_id' => 'nullable|integer|min:1',
            'cubiertos' => 'nullable|integer|min:0',
            'mozo_gastronomia_id' => 'nullable|integer',
            'incluir_orden_pagada' => 'nullable|boolean',
            'importar_por_id' => 'nullable|boolean',
        ]);

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        if (! $cfg->waitryHabilitadoEnTerminal()) {
            return response()->json(['ok' => false, 'error' => 'Integración Waitry deshabilitada para esta terminal.'], 422);
        }

        $identificadorPapelito = trim((string) $request->get('waitry_order_id'));

        $resultado = $this->waitryOrdenesExternasService->importarOrdenEnCuenta(
            $cfg,
            $identificadorPapelito,
            $request->only(['cubiertos', 'mozo_gastronomia_id']),
            $request->filled('cuenta_id') ? (int) $request->get('cuenta_id') : null,
            $request->boolean('importar_por_id') || $request->boolean('incluir_orden_pagada'),
        );

        if (! ($resultado['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => $resultado['error'] ?? 'No se pudo importar la orden.',
                'errores' => $resultado['errores'] ?? [],
                'requiere_carga_opcionales_en_pos' => (bool) ($resultado['requiere_carga_opcionales_en_pos'] ?? false),
            ], 422);
        }

        $erroresImport = $resultado['errores'] ?? [];
        $skusOpcionalesPendientes = $this->extraerSkusOpcionalesPendientesDesdeErroresWaitry($erroresImport);
        $soloOpcionalesEnPos = (bool) ($resultado['requiere_carga_opcionales_en_pos'] ?? false);

        return response()->json([
            'ok' => true,
            'cuenta_id' => $resultado['cuenta']->id,
            'cuenta' => $resultado['cuenta'],
            'errores' => $erroresImport,
            'skus_opcionales_pendientes' => $skusOpcionalesPendientes,
            'requiere_carga_opcionales_en_pos' => $soloOpcionalesEnPos,
            'mensaje' => $soloOpcionalesEnPos
                ? 'Cuenta Waitry «'.$identificadorPapelito.'» abierta. Complete los consumos con opcionales en el POS.'
                : 'Cuenta Waitry «'.$identificadorPapelito.'» importada correctamente.',
            'warn' => $erroresImport !== []
                ? ($skusOpcionalesPendientes !== []
                    ? 'Faltan en la cuenta (opcionales): '.implode(', ', $skusOpcionalesPendientes)
                        .'. Se abrirá el asistente para cargarlos.'
                    : 'Importación parcial: algunos ítems no se cargaron (ver detalle).')
                : null,
        ]);
    }

    public function apiTurnoEstado(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        return response()->json([
            'ok' => true,
            ...$this->turnoOperativoService->estadoParaTerminal(
                $cfg,
                GastronomiaIdentificadorPc::resolver($request),
            ),
        ]);
    }

    public function apiCierreParcialTurno(Request $request)
    {
        can('cierre-parcial-turno-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $pc = GastronomiaIdentificadorPc::resolver($request);
        $activo = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);
        if ($activo === null) {
            return response()->json(['ok' => false, 'error' => 'No hay turno habilitado en esta terminal.'], 422);
        }

        try {
            $activo->loadMissing('jornada');
            $parcial = $this->turnoOperativoService->registrarCierreParcial($activo, $pc);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Cierre parcial #'.$parcial->numero_parcial.' registrado.',
                'parcial' => [
                    'numero' => $parcial->numero_parcial,
                    'total' => $parcial->total_facturacion_turno,
                    'totales' => $parcial->totales_json,
                ],
                'url_comprobante_pdf' => route('gastronomia_cierre_turno_comprobante_parcial', [
                    'id' => $parcial->id,
                    'inline' => 1,
                ]),
                'estado' => $this->turnoOperativoService->estadoParaTerminal($cfg, $pc),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiCerrarTurno(Request $request)
    {
        can('cerrar-turno-operativo-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $pc = GastronomiaIdentificadorPc::resolver($request);
        $activo = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);
        if ($activo === null) {
            return response()->json(['ok' => false, 'error' => 'No hay turno habilitado en esta terminal.'], 422);
        }

        try {
            $activo->loadMissing('jornada');
            $turnoCerrado = $this->turnoOperativoService->cerrar($activo, $pc, [
                'redondeo_invitaciones' => $request->input('redondeo_invitaciones'),
                'redondeo_turno' => $request->input('redondeo_turno'),
                'sobrante_faltante' => $request->input('sobrante_faltante'),
                'observacion_cierre' => $request->input('observacion_cierre'),
                'medios_contado' => $request->input('medios_contado'),
            ]);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Turno cerrado correctamente.',
                'url_comprobante_pdf' => route('gastronomia_cierre_turno_comprobante_cierre', [
                    'id' => $turnoCerrado->id,
                    'inline' => 1,
                ]),
                'estado' => $this->turnoOperativoService->estadoParaTerminal($cfg, $pc),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiGuardarPreferenciaModoSeleccion(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $modoRaw = $request->input('modo');
        $modo = is_string($modoRaw) ? trim($modoRaw) : '';
        if ($modo === 'cuenta' && ! config('gastronomia.cuentas_libres_habilitadas', true)) {
            return response()->json(['ok' => false, 'message' => 'Las cuentas libres no están habilitadas.'], 422);
        }
        $modosValidos = ['mesa', 'cuenta'];
        if ($cfg->waitryHabilitadoEnTerminal()) {
            $modosValidos[] = 'waitry';
        }
        if (! in_array($modo, $modosValidos, true)) {
            return response()->json(['ok' => false, 'message' => 'Modo inválido.'], 422);
        }

        Cache::forever(generaKey('gastronomia-modo-seleccion'), $modo);

        return response()->json(['ok' => true, 'modo' => $modo]);
    }

    public function apiMesas(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $ubicacionId = $cfg->ubicacion_id ? (int) $cfg->ubicacion_id : null;
        $mesas = $this->cuentaService->listarMesasUbicacion($ubicacionId);

        return response()->json([
            'mesas' => $this->cuentaService->mesasConOcupacion($mesas),
            'ubicacion_id' => $ubicacionId,
        ]);
    }

    public function apiCuentasActivas(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cuentas = $this->cuentaService->listarCuentasLibresActivasPc($request);

        return response()->json([
            'cuentas' => $cuentas->map(fn ($c) => [
                'id' => $c->id,
                'tipo' => $c->tipo,
                'cliente_id' => $c->cliente_id,
                'mozo_gastronomia_id' => $c->mozo_gastronomia_id,
                'cubiertos' => $c->cubiertos,
            ])->values(),
        ]);
    }

    public function apiCuentaVer($id)
    {
        can('usar-proceso-facturacion-gastronomia');

        try {
            $cuenta = $this->cuentaService->cuentaConLineas((int) $id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['cuenta' => $cuenta]);
    }

    public function apiAbrirMesa(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $request->validate([
            'mesa_id' => 'required|integer',
            'empresa_id' => 'nullable|integer',
        ]);

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $empresaId = (int) $cfg->empresa_id;
        $mesaId = (int) $request->get('mesa_id');

        $mesa = MesaGastronomia::query()->where('id', $mesaId)->where('empresa_id', $empresaId)->first();
        if (! $mesa) {
            return response()->json(['error' => 'Mesa no encontrada para la empresa.'], 422);
        }

        if ($cfg->ubicacion_id && (int) $mesa->ubicacion_id !== (int) $cfg->ubicacion_id) {
            return response()->json(['error' => 'La mesa no pertenece a la ubicación configurada en este punto de venta.'], 422);
        }

        $existente = CuentaGastronomia::query()
            ->where('mesa_gastronomia_id', $mesaId)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->first();

        if ($existente) {
            return response()->json([
                'cuenta_id' => $existente->id,
                'reutilizada' => true,
            ]);
        }

        try {
            $cuenta = $this->cuentaService->abrirMesa(
                $mesaId,
                $empresaId,
                $cfg,
                $request->only(['cubiertos', 'mozo_gastronomia_id'])
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['cuenta_id' => $cuenta->id, 'reutilizada' => false]);
    }

    public function apiAbrirCuenta(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        if (! config('gastronomia.cuentas_libres_habilitadas', true)) {
            return response()->json(['error' => 'Las cuentas libres no están habilitadas.'], 422);
        }

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        try {
            $cuenta = $this->cuentaService->abrirCuentaLibre(
                (int) $cfg->empresa_id,
                $cfg,
                $request->only(['cubiertos', 'mozo_gastronomia_id'])
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['cuenta_id' => $cuenta->id]);
    }

    public function apiActualizarCuenta(Request $request, int $id)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cuenta = $this->cuentaService->cuentaConLineasSinEnriquecer($id);

        try {
            $cuenta = $this->cuentaService->actualizarCabecera($cuenta, $request->all());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'cuenta' => $this->cuentaService->enriquecerCuentaParaApi(
                $cuenta->fresh(['lineas.articulo', 'cliente', 'mozo', 'mesa', 'descuentoGastronomia.cliente', 'clienteInternoDescuento'])
            ),
        ]);
    }

    public function apiAgregarLinea(Request $request, int $id)
    {
        can('usar-proceso-facturacion-gastronomia');

        $request->validate([
            'articulo_id' => 'required|integer',
            'cantidad' => 'required|numeric|min:0.0001',
            'precio_unitario' => 'nullable|numeric|min:0',
            'opcionales' => 'nullable|array',
        ]);

        $cuenta = $this->cuentaService->cuentaConLineas($id);

        $articulo = Articulo::query()->findOrFail((int) $request->get('articulo_id'));

        $precio = $request->has('precio_unitario')
            ? (float) $request->get('precio_unitario')
            : $this->resolverPrecioLista((int) $articulo->id);

        $opcionales = \App\Support\Ventas\GastronomiaFormulaOpcionalSeleccion::normalizarMapaDesdeRequest(
            (array) ($request->get('opcionales') ?? [])
        );

        if (FormulaArticuloGastronomia::opcionalesHabilitados()) {
            $grupos = $this->opcionalesService->gruposOpcionalesPorArticulo($articulo);
            if ($grupos !== []) {
                try {
                    $this->opcionalesService->validarSeleccionOpcionales($articulo, $opcionales);
                } catch (\InvalidArgumentException $e) {
                    return response()->json(['error' => $e->getMessage(), 'requiere_opcionales' => true, 'grupos' => $grupos], 422);
                }
            }
        }

        try {
            $linea = $this->cuentaService->agregarLinea(
                $cuenta,
                (int) $articulo->id,
                (float) $request->get('cantidad'),
                $precio,
                $opcionales,
                (float) ($request->get('descuento_linea_pct') ?? 0.)
            );
            $cuentaActualizada = $this->cuentaService->cuentaConLineas($cuenta->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'linea' => $linea->load('articulo'),
            'cuenta' => $cuentaActualizada,
        ]);
    }

    public function apiEliminarLinea(int $cuentaId, int $lineaId)
    {
        can('usar-proceso-facturacion-gastronomia');

        $linea = CuentaGastronomiaLinea::query()
            ->where('cuenta_gastronomia_id', $cuentaId)
            ->where('id', $lineaId)
            ->firstOrFail();

        try {
            $this->cuentaService->eliminarLinea($linea);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'cuenta' => $this->cuentaService->cuentaConLineas($cuentaId)]);
    }

    public function apiCerrarCuenta(Request $request, int $id)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cuenta = $this->cuentaService->cuentaConLineas($id);

        try {
            GastronomiaAnularCuentaPendienteClaveSupport::validar(
                $cuenta,
                $request->input('clave')
            );
            $this->cuentaService->cerrarSinFacturar($cuenta);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function apiArticulosCatalogo(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $q = trim((string) $request->get('q', ''));
        $listaId = $this->listaPrecioIdDesdeCfg($cfg);

        $articulos = $this->cuentaService->queryArticulosCatalogo($cfg, $q, 80)->get(['id', 'sku', 'descripcion']);
        $fechaVigencia = $this->fechaVigenciaListaPrecioDesdeCfg($cfg);

        $out = [];
        foreach ($articulos as $a) {
            $precios = PrecioService::asignaPrecioPorLista((int) $a->id, $listaId, $fechaVigencia);
            $precioInfo = $precios !== [] ? end($precios) : ['precio' => 0, 'moneda_id' => 1, 'listaprecio_id' => $listaId];

            $out[] = [
                'id' => $a->id,
                'sku' => $a->sku,
                'descripcion' => $a->descripcion,
                'precio_sugerido' => (float) ($precioInfo['precio'] ?? 0),
                'moneda_id' => (int) ($precioInfo['moneda_id'] ?? 1),
                'listaprecio_id' => $listaId,
            ];
        }

        return response()->json(['articulos' => $out]);
    }

    /**
     * Un artículo del catálogo gastronomía por SKU exacto (precio lista del PV).
     */
    public function apiArticuloCatalogoPorSku(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $sku = trim((string) $request->get('sku', ''));
        if ($sku === '') {
            return response()->json(['error' => 'SKU vacío'], 422);
        }

        $a = $this->cuentaService->buscarArticuloCatalogoPorSku($cfg, $sku);

        if (! $a) {
            return response()->json(['error' => 'Artículo no encontrado en catálogo gastronomía'], 404);
        }

        $listaId = $this->listaPrecioIdDesdeCfg($cfg);
        $precios = PrecioService::asignaPrecioPorLista(
            (int) $a->id,
            $listaId,
            $this->fechaVigenciaListaPrecioDesdeCfg($cfg),
        );
        $precioInfo = $precios !== [] ? end($precios) : ['precio' => 0, 'moneda_id' => 1, 'listaprecio_id' => $listaId];

        return response()->json([
            'articulo' => [
                'id' => $a->id,
                'sku' => $a->sku,
                'descripcion' => $a->descripcion,
                'precio_sugerido' => (float) ($precioInfo['precio'] ?? 0),
                'moneda_id' => (int) ($precioInfo['moneda_id'] ?? 1),
                'listaprecio_id' => $listaId,
            ],
        ]);
    }

    public function apiActualizarCantidadLinea(Request $request, int $cuentaId, int $lineaId)
    {
        can('usar-proceso-facturacion-gastronomia');

        $request->validate([
            'cantidad' => 'sometimes|required|numeric|min:0.0001',
            'comentario_cocina' => 'sometimes|nullable|string|max:255',
        ]);

        $linea = CuentaGastronomiaLinea::query()
            ->where('cuenta_gastronomia_id', $cuentaId)
            ->where('id', $lineaId)
            ->firstOrFail();

        try {
            if ($request->has('comentario_cocina')) {
                $cuenta = $this->cuentaService->actualizarComentarioCocinaLinea(
                    $linea,
                    $request->input('comentario_cocina'),
                );
            } elseif ($request->has('cantidad')) {
                $cuenta = $this->cuentaService->actualizarCantidadLinea($linea, (float) $request->get('cantidad'));
            } else {
                return response()->json(['error' => 'Indique cantidad o comentario de cocina.'], 422);
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'cuenta' => $cuenta]);
    }

    public function apiOpcionalesArticulo(int $articuloId)
    {
        can('usar-proceso-facturacion-gastronomia');

        $articulo = Articulo::query()->findOrFail($articuloId);

        return response()->json([
            'grupos' => $this->opcionalesService->gruposOpcionalesPorArticulo($articulo),
        ]);
    }

    public function apiMozos(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        return response()->json([
            'mozos' => MozoGastronomia::query()->where('empresa_id', $cfg->empresa_id)->orderBy('nombre')->get(['id', 'nombre', 'codigo']),
        ]);
    }

    public function apiDescuentos()
    {
        can('usar-proceso-facturacion-gastronomia');

        return response()->json([
            'descuentos' => DescuentoGastronomia::query()->orderBy('nombre')->get(['id', 'nombre', 'codigo', 'tipovalor', 'valor']),
        ]);
    }

    public function apiMonedas()
    {
        can('usar-proceso-facturacion-gastronomia');

        return response()->json([
            'monedas' => Moneda::query()->orderBy('nombre')->get(['id', 'nombre', 'abreviatura']),
        ]);
    }

    public function apiUsosCuentacaja()
    {
        can('usar-proceso-facturacion-gastronomia');

        if (! Schema::hasTable('usocuentacaja')) {
            return response()->json(['usos' => []]);
        }

        return response()->json([
            'usos' => Usocuentacaja::query()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function apiCuentasCaja(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $usoId = $this->usoCuentacajaGastronomiaId();
        if (! $usoId) {
            return response()->json([
                'error' => 'No está configurado el uso de cuenta de caja para gastronomía (usocuentacaja «Gastronomia» o GASTRONOMIA_USO_CUENTACAJA_ID).',
                'cuentas_caja' => [],
            ], 422);
        }

        $empresaId = (int) $cfg->empresa_id;
        $excluidas = GastronomiaCuentacajaSoloAutomaticaSupport::idsParaEmpresa($empresaId);

        $cuentas = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->whereHas('usocuentacajas', fn ($r) => $r->whereKey($usoId))
            ->when($excluidas !== [], fn ($q) => $q->whereNotIn('id', $excluidas))
            ->with('monedas:id,abreviatura,nombre')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo', 'moneda_id']);

        return response()->json([
            'usocuentacaja_id' => $usoId,
            'cuentas_caja' => $cuentas->map(function ($c) {
                $presentacion = GastronomiaCuentacajaIconoSupport::presentacion((string) $c->nombre, (string) $c->codigo);

                return [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'codigo' => $c->codigo,
                    'moneda_id' => $c->moneda_id,
                    'moneda_abreviatura' => $c->monedas->abreviatura ?? null,
                    'icono' => $presentacion['icono'],
                    'icono_color' => $presentacion['color'],
                    'etiqueta_boton' => $presentacion['etiqueta_boton'],
                ];
            })->values(),
        ]);
    }

    public function apiCuentacajaPorCodigo(Request $request, string $codigo)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $usoId = $this->usoCuentacajaGastronomiaId();
        if (! $usoId) {
            return response()->json(['error' => 'Uso de cuenta de caja gastronomía no configurado.'], 422);
        }

        $codigo = trim(urldecode($codigo));
        $variantes = array_values(array_unique(array_filter([
            $codigo,
            ltrim($codigo, '0') !== '' ? ltrim($codigo, '0') : null,
        ])));

        $query = Cuentacaja::query()
            ->whereHas('usocuentacajas', fn ($r) => $r->whereKey($usoId))
            ->whereIn('codigo', $variantes)
            ->with('monedas:id,abreviatura,nombre');

        $empresaId = (int) $cfg->empresa_id;
        if ($empresaId > 0) {
            $query->paraEmpresa($empresaId);
        }

        $cuentas = $query->get(['id', 'nombre', 'codigo', 'moneda_id', 'empresa_id']);
        $cuenta = $cuentas->first(fn ($c) => (int) $c->empresa_id === $empresaId)
            ?? $cuentas->first();

        if (! $cuenta) {
            return response()->json([
                'id' => 0,
                'error' => 'No hay cuenta de caja con uso Gastronomía para el código indicado.',
            ], 404);
        }

        if (GastronomiaCuentacajaSoloAutomaticaSupport::esSoloAutomatica(
            (int) $cuenta->id,
            (string) $cuenta->codigo,
            $empresaId,
        )) {
            return response()->json([
                'id' => 0,
                'error' => GastronomiaCuentacajaSoloAutomaticaSupport::mensajeRechazoManual($cuenta->codigo),
            ], 422);
        }

        $presentacion = GastronomiaCuentacajaIconoSupport::presentacion((string) $cuenta->nombre, (string) $cuenta->codigo);

        return response()->json([
            'id' => $cuenta->id,
            'nombre' => $cuenta->nombre,
            'codigo' => $cuenta->codigo,
            'moneda_id' => $cuenta->moneda_id,
            'moneda_abreviatura' => $cuenta->monedas->abreviatura ?? null,
            'icono' => $presentacion['icono'],
            'icono_color' => $presentacion['color'],
            'etiqueta_boton' => $presentacion['etiqueta_boton'],
        ]);
    }

    public function apiCotizacion(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $request->validate([
            'moneda_id' => 'required|integer',
            'fecha' => 'nullable|date',
            'empresa_id' => 'nullable|integer',
        ]);

        $fecha = $request->get('fecha') ?: Carbon::today()->format('Y-m-d');

        $monedaId = (int) $request->get('moneda_id');
        $empresaId = (int) ($request->get('empresa_id') ?: 1);
        $cot = CotizacionTesoreriaConsultaSupport::leeDiaria($fecha, $monedaId, $empresaId);
        $valor = (float) ($cot['cotizacionventa'] ?? 0);
        if ($valor <= 0) {
            $valor = 1.;
        }

        return response()->json([
            'cotizacion' => $valor,
            'fecha' => $fecha,
            'fecha_cotizacion' => $cot['fecha_usada'] ?? $fecha,
            'empresa_id' => $empresaId,
            'origen' => 'cotizacion_tesoreria',
        ]);
    }

    public function apiValidarEmision(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $request->validate([
            'cuenta_id' => 'required|integer',
            'moneda_id' => 'required|integer',
            'medios_pago' => 'nullable|array',
            'efectivizar' => 'nullable|boolean',
            'facturacion_con_descuento' => 'nullable|boolean',
        ]);

        $cuenta = $this->cuentaService->cuentaConLineas((int) $request->get('cuenta_id'));
        $monedaId = (int) $request->get('moneda_id');
        $mediosPago = array_values(array_filter(
            $request->input('medios_pago', []),
            fn ($m) => is_array($m) && (int) ($m['cuentacaja_id'] ?? 0) > 0
        ));

        $facturacionConDescuento = filter_var(
            $request->get('facturacion_con_descuento'),
            FILTER_VALIDATE_BOOLEAN,
        );

        $errores = $this->facturaEmisionService->erroresPreflightEmision(
            $cuenta,
            $monedaId,
            $mediosPago,
            $facturacionConDescuento,
        );

        if (filter_var($request->get('efectivizar'), FILTER_VALIDATE_BOOLEAN)) {
            $cfg = $cuenta->configuracionPuntoventa ?? $this->cuentaService->resolverConfiguracionPv();
            $errores = array_values(array_unique(array_merge(
                $errores,
                app(GastronomiaPreflightEmisionService::class)->erroresCuentacajaEfectivo($cuenta, $cfg),
            )));
        }

        $preview = $this->facturaEmisionService->previewTotalesParaCuenta($cuenta, $monedaId);

        return response()->json([
            'ok' => $errores === [],
            'errores' => $errores,
            'error' => $errores === [] ? null : implode(' ', $errores),
            'total_facturar_ars' => (float) ($preview['total'] ?? 0),
            'sin_cobranza' => ! empty($preview['sin_cobranza']),
            'factura_cortesia' => ! empty($preview['factura_cortesia']),
        ], $errores === [] ? 200 : 422);
    }

    public function apiValidarTicketTarjeta(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $request->validate([
            'codigo_barras' => 'required|string|min:7|max:64',
            'total_factura_ars' => 'required|numeric|min:0.01',
            'monto_cobranza_ya_cargado_ars' => 'nullable|numeric|min:0',
            'tickets_ya_seleccionados' => 'nullable|array',
            'tickets_ya_seleccionados.*.ticket_id' => 'required_with:tickets_ya_seleccionados|integer|min:1',
            'tickets_ya_seleccionados.*.numeroticket' => 'required_with:tickets_ya_seleccionados|integer|min:1',
        ]);

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        try {
            $resultado = $this->ticketTarjetaCanjeService->validarParaCobranza(
                (string) $request->get('codigo_barras'),
                (int) $cfg->empresa_id,
                (float) $request->get('total_factura_ars'),
                (float) $request->get('monto_cobranza_ya_cargado_ars', 0),
                array_values((array) $request->input('tickets_ya_seleccionados', [])),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'mensaje' => $e->getMessage(),
            ], 422);
        }

        return response()->json(array_merge(['ok' => true], $resultado));
    }

    public function apiValidarTicketCanjePremio(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $request->validate([
            'codigo_barras' => 'required|string|min:1|max:64',
        ]);

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        try {
            $resultado = $this->ticketCanjePremioService->validarParaAplicar(
                (string) $request->get('codigo_barras'),
                (int) $cfg->empresa_id,
                $this->listaPrecioIdDesdeCfg($cfg),
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'mensaje' => $e->getMessage(),
            ], 422);
        }

        return response()->json(array_merge(['ok' => true], $resultado));
    }

    public function apiAplicarTicketCanjePremio(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $request->validate([
            'cuenta_id' => 'required|integer|min:1',
            'codigo_barras' => 'required|string|min:1|max:64',
            'opcionales_por_articulo' => 'nullable|array',
            'comentarios_por_articulo' => 'nullable|array',
        ]);

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $cuenta = $this->cuentaService->cuentaConLineas((int) $request->get('cuenta_id'));

        try {
            $opcionalesPorArticulo = $this->normalizarOpcionalesPorArticuloDesdeRequest(
                (array) ($request->get('opcionales_por_articulo') ?? [])
            );
            $comentariosPorArticulo = $this->normalizarComentariosPorArticuloDesdeRequest(
                (array) ($request->get('comentarios_por_articulo') ?? [])
            );

            $resultado = $this->ticketCanjePremioService->aplicarACuenta(
                $cuenta,
                (string) $request->get('codigo_barras'),
                $this->listaPrecioIdDesdeCfg($cfg),
                $opcionalesPorArticulo,
                $comentariosPorArticulo,
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'mensaje' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'cuenta' => $resultado['cuenta'],
            'validacion' => $resultado['validacion'],
        ]);
    }

    public function apiValidarCanjeFidelidad(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $request->validate([
            'trackdata' => 'required|string|min:1|max:128',
            'articulo_id' => 'nullable|integer|min:1',
        ]);

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        try {
            $resultado = $this->categoriafidelidadCanjeService->validarTarjeta(
                (string) $request->get('trackdata'),
                (int) $cfg->empresa_id,
                $this->listaPrecioIdDesdeCfg($cfg),
                $request->filled('articulo_id') ? (int) $request->get('articulo_id') : null,
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->respuestaErrorCanjeFidelidad($e);
        } catch (\Throwable $e) {
            report($e);

            return $this->respuestaErrorCanjeFidelidad(
                new \RuntimeException(
                    'No se pudo validar la tarjeta. Si el problema continúa, avise a sistemas.',
                    0,
                    $e
                )
            );
        }

        return response()->json(array_merge(['ok' => true], $resultado));
    }

    public function apiAplicarCanjeFidelidad(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $request->validate([
            'cuenta_id' => 'required|integer|min:1',
            'trackdata' => 'required|string|min:1|max:128',
            'articulo_id' => 'required|integer|min:1',
            'opcionales_por_articulo' => 'nullable|array',
            'opcionales' => 'nullable|array',
            'comentarios_por_articulo' => 'nullable|array',
            'comentario_cocina' => 'nullable|string|max:255',
        ]);

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $cuenta = $this->cuentaService->cuentaConLineas((int) $request->get('cuenta_id'));

        try {
            $articuloId = (int) $request->get('articulo_id');
            $opcionalesPorArticulo = $this->normalizarOpcionalesPorArticuloDesdeRequest(
                (array) ($request->get('opcionales_por_articulo') ?? [])
            );
            $opcionalesLinea = $opcionalesPorArticulo[$articuloId]
                ?? $opcionalesPorArticulo[(string) $articuloId]
                ?? [];
            $comentariosPorArticulo = $this->normalizarComentariosPorArticuloDesdeRequest(
                (array) ($request->get('comentarios_por_articulo') ?? [])
            );
            $comentarioCocina = $comentariosPorArticulo[$articuloId]
                ?? $comentariosPorArticulo[(string) $articuloId]
                ?? \App\Support\Ventas\GastronomiaComentarioCocinaSupport::normalizar(
                    $request->input('comentario_cocina')
                );

            $resultado = $this->categoriafidelidadCanjeService->aplicarACuenta(
                $cuenta,
                (string) $request->get('trackdata'),
                $articuloId,
                $this->listaPrecioIdDesdeCfg($cfg),
                $opcionalesLinea,
                $comentarioCocina,
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->respuestaErrorCanjeFidelidad($e);
        } catch (\Throwable $e) {
            report($e);

            return $this->respuestaErrorCanjeFidelidad(
                new \RuntimeException(
                    'No se pudo aplicar el canje de fidelidad. Si el problema continúa, avise a sistemas.',
                    0,
                    $e
                )
            );
        }

        return response()->json([
            'ok' => true,
            'cuenta' => $resultado['cuenta'],
            'validacion' => $resultado['validacion'],
        ]);
    }

    public function apiListarCanjesPremioTurno(Request $request)
    {
        if (
            ! can('usar-proceso-facturacion-gastronomia', false)
            && ! can('gestionar-habilitacion-turno-gastronomia', false)
            && ! can('listar-facturas-gastronomia-dia', false)
            && ! can('listar-cierres-turno-gastronomia', false)
        ) {
            abort(403);
        }

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $fechaJornada = trim((string) $request->get('fecha_jornada', ''));
        if ($fechaJornada === '') {
            $fechaJornada = Carbon::today()->format('Y-m-d');
        }

        $desde = $request->get('desde');
        $hasta = $request->get('hasta');
        $desdeCarbon = is_string($desde) && $desde !== '' ? Carbon::parse($desde) : null;
        $hastaCarbon = is_string($hasta) && $hasta !== '' ? Carbon::parse($hasta) : null;

        $tickets = $this->ticketCanjePremioService->listarPorAlcanceTurno(
            (int) $cfg->empresa_id,
            $fechaJornada,
            GastronomiaIdentificadorPc::resolver($request),
            $desdeCarbon,
            $hastaCarbon,
        );

        return response()->json([
            'ok' => true,
            'canjes' => collect($tickets)->map(fn ($t) => [
                'id' => $t->id,
                'numerocupon' => $t->numerocupon,
                'ticket_id' => $t->ticket_id,
                'renglon' => $t->renglon,
                'sku' => $t->articulo->sku ?? '',
                'articulo' => $t->articulo->descripcion ?? '',
                'cantidad' => round((float) $t->cantidad, 4),
                'puntos' => (int) $t->puntos,
                'venta_id' => $t->venta_id,
                'venta_codigo' => $t->venta->codigo ?? '',
                'mozo' => $t->mozo->nombre ?? '',
                'apellido' => $t->apellido,
                'nombre' => $t->nombre,
                'numerodocumento' => $t->numerodocumento,
                'fechacanje' => $t->fechacanje?->format('d/m/Y H:i:s'),
            ])->values(),
        ]);
    }

    public function apiListarTicketsTarjetaTurno(Request $request)
    {
        if (
            ! can('usar-proceso-facturacion-gastronomia', false)
            && ! can('gestionar-habilitacion-turno-gastronomia', false)
            && ! can('listar-facturas-gastronomia-dia', false)
            && ! can('listar-cierres-turno-gastronomia', false)
        ) {
            abort(403);
        }

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $fechaJornada = trim((string) $request->get('fecha_jornada', ''));
        if ($fechaJornada === '') {
            $fechaJornada = Carbon::today()->format('Y-m-d');
        }

        $desde = $request->get('desde');
        $hasta = $request->get('hasta');
        $desdeCarbon = is_string($desde) && $desde !== '' ? Carbon::parse($desde) : null;
        $hastaCarbon = is_string($hasta) && $hasta !== '' ? Carbon::parse($hasta) : null;

        $tickets = $this->ticketTarjetaCanjeService->listarPorAlcanceTurno(
            (int) $cfg->empresa_id,
            $fechaJornada,
            GastronomiaIdentificadorPc::resolver($request),
            $desdeCarbon,
            $hastaCarbon,
        );

        return response()->json([
            'ok' => true,
            'tickets' => collect($tickets)->map(fn ($t) => [
                'id' => $t->id,
                'ticket_id' => $t->ticket_id,
                'numeroticket' => $t->numeroticket,
                'numerodocumento' => $t->numerodocumento,
                'fecha_emision' => $t->fecha?->format('d/m/Y'),
                'monto' => round((float) $t->monto, 2),
                'montoticket' => round((float) $t->montoticket, 2),
                'numerocupon' => $t->numerocupon,
                'venta_id' => $t->venta_id,
                'venta_codigo' => $t->venta->codigo ?? '',
                'created_at' => $t->created_at?->format('d/m/Y H:i:s'),
            ])->values(),
        ]);
    }

    public function apiListarInvitacionesTurno(Request $request)
    {
        if (
            ! can('usar-proceso-facturacion-gastronomia', false)
            && ! can('gestionar-habilitacion-turno-gastronomia', false)
            && ! can('listar-facturas-gastronomia-dia', false)
            && ! can('listar-cierres-turno-gastronomia', false)
        ) {
            abort(403);
        }

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $fechaJornada = trim((string) $request->get('fecha_jornada', ''));
        if ($fechaJornada === '') {
            $fechaJornada = Carbon::today()->format('Y-m-d');
        }

        $desde = $request->get('desde');
        $hasta = $request->get('hasta');
        $desdeCarbon = is_string($desde) && $desde !== '' ? Carbon::parse($desde) : null;
        $hastaCarbon = is_string($hasta) && $hasta !== '' ? Carbon::parse($hasta) : null;

        $facturas = GastronomiaTurnoOperativoTotalesSupport::invitacionesDelTurno(
            GastronomiaIdentificadorPc::resolver($request),
            (int) $cfg->empresa_id,
            $fechaJornada,
            $desdeCarbon,
            $hastaCarbon,
        );

        return response()->json([
            'ok' => true,
            'facturas' => $facturas,
            'cantidad' => count($facturas),
            'total' => round(array_sum(array_map(
                fn (array $f) => (float) ($f['total_facturado'] ?? 0),
                $facturas
            )), 2),
            'url_factura_ver_base' => can('ver-factura-gastronomia', false)
                ? url('ventas/gastronomia/facturas-dia')
                : null,
        ]);
    }

    public function apiEmitirFactura(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $request->validate([
            'cuenta_id' => 'required|integer',
            'moneda_id' => 'required|integer',
            'actividad_arca_id' => 'nullable|integer',
            'medios_pago' => 'nullable|array',
            'medios_pago.*.cuentacaja_id' => 'required_with:medios_pago|integer|min:1',
            'medios_pago.*.moneda_id' => 'required_with:medios_pago|integer|min:1',
            'medios_pago.*.monto' => 'required_with:medios_pago|numeric|min:0.01',
            'medios_pago.*.cotizacion' => 'nullable|numeric|min:0.0001',
            'medios_pago.*.ticket_id' => 'nullable|integer|min:1',
            'medios_pago.*.numeroticket' => 'nullable|integer|min:1',
            'facturacion_con_descuento' => 'nullable|boolean',
        ]);

        $cuenta = $this->cuentaService->cuentaConLineas((int) $request->get('cuenta_id'));

        $mediosPago = array_values(array_filter(
            $request->input('medios_pago', []),
            fn ($m) => is_array($m) && (int) ($m['cuentacaja_id'] ?? 0) > 0
        ));

        $facturacionConDescuento = filter_var(
            $request->get('facturacion_con_descuento'),
            FILTER_VALIDATE_BOOLEAN,
        );

        $res = $this->facturaEmisionService->emitirFacturaDesdeCuenta(
            $cuenta,
            (int) $request->get('moneda_id'),
            $request->get('actividad_arca_id') ? (int) $request->get('actividad_arca_id') : null,
            filter_var($request->get('forzar_caea'), FILTER_VALIDATE_BOOLEAN),
            $mediosPago,
            false,
            $facturacionConDescuento,
        );

        if (isset($res['error'])) {
            $mensaje = trim((string) ($res['mensaje'] ?? $res['error']));

            return response()->json([
                'error' => $mensaje,
                'mensaje' => $mensaje,
            ], 422);
        }

        return response()->json($res);
    }

    private function cfgPv(Request $request): ?ConfiguracionPuntoventaGastronomia
    {
        return $this->cuentaService->resolverConfiguracionPv($request);
    }

    /**
     * @return ConfiguracionPuntoventaGastronomia|\Illuminate\Http\JsonResponse
     */
    private function requireCfgPv(Request $request)
    {
        try {
            $cfg = $this->cuentaService->resolverConfiguracionPv($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'mensaje' => $e->getMessage(),
                'identificador_pc' => GastronomiaIdentificadorPc::resolver($request),
            ], 422);
        }

        if (! $cfg) {
            $pc = GastronomiaIdentificadorPc::resolver($request);

            return response()->json([
                'error' => 'Sin configuración PV gastronomía para identificador_pc '.$pc,
                'mensaje' => 'Sin configuración PV gastronomía para identificador_pc '.$pc,
                'identificador_pc' => $pc,
            ], 422);
        }

        return $cfg;
    }

    /**
     * @return array{id:int,nombre:string,codigo:string,moneda_id:int,moneda_abreviatura:?string}|null
     */
    private function resolverCuentacajaEfectivo(ConfiguracionPuntoventaGastronomia $cfg): ?array
    {
        return GastronomiaCuentacajaEfectivo::cuentaParaEmpresa((int) $cfg->empresa_id);
    }

    private function usoCuentacajaGastronomiaId(): ?int
    {
        $configured = config('gastronomia.usocuentacaja_id');
        if ($configured !== null && $configured !== '') {
            return (int) $configured;
        }

        if (! Schema::hasTable('usocuentacaja')) {
            return null;
        }

        $id = Usocuentacaja::query()->where('nombre', 'Gastronomia')->value('id');

        return $id ? (int) $id : null;
    }

    private function leerPreferenciaModoSeleccion(?ConfiguracionPuntoventaGastronomia $cfg = null): ?string
    {
        $modo = Cache::get(generaKey('gastronomia-modo-seleccion'));

        $modosValidos = ['mesa', 'cuenta'];
        if ($cfg !== null && $cfg->waitryHabilitadoEnTerminal()) {
            $modosValidos[] = 'waitry';
        }

        return in_array($modo, $modosValidos, true) ? $modo : null;
    }

    private function listaPrecioIdDesdeCfg(ConfiguracionPuntoventaGastronomia $cfg): int
    {
        return (int) ($cfg->listaprecio_id ?? 1);
    }

    private function fechaVigenciaListaPrecioDesdeCfg(?ConfiguracionPuntoventaGastronomia $cfg): string
    {
        $empresaId = $cfg ? (int) $cfg->empresa_id : 0;

        return $this->jornadaService->fechaVigenciaListaPrecio($empresaId);
    }

    private function respuestaErrorCanjeFidelidad(\Throwable $e): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => $e->getMessage(),
            'mensaje' => $e->getMessage(),
        ], 422);
    }

    private function resolverPrecioLista(int $articuloId): float
    {
        $cfg = $this->cuentaService->resolverConfiguracionPv();
        $listaId = $cfg ? $this->listaPrecioIdDesdeCfg($cfg) : 1;
        $precios = PrecioService::asignaPrecioPorLista(
            $articuloId,
            $listaId,
            $this->fechaVigenciaListaPrecioDesdeCfg($cfg),
        );

        if ($precios === []) {
            return 0.;
        }

        $p = end($precios);

        return (float) ($p['precio'] ?? 0);
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @return array<int, array<string, int|string>>
     */
    private function normalizarOpcionalesPorArticuloDesdeRequest(array $raw): array
    {
        $out = [];
        foreach ($raw as $articuloId => $mapa) {
            if (! is_array($mapa)) {
                continue;
            }
            $norm = \App\Support\Ventas\GastronomiaFormulaOpcionalSeleccion::normalizarMapaDesdeRequest($mapa);
            if ($norm !== []) {
                $out[(int) $articuloId] = $norm;
            }
        }

        return $out;
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @return array<int, string>
     */
    private function normalizarComentariosPorArticuloDesdeRequest(array $raw): array
    {
        $out = [];
        foreach ($raw as $articuloId => $texto) {
            $norm = \App\Support\Ventas\GastronomiaComentarioCocinaSupport::normalizar(
                is_string($texto) ? $texto : null
            );
            if ($norm !== null) {
                $out[(int) $articuloId] = $norm;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $errores
     * @return list<string>
     */
    private function extraerSkusOpcionalesPendientesDesdeErroresWaitry(array $errores): array
    {
        $skus = [];
        foreach ($errores as $err) {
            $err = trim((string) $err);
            if ($err === '') {
                continue;
            }
            $esOpcional = str_contains($err, 'requiere opcionales de fórmula')
                || str_contains($err, 'modal de opcionales')
                || str_contains($err, 'Debe seleccionar opcional');
            if (! $esOpcional) {
                continue;
            }
            if (preg_match('/SKU «([^»]+)»/u', $err, $m)) {
                $skus[] = $m[1];
            } elseif (preg_match('/^([A-Za-z0-9]+):\s*Debe seleccionar opcional/u', $err, $m)) {
                $skus[] = $m[1];
            }
        }

        return array_values(array_unique($skus));
    }
}
