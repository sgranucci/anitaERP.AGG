<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Stock\Color;
use App\Models\Stock\Talle;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Cliente;
use App\Repositories\Ventas\TurnoLocalRepositoryInterface;
use App\Services\Stock\PrecioServiceFerli;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalEmisionService;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalTurnoService;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalValeService;
use App\Services\Ventas\FacturacionLocal\StockLocalConsultaService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\ArticuloCanalSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalMedioPresentacionSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPosContextoSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPrecioIvaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalSplitFacNcSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalUsoCuentacajaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalVarianteArticuloSupport;
use App\Support\Ventas\FacturacionLocal\StockLocalInformeListadoFiltros;
use Illuminate\Http\Request;

class FacturacionLocalProcesoController extends Controller
{
    public function __construct(
        private readonly FacturacionLocalTurnoService $turnoService,
        private readonly FacturacionLocalEmisionService $emisionService,
        private readonly FacturacionLocalValeService $valeService,
        private readonly TurnoLocalRepositoryInterface $turnoLocalRepository,
        private readonly StockLocalConsultaService $stockConsultaService,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        can('usar-facturacion-local');

        $locales = LocalVenta::query()->where('activo', true)->orderBy('codigo')->get();
        $localId = (int) $request->input('local_id', 0);
        if ($localId > 0) {
            session(['facturacion_local.local_id' => $localId]);
        } else {
            $localId = (int) session('facturacion_local.local_id', $locales->first()?->id ?? 0);
        }
        if ($localId > 0 && ! $locales->contains('id', $localId)) {
            $localId = (int) ($locales->first()?->id ?? 0);
        }

        $local = $localId > 0
            ? LocalVenta::query()->with([
                'cuentacajas',
                'puntoventa:id,codigo,nombre',
                'deposito:id,codigo,nombre',
                'listaprecio:id,codigo,nombre',
                'tipotransaccionFac:id,abreviatura,codigo,nombre',
            ])->find($localId)
            : null;
        $turno = $local ? $this->turnoService->turnoAbierto((int) $local->id) : null;
        if ($turno) {
            $turno->loadMissing(['turnoLocal:id,codigo,nombre', 'usuarioApertura:id,nombre']);
        }

        $cuentasPos = ($local?->cuentacajas ?? collect())->map(static function ($c) {
            $presentacion = FacturacionLocalMedioPresentacionSupport::presentacion(
                (string) $c->nombre,
                (string) $c->codigo,
                (bool) ($c->es_tarjeta ?? false)
            );

            return [
                'id' => (int) $c->id,
                'codigo' => (string) $c->codigo,
                'nombre' => (string) $c->nombre,
                'icono' => $presentacion['icono'],
                'icono_color' => $presentacion['icono_color'],
                'tema' => $presentacion['tema'],
                'etiqueta_boton' => $presentacion['etiqueta'],
                'pide_cupon' => $presentacion['pide_cupon'],
                'es_tarjeta' => (bool) ($c->es_tarjeta ?? false),
            ];
        })->values()->all();

        $usocuentacajaLocalId = FacturacionLocalUsoCuentacajaSupport::resolverId() ?? 0;
        $empresaIdPos = (int) ($local?->empresa_id ?? 0);
        // Index: sin SOAP (rápido). El JS refresca el próximo número vía apiContextoPos.
        $contextoPos = FacturacionLocalPosContextoSupport::paraLocal($local, false);

        $turnosQuery = $this->turnoLocalRepository
            ->listarParaSelect($local?->empresa_id ? (int) $local->empresa_id : null);
        $turnoSugeridoId = 0;
        foreach ($turnosQuery as $t) {
            if ($t->cubreHora()) {
                $turnoSugeridoId = (int) $t->id;
                break;
            }
        }
        if ($turnoSugeridoId === 0 && $turnosQuery->isNotEmpty()) {
            $turnoSugeridoId = (int) $turnosQuery->first()->id;
        }

        $turnosMaestro = $turnosQuery->map(static function ($t) {
            return [
                'id' => (int) $t->id,
                'codigo' => (string) ($t->codigo ?? ''),
                'nombre' => (string) $t->nombre,
                'etiqueta' => trim($t->nombre.' · '.$t->etiquetaHorario()),
            ];
        })->values()->all();

        return view('ventas.facturacion_local.proceso.index', compact(
            'locales',
            'local',
            'turno',
            'cuentasPos',
            'turnosMaestro',
            'turnoSugeridoId',
            'usocuentacajaLocalId',
            'empresaIdPos',
            'contextoPos'
        ));
    }

    public function apiContextoPos(Request $request)
    {
        $this->assertFerli();
        can('usar-facturacion-local', false);

        $local = LocalVenta::query()->find((int) $request->input('local_id', 0));
        if (! $local) {
            return response()->json(['ok' => false, 'error' => 'Local inválido'], 422);
        }

        return response()->json([
            'ok' => true,
            'contexto' => FacturacionLocalPosContextoSupport::paraLocal($local, true),
        ]);
    }

