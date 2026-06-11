<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Exports\Stock\RecepcionProveedorListadoExport;
use App\Http\Requests\ValidacionRecepcionProveedor;
use App\Models\Stock\Configuracion_RecepcionProveedor;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Services\Stock\RecepcionProveedorOcrService;
use App\Services\Stock\RecepcionProveedorOrdencompraResolverService;
use App\Services\Stock\RecepcionProveedorPdfService;
use App\Services\Stock\RecepcionProveedorService;
use App\Support\Stock\RecepcionProveedorListadoFiltros;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecepcionProveedorController extends Controller
{
    public function __construct(
        private readonly RecepcionProveedorService $service,
        private readonly RecepcionProveedorOrdencompraResolverService $ocResolver,
        private readonly RecepcionProveedorOcrService $ocrService,
        private readonly RecepcionProveedorPdfService $pdfService,
        private readonly Recepcion_ProveedorRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-recepcion-proveedor');

        $filtros = RecepcionProveedorListadoFiltros::resolverDesdeRequest($request);
        $coleccion = $this->service->listar($filtros);
        $filtrosQuery = RecepcionProveedorListadoFiltros::paraQueryString($filtros);
        $camposFiltro = RecepcionProveedorListadoFiltros::CAMPOS;

        return view('stock.recepcion_proveedor.index', compact(
            'coleccion', 'filtros', 'filtrosQuery', 'camposFiltro'
        ));
    }

    public function crear()
    {
        can('crear-recepcion-proveedor');
        $empresa_query = $this->empresaRepository->allFiltrado();
        $moneda_query = Moneda::query()->orderBy('nombre')->get();

        return view('stock.recepcion_proveedor.crear', compact('empresa_query', 'moneda_query'));
    }

    public function guardar(ValidacionRecepcionProveedor $request)
    {
        can('crear-recepcion-proveedor');

        try {
            $recepcion = $this->service->guardar($request->validated());
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', 'Error: '.$e->getMessage());
        }

        return redirect('stock/recepcion-proveedor/'.$recepcion->id.'/editar')
            ->with('mensaje', 'Recepción creada en BORRADOR. Revise los datos y confirme.');
    }

    public function editar(int $id)
    {
        can('editar-recepcion-proveedor');
        $recepcion = $this->service->buscar($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $moneda_query = Moneda::query()->orderBy('nombre')->get();

        return view('stock.recepcion_proveedor.editar', compact('recepcion', 'empresa_query', 'moneda_query'));
    }

    public function actualizar(ValidacionRecepcionProveedor $request, int $id)
    {
        can('actualizar-recepcion-proveedor');

        try {
            $this->service->actualizar($id, $request->validated());
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', 'Error: '.$e->getMessage());
        }

        return redirect('stock/recepcion-proveedor/'.$id.'/editar')
            ->with('mensaje', 'Recepción actualizada.');
    }

    public function confirmar(int $id)
    {
        can('confirmar-recepcion-proveedor');

        try {
            $this->service->confirmar($id);
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'Error al confirmar: '.$e->getMessage());
        }

        return redirect('stock/recepcion-proveedor/'.$id.'/editar')
            ->with('mensaje', 'Recepción confirmada. Stock y contabilidad generados.');
    }

    public function apiPrecargaOc(Request $request): JsonResponse
    {
        can('crear-recepcion-proveedor');

        $numeroOc = (int) $request->input('numero_oc', 0);
        if ($numeroOc <= 0) {
            return response()->json(['error' => 'Número de OC inválido'], 422);
        }

        try {
            $data = $this->service->precargaDesdeOc($numeroOc);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $oc = $data['cabecera'];

        return response()->json([
            'ordencompra_id' => $oc->id,
            'numeroordencompra' => $oc->numeroordencompra,
            'empresa_id' => $oc->empresa_id,
            'proveedor_id' => $oc->proveedor_id,
            'proveedor_nombre' => optional($oc->proveedores)->nombre,
            'empresa_nombre' => optional($oc->empresas)->nombre,
            'tratamiento' => $oc->tratamiento,
            'lineas' => $data['lineas'],
        ]);
    }

    public function subirOcr(Request $request, int $id): JsonResponse
    {
        can('ocr-recepcion-proveedor');

        $request->validate([
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        try {
            $resultado = $this->ocrService->procesarArchivo($id, $request->file('archivo'));
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($resultado);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-recepcion-proveedor');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = RecepcionProveedorListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $coleccion = $this->repository->leeRecepciones($filtros, false);
                $view = view('stock.recepcion_proveedor.listado', compact('coleccion'))->render();
                $path = storage_path('pdf/listados');
                $nombre = 'listado_recepcion_proveedor';
                $pdf = app('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombre.'.pdf');

                return response()->download($path.'/'.$nombre.'.pdf');

            case 'EXCEL':
                return (new RecepcionProveedorListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('recepcion_proveedor.xlsx');

            case 'CSV':
                return (new RecepcionProveedorListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('recepcion_proveedor.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('recepcion_proveedor', RecepcionProveedorListadoFiltros::paraQueryString($filtros));
    }

    public function imprimirCom(int $id)
    {
        can('listar-recepcion-proveedor');

        return $this->pdfService->descargarCom($id);
    }

    public function crearDevolucion(int $id)
    {
        can('devolver-recepcion-proveedor');
        $recepcion = $this->service->buscar($id);
        if ($recepcion->estado !== Recepcion_Proveedor::ESTADO_CONFIRMADA || $recepcion->tipo !== Recepcion_Proveedor::TIPO_RECEPCION) {
            return redirect('stock/recepcion-proveedor/'.$id.'/editar')
                ->with('mensaje', 'Solo se puede devolver contra una recepción confirmada.');
        }

        return view('stock.recepcion_proveedor.devolucion', [
            'recepcion' => $recepcion,
            'moneda_query' => Moneda::query()->orderBy('nombre')->get(),
        ]);
    }

    public function guardarDevolucion(ValidacionRecepcionProveedor $request, int $id)
    {
        can('devolver-recepcion-proveedor');

        try {
            $dev = $this->service->crearDevolucion($id, $request->validated());
        } catch (\Throwable $e) {
            return back()->withInput()->with('mensaje', 'Error: '.$e->getMessage());
        }

        return redirect('stock/recepcion-proveedor/'.$dev->id.'/editar')
            ->with('mensaje', 'Devolución registrada y confirmada.');
    }

    public function anular(Request $request, int $id)
    {
        can('anular-recepcion-proveedor');

        try {
            $this->service->anular($id, $request->input('motivo'));
        } catch (\Throwable $e) {
            return back()->with('mensaje', 'Error al anular: '.$e->getMessage());
        }

        return redirect('stock/recepcion-proveedor/'.$id.'/editar')
            ->with('mensaje', 'Recepción anulada. Se revirtió stock, asiento y sincronización Anita.');
    }
}
