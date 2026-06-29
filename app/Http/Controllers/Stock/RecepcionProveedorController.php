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
use App\Services\Stock\RecepcionProveedorAsientoService;
use App\Services\Stock\RecepcionProveedorOcrService;
use App\Services\Stock\RecepcionProveedorOrdencompraResolverService;
use App\Services\Stock\RecepcionProveedorPdfService;
use App\Services\Stock\RecepcionProveedorService;
use App\Support\Stock\RecepcionProveedorArticuloProveedorSyncSupport;
use App\Support\Stock\RecepcionProveedorListadoFiltros;
use App\Support\Stock\RecepcionProveedorOcPendienteSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class RecepcionProveedorController extends Controller
{
    public function __construct(
        private readonly RecepcionProveedorService $service,
        private readonly RecepcionProveedorOrdencompraResolverService $ocResolver,
        private readonly RecepcionProveedorOcrService $ocrService,
        private readonly RecepcionProveedorPdfService $pdfService,
        private readonly RecepcionProveedorAsientoService $asientoService,
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
            return back()->withInput()->withErrors(['general' => $e->getMessage()]);
        }

        return redirect('stock/recepcion-proveedor/'.$recepcion->id.'/editar')
            ->with('mensaje', 'Recepción creada en BORRADOR. Revise los datos y confirme.');
    }

    public function editar(int $id)
    {
        $recepcion = $this->service->buscar($id);

        if ($recepcion->estado === 'BORRADOR') {
            if (! can('editar-recepcion-proveedor', false) && ! can('actualizar-recepcion-proveedor', false)) {
                can('editar-recepcion-proveedor');
            }
        } else {
            can('editar-recepcion-proveedor');
        }
        $empresa_query = $this->empresaRepository->allFiltrado();
        $moneda_query = Moneda::query()->orderBy('nombre')->get();
        $asientoPreview = $this->asientoService->previewParaVista($recepcion);

        return view('stock.recepcion_proveedor.editar', compact(
            'recepcion', 'empresa_query', 'moneda_query', 'asientoPreview'
        ));
    }

    public function actualizar(ValidacionRecepcionProveedor $request, int $id)
    {
        can('actualizar-recepcion-proveedor');

        try {
            $this->service->actualizar($id, $request->validated(), $request);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['general' => $e->getMessage()]);
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

    public function apiPreviewArticuloProveedor(Request $request): JsonResponse
    {
        if (! config('recepcion_proveedor.modal_articulo_proveedor_habilitado')) {
            return response()->json(['requiere_modal' => false, 'lineas' => []]);
        }

        if (! can('crear-recepcion-proveedor', false) && ! can('actualizar-recepcion-proveedor', false)) {
            can('crear-recepcion-proveedor');
        }

        $request->validate([
            'proveedor_id' => 'required|integer|min:1',
            'fecha' => 'nullable|date',
            'items' => 'required|array|min:1',
        ]);

        try {
            $preview = RecepcionProveedorArticuloProveedorSyncSupport::previewDesdeItems(
                (int) $request->input('proveedor_id'),
                $request->input('items', []),
                (string) ($request->input('fecha') ?: date('Y-m-d'))
            );
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(array_merge($preview, [
            'unidades' => RecepcionProveedorArticuloProveedorSyncSupport::unidadesMedidaParaModal(),
        ]));
    }

    public function apiPrecargaOc(Request $request): JsonResponse
    {
        can('crear-recepcion-proveedor');

        $ordencompraId = (int) $request->input('ordencompra_id', 0);
        $numeroOc = (int) $request->input('numero_oc', 0);

        try {
            if ($ordencompraId > 0) {
                $data = $this->ocResolver->resolverPorId($ordencompraId, true);
            } elseif ($numeroOc > 0) {
                $data = $this->service->precargaDesdeOc($numeroOc);
            } else {
                return response()->json(['error' => 'Indique número de OC o seleccione una desde AnitaERP'], 422);
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $oc = $data['cabecera'];

        return response()->json([
            'ordencompra_id' => $oc->id,
            'numeroordencompra' => $oc->numeroordencompra,
            'empresa_id' => $oc->empresa_id,
            'centrocosto_id' => $oc->centrocosto_id,
            'proveedor_id' => $oc->proveedor_id,
            'proveedor_nombre' => optional($oc->proveedores)->nombre,
            'empresa_nombre' => optional($oc->empresas)->nombre,
            'tratamiento' => $oc->tratamiento,
            'lineas' => $data['lineas'],
        ]);
    }

    public function apiBuscarOcPendientes(Request $request): JsonResponse
    {
        if (! can('crear-recepcion-proveedor', false) && ! can('editar-recepcion-proveedor', false)) {
            can('crear-recepcion-proveedor');
        }

        $proveedorId = (int) $request->query('proveedor_id', 0) ?: null;
        $consulta = $request->query('q');
        $consulta = is_string($consulta) ? trim($consulta) : null;

        return response()->json(
            RecepcionProveedorOcPendienteSupport::buscar($proveedorId, $consulta !== '' ? $consulta : null)
        );
    }

    public function subirOcr(Request $request, int $id): JsonResponse
    {
        can('ocr-recepcion-proveedor');

        return $this->responderOcr($request, fn (UploadedFile $archivo) => $this->ocrService->procesarArchivo($id, $archivo));
    }

    public function descargarArchivo(Request $request, int $id, int $archivo)
    {
        if (! can('listar-recepcion-proveedor', false) && ! can('editar-recepcion-proveedor', false)) {
            can('listar-recepcion-proveedor');
        }

        $recepcion = $this->repository->find($id);
        $registro = $recepcion->recepcion_proveedor_archivos->firstWhere('id', $archivo);
        if ($registro === null) {
            abort(404);
        }

        $ruta = $this->ocrService->rutaAbsoluta($registro);
        if (! is_file($ruta)) {
            abort(404, 'Archivo no encontrado en el servidor.');
        }

        if ($request->query('inline') === '1') {
            return response()->file($ruta, [
                'Content-Disposition' => 'inline; filename="'.addslashes($registro->nombre).'"',
            ]);
        }

        return response()->download($ruta, $registro->nombre);
    }

    public function procesarOcrPreview(Request $request): JsonResponse
    {
        can('ocr-recepcion-proveedor');

        $ordencompraId = (int) $request->input('ordencompra_id', 0) ?: null;
        $numeroOc = (int) $request->input('numero_oc', 0) ?: null;

        return $this->responderOcr(
            $request,
            fn (UploadedFile $archivo) => $this->ocrService->procesarArchivoPreview($archivo, $ordencompraId, $numeroOc)
        );
    }

    /**
     * @param  callable(UploadedFile): array<string, mixed>  $procesar
     */
    private function responderOcr(Request $request, callable $procesar): JsonResponse
    {
        /** @var UploadedFile|null $archivo */
        $archivo = $request->file('archivo');
        if ($archivo === null || ! $archivo->isValid()) {
            return response()->json(['error' => $this->mensajeErrorSubidaOcr($archivo)], 422);
        }

        $request->validate([
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        try {
            $resultado = $procesar($archivo);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($resultado);
    }

    private function mensajeErrorSubidaOcr(?UploadedFile $archivo): string
    {
        if ($archivo === null) {
            return 'No se recibió el archivo. Si la foto pesa varios MB, el servidor puede rechazarla '
                .'(límite PHP: '.ini_get('upload_max_filesize').'). Recargue la página e intente de nuevo.';
        }

        return match ($archivo->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el límite de subida del servidor ('
                .ini_get('upload_max_filesize').'). La imagen debería comprimirse automáticamente; recargue la página (Ctrl+F5) e intente otra vez.',
            UPLOAD_ERR_PARTIAL => 'La subida se interrumpió. Intente nuevamente.',
            UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo.',
            default => 'No se pudo subir el archivo (código '.$archivo->getError().').',
        };
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

    public function imprimirCom(Request $request, int $id)
    {
        can('listar-recepcion-proveedor');

        return $this->pdfService->descargarCom($id, $request->boolean('inline'));
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

    public function eliminar(Request $request, int $id)
    {
        can('borrar-recepcion-proveedor');

        if ($request->ajax()) {
            try {
                $this->service->eliminarBorrador($id);

                return response()->json(['mensaje' => 'ok']);
            } catch (\Throwable $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        }

        try {
            $this->service->eliminarBorrador($id);
        } catch (\Throwable $e) {
            return redirect('stock/recepcion-proveedor')
                ->with('mensaje', 'Error al eliminar: '.$e->getMessage());
        }

        return redirect('stock/recepcion-proveedor')
            ->with('mensaje', 'Borrador de recepción eliminado.');
    }
}
