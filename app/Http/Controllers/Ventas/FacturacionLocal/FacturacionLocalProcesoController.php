<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Stock\Color;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Talle;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Cliente;
use App\Repositories\Ventas\TurnoLocalRepositoryInterface;
use App\Services\Stock\PrecioServiceFerli;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalEmisionService;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalTurnoService;
use App\Services\Ventas\FacturacionLocal\FacturacionLocalValeService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\ArticuloCanalSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalSplitFacNcSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalVarianteArticuloSupport;
use Illuminate\Http\Request;

class FacturacionLocalProcesoController extends Controller
{
    public function __construct(
        private readonly FacturacionLocalTurnoService $turnoService,
        private readonly FacturacionLocalEmisionService $emisionService,
        private readonly FacturacionLocalValeService $valeService,
        private readonly TurnoLocalRepositoryInterface $turnoLocalRepository,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        can('usar-facturacion-local');

        $locales = LocalVenta::query()->where('activo', true)->orderBy('codigo')->get();
        $localId = (int) $request->input('local_id', $locales->first()?->id ?? 0);
        $local = $localId > 0 ? LocalVenta::query()->with('cuentacajas')->find($localId) : null;
        $turno = $local ? $this->turnoService->turnoAbierto((int) $local->id) : null;
        if ($turno) {
            $turno->loadMissing('turnoLocal:id,codigo,nombre');
        }

        $cuentasPos = ($local?->cuentacajas ?? collect())->map(static function ($c) {
            return [
                'id' => (int) $c->id,
                'codigo' => (string) $c->codigo,
                'nombre' => (string) $c->nombre,
            ];
        })->values()->all();

        $turnosMaestro = $this->turnoLocalRepository
            ->listarParaSelect($local?->empresa_id ? (int) $local->empresa_id : null)
            ->map(static function ($t) {
                return [
                    'id' => (int) $t->id,
                    'codigo' => (string) ($t->codigo ?? ''),
                    'nombre' => (string) $t->nombre,
                    'etiqueta' => trim(($t->codigo ? $t->codigo.' — ' : '').$t->nombre.' ('.$t->etiquetaHorario().')'),
                ];
            })
            ->values()
            ->all();

        return view('ventas.facturacion_local.proceso.index', compact(
            'locales',
            'local',
            'turno',
            'cuentasPos',
            'turnosMaestro'
        ));
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
        ArticuloCanalSupport::scopeArticulosCanalLocal($query);
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
                'error' => 'El artículo no está operativo en canal Local.',
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
            $payload['combinaciones'] = Combinacion::query()
                ->where('articulo_id', $articuloId)
                ->where(function ($q) {
                    $q->whereNull('estado')->orWhere('estado', '!=', 'I');
                })
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre', 'observacion']);
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
        $precio = 0.;
        try {
            if (class_exists(PrecioServiceFerli::class) && $articuloId > 0 && $talleId > 0) {
                $precio = (float) app(PrecioServiceFerli::class)->asignaPrecio(
                    $articuloId,
                    $combinacionId > 0 ? $combinacionId : null,
                    $talleId,
                    now()->format('Y-m-d')
                );
            }
        } catch (\Throwable $e) {
            $precio = 0.;
        }

        return response()->json([
            'precio' => $precio,
            'listaprecio_id' => $local?->listaprecio_id,
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
        $codigo = trim((string) $request->input('codigo', ''));
        if ($codigo === '') {
            return response()->json(['cliente' => null]);
        }
        $cliente = Cliente::query()
            ->where('codigo', $codigo)
            ->orWhere('numerodocumento', $codigo)
            ->orWhere('nroiibb', $codigo)
            ->first();

        return response()->json(['cliente' => $cliente]);
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
