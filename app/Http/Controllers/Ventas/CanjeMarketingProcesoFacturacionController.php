<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\CuentaGastronomiaLinea;
use App\Models\Ventas\DescuentoGastronomia;
use App\Models\Ventas\MozoGastronomia;
use App\Repositories\Ventas\ClienteVipGastronomiaRepositoryInterface;
use App\Repositories\Ventas\MozoGastronomiaRepositoryInterface;
use App\Services\Stock\PrecioService;
use App\Services\Ventas\Gastronomia\CanjeMarketing\CanjeMarketingCuentaService;
use App\Services\Ventas\Gastronomia\CanjeMarketing\CanjeMarketingFacturaEmisionService;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Services\Ventas\Gastronomia\GastronomiaFormulaOpcionalesService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CanjeMarketingProcesoFacturacionController extends Controller
{
    public function __construct(
        private readonly CanjeMarketingCuentaService $canjeMarketingCuentaService,
        private readonly CanjeMarketingFacturaEmisionService $canjeMarketingEmisionService,
        private readonly GastronomiaCuentaService $cuentaService,
        private readonly GastronomiaFormulaOpcionalesService $opcionalesService,
        private readonly GastronomiaJornadaService $jornadaService,
        private readonly ClienteVipGastronomiaRepositoryInterface $clienteVipRepository,
        private readonly MozoGastronomiaRepositoryInterface $mozoGastronomiaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('usar-facturador-canje-marketing');

        $cfg = $this->cfgPv($request);
        $empresaId = $cfg ? (int) $cfg->empresa_id : null;
        $pc = GastronomiaIdentificadorPc::resolver($request);

        $cfg?->loadMissing(['tipotransaccion', 'empresa', 'listaprecio']);

        return view('ventas.gastronomia.canjes.proceso_facturacion.index', [
            'cfg' => $cfg,
            'empresa_id' => $empresaId,
            'empresa_nombre' => $cfg?->empresa?->nombre,
            'tiene_cfg_pv' => $cfg !== null,
            'identificador_pc_actual' => $pc,
            'descuento_codigo' => trim((string) config('gastronomia.canje_marketing_descuento_codigo', '40')),
            'jornada' => $empresaId > 0 ? $this->jornadaService->estadoParaEmpresa($empresaId) : null,
            'jornada_obligatoria' => (bool) config('gastronomia.jornada_obligatoria', true),
            'wigos_account_info_habilitado' => (bool) config('wigos.account_info_habilitado', false),
            'sku_catalogo_prefijo' => (string) config('gastronomia.sku_catalogo_prefijo', 'V'),
            'sku_catalogo_digitos_sufijo' => (int) config('gastronomia.sku_catalogo_digitos_sufijo', 0),
            'moneda_factura_id' => (int) config('gastronomia.moneda_factura_id', 1),
            'listaprecio_id' => (int) ($cfg?->listaprecio_id ?? config('precio.listaprecio_default_id', 1)),
            'listaprecio_nombre' => $cfg?->listaprecio?->nombre ?? '',
        ]);
    }

    public function apiConfig(Request $request)
    {
        can('usar-facturador-canje-marketing');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            $body = $cfg->getData(true);
            $body['ok'] = false;

            return response()->json($body, 422);
        }

        $cfg->loadMissing(['tipotransaccion', 'empresa']);

        return response()->json([
            'ok' => true,
            'empresa_id' => (int) $cfg->empresa_id,
            'empresa_nombre' => $cfg->empresa->nombre ?? null,
            'identificador_pc' => GastronomiaIdentificadorPc::resolver($request),
            'descuento_codigo' => trim((string) config('gastronomia.canje_marketing_descuento_codigo', '40')),
            'jornada' => $this->jornadaService->estadoParaEmpresa((int) $cfg->empresa_id),
            'jornada_obligatoria' => (bool) config('gastronomia.jornada_obligatoria', true),
            'wigos_account_info_habilitado' => (bool) config('wigos.account_info_habilitado', false),
            'sku_catalogo_prefijo' => (string) config('gastronomia.sku_catalogo_prefijo', 'V'),
            'sku_catalogo_digitos_sufijo' => (int) config('gastronomia.sku_catalogo_digitos_sufijo', 0),
            'moneda_factura_id' => (int) config('gastronomia.moneda_factura_id', 1),
        ]);
    }

    public function apiAutenticarMozo(Request $request)
    {
        can('usar-facturador-canje-marketing');

        $request->validate([
            'codigo' => 'required|string|max:50',
            'clave' => 'required|string|max:60',
        ]);

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        try {
            $auth = $this->canjeMarketingCuentaService->autenticarMozo(
                (string) $request->get('codigo'),
                (string) $request->get('clave'),
                (int) $cfg->empresa_id,
            );
            $forzarNueva = filter_var($request->get('forzar_nueva_cuenta'), FILTER_VALIDATE_BOOLEAN);
            $cuenta = $forzarNueva
                ? $this->canjeMarketingCuentaService->abrirCuentaParaMozo(
                    (int) $cfg->empresa_id,
                    $cfg,
                    (int) $auth['mozo']->id,
                )
                : $this->canjeMarketingCuentaService->abrirOCargarCuentaParaMozo(
                    (int) $cfg->empresa_id,
                    $cfg,
                    (int) $auth['mozo']->id,
                    $request,
                );
            $cuenta = $this->canjeMarketingCuentaService->aplicarDescuentoPrefijado($cuenta);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'session_token' => $auth['session_token'],
            'mozo' => [
                'id' => (int) $auth['mozo']->id,
                'codigo' => (string) $auth['mozo']->codigo,
                'nombre' => (string) $auth['mozo']->nombre,
            ],
            'cuenta' => $cuenta,
            'cuenta_id' => (int) $cuenta->id,
        ]);
    }

    public function apiCuentasActivas(Request $request)
    {
        can('usar-facturador-canje-marketing');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $cuentas = $this->canjeMarketingCuentaService->listarCuentasActivasPc($request);

        return response()->json([
            'cuentas' => $cuentas,
            'empresa_id' => (int) $cfg->empresa_id,
        ]);
    }

    public function apiAbrirCuenta(Request $request)
    {
        can('usar-facturador-canje-marketing');

        $request->validate([
            'mozo_gastronomia_id' => 'required|integer|min:1',
            'clave' => 'required|string|max:60',
        ]);

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $mozo = \App\Models\Ventas\MozoGastronomia::query()
            ->where('id', (int) $request->get('mozo_gastronomia_id'))
            ->where('empresa_id', (int) $cfg->empresa_id)
            ->firstOrFail();

        try {
            $this->canjeMarketingCuentaService->autenticarMozo(
                (string) $mozo->codigo,
                (string) $request->get('clave'),
                (int) $cfg->empresa_id,
            );
            $cuenta = $this->canjeMarketingCuentaService->abrirCuentaParaMozo(
                (int) $cfg->empresa_id,
                $cfg,
                (int) $mozo->id,
            );
            $cuenta = $this->canjeMarketingCuentaService->aplicarDescuentoPrefijado($cuenta);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['cuenta_id' => $cuenta->id, 'cuenta' => $cuenta]);
    }

    public function apiCuentaVer(int $id)
    {
        can('usar-facturador-canje-marketing');

        $this->canjeMarketingCuentaService->exigirCuentaCanjeMarketing($id);

        return response()->json([
            'cuenta' => $this->cuentaService->cuentaConLineas($id),
        ]);
    }

    public function apiActualizarCuenta(Request $request, int $id)
    {
        can('usar-facturador-canje-marketing');

        $cuenta = $this->canjeMarketingCuentaService->exigirCuentaCanjeMarketing($id);

        try {
            $cuenta = $this->canjeMarketingCuentaService->actualizarCabecera($cuenta, $request->all());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['cuenta' => $cuenta]);
    }

    public function apiCerrarCuenta(int $id)
    {
        can('usar-facturador-canje-marketing');

        $cuenta = $this->canjeMarketingCuentaService->exigirCuentaCanjeMarketing($id);

        try {
            $this->cuentaService->cerrarSinFacturar($cuenta);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function apiCerrarTodasCuentas(Request $request)
    {
        can('usar-facturador-canje-marketing');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        try {
            $cerradas = $this->canjeMarketingCuentaService->cerrarTodasCuentasActivasPc($request);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'cerradas' => $cerradas]);
    }

    public function apiAgregarLinea(Request $request, int $id)
    {
        can('usar-facturador-canje-marketing');

        $request->validate([
            'articulo_id' => 'required|integer',
            'cantidad' => 'required|numeric|min:0.0001',
            'precio_unitario' => 'nullable|numeric|min:0',
            'opcionales' => 'nullable|array',
        ]);

        $this->canjeMarketingCuentaService->exigirCuentaCanjeMarketing($id);
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
        can('usar-facturador-canje-marketing');

        $this->canjeMarketingCuentaService->exigirCuentaCanjeMarketing($cuentaId);

        $linea = CuentaGastronomiaLinea::query()
            ->where('cuenta_gastronomia_id', $cuentaId)
            ->where('id', $lineaId)
            ->firstOrFail();

        try {
            $this->cuentaService->eliminarLinea($linea);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['cuenta' => $this->cuentaService->cuentaConLineas($cuentaId)]);
    }

    public function apiActualizarCantidadLinea(Request $request, int $cuentaId, int $lineaId)
    {
        can('usar-facturador-canje-marketing');

        $request->validate([
            'cantidad' => 'sometimes|required|numeric|min:0.0001',
        ]);

        $this->canjeMarketingCuentaService->exigirCuentaCanjeMarketing($cuentaId);

        $linea = CuentaGastronomiaLinea::query()
            ->where('cuenta_gastronomia_id', $cuentaId)
            ->where('id', $lineaId)
            ->firstOrFail();

        try {
            $this->cuentaService->actualizarCantidadLinea($linea, (float) $request->get('cantidad'));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['cuenta' => $this->cuentaService->cuentaConLineas($cuentaId)]);
    }

    public function apiArticuloCatalogoPorSku(Request $request)
    {
        can('usar-facturador-canje-marketing');

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

    public function apiArticulosCatalogo(Request $request)
    {
        can('usar-facturador-canje-marketing');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        return response()->json([
            'articulos' => $this->cuentaService->queryArticulosCatalogo($cfg, trim((string) $request->get('q', ''))),
        ]);
    }

    public function apiOpcionalesArticulo(int $articuloId)
    {
        can('usar-facturador-canje-marketing');

        $articulo = Articulo::query()->findOrFail($articuloId);

        return response()->json([
            'grupos' => $this->opcionalesService->gruposOpcionalesPorArticulo($articulo),
        ]);
    }

    public function apiValidarEmision(Request $request)
    {
        can('usar-facturador-canje-marketing');

        $request->validate([
            'cuenta_id' => 'required|integer',
            'moneda_id' => 'required|integer',
        ]);

        $cuentaId = (int) $request->get('cuenta_id');
        $this->canjeMarketingCuentaService->exigirCuentaCanjeMarketing($cuentaId);
        $cuenta = $this->cuentaService->cuentaConLineas($cuentaId);
        $monedaId = (int) $request->get('moneda_id');
        $errores = $this->canjeMarketingEmisionService->erroresPreflight($cuenta, $monedaId);
        $preview = $this->canjeMarketingEmisionService->previewTotalesParaCuenta($cuenta);

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
        can('usar-facturador-canje-marketing');

        $request->validate([
            'cuenta_id' => 'required|integer',
            'moneda_id' => 'required|integer',
            'actividad_arca_id' => 'nullable|integer',
        ]);

        $cuentaId = (int) $request->get('cuenta_id');
        $this->canjeMarketingCuentaService->exigirCuentaCanjeMarketing($cuentaId);
        $cuenta = $this->cuentaService->cuentaConLineas($cuentaId);

        $res = $this->canjeMarketingEmisionService->emitirFacturaDesdeCuenta(
            $cuenta,
            (int) $request->get('moneda_id'),
            $request->get('actividad_arca_id') ? (int) $request->get('actividad_arca_id') : null,
            filter_var($request->get('forzar_caea'), FILTER_VALIDATE_BOOLEAN),
            [],
        );

        if (isset($res['error'])) {
            $mensaje = trim((string) ($res['mensaje'] ?? $res['error']));

            return response()->json(['error' => $mensaje, 'mensaje' => $mensaje], 422);
        }

        return response()->json($res);
    }

    public function apiConsultaClienteVip(Request $request)
    {
        can('usar-facturador-canje-marketing');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $payload = json_decode($this->clienteVipRepository->consultaClienteVipPos(
            (string) $request->get('consulta', ''),
            (int) $cfg->empresa_id,
        ), true);

        return response()->json(is_array($payload) ? $payload : ['data' => '']);
    }

    public function apiClienteVipPorCodigo(Request $request, string $codigo)
    {
        can('usar-facturador-canje-marketing');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $codigoTrim = trim($codigo);
        $vip = null;
        if (ctype_digit($codigoTrim)) {
            $vip = $this->clienteVipRepository->findPorNumeroid((int) $cfg->empresa_id, (int) $codigoTrim);
        }
        if (! $vip) {
            $vip = $this->clienteVipRepository->findPorDocumento((int) $cfg->empresa_id, $codigoTrim);
        }
        if (! $vip && ctype_digit($codigoTrim)) {
            $porId = $this->clienteVipRepository->find((int) $codigoTrim);
            if ($porId && (int) $porId->empresa_id === (int) $cfg->empresa_id) {
                $vip = $porId;
            }
        }

        if (! $vip) {
            return response()->json(['error' => 'Cliente VIP no encontrado'], 404);
        }

        return response()->json([
            'cliente_vip' => $this->canjeMarketingCuentaService->serializarClienteVip($vip),
        ]);
    }

    public function apiClienteVipWigos(Request $request)
    {
        can('usar-facturador-canje-marketing');

        $request->validate(['trackdata' => 'required|string|min:1|max:128']);

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        try {
            $resultado = $this->canjeMarketingCuentaService->resolverClienteVipDesdeWigos(
                (int) $cfg->empresa_id,
                (string) $request->get('trackdata'),
            );
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'creado' => $resultado['creado'],
            'cliente_vip' => $this->canjeMarketingCuentaService->serializarClienteVip($resultado['cliente_vip']),
            'wigos' => $resultado['wigos'],
        ]);
    }

    public function apiDescuentoPrefijado(Request $request)
    {
        can('usar-facturador-canje-marketing');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $codigo = trim((string) config('gastronomia.canje_marketing_descuento_codigo', '40'));
        $desc = DescuentoGastronomia::query()
            ->where('codigo', $codigo)
            ->first();

        if (! $desc) {
            return response()->json(['error' => 'Descuento código '.$codigo.' no configurado.'], 404);
        }

        return response()->json([
            'descuento' => [
                'id' => (int) $desc->id,
                'codigo' => (string) $desc->codigo,
                'nombre' => (string) $desc->nombre,
                'tipovalor' => (string) $desc->tipovalor,
                'valor' => (float) $desc->valor,
            ],
        ]);
    }

    public function apiConsultaMozo(Request $request)
    {
        can('usar-facturador-canje-marketing');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $json = $this->mozoGastronomiaRepository->consultaMozo(
            (string) ($request->get('consulta') ?? ''),
            (int) $cfg->empresa_id,
            false,
        );

        return response($json, 200)->header('Content-Type', 'application/json');
    }

    public function apiMozoPorCodigo(Request $request, string $codigo)
    {
        can('usar-facturador-canje-marketing');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $mozo = $this->mozoGastronomiaRepository->findPorCodigo($codigo, (int) $cfg->empresa_id, false);
        if (! $mozo) {
            return response()->json(['error' => 'Mozo no encontrado'], 404);
        }

        return response()->json($this->mozoJson($mozo));
    }

    public function apiMozoPorId(Request $request, int $id)
    {
        can('usar-facturador-canje-marketing');

        $cfg = $this->requireCfgPv($request);
        if ($cfg instanceof \Illuminate\Http\JsonResponse) {
            return $cfg;
        }

        $mozo = $this->mozoGastronomiaRepository->findPorId($id, (int) $cfg->empresa_id);
        if (! $mozo) {
            return response()->json(['error' => 'Mozo no encontrado'], 404);
        }

        return response()->json($this->mozoJson($mozo));
    }

    /**
     * @return array{id: int, codigo: string, nombre: string}
     */
    private function mozoJson(MozoGastronomia $mozo): array
    {
        return [
            'id' => (int) $mozo->id,
            'codigo' => (string) $mozo->codigo,
            'nombre' => (string) $mozo->nombre,
        ];
    }

    private function cfgPv(Request $request): ?ConfiguracionPuntoventaGastronomia
    {
        return $this->canjeMarketingCuentaService->resolverConfiguracionPv($request);
    }

    /**
     * @return ConfiguracionPuntoventaGastronomia|\Illuminate\Http\JsonResponse
     */
    private function requireCfgPv(Request $request)
    {
        try {
            $cfg = $this->canjeMarketingCuentaService->resolverConfiguracionPv($request);
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
