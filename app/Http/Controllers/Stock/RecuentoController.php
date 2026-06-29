<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\RecuentoDetalleExport;
use App\Exports\Stock\RecuentoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionRecuento;
use App\Imports\Stock\RecuentoImport;
use App\Models\Stock\Depmae;
use App\Models\Stock\Recuento;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Stock\DepmaeRepositoryInterface;
use App\Repositories\Stock\RecuentoRepositoryInterface;
use App\Services\Stock\RecuentoService;
use App\Support\Stock\ArticuloPrecioUltimaCompraSupport;
use App\Support\Stock\RecuentoListadoFiltros;
use App\Support\Stock\RecuentoModoCierreSupport;
use App\Support\Stock\RecuentoVisibilidadSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;

class RecuentoController extends Controller
{
    public function __construct(
        private readonly RecuentoService $service,
        private readonly RecuentoRepositoryInterface $recuentoRepository,
        private readonly DepmaeRepositoryInterface $depmaeRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-recuento');

        $filtros = $this->resolverFiltrosListado($request);
        $recuentos = $this->recuentoRepository->leeRecuentos($filtros, true);

        return view('stock.recuento.index', [
            'recuentos' => $recuentos,
            'busqueda' => $filtros['busqueda'],
            'filtros' => $filtros,
            'filtrosQuery' => RecuentoListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => RecuentoListadoFiltros::CAMPOS,
            'ver_todos_recuentos' => ! empty($filtros['ver_todos_recuentos']),
            'puede_ver_todos_recuentos' => RecuentoVisibilidadSupport::puedeVerTodos(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-recuento');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $recuentos = $this->recuentoRepository->leeRecuentos($filtros, false);
                $view = \View::make('stock.recuento.listado', compact('recuentos'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_recuento';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new RecuentoExport($this->recuentoRepository))
                    ->parametros($filtros)
                    ->download('recuento.xlsx');

            case 'CSV':
                return (new RecuentoExport($this->recuentoRepository))
                    ->parametros($filtros)
                    ->download('recuento.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('recuento', RecuentoListadoFiltros::paraQueryString($filtros));
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = RecuentoListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);

        return RecuentoListadoFiltros::aplicarAlcanceUsuario($filtros, (int) auth()->id());
    }

    public function crear(Request $request)
    {
        can('crear-recuento');
        $depositos = $this->depmaeRepository->allFiltrado();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $tipo = $request->query('tipo', Recuento::TIPO_MANUAL);

        return view('stock.recuento.crear', compact('depositos', 'empresa_query', 'tipo'));
    }

    public function guardar(ValidacionRecuento $request)
    {
        can('crear-recuento');

        try {
            $data = $request->validated();
            $data['tipo'] = $request->input('tipo', Recuento::TIPO_MANUAL);
            $data['cantidad_aleatoria'] = $request->input('cantidad_aleatoria');
            $recuento = $this->service->guardar($data, $request);
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', 'Error al crear el recuento: '.$e->getMessage());
        }

        return redirect('stock/recuento/'.$recuento->id.'/editar')
            ->with('mensaje', 'Recuento creado en estado PENDIENTE.');
    }

    public function editar(int $id)
    {
        can('editar-recuento');
        $recuento = $this->service->buscar($id);
        $depositos = $this->depmaeRepository->allFiltrado();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = (int) ($recuento->empresa_id ?? $empresa_query->first()->id);
        $soloLectura = ! $recuento->esEditable();

        return view('stock.recuento.editar', compact(
            'recuento', 'depositos', 'empresa_query', 'empresa_id', 'soloLectura'
        ));
    }

    public function actualizar(ValidacionRecuento $request, int $id)
    {
        can('actualizar-recuento');

        try {
            $this->service->actualizar($id, $request->validated(), $request);
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', 'Error al actualizar: '.$e->getMessage());
        }

        return redirect('stock/recuento/'.$id.'/editar')
            ->with('mensaje', 'Recuento actualizado.');
    }

    public function eliminar(Request $request, int $id)
    {
        can('borrar-recuento');
        try {
            $this->service->eliminar($id);
        } catch (\Throwable $e) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
            }

            return back()->with('mensaje', $e->getMessage());
        }

        if ($request->ajax()) {
            return response()->json(['mensaje' => 'ok']);
        }

        return redirect('stock/recuento')->with('mensaje', 'Recuento eliminado.');
    }

    public function ver(int $id)
    {
        can('ver-recuento');
        $recuento = $this->service->buscar($id);

        return view('stock.recuento.ver', compact('recuento'));
    }

    public function suspender(Request $request, int $id)
    {
        can('suspender-recuento');
        try {
            $this->service->suspender($id, $request->input('observaciones'));
        } catch (\Throwable $e) {
            return back()->with('mensaje', $e->getMessage());
        }

        return back()->with('mensaje', 'Recuento suspendido.');
    }

    public function reactivar(Request $request, int $id)
    {
        can('reactivar-recuento');
        try {
            $this->service->reactivar($id, $request->input('observaciones'));
        } catch (\Throwable $e) {
            return back()->with('mensaje', $e->getMessage());
        }

        return back()->with('mensaje', 'Recuento reactivado.');
    }

    public function anular(Request $request, int $id)
    {
        can('anular-recuento');
        try {
            $this->service->anular($id, $request->input('observaciones'));
        } catch (\Throwable $e) {
            return back()->with('mensaje', $e->getMessage());
        }

        return redirect('stock/recuento/'.$id.'/ver')->with('mensaje', 'Recuento anulado.');
    }

    public function cerrarParcial(Request $request, int $id)
    {
        can('cerrar-recuento-parcial');
        $modo = RecuentoModoCierreSupport::resolverModo($request->input('modo_cierre'));
        try {
            $this->service->cerrarParcial($id, $request->input('observaciones'), $modo);
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo cerrar parcialmente: '.$e->getMessage());
        }

        return redirect('stock/recuento/'.$id.'/ver')
            ->with('mensaje', 'Recuento cerrado parcialmente ('.RecuentoModoCierreSupport::etiqueta($modo).').');
    }

    public function cerrarTotal(Request $request, int $id)
    {
        can('cerrar-recuento-total');
        $modo = RecuentoModoCierreSupport::resolverModo($request->input('modo_cierre'));
        try {
            $this->service->cerrarTotal($id, $request->input('observaciones'), $modo);
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo cerrar totalmente: '.$e->getMessage());
        }

        return redirect('stock/recuento/'.$id.'/ver')
            ->with('mensaje', 'Recuento cerrado totalmente ('.RecuentoModoCierreSupport::etiqueta($modo).').');
    }

    public function anularCierre(Request $request, int $id)
    {
        can('anular-cierre-recuento');
        try {
            $this->service->anularCierre($id, $request->input('observaciones'));
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'No se pudo anular el cierre: '.$e->getMessage());
        }

        return redirect('stock/recuento/'.$id.'/editar')
            ->with('mensaje', 'Cierre anulado. El recuento volvió a estado PENDIENTE.');
    }

    public function pdf(int $id)
    {
        can('imprimir-recuento');
        $recuento = $this->service->buscar($id);
        ArticuloPrecioUltimaCompraSupport::enriquecerLineas($recuento->items);

        $html = view('stock.recuento.pdf', compact('recuento'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html);

        $nombreArchivo = 'Recuento_'.preg_replace('/[^\w\-]+/', '_', (string) $recuento->codigo).'.pdf';

        return $pdf->download($nombreArchivo);
    }

    public function excel(int $id)
    {
        can('imprimir-recuento');
        $recuento = $this->service->buscar($id);
        ArticuloPrecioUltimaCompraSupport::enriquecerLineas($recuento->items);

        $nombreArchivo = 'Recuento_'.preg_replace('/[^\w\-]+/', '_', (string) $recuento->codigo).'.xlsx';

        return (new RecuentoDetalleExport)
            ->parametros($recuento)
            ->download($nombreArchivo);
    }

    public function saldoArticulo(Request $request): JsonResponse
    {
        $articuloId = (int) $request->query('articulo_id', 0);
        $depositoId = (int) $request->query('deposito_id', 0);

        try {
            $saldo = $this->service->saldoArticulo($articuloId, $depositoId);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['saldo' => $saldo]);
    }

    public function aleatorio(Request $request): JsonResponse
    {
        can('recuento-aleatorio');
        $depositoId = (int) $request->input('deposito_id', 0);
        $cantidad = (int) $request->input('cantidad', 0);

        if ($depositoId <= 0 || $cantidad <= 0) {
            return response()->json(['error' => 'Depósito y cantidad son requeridos.'], 422);
        }

        try {
            $lineas = $this->service->generarLineasAleatorias($depositoId, $cantidad);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['lineas' => $lineas]);
    }

    public function importarPreview(Request $request)
    {
        can('importar-recuento');
        $request->validate([
            'deposito_id' => 'required|integer|min:1',
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'col_sku' => 'required|string|max:100',
            'col_cantidad' => 'required|string|max:100',
            'col_detalle' => 'nullable|string|max:100',
        ]);

        try {
            $import = new RecuentoImport(
                $request->input('col_sku'),
                $request->input('col_cantidad'),
                $request->input('col_detalle')
            );
            Excel::import($import, $request->file('archivo'));
            $lineas = $this->service->lineasDesdeImportacion((int) $request->input('deposito_id'), $import->filas());
            $mensaje = count($lineas).' líneas importadas.';

            return response()->json([
                'ok' => true,
                'preview' => true,
                'mensaje' => $mensaje,
                'lineas' => $this->lineasArrayParaFormulario($lineas),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error al importar: '.$e->getMessage()], 422);
        }
    }

    public function importarForm(int $id)
    {
        can('importar-recuento');
        $recuento = $this->service->buscar($id);
        if (! $recuento->esEditable()) {
            return redirect('stock/recuento/'.$id.'/ver')
                ->with('mensaje', 'No se puede importar en el estado actual.');
        }

        return view('stock.recuento.importar', compact('recuento'));
    }

    public function importar(Request $request, int $id)
    {
        can('importar-recuento');
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'col_sku' => 'required|string|max:100',
            'col_cantidad' => 'required|string|max:100',
            'col_detalle' => 'nullable|string|max:100',
        ]);

        $recuento = $this->service->buscar($id);
        if (! $recuento->esEditable()) {
            $mensaje = 'No se puede importar en el estado actual.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $mensaje], 422);
            }

            return back()->with('mensaje', $mensaje);
        }

        try {
            $import = new RecuentoImport(
                $request->input('col_sku'),
                $request->input('col_cantidad'),
                $request->input('col_detalle')
            );
            Excel::import($import, $request->file('archivo'));
            $recuento = $this->service->importarLineas($id, $import->filas());
            $recuento->load(['items.articulos', 'items.unidadmedida']);

            $mensaje = $recuento->items->count().' líneas importadas.';
            session()->flash('mensaje', $mensaje);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'mensaje' => $mensaje,
                    'lineas' => $this->lineasParaFormulario($recuento),
                ]);
            }
        } catch (\Throwable $e) {
            $mensaje = 'Error al importar: '.$e->getMessage();
            if ($request->expectsJson()) {
                return response()->json(['message' => $mensaje], 422);
            }

            return back()->withInput()->with('mensaje', $mensaje);
        }

