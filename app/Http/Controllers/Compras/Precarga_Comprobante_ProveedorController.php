<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPrecarga_Comprobante_Proveedor;
use App\Repositories\Compras\Precarga_Comprobante_ProveedorRepositoryInterface;
use App\Repositories\Compras\Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface;
use App\Repositories\Compras\Tipotransaccion_CompraRepositoryInterface;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\PrecargaComprobanteAnitaSyncService;
use App\Services\Compras\ComprobanteProveedorPdfIaService;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Precarga_Comprobante_ProveedorController extends Controller
{
	private $precarga_comprobante_proveedorRepository;
    private $precarga_comprobante_proveedor_conceptoRepository;
    private $tipotransaccion_compraRepository;
    protected $concepto_ivacompraRepository;
    private $empresaRepository;
    private PrecargaComprobanteAnitaSyncService $precargaAnitaSync;

    private PrecargaFacturaScanPathResolver $facturaScanPathResolver;

    private ComprobanteProveedorPdfIaService $pdfIaService;

	public function __construct(Precarga_Comprobante_ProveedorRepositoryInterface $precarga_comprobante_proveedorRepository,
                                Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface $precarga_comprobante_proveedor_conceptoRepository,
                                EmpresaRepositoryInterface $empresaRepository,
                                Tipotransaccion_CompraRepositoryInterface $tipotransaccion_comprarepository,
                                Concepto_IvacompraRepositoryInterface $concepto_ivacompraRepository,
                                PrecargaComprobanteAnitaSyncService $precargaAnitaSync,
                                PrecargaFacturaScanPathResolver $facturaScanPathResolver,
                                ComprobanteProveedorPdfIaService $pdfIaService,
                                )
    {
        $this->precarga_comprobante_proveedorRepository = $precarga_comprobante_proveedorRepository;
        $this->precarga_comprobante_proveedor_conceptoRepository = $precarga_comprobante_proveedor_conceptoRepository;
        $this->empresaRepository = $empresaRepository;
        $this->tipotransaccion_compraRepository = $tipotransaccion_comprarepository;
        $this->concepto_ivacompraRepository = $concepto_ivacompraRepository;
        $this->precargaAnitaSync = $precargaAnitaSync;
        $this->facturaScanPathResolver = $facturaScanPathResolver;
        $this->pdfIaService = $pdfIaService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-precarga-proveedores');
		
        $busqueda = $request->busqueda;

        $precarga = $this->precarga_comprobante_proveedorRepository->leePrecargaComprobanteProveedor($busqueda, true);

        $datas = [
            'datas' => $precarga,
            'busqueda' => $busqueda,
            'pdfIaHabilitado' => (bool) config('comprobante_proveedor_pdf_ia.habilitado'),
        ];

        return view('compras.precarga_comprobante_proveedor.index', $datas);
    }

    public function previewPdfIa(Request $request)
    {
        can('crear-precarga-proveedores');

        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:20480',
            'numero_oc' => 'nullable|string|max:12',
        ]);

        try {
            $preview = $this->pdfIaService->preview(
                $request->file('pdf'),
                $request->input('numero_oc')
            );

            return response()->json($preview, ($preview['ok'] ?? false) ? 200 : 422);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => 'Error al procesar PDF con IA.'], 500);
        }
    }

    public function resolverOcPdfIa(Request $request)
    {
        can('crear-precarga-proveedores');

        $request->validate([
            'extraccion' => 'required|json',
            'numero_oc' => 'required|string|max:12',
        ]);

        $extraccion = json_decode((string) $request->input('extraccion'), true);
        if (! is_array($extraccion)) {
            return response()->json(['ok' => false, 'message' => 'Extracción inválida.'], 422);
        }

        try {
            $preview = $this->pdfIaService->resolverConOcManual(
                $extraccion,
                (string) $request->input('numero_oc')
            );

            return response()->json($preview);
        } catch (RuntimeException $e) {
            return response()->json([
                'ok' => false,
                'oc_requerida' => true,
                'message' => $e->getMessage(),
                'extraccion' => $extraccion,
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => 'Error al resolver con OC.'], 500);
        }
    }

    public function confirmarPdfIa(Request $request)
    {
        can('crear-precarga-proveedores');

        $request->validate([
            'payload' => 'required|json',
            'pdf' => 'required|file|mimes:pdf|max:20480',
        ]);

        $payload = json_decode((string) $request->input('payload'), true);
        if (! is_array($payload)) {
            return response()->json(['ok' => false, 'message' => 'Payload inválido.'], 422);
        }

        try {
            $resultado = $this->pdfIaService->confirmar($payload, $request->file('pdf'));

            return response()->json([
                'ok' => true,
                'precarga_id' => $resultado['precarga_id'],
                'message' => $resultado['message'],
                'redirect' => route('editar_precarga_comprobante_proveedor', ['id' => $resultado['precarga_id']]),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => 'Error al grabar precarga desde PDF+IA.'], 500);
        }
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-precarga-proveedores'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $precarga = $this->precarga_comprobante_proveedorRepository->leePrecargaComprobanteProveedor($busqueda, false);

            $view =  \View::make('compras.precarga_comprobante_proveedor.listado', compact('precarga'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_precarga_comprobante_proveedor';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new CapexExport($this->precarga_comprobante_proveedorRepository))
                        ->parametros($busqueda)
                        ->download('precarga_comprobante_proveedor.xlsx');
            break;

        case 'CSV':
            return (new CapexExport($this->precarga_comprobante_proveedorRepository))
                        ->parametros($busqueda)
                        ->download('precarga_comprobante_proveedor.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['datas' => $precarga, 'busqueda' => $busqueda];

		return view('compras.precarga_comprobante_proveedor.index', $datas);       
    }

    public function verFacturaPdf(Request $request, int $id): BinaryFileResponse
    {
        if (! puedeVerPrecargaFacturaPdf()) {
            abort(403, 'No tiene permiso para ver el PDF de la precarga.');
        }

        $precarga = $this->precarga_comprobante_proveedorRepository->find($id);
        $path = $this->facturaScanPathResolver->resolve($precarga->rutaalmacenamiento);

        if ($path === null) {
            abort(404, 'No se encontró el PDF en /Facturas_scan/comprobantes para: '.$precarga->rutaalmacenamiento);
        }

        $nombre = basename($path);

        if ($request->boolean('inline')) {
            return response()->file($path, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            ]);
        }

        return response()->download($path, $nombre);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-precarga-proveedores');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $tipotransaccion_compra_query = $this->tipotransaccion_compraRepository->all('*');
        $concepto_ivacompra_query = $this->concepto_ivacompraRepository->all();

        return view('compras.precarga_comprobante_proveedor.crear', compact('empresa_query', 'tipotransaccion_compra_query', 'concepto_ivacompra_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionPrecarga_Comprobante_Proveedor $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $payload = $this->precargaAnitaSync->enriquecerPayloadParaAnita($request->all());
                $payload['origen_entrada'] = \App\Support\Compras\PrecargaComprobanteOrigenEntrada::MANUAL;
                $precarga = $this->precarga_comprobante_proveedorRepository->create($payload);

                $this->guardarConceptosPrecarga($request, (int) $precarga->id);
            });
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errores', ['No se pudo guardar la precarga: '.$e->getMessage()]);
        }

        return redirect('compras/precarga_comprobante_proveedor')
            ->with('mensaje', 'Precarga guardada y sincronizada con Anita.');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-precarga-proveedores');

		$data = $this->precarga_comprobante_proveedorRepository->find($id);

        $empresa_query = $this->empresaRepository->allFiltrado();
        $tipotransaccion_compra_query = $this->tipotransaccion_compraRepository->all('*');
        $concepto_ivacompra_query = $this->concepto_ivacompraRepository->all();

        return view('compras.precarga_comprobante_proveedor.editar', compact('data', 'empresa_query', 'tipotransaccion_compra_query',
                                                                                'concepto_ivacompra_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionPrecarga_Comprobante_Proveedor $request, $id)
    {
        can('actualizar-precarga-proveedores');

        try {
            DB::transaction(function () use ($request, $id) {
                $payload = $this->precargaAnitaSync->enriquecerPayloadParaAnita($request->all());
                $this->precarga_comprobante_proveedorRepository->update($payload, $id);

                $this->precarga_comprobante_proveedor_conceptoRepository
                    ->deletePorPrecargaComprobanteProveedor($id);

                $this->guardarConceptosPrecarga($request, (int) $id);
            });
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errores', ['No se pudo actualizar la precarga en Anita: '.$e->getMessage()]);
        }

        return redirect('compras/precarga_comprobante_proveedor')
            ->with('mensaje', 'Precarga actualizada y sincronizada con Anita.');
    }

    private function guardarConceptosPrecarga(Request $request, int $precargaId): void
    {
        $conceptoIds = $request->input('concepto_ivacompra_ids', []);
        $montos = $request->input('montos', []);

        for ($i = 0; $i < count($conceptoIds); $i++) {
            if ((int) $conceptoIds[$i] <= 0) {
                continue;
            }

            $concepto = $this->concepto_ivacompraRepository->find((int) $conceptoIds[$i]);
            if (! $concepto) {
                throw new RuntimeException('Concepto IVA compra id «'.$conceptoIds[$i].'» inexistente.');
            }

            $this->precarga_comprobante_proveedor_conceptoRepository->create([
                'precarga_comprobante_proveedor_id' => $precargaId,
                'concepto_ivacompra_id' => $concepto->id,
                'codigo_concepto_anita' => $concepto->codigo,
                'monto' => $montos[$i] ?? 0,
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-precarga-proveedores');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->precarga_comprobante_proveedorRepository->delete($id))
				$fl_borro = true;

            if ($fl_borro) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
