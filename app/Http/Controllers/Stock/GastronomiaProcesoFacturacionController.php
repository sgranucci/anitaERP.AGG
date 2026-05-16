<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Usocuentacaja;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Articulo;
use App\Models\Stock\CuentaGastronomia;
use App\Models\Stock\CuentaGastronomiaLinea;
use App\Models\Stock\DescuentoGastronomia;
use App\Models\Stock\MesaGastronomia;
use App\Models\Stock\MozoGastronomia;
use App\Services\Configuracion\CotizacionService;
use App\Services\Stock\Gastronomia\GastronomiaCuentaService;
use App\Services\Stock\Gastronomia\GastronomiaFacturaEmisionService;
use App\Services\Stock\Gastronomia\GastronomiaFormulaOpcionalesService;
use App\Services\Stock\PrecioService;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Support\Stock\GastronomiaIdentificadorPc;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class GastronomiaProcesoFacturacionController extends Controller
{
    public function __construct(
        private readonly GastronomiaCuentaService $cuentaService,
        private readonly GastronomiaFacturaEmisionService $facturaEmisionService,
        private readonly GastronomiaFormulaOpcionalesService $opcionalesService,
    ) {}

    public function index(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $empresaId = (int) config('cliente.EMPRESA_DEFAULT_ID');
        $cfg = $this->cuentaService->resolverConfiguracionPv($empresaId);

        return view('stock.gastronomia.proceso_facturacion.index', [
            'empresa_id' => $empresaId,
            'prefijo_sku' => (string) config('gastronomia.sku_catalogo_prefijo', 'V'),
            'sku_catalogo_digitos_sufijo' => max(0, (int) config('gastronomia.sku_catalogo_digitos_sufijo', 0)),
            'tiene_cfg_pv' => $cfg !== null,
            'ubicacion_id' => $cfg?->ubicacion_id,
            'salida_factura_id' => $cfg?->salida_factura_id,
            'identificador_pc_actual' => GastronomiaIdentificadorPc::resolver($request),
        ]);
    }

    public function apiConfig(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $empresaId = (int) ($request->get('empresa_id') ?: config('cliente.EMPRESA_DEFAULT_ID'));
        $cfg = $this->cuentaService->resolverConfiguracionPv($empresaId);

        if (! $cfg) {
            $pc = GastronomiaIdentificadorPc::resolver($request);

            return response()->json([
                'ok' => false,
                'mensaje' => 'No existe configuración PV gastronomía para identificador_pc '.$pc,
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'identificador_pc' => GastronomiaIdentificadorPc::resolver($request),
            'ubicacion_id' => $cfg->ubicacion_id,
            'puntoventa_cae_id' => $cfg->puntoventa_cae_id,
            'puntoventa_caea_id' => $cfg->puntoventa_caea_id,
            'salida_factura_id' => $cfg->salida_factura_id,
            'salida_comanda_id' => $cfg->salida_comanda_id,
            'listaprecio_id' => $cfg->listaprecio_id,
            'listaprecio_nombre' => $cfg->listaprecio->nombre ?? null,
        ]);
    }

    public function apiMesas(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $empresaId = (int) ($request->get('empresa_id') ?: config('cliente.EMPRESA_DEFAULT_ID'));
        $cfg = $this->cuentaService->resolverConfiguracionPv($empresaId);

        $ubicacionId = $cfg?->ubicacion_id ? (int) $cfg->ubicacion_id : null;
        $mesas = $this->cuentaService->listarMesasUbicacion($ubicacionId);

        return response()->json([
            'mesas' => $this->cuentaService->mesasConOcupacion($mesas),
            'ubicacion_id' => $ubicacionId,
        ]);
    }

    public function apiCuentasActivas(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $empresaId = (int) ($request->get('empresa_id') ?: config('cliente.EMPRESA_DEFAULT_ID'));
        $cuentas = $this->cuentaService->listarCuentasLibresActivasPc($empresaId);

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

        $empresaId = (int) ($request->get('empresa_id') ?: config('cliente.EMPRESA_DEFAULT_ID'));
        $cfg = $this->cuentaService->resolverConfiguracionPv($empresaId);

        if (! $cfg) {
            return response()->json(['error' => 'Sin configuración PV gastronomía para esta PC.'], 422);
        }

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
            $cuenta = $this->cuentaService->abrirMesa($mesaId, $empresaId, $cfg);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['cuenta_id' => $cuenta->id, 'reutilizada' => false]);
    }

    public function apiAbrirCuenta(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $empresaId = (int) ($request->get('empresa_id') ?: config('cliente.EMPRESA_DEFAULT_ID'));
        $cfg = $this->cuentaService->resolverConfiguracionPv($empresaId);

        if (! $cfg) {
            return response()->json(['error' => 'Sin configuración PV gastronomía para esta PC.'], 422);
        }

        try {
            $cuenta = $this->cuentaService->abrirCuentaLibre($empresaId, $cfg);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['cuenta_id' => $cuenta->id]);
    }

    public function apiActualizarCuenta(Request $request, int $id)
    {
        can('usar-proceso-facturacion-gastronomia');

        $cuenta = $this->cuentaService->cuentaConLineas($id);

        try {
            $cuenta = $this->cuentaService->actualizarCabecera($cuenta, $request->all());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['cuenta' => $cuenta]);
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
            : $this->resolverPrecioLista((int) $articulo->id, (int) $cuenta->empresa_id);

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

        $empresaId = (int) ($request->get('empresa_id') ?: config('cliente.EMPRESA_DEFAULT_ID'));
        $prefijo = (string) config('gastronomia.sku_catalogo_prefijo', 'V');
        $q = trim((string) $request->get('q', ''));

        $query = Articulo::query()
            ->where('empresa_id', $empresaId)
            ->where('sku', 'like', $prefijo.'%');

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('sku', 'like', '%'.$q.'%')
                    ->orWhere('descripcion', 'like', '%'.$q.'%');
            });
        }

        $listaId = $this->listaPrecioIdParaPc($empresaId);

        $articulos = $query->orderBy('sku')->limit(80)->get(['id', 'sku', 'descripcion']);

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

        $empresaId = (int) ($request->get('empresa_id') ?: config('cliente.EMPRESA_DEFAULT_ID'));
        $prefijo = (string) config('gastronomia.sku_catalogo_prefijo', 'V');
        $sku = trim((string) $request->get('sku', ''));
        if ($sku === '') {
            return response()->json(['error' => 'SKU vacío'], 422);
        }

        $a = Articulo::query()
            ->where('empresa_id', $empresaId)
            ->where('sku', 'like', $prefijo.'%')
            ->whereRaw('UPPER(sku) = ?', [mb_strtoupper($sku, 'UTF-8')])
            ->first(['id', 'sku', 'descripcion']);

        if (! $a) {
            return response()->json(['error' => 'Artículo no encontrado en catálogo gastronomía'], 404);
        }

        $listaId = $this->listaPrecioIdParaPc($empresaId);
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

        $empresaId = (int) ($request->get('empresa_id') ?: config('cliente.EMPRESA_DEFAULT_ID'));

        return response()->json([
            'mozos' => MozoGastronomia::query()->where('empresa_id', $empresaId)->orderBy('nombre')->get(['id', 'nombre', 'codigo']),
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

        $empresaId = (int) ($request->get('empresa_id') ?: config('cliente.EMPRESA_DEFAULT_ID'));
        $usoId = (int) $request->get('usocuentacaja_id');
        $monedaId = (int) $request->get('moneda_id');

        $q = Cuentacaja::query()->where('empresa_id', $empresaId);

        if ($usoId > 0) {
            $q->whereHas('usocuentacajas', fn ($r) => $r->whereKey($usoId));
        }

        if ($monedaId > 0) {
            $q->where('moneda_id', $monedaId);
        }

        return response()->json([
            'cuentas_caja' => $q->orderBy('nombre')->get(['id', 'nombre', 'codigo', 'moneda_id']),
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

    public function apiEmitirFactura(Request $request)
    {
        can('usar-proceso-facturacion-gastronomia');

        $request->validate([
            'cuenta_id' => 'required|integer',
            'moneda_id' => 'required|integer',
            'actividad_arca_id' => 'nullable|integer',
        ]);

        $cuenta = $this->cuentaService->cuentaConLineas((int) $request->get('cuenta_id'));

        $res = $this->facturaEmisionService->emitirFacturaDesdeCuenta(
            $cuenta,
            (int) $request->get('moneda_id'),
            $request->get('actividad_arca_id') ? (int) $request->get('actividad_arca_id') : null,
            filter_var($request->get('forzar_caea'), FILTER_VALIDATE_BOOLEAN),
        );

        if (isset($res['error'])) {
            return response()->json($res, 422);
        }

        return response()->json($res);
    }

    private function listaPrecioIdParaPc(int $empresaId): int
    {
        $cfg = $this->cuentaService->resolverConfiguracionPv($empresaId);

        return (int) ($cfg->listaprecio_id ?? 1);
    }

    private function resolverPrecioLista(int $articuloId, int $empresaId): float
    {
        $listaId = $this->listaPrecioIdParaPc($empresaId);
        $precios = PrecioService::asignaPrecioPorLista($articuloId, $listaId, Carbon::today()->format('Y-m-d'));

        if ($precios === []) {
            return 0.;
        }

        $p = end($precios);

        return (float) ($p['precio'] ?? 0);
    }
}