        return redirect('stock/recuento/'.$id.'/editar');
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     * @return array<int, array<string, mixed>>
     */
    private function lineasArrayParaFormulario(array $lineas): array
    {
        return array_map(fn (array $ln) => [
            'recuento_item_id' => $ln['recuento_item_id'] ?? '',
            'articulo_id' => $ln['articulo_id'] ?? '',
            'sku' => $ln['sku'] ?? '',
            'descripcion' => $ln['descripcion'] ?? '',
            'detalle' => $ln['detalle'] ?? '',
            'unidadmedida_id' => $ln['unidadmedida_id'] ?? '',
            'unidadmedida' => $ln['unidadmedida'] ?? '',
            'saldo_sistema' => $ln['saldo_sistema'] ?? '',
            'cantidad_contada' => $ln['cantidad_contada'] ?? '',
        ], $lineas);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lineasParaFormulario(Recuento $recuento): array
    {
        return $recuento->items->map(fn ($i) => [
            'recuento_item_id' => $i->id,
            'articulo_id' => $i->articulo_id,
            'sku' => optional($i->articulos)->sku,
            'descripcion' => optional($i->articulos)->descripcion,
            'detalle' => $i->detalle,
            'unidadmedida_id' => $i->unidadmedida_id,
            'unidadmedida' => optional($i->unidadmedida)->abreviatura ?? optional($i->articulos?->unidadesdemedidas)->abreviatura,
            'saldo_sistema' => $i->saldo_sistema,
            'cantidad_contada' => $i->cantidad_contada,
        ])->all();
    }

    public function descargarArchivo(int $id, string $nombre)
    {
        can('ver-recuento');
        $recuento = $this->service->buscar($id);
        $path = public_path('storage/archivos/recuentos/'.$recuento->id.'/'.basename($nombre));
        if (! is_file($path)) {
            abort(404);
        }

        return response()->download($path);
    }
}