    /**
     * Consulta stock + precios del local desde el POS (misma lógica que pantallas dedicadas).
     */
    public function apiConsultaStockPrecios(Request $request)
    {
        $this->assertFerli();
        can('usar-facturacion-local', false);

        $local = LocalVenta::query()->find((int) $request->input('local_id', 0));
        if (! $local) {
            return response()->json(['ok' => false, 'error' => 'Local inválido'], 422);
        }

        $busqueda = trim((string) (
            $request->input('articulo_id')
            ?: $request->input('codigo')
            ?: $request->input('q')
            ?: ''
        ));
        if ($busqueda === '') {
            return response()->json(['ok' => false, 'error' => 'Ingrese un artículo (SKU o ID).'], 422);
        }

        $origen = strtolower(trim((string) $request->input('origen', StockLocalInformeListadoFiltros::ORIGEN_ANITA)));
        if ($origen !== StockLocalInformeListadoFiltros::ORIGEN_ERP) {
            $origen = StockLocalInformeListadoFiltros::ORIGEN_ANITA;
        }

        $resultado = $this->stockConsultaService->consultarPreciosYStock($local, $busqueda, $origen);
        $status = ($resultado['ok'] ?? false) ? 200 : 422;

        return response()->json($resultado, $status);
    }

    public function apiBuscarArticulo(Request $request)
    {
        $this->assertFerli();
        can('usar-facturacion-local', false);

        $q = trim((string) $request->input('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $query = Articulo::query()
            ->select(['id', 'sku', 'descripcion', 'maneja_stock_color_talle', 'nofactura'])
            ->where(function ($w) use ($q) {
                $w->where('sku', 'like', '%'.$q.'%')
                    ->orWhere('descripcion', 'like', '%'.$q.'%');
            });
        ArticuloCanalSupport::scopeArticulosPosLocal($query);
        $query->where(function ($w) {
            $w->where('nofactura', false)->orWhereNull('nofactura');
        });
        $rows = $query->orderBy('sku')->limit(30)->get()->map(function (Articulo $a) {
            return [
                'id' => (int) $a->id,
                'sku' => (string) $a->sku,
                'descripcion' => (string) $a->descripcion,
                'modo_variante' => FacturacionLocalVarianteArticuloSupport::modo($a),
                'nofactura' => (bool) $a->nofactura,
            ];
        });

        return response()->json(['data' => $rows]);
    }

    public function apiVariantesArticulo(Request $request, int $articuloId)
    {
        $this->assertFerli();
        can('usar-facturacion-local', false);

        $articulo = Articulo::query()->findOrFail($articuloId);
        if (! ArticuloCanalSupport::articuloOperativoLocal($articuloId)) {
            return response()->json([
                'error' => 'El artículo no está operativo en canal Local (sin canal o inactivo).',
            ], 422);
        }
        $modo = FacturacionLocalVarianteArticuloSupport::modo($articulo);
        $talles = Talle::query()->orderBy('nombre')->get(['id', 'nombre', 'codigo']);
        $payload = [
            'modo' => $modo,
            'talles' => $talles,
            'colores' => [],
            'combinaciones' => [],
        ];
        if ($modo === FacturacionLocalVarianteArticuloSupport::MODO_COLOR_TALLE) {
            $payload['colores'] = Color::query()->orderBy('nombre')->limit(500)->get(['id', 'nombre', 'codigo']);
        } else {
            $payload['combinaciones'] = FacturacionLocalVarianteArticuloSupport::queryCombinacionesActivas($articuloId)
                ->get(['id', 'codigo', 'nombre', 'observacion', 'estado']);
        }

        return response()->json($payload);
    }

    public function apiPrecio(Request $request)
    {
        $this->assertFerli();
        can('usar-facturacion-local', false);

        $articuloId = (int) $request->input('articulo_id');
        $combinacionId = (int) $request->input('combinacion_id', 0);
        $talleId = (int) $request->input('talle_id', 0);
        $localId = (int) $request->input('local_id', 0);
        $local = LocalVenta::query()->find($localId);
        $listaId = (int) ($local?->listaprecio_id ?? 0);
        $precio = 0.;
        $listaUsada = $listaId;
        try {
            $svc = app(PrecioServiceFerli::class);
            $fecha = now()->format('Y-m-d');
            $comb = $combinacionId > 0 ? $combinacionId : null;

            // Lista del local (Lugano/Web/etc.) manda en POS Local.
            if ($articuloId > 0 && $listaId > 0) {
                $precio = $svc->precioVigente($articuloId, $listaId, $comb, $fecha);
            }

            // Fallback: lista por rango de talle (ABM Ferli clásico).
            if ($precio <= 0 && $articuloId > 0 && $talleId > 0) {
                $filas = $svc->asignaPrecio($articuloId, $comb, $talleId, $fecha);
                $precio = PrecioServiceFerli::primerPrecioNumerico($filas);
                if (is_array($filas[0] ?? null) && (int) ($filas[0]['listaprecio_id'] ?? 0) > 0) {
                    $listaUsada = (int) $filas[0]['listaprecio_id'];
                }
            }
        } catch (\Throwable $e) {
            $precio = 0.;
        }

        // POS muestra/cobra precio final de lista (locales = IVA incluido siempre).
        $flagLista = FacturacionLocalPrecioIvaSupport::flagLista($listaUsada > 0 ? $listaUsada : $listaId);
        $precioMostrar = FacturacionLocalPrecioIvaSupport::precioParaPos($precio, $listaUsada > 0 ? $listaUsada : $listaId);

        return response()->json([
            'precio' => $precioMostrar,
            'precio_lista' => $precio,
            'incluyeimpuesto_lista' => $flagLista,
            'listaprecio_id' => $listaUsada > 0 ? $listaUsada : ($local?->listaprecio_id),
        ]);
    }

    public function apiEmitir(Request $request)
    {
        $this->assertFerli();
        can('usar-facturacion-local');

        $local = LocalVenta::query()->findOrFail((int) $request->input('local_id'));
        $input = [
            'lineas' => $request->input('lineas', []),
            'medios_pago' => $request->input('medios_pago', []),
            'cliente_id' => $request->input('cliente_id'),
            'receptor' => $request->input('receptor', []),
            'receptor_manual' => $request->input('receptor_manual', []),
            'descuentopie' => (float) $request->input('descuentopie', 0),
            'descuentoimportepie' => (float) $request->input('descuentoimportepie', 0),
            'excedente_accion' => $request->input('excedente_accion'),
            'vale_aplicar_id' => $request->input('vale_aplicar_id'),
            'vale_aplicar_importe' => $request->input('vale_aplicar_importe'),
            'es_ticket_regalo' => (bool) $request->boolean('es_ticket_regalo'),
            'identificador_pc' => $request->input('identificador_pc'),
        ];

        $resultado = $this->emisionService->emitir($local, $input);
        $status = ($resultado['ok'] ?? false) ? 200 : 422;
        if ($status === 200) {
            $pdfUrls = [];
            if ((int) ($resultado['venta_id'] ?? 0) > 0) {
                $pdfUrls[] = route('lista_una_factura_pdf', (int) $resultado['venta_id']);
            }
            if ((int) ($resultado['venta_nc_id'] ?? 0) > 0
                && (int) $resultado['venta_nc_id'] !== (int) ($resultado['venta_id'] ?? 0)) {
                $pdfUrls[] = route('lista_una_factura_pdf', (int) $resultado['venta_nc_id']);
            }
            $resultado['pdf_urls'] = $pdfUrls;
        }

        return response()->json($resultado, $status);
    }

    public function apiPreviewTotales(Request $request)
    {
        $this->assertFerli();
        can('usar-facturacion-local', false);
        $split = FacturacionLocalSplitFacNcSupport::partir($request->input('lineas', []));

        return response()->json($split);
    }

    public function apiVales(Request $request)
    {
        $this->assertFerli();
        can('usar-facturacion-local', false);
        $vales = $this->valeService->buscarActivos(
            (int) $request->input('cliente_id') ?: null,
            $request->input('tipo_documento'),
            $request->input('nro_documento')
        );

        return response()->json(['data' => $vales]);
    }

    public function apiCliente(Request $request)
    {
        $this->assertFerli();
        can('usar-facturacion-local', false);

        $id = (int) $request->input('id', 0);
        $codigo = trim((string) $request->input('codigo', ''));
        if ($id <= 0 && $codigo === '') {
            return response()->json(['cliente' => null]);
        }

        $q = Cliente::query()->with(['condicionivas:id,nombre,letra', 'tipodocumentos:id,nombre,abreviatura']);
        if ($id > 0) {
            $cliente = $q->find($id);
        } else {
            $cliente = $q->where(function ($w) use ($codigo) {
                $w->where('codigo', $codigo)
                    ->orWhere('numerodocumento', $codigo)
                    ->orWhere('nroiibb', $codigo);
            })->first();
        }

        if (! $cliente) {
            return response()->json(['cliente' => null]);
        }

        $letra = (string) ($cliente->condicionivas?->letra ?? 'B');
        $tipoDoc = $cliente->tipodocumentos;

        return response()->json([
            'cliente' => [
                'id' => (int) $cliente->id,
                'codigo' => (string) $cliente->codigo,
                'nombre' => (string) $cliente->nombre,
                'numerodocumento' => (string) ($cliente->numerodocumento ?? ''),
                'tipodocumento_id' => (int) ($cliente->tipodocumento_id ?? 0),
                'tipodocumento' => (string) ($tipoDoc?->abreviatura ?? $tipoDoc?->nombre ?? ''),
                'condicioniva_id' => (int) ($cliente->condicioniva_id ?? 0),
                'condicioniva' => (string) ($cliente->condicionivas?->nombre ?? ''),
                'letra' => $letra !== '' ? $letra : 'B',
                'domicilio' => (string) ($cliente->domicilio ?? ''),
            ],
        ]);
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
