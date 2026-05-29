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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Precarga_Comprobante_ProveedorController extends Controller
{
	private $precarga_comprobante_proveedorRepository;
    private $precarga_comprobante_proveedor_conceptoRepository;
    private $tipotransaccion_compraRepository;
    protected $concepto_ivacompraRepository;
    private $empresaRepository;
    private PrecargaComprobanteAnitaSyncService $precargaAnitaSync;

	public function __construct(Precarga_Comprobante_ProveedorRepositoryInterface $precarga_comprobante_proveedorRepository,
                                Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface $precarga_comprobante_proveedor_conceptoRepository,
                                EmpresaRepositoryInterface $empresaRepository,
                                Tipotransaccion_CompraRepositoryInterface $tipotransaccion_comprarepository,
                                Concepto_IvacompraRepositoryInterface $concepto_ivacompraRepository,
                                PrecargaComprobanteAnitaSyncService $precargaAnitaSync,
                                )
    {
        $this->precarga_comprobante_proveedorRepository = $precarga_comprobante_proveedorRepository;
        $this->precarga_comprobante_proveedor_conceptoRepository = $precarga_comprobante_proveedor_conceptoRepository;
        $this->empresaRepository = $empresaRepository;
        $this->tipotransaccion_compraRepository = $tipotransaccion_comprarepository;
        $this->concepto_ivacompraRepository = $concepto_ivacompraRepository;
        $this->precargaAnitaSync = $precargaAnitaSync;
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

        $datas = ['datas' => $precarga, 'busqueda' => $busqueda];

        return view('compras.precarga_comprobante_proveedor.index', $datas);
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
