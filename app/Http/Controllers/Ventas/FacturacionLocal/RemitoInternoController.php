<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRemitoInterno;
use App\Models\Stock\Articulo;
use App\Models\Stock\Color;
use App\Models\Stock\Talle;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\RemitoInterno;
use App\Services\Ventas\FacturacionLocal\RemitoInternoService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\ArticuloCanalSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalVarianteArticuloSupport;
use App\Support\Ventas\FacturacionLocal\RemitoInternoEstadosSupport;
use App\Support\Ventas\FacturacionLocal\RemitoInternoListadoFiltros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

/**
 * Remitos internos de locales — solo Facturación Local Ferli.
 */
class RemitoInternoController extends Controller
{
    public function __construct(
        private readonly RemitoInternoService $service,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-remito-interno-facturacion-local');

        $filtros = RemitoInternoListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->service->leeListado($filtros, true);
        $locales = LocalVenta::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre', 'deposito_id', 'empresa_id']);

        return view('ventas.facturacion_local.remito_interno.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => RemitoInternoListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => RemitoInternoListadoFiltros::CAMPOS,
            'locales' => $locales,
            'estados' => RemitoInternoEstadosSupport::opcionesSelect(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        $this->assertFerli();
        can('listar-remito-interno-facturacion-local');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = RemitoInternoListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $datas = $this->service->leeListado($filtros, false);

        if ($formato === 'PDF') {
            $view = View::make('ventas.facturacion_local.remito_interno.listado', compact('datas', 'filtros'))->render();
            $path = storage_path('pdf/listados');
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
            $nombrePdf = 'listado_remito_interno';
            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal', 'landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

            return response()->download($path.'/'.$nombrePdf.'.pdf');
        }

        return redirect()->route(
            'facturacion_local_remitos_internos',
            RemitoInternoListadoFiltros::paraQueryString($filtros)
        );
    }

    public function crear()
    {
        $this->assertFerli();
        can('crear-remito-interno-facturacion-local');

        $data = new RemitoInterno([
            'fecha' => now()->toDateString(),
            'estado' => RemitoInternoEstadosSupport::BORRADOR,
        ]);

        return view('ventas.facturacion_local.remito_interno.crear', $this->formData($data, true));
    }

    public function guardar(ValidacionRemitoInterno $request)
    {
        $this->assertFerli();
        can('crear-remito-interno-facturacion-local');

        try {
            $remito = $this->service->crear($request->validated(), $request->lineasNormalizadas());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('editar_remito_interno', $remito->id)
            ->with('mensaje', 'Remito interno Nº '.$remito->numero.' guardado como borrador.');
    }

    public function editar($id)
    {
        $this->assertFerli();
        can('ver-remito-interno-facturacion-local');

        $data = RemitoInterno::query()
            ->with([
                'lineas.articulo:id,sku,descripcion',
                'lineas.combinacion:id,codigo,nombre',
                'lineas.talle:id,codigo,nombre',
                'lineas.color:id,codigo,nombre',
                'localVenta',
                'deposito',
                'movimientoStock',
            ])
            ->findOrFail($id);

        $editable = RemitoInternoEstadosSupport::esEditable($data->estado)
            && can('actualizar-remito-interno-facturacion-local', false);

        return view('ventas.facturacion_local.remito_interno.editar', $this->formData($data, $editable));
    }

    public function actualizar(ValidacionRemitoInterno $request, $id)
    {
        $this->assertFerli();
        can('actualizar-remito-interno-facturacion-local');

        $remito = RemitoInterno::query()->findOrFail($id);

        try {
            $this->service->actualizar($remito, $request->validated(), $request->lineasNormalizadas());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('editar_remito_interno', $remito->id)
            ->with('mensaje', 'Remito interno actualizado.');
    }

    public function confirmar($id)
    {
        $this->assertFerli();
        can('confirmar-remito-interno-facturacion-local');

        $remito = RemitoInterno::query()->findOrFail($id);

        try {
            $this->service->confirmar($remito);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Error al confirmar: '.$e->getMessage());
        }

        return redirect()
            ->route('editar_remito_interno', $id)
            ->with('mensaje', 'Remito confirmado. Se generó el movimiento de stock.');
    }

    public function anular($id)
    {
        $this->assertFerli();
        can('anular-remito-interno-facturacion-local');

        $remito = RemitoInterno::query()->findOrFail($id);

        try {
            $this->service->anular($remito);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Error al anular: '.$e->getMessage());
        }

        return redirect()
            ->route('editar_remito_interno', $id)
            ->with('mensaje', 'Remito anulado. Se revirtió el stock.');
    }

    public function pdf($id)
    {
        $this->assertFerli();
        can('pdf-remito-interno-facturacion-local');

        $remito = RemitoInterno::query()->findOrFail($id);
        if ($remito->estado === RemitoInternoEstadosSupport::BORRADOR) {
            return back()->with('error', 'Confirme el remito antes de imprimir el PDF.');
        }

        $archivo = $this->service->generarPdfArchivo($remito);

        return response()->download($archivo, 'remito_interno_'.$remito->numero.'.pdf');
    }

    public function apiBuscarArticulo(Request $request)
    {
        $this->assertFerli();
        $this->assertPuedeConsultarArticulos();

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
        $rows = $query->orderBy('sku')->limit(30)->get()->map(function (Articulo $a) {
            return [
                'id' => (int) $a->id,
                'sku' => (string) $a->sku,
                'descripcion' => (string) $a->descripcion,
                'modo_variante' => FacturacionLocalVarianteArticuloSupport::modo($a),
            ];
        });

        return response()->json(['data' => $rows]);
    }

    public function apiVariantesArticulo(int $articuloId)
    {
        $this->assertFerli();
        $this->assertPuedeConsultarArticulos();

        $articulo = Articulo::query()->findOrFail($articuloId);
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

    /**
     * @return array<string, mixed>
     */
    private function formData(RemitoInterno $data, bool $editable): array
    {
        $locales = LocalVenta::query()
            ->where('activo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'deposito_id', 'empresa_id']);

        return [
            'data' => $data,
            'locales' => $locales,
            'editable' => $editable,
            'estados' => RemitoInternoEstadosSupport::opcionesSelect(),
        ];
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }

    private function assertPuedeConsultarArticulos(): void
    {
        if (
            can('crear-remito-interno-facturacion-local', false)
            || can('actualizar-remito-interno-facturacion-local', false)
            || can('ver-remito-interno-facturacion-local', false)
        ) {
            return;
        }
        can('crear-remito-interno-facturacion-local');
    }
}
