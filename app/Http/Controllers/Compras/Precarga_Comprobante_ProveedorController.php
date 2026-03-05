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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Precarga_Comprobante_ProveedorController extends Controller
{
	private $precarga_comprobante_proveedorRepository;
    private $precarga_comprobante_proveedor_conceptoRepository;
    private $tipotransaccion_compraRepository;
    protected $concepto_ivacompraRepository;
    private $empresaRepository;

	public function __construct(Precarga_Comprobante_ProveedorRepositoryInterface $precarga_comprobante_proveedorRepository,
                                Precarga_Comprobante_Proveedor_ConceptoRepositoryInterface $precarga_comprobante_proveedor_conceptoRepository,
                                EmpresaRepositoryInterface $empresaRepository,
                                Tipotransaccion_CompraRepositoryInterface $tipotransaccion_comprarepository,
                                Concepto_IvacompraRepositoryInterface $concepto_ivacompraRepository
                                )
    {
        $this->precarga_comprobante_proveedorRepository = $precarga_comprobante_proveedorRepository;
        $this->precarga_comprobante_proveedor_conceptoRepository = $precarga_comprobante_proveedor_conceptoRepository;
        $this->empresaRepository = $empresaRepository;
        $this->tipotransaccion_compraRepository = $tipotransaccion_comprarepository;
        $this->concepto_ivacompraRepository = $concepto_ivacompraRepository;
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
        DB::beginTransaction();
        try
        {
            $precarga_comprobante_proveedor = $this->precarga_comprobante_proveedorRepository->create($request->all());

            if ($precarga_comprobante_proveedor)
            {
                $concepto_ids = $request->input('concepto_ivacompra_ids', []);
                $montos = $request->input('montos', []);

                for ($i_concepto=0; $i_concepto < count($concepto_ids); $i_concepto++) {
                    if ($concepto_ids[$i_concepto] > 0)
                    {
                        $concepto_ivacompra_condicioniva = $this->precarga_comprobante_proveedor_conceptoRepository->create([
                                                            'precarga_comprobante_proveedor_id' => $precarga_comprobante_proveedor->id,
                                                            'concepto_ivacompra_id' => $concepto_ids[$i_concepto], 
                                                            'monto' => $montos[$i_concepto]
                                                            ]);
                    }
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['errores' => $e->getMessage()];
        }    

    	return redirect('compras/precarga_comprobante_proveedor')->with('mensaje', 'Concepto iva compras creado con exito');
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

        $precarga_comprobante_proveedor = $this->precarga_comprobante_proveedorRepository->update($request->all(), $id);

        $this->precarga_comprobante_proveedor_conceptoRepository->deletePorPrecargaComprobanteProveedor($id);

		if ($precarga_comprobante_proveedor)
		{
            $concepto_ids = $request->input('concepto_ivacompra_ids', []);
            $montos = $request->input('montos', []);

    		for ($i_concepto=0; $i_concepto < count($concepto_ids); $i_concepto++) {
                if ($concepto_ids[$i_concepto] > 0)
                {
                    $concepto_ivacompra_condicioniva = $this->precarga_comprobante_proveedor_conceptoRepository->create([
                                                        'precarga_comprobante_proveedor_id' => $id,
                                                        'concepto_ivacompra_id' => $concepto_ids[$i_concepto], 
                                                        'monto' => $montos[$i_concepto]
                                                        ]);
                }
    		}
		}
		return redirect('compras/precarga_comprobante_proveedor')->with('mensaje', 'Concepto iva compras actualizado con exito');
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
