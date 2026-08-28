<?php

namespace App\Http\Controllers\Caja\Estacionamiento;

use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Estacionamiento\CategoriaAutomovil;
use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Caja\Estacionamiento\CuentaEstacionamientoLinea;
use App\Models\Caja\Estacionamiento\DescuentoEstacionamiento;
use App\Models\Caja\Estacionamiento\ListaPrecioEstacionamiento;
use App\Models\Caja\Usocuentacaja;
use App\Models\Configuracion\Moneda;
use App\Repositories\Caja\Estacionamiento\DescuentoEstacionamientoRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\Services\Caja\Estacionamiento\EstacionamientoCuentaService;
use App\Services\Caja\Estacionamiento\EstacionamientoFacturaEmisionService;
use App\Services\Caja\Estacionamiento\EstacionamientoPvService;
use App\Services\Caja\Estacionamiento\EstacionamientoTurnoOperativoService;
use App\Services\Caja\Estacionamiento\JornadaEstacionamientoService;
use App\Support\Caja\CotizacionTesoreriaConsultaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use App\Support\Caja\Estacionamiento\EstacionamientoCuentacajaEfectivo;
use App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc;
use App\Support\Caja\Estacionamiento\EstacionamientoItemCatalogoSupport;
use App\Support\Ventas\GastronomiaCuentacajaIconoSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class EstacionamientoProcesoFacturacionController extends Controller
{
    public function __construct(
        private readonly EstacionamientoCuentaService $cuentaService,
        private readonly EstacionamientoFacturaEmisionService $facturaEmisionService,
        private readonly EstacionamientoPvService $pvService,
        private readonly JornadaEstacionamientoService $jornadaService,
        private readonly EstacionamientoTurnoOperativoService $turnoOperativoService,
        private readonly DescuentoEstacionamientoRepositoryInterface $descuentoRepository,
        private readonly ClienteRepositoryInterface $clienteRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $cfg = $this->cfgPv($request);
        $empresaId = $cfg ? (int) $cfg->empresa_id : null;
        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $clienteDescuento = $this->resolverClienteDescuentoPrefijado();

        $cfg?->loadMissing(['tipotransaccion', 'empresa']);
        $tt = $cfg?->tipotransaccion;
        $tipoFacturaId = (int) ($cfg?->tipotransaccion_id ?? 0) ?: (int) config('estacionamiento.tipotransaccion_factura_id', 0);
        $cfgTipotransaccionNombre = $tt
            ? trim($tt->abreviatura.' — '.$tt->nombre)
            : ($tipoFacturaId > 0 ? 'ID '.$tipoFacturaId.' (solo env)' : null);

        return view('caja.estacionamiento.proceso_facturacion.index', [
            'cfg' => $cfg,
            'empresa_id' => $empresaId,
            'empresa_nombre' => $cfg?->empresa?->nombre,
            'cfg_tipotransaccion_nombre' => $cfgTipotransaccionNombre,
            'tiene_cfg_pv' => $cfg !== null,
            'salida_factura_id' => $cfg?->salida_factura_id,
            'identificador_pc_actual' => $pc,
            'usocuentacaja_estacionamiento_id' => $this->usoCuentacajaEstacionamientoId(),
            'cliente_descuento_codigo' => $clienteDescuento['codigo'],
            'cliente_descuento' => $clienteDescuento['cliente'],
            'wsfe_receptor_cf_umbral_monto' => ParametroSistemaSupport::topeConsumidorFinal(),
            'wsfe_forzar_modo_caea' => \App\Support\Ventas\ArcaWsfeEmisionResiliencia::forzarModoCaea(),
            'wsfe_failover_automatico' => \App\Support\Ventas\ArcaWsfeEmisionResiliencia::failoverAutomaticoActivo(),
            'jornada' => $empresaId > 0
                ? $this->jornadaService->estadoParaEmpresa($empresaId)
                : null,
            'jornada_obligatoria' => (bool) config('estacionamiento.jornada_obligatoria', true),
            'requiere_habilitacion_turno' => EstacionamientoTurnoOperativoService::requiereHabilitacionTurno(),
            'turno_operativo' => $cfg && $empresaId > 0
                ? $this->turnoOperativoService->estadoParaTerminal($cfg, $pc)
                : null,
            'url_habilitacion_turno' => route('estacionamiento_habilitacion_turno'),
        ]);
    }

    public function apiConfig(Request $request)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            $body = $cfg->getData(true);
            $body['ok'] = false;

            return response()->json($body, 422);
        }

        $cfg->loadMissing(['tipotransaccion', 'empresa']);
        $clienteDescuento = $this->resolverClienteDescuentoPrefijado();

        return response()->json([
            'ok' => true,
            'empresa_id' => (int) $cfg->empresa_id,
            'empresa_nombre' => $cfg->empresa->nombre ?? null,
            'identificador_pc' => EstacionamientoIdentificadorPc::resolver($request),
            'puntoventa_cae_id' => $cfg->puntoventa_cae_id,
            'puntoventa_caea_id' => $cfg->puntoventa_caea_id,
            'salida_factura_id' => $cfg->salida_factura_id,
            'tipotransaccion_id' => $cfg->tipotransaccion_id,
            'tipotransaccion_nombre' => $cfg->tipotransaccion
                ? trim($cfg->tipotransaccion->abreviatura.' — '.$cfg->tipotransaccion->nombre)
                : null,
            'usocuentacaja_estacionamiento_id' => $this->usoCuentacajaEstacionamientoId(),
            'cuentacaja_efectivo' => EstacionamientoCuentacajaEfectivo::cuentaParaEmpresa((int) $cfg->empresa_id),
            'cuentacaja_efectivo_id' => EstacionamientoCuentacajaEfectivo::idParaEmpresa((int) $cfg->empresa_id),
            'cuentacaja_efectivo_error' => EstacionamientoCuentacajaEfectivo::mensajeErrorResolucion((int) $cfg->empresa_id),
            'cliente_descuento_codigo' => $clienteDescuento['codigo'],
            'cliente_descuento' => $clienteDescuento['cliente'],
            'receptor_cf_nombre' => trim((string) config('arca_wsfe.receptor.consumidor_final_razon_social', 'CONSUMIDOR FINAL')),
            'moneda_factura_id' => (int) config('estacionamiento.moneda_factura_id', 1),
            'jornada' => $this->jornadaService->estadoParaEmpresa((int) $cfg->empresa_id),
            'jornada_obligatoria' => (bool) config('estacionamiento.jornada_obligatoria', true),
            'requiere_habilitacion_turno' => EstacionamientoTurnoOperativoService::requiereHabilitacionTurno(),
            'turno_operativo' => $this->turnoOperativoService->estadoParaTerminal(
                $cfg,
                EstacionamientoIdentificadorPc::resolver($request),
            ),
            'url_habilitacion_turno' => route('estacionamiento_habilitacion_turno'),
        ]);
    }

    public function apiCuentaActiva(Request $request)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        try {
            $cuenta = $this->cuentaService->obtenerOCrearCuentaActiva($cfg, $request);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'cuenta' => $cuenta,
            'cuenta_id' => $cuenta->id,
        ]);
    }

    public function apiCategorias(Request $request)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $empresaId = (int) $cfg->empresa_id;
        $categoriaIds = ListaPrecioEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->pluck('categoria_automovil_id')
            ->unique()
            ->filter(fn ($id) => (int) $id > 0)
            ->values()
            ->all();

        $categorias = CategoriaAutomovil::query()
            ->when($categoriaIds !== [], fn ($q) => $q->whereIn('id', $categoriaIds))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return response()->json([
            'categorias' => $categorias,
            'empresa_id' => $empresaId,
        ]);
    }

    public function apiItemsCatalogo(Request $request)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $request->validate([
            'categoria_id' => 'required|integer|min:1',
        ]);

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $categoriaId = (int) $request->get('categoria_id');
        $termino = trim((string) $request->get('q', ''));

        return response()->json([
            'items' => EstacionamientoItemCatalogoSupport::itemsActivosConPrecios(
                (int) $cfg->empresa_id,
                $categoriaId,
                $termino !== '' ? $termino : null,
            ),
            'categoria_id' => $categoriaId,
        ]);
    }

    public function apiItemPorId(Request $request, int $id)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $request->validate([
            'categoria_id' => 'required|integer|min:1',
        ]);

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $item = EstacionamientoItemCatalogoSupport::itemConPrecio(
            (int) $cfg->empresa_id,
            (int) $request->get('categoria_id'),
            $id,
        );

        if (! $item) {
            return response()->json(['error' => 'Ítem no encontrado o inactivo'], 404);
        }

        return response()->json(['item' => $item]);
    }

    public function apiDescuentos()
    {
        can('usar-proceso-facturacion-estacionamiento');

        return response()->json([
            'descuentos' => DescuentoEstacionamiento::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo', 'tipovalor', 'valor', 'cliente_id']),
        ]);
    }

    public function apiDescuentoPorCodigo(string $codigo)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $descuento = $this->descuentoRepository->findPorCodigo(urldecode($codigo));
        if (! $descuento) {
            return response()->json(['error' => 'Descuento no encontrado'], 404);
        }

        $cli = $descuento->cliente;

        return response()->json([
            'id' => $descuento->id,
            'codigo' => $descuento->codigo,
            'nombre' => $descuento->nombre,
            'tipovalor' => $descuento->tipovalor,
            'valor' => (float) $descuento->valor,
            'cliente_id' => $descuento->cliente_id,
            'cliente' => $cli ? [
                'id' => $cli->id,
                'codigo' => $cli->codigo,
                'nombre' => $cli->nombre,
            ] : null,
        ]);
    }

    public function apiCuentasCaja(Request $request)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $usoId = $this->usoCuentacajaEstacionamientoId();
        if (! $usoId) {
            return response()->json([
                'error' => 'No está configurado el uso de cuenta de caja para estacionamiento (usocuentacaja «Estacionamiento» o ESTACIONAMIENTO_USO_CUENTACAJA_ID).',
                'cuentas_caja' => [],
            ], 422);
        }

        $empresaId = (int) $cfg->empresa_id;
        $cuentas = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->whereHas('usocuentacajas', fn ($r) => $r->whereKey($usoId))
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
        can('usar-proceso-facturacion-estacionamiento');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $usoId = $this->usoCuentacajaEstacionamientoId();
        if (! $usoId) {
            return response()->json(['error' => 'Uso de cuenta de caja estacionamiento no configurado.'], 422);
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
                'error' => 'No hay cuenta de caja con uso Estacionamiento para el código indicado.',
            ], 404);
        }

        $presentacion = GastronomiaCuentacajaIconoSupport::presentacion(
            (string) $cuenta->nombre,
            (string) $cuenta->codigo,
        );

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

    public function apiMonedas()
    {
        can('usar-proceso-facturacion-estacionamiento');

        return response()->json([
            'monedas' => Moneda::query()->orderBy('nombre')->get(['id', 'nombre', 'abreviatura']),
        ]);
    }

    public function apiCotizacion(Request $request)
    {
        can('usar-proceso-facturacion-estacionamiento');

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

    public function apiActualizarCuenta(Request $request, int $id)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $cuenta = $this->cuentaService->cuentaConLineasSinEnriquecer($id);

        try {
            $cuenta = $this->cuentaService->actualizarCabecera($cuenta, $request->all());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'cuenta' => $this->cuentaService->enriquecerCuentaParaApi($cuenta),
        ]);
    }

    public function apiAgregarLinea(Request $request, int $id)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $request->validate([
            'item_estacionamiento_id' => 'required|integer|min:1',
            'cantidad' => 'nullable|numeric|min:0.0001',
        ]);

        $cuenta = $this->cuentaService->cuentaConLineas($id);

        try {
            $linea = $this->cuentaService->agregarLinea(
                $cuenta,
                (int) $request->get('item_estacionamiento_id'),
                (float) ($request->input('cantidad', 1)),
            );
            $cuentaActualizada = $this->cuentaService->cuentaConLineas($cuenta->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'linea' => $linea->load(['itemEstacionamiento']),
            'cuenta' => $this->cuentaService->enriquecerCuentaParaApi($cuentaActualizada),
        ]);
    }

    public function apiEliminarLinea(int $cuentaId, int $lineaId)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $linea = CuentaEstacionamientoLinea::query()
            ->where('cuenta_estacionamiento_id', $cuentaId)
            ->where('id', $lineaId)
            ->firstOrFail();

        try {
            $this->cuentaService->eliminarLinea($linea);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'cuenta' => $this->cuentaService->enriquecerCuentaParaApi(
                $this->cuentaService->cuentaConLineas($cuentaId)
            ),
        ]);
    }

    public function apiActualizarCantidadLinea(Request $request, int $cuentaId, int $lineaId)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $request->validate([
            'cantidad' => 'required|numeric|min:0.0001',
        ]);

        $linea = CuentaEstacionamientoLinea::query()
            ->where('cuenta_estacionamiento_id', $cuentaId)
            ->where('id', $lineaId)
            ->firstOrFail();

        try {
            $cuenta = $this->cuentaService->actualizarCantidadLinea($linea, (float) $request->get('cantidad'));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'cuenta' => $this->cuentaService->enriquecerCuentaParaApi($cuenta),
        ]);
    }

    public function apiCerrarCuenta(int $id)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $cuenta = $this->cuentaService->cuentaConLineas($id);

        try {
            $this->cuentaService->cerrarSinFacturar($cuenta);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function apiValidarEmision(Request $request)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $request->validate([
            'cuenta_id' => 'required|integer',
            'moneda_id' => 'required|integer',
            'medios_pago' => 'nullable|array',
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

    public function apiEmitirFactura(Request $request)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $request->validate([
            'cuenta_id' => 'required|integer',
            'moneda_id' => 'required|integer',
            'actividad_arca_id' => 'nullable|integer',
            'medios_pago' => 'nullable|array',
            'medios_pago.*.cuentacaja_id' => 'required_with:medios_pago|integer|min:1',
            'medios_pago.*.moneda_id' => 'required_with:medios_pago|integer|min:1',
            'medios_pago.*.monto' => 'required_with:medios_pago|numeric|min:0.01',
            'medios_pago.*.cotizacion' => 'nullable|numeric|min:0.0001',
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

        try {
            $res = $this->facturaEmisionService->emitirFacturaDesdeCuenta(
                $cuenta,
                (int) $request->get('moneda_id'),
                $request->get('actividad_arca_id') ? (int) $request->get('actividad_arca_id') : null,
                filter_var($request->get('forzar_caea'), FILTER_VALIDATE_BOOLEAN),
                $mediosPago,
                false,
                $facturacionConDescuento,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'mensaje' => $e->getMessage(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'mensaje' => $e->getMessage(),
            ], 422);
        }

        if (isset($res['error'])) {
            $mensaje = trim((string) ($res['mensaje'] ?? $res['error']));

            return response()->json([
                'error' => $mensaje,
                'mensaje' => $mensaje,
            ], 422);
        }

        return response()->json($res);
    }

    private function cfgPv(Request $request): ?ConfiguracionPuntoventaEstacionamiento
    {
        return $this->pvService->resolverConfiguracionPv($request);
    }

    /**
     * @return ConfiguracionPuntoventaEstacionamiento|\Illuminate\Http\JsonResponse
     */
    private function requireCfgPv(Request $request)
    {
        try {
            $cfg = $this->pvService->resolverConfiguracionPv($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'mensaje' => $e->getMessage(),
                'identificador_pc' => EstacionamientoIdentificadorPc::resolver($request),
            ], 422);
        }

        if (! $cfg) {
            $pc = EstacionamientoIdentificadorPc::resolver($request);

            return response()->json([
                'error' => 'Sin configuración PV estacionamiento para identificador_pc '.$pc,
                'mensaje' => 'Sin configuración PV estacionamiento para identificador_pc '.$pc,
                'identificador_pc' => $pc,
            ], 422);
        }

        return $cfg;
    }

    private function usoCuentacajaEstacionamientoId(): ?int
    {
        $configured = config('estacionamiento.usocuentacaja_id');
        if ($configured !== null && $configured !== '') {
            return (int) $configured;
        }

        if (! Schema::hasTable('usocuentacaja')) {
            return null;
        }

        $id = Usocuentacaja::query()->where('nombre', 'Estacionamiento')->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * @return array{codigo: string, cliente: ?array{id:int,codigo:mixed,nombre:string}}
     */
    private function resolverClienteDescuentoPrefijado(): array
    {
        $codigo = trim((string) config('estacionamiento.cliente_descuento_codigo', '501'));
        $cliente = $codigo !== '' ? $this->clienteRepository->findPorCodigo($codigo) : null;

        return [
            'codigo' => $codigo,
            'cliente' => $cliente ? [
                'id' => (int) $cliente->id,
                'codigo' => $cliente->codigo,
                'nombre' => (string) ($cliente->nombre ?? ''),
            ] : null,
        ];
    }
}
