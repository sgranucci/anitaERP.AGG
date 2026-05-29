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
use App\Services\Configuracion\CotizacionService;
use App\Services\Ventas\Gastronomia\GastronomiaCobranzaService;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Services\Ventas\Gastronomia\GastronomiaFacturaEmisionService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Services\Ventas\Gastronomia\GastronomiaTicketTarjetaCanjeService;
use App\Services\Ventas\Gastronomia\GastronomiaCategoriafidelidadCanjeService;
use App\Services\Ventas\Gastronomia\GastronomiaTicketCanjePremioService;
use App\Services\Ventas\Gastronomia\GastronomiaPreflightEmisionService;
use App\Services\Ventas\Gastronomia\GastronomiaEmisionDiagnosticoService;
use App\Services\Ventas\Gastronomia\GastronomiaFormulaOpcionalesService;
use App\Services\Ventas\Gastronomia\GastronomiaTurnoOperativoService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryOrdenesExternasService;
use App\Services\Stock\PrecioService;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Support\Ventas\GastronomiaCuentacajaCanjeTarjeta;
use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Support\Ventas\GastronomiaIdentificadorPc;
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
                .'Candidato principal: buscaUltimoNumeroComprobante (2 round-trips al bridge).';
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

        $cfg?->loadMissing('tipotransaccion');
        $tt = $cfg?->tipotransaccion;
        $tipoFacturaId = (int) ($cfg?->tipotransaccion_id ?? 0) ?: (int) config('gastronomia.tipotransaccion_factura_id', 0);
        $cfgTipotransaccionNombre = $tt
            ? trim($tt->abreviatura.' — '.$tt->nombre)
            : ($tipoFacturaId > 0 ? 'ID '.$tipoFacturaId.' (solo env)' : null);

        return view('ventas.gastronomia.proceso_facturacion.index', [
            'empresa_id' => $empresaId,
            'empresa_nombre' => $cfg?->empresa?->nombre,
            'cfg_tipotransaccion_nombre' => $cfgTipotransaccionNombre,
            'prefijo_sku' => (string) config('gastronomia.sku_catalogo_prefijo', 'V'),
            'sku_catalogo_digitos_sufijo' => max(0, (int) config('gastronomia.sku_catalogo_digitos_sufijo', 0)),
            'tiene_cfg_pv' => $cfg !== null,
            'ubicacion_id' => $cfg?->ubicacion_id,
            'salida_factura_id' => $cfg?->salida_factura_id,
            'identificador_pc_actual' => GastronomiaIdentificadorPc::resolver($request),
            'usocuentacaja_gastronomia_id' => $this->usoCuentacajaGastronomiaId(),
            'wsfe_receptor_cf_umbral_monto' => (float) config('arca_wsfe.receptor.consumidor_final_umbral_monto', 0),
            'wsfe_forzar_modo_caea' => filter_var(config('arca_wsfe.emision.forzar_modo_caea'), FILTER_VALIDATE_BOOLEAN),
            'modo_seleccion_preferido' => $this->leerPreferenciaModoSeleccion() ?? 'mesa',
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
            'modo_seleccion_preferido' => $this->leerPreferenciaModoSeleccion() ?? 'mesa',
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
            'waitry_habilitado' => config('waitry.habilitado', false),
            'waitry_get_orders_minutos_atras' => max(0, (int) config('waitry.get_orders_minutos_atras', 20)),
            'waitry_get_orders_cache_segundos' => max(0, (int) config('waitry.get_orders_cache_segundos', 15)),
        ]);
    }

    public function apiWaitryOrdenesPendientes(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        if (! config('waitry.habilitado', false)) {
            return response()->json(['ok' => false, 'error' => 'Integración Waitry deshabilitada.'], 422);
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

        if (! config('waitry.habilitado', false)) {
            return response()->json(['ok' => false, 'error' => 'Integración Waitry deshabilitada.'], 422);
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
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'cuenta_id' => $resultado['cuenta']->id,
            'cuenta' => $resultado['cuenta'],
            'errores' => $resultado['errores'] ?? [],
            'mensaje' => 'Cuenta Waitry «'.$identificadorPapelito.'» importada correctamente.',
            'warn' => ($resultado['errores'] ?? []) !== []
                ? 'Importación parcial: algunos ítems no se cargaron (ver detalle).'
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

        $modoRaw = $request->input('modo');
        $modo = is_string($modoRaw) ? trim($modoRaw) : '';
        if ($modo === 'cuenta' && ! config('gastronomia.cuentas_libres_habilitadas', true)) {
            return response()->json(['ok' => false, 'message' => 'Las cuentas libres no están habilitadas.'], 422);
        }
        $modosValidos = ['mesa', 'cuenta'];
        if (config('waitry.habilitado', false)) {
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

        $cuenta = $this->cuentaService->cuentaConLineas((int) $id);

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

        $opcionales = [];
        foreach (($request->get('opcionales') ?? []) as $k => $v) {
            $opcionales[(string) $k] = $v !== null ? (int) $v : null;
        }

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
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'linea' => $linea->load('articulo'),
            'cuenta' => $this->cuentaService->cuentaConLineas($cuenta->id),
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

    public function apiCerrarCuenta(int $id)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cuenta = $this->cuentaService->cuentaConLineas($id);

        try {
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

        $out = [];
        foreach ($articulos as $a) {
            $precios = PrecioService::asignaPrecioPorLista((int) $a->id, $listaId, Carbon::today()->format('Y-m-d'));
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
        $precios = PrecioService::asignaPrecioPorLista((int) $a->id, $listaId, Carbon::today()->format('Y-m-d'));
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
            'cantidad' => 'required|numeric|min:0.0001',
        ]);

        $linea = CuentaGastronomiaLinea::query()
            ->where('cuenta_gastronomia_id', $cuentaId)
            ->where('id', $lineaId)
            ->firstOrFail();

        try {
            $cuenta = $this->cuentaService->actualizarCantidadLinea($linea, (float) $request->get('cantidad'));
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

        $cuentas = Cuentacaja::query()
            ->paraEmpresa((int) $cfg->empresa_id)
            ->whereHas('usocuentacajas', fn ($r) => $r->whereKey($usoId))
            ->with('monedas:id,abreviatura,nombre')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo', 'moneda_id']);

        return response()->json([
            'usocuentacaja_id' => $usoId,
            'cuentas_caja' => $cuentas->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'codigo' => $c->codigo,
                'moneda_id' => $c->moneda_id,
                'moneda_abreviatura' => $c->monedas->abreviatura ?? null,
            ])->values(),
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

        return response()->json([
            'id' => $cuenta->id,
            'nombre' => $cuenta->nombre,
            'codigo' => $cuenta->codigo,
            'moneda_id' => $cuenta->moneda_id,
            'moneda_abreviatura' => $cuenta->monedas->abreviatura ?? null,
        ]);
    }

    public function apiCotizacion(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $request->validate([
            'moneda_id' => 'required|integer',
            'fecha' => 'nullable|date',
        ]);

        $fecha = $request->get('fecha') ?: Carbon::today()->format('Y-m-d');

        /** @var CotizacionService $svc */
        $svc = app(CotizacionService::class);
        $cot = $svc->leeCotizacionDiaria($fecha, (int) $request->get('moneda_id'));
        $valor = 0.;
        if ($cot && isset($cot['cotizacionventa'])) {
            $valor = (float) $cot['cotizacionventa'];
        }

        if ($valor <= 0) {
            $valor = 1.;
        }

        return response()->json(['cotizacion' => $valor, 'fecha' => $fecha]);
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

        return response()->json([
            'ok' => $errores === [],
            'errores' => $errores,
            'error' => $errores === [] ? null : implode(' ', $errores),
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
        ]);

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $cuenta = $this->cuentaService->cuentaConLineas((int) $request->get('cuenta_id'));

        try {
            $resultado = $this->ticketCanjePremioService->aplicarACuenta(
                $cuenta,
                (string) $request->get('codigo_barras'),
                $this->listaPrecioIdDesdeCfg($cfg),
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
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'mensaje' => $e->getMessage(),
            ], 422);
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
        ]);

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $cuenta = $this->cuentaService->cuentaConLineas((int) $request->get('cuenta_id'));

        try {
            $resultado = $this->categoriafidelidadCanjeService->aplicarACuenta(
                $cuenta,
                (string) $request->get('trackdata'),
                (int) $request->get('articulo_id'),
                $this->listaPrecioIdDesdeCfg($cfg),
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

    private function leerPreferenciaModoSeleccion(): ?string
    {
        $modo = Cache::get(generaKey('gastronomia-modo-seleccion'));

        $modosValidos = ['mesa', 'cuenta'];
        if (config('waitry.habilitado', false)) {
            $modosValidos[] = 'waitry';
        }

        return in_array($modo, $modosValidos, true) ? $modo : null;
    }

    private function listaPrecioIdDesdeCfg(ConfiguracionPuntoventaGastronomia $cfg): int
    {
        return (int) ($cfg->listaprecio_id ?? 1);
    }

    private function resolverPrecioLista(int $articuloId): float
    {
        $cfg = $this->cuentaService->resolverConfiguracionPv();
        $listaId = $cfg ? $this->listaPrecioIdDesdeCfg($cfg) : 1;
        $precios = PrecioService::asignaPrecioPorLista($articuloId, $listaId, Carbon::today()->format('Y-m-d'));

        if ($precios === []) {
            return 0.;
        }

        $p = end($precios);

        return (float) ($p['precio'] ?? 0);
    }
}
