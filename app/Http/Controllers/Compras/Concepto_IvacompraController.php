<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConcepto_Ivacompra;
use App\Repositories\Compras\Columna_IvacompraRepositoryInterface;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Repositories\Compras\Concepto_Ivacompra_CondicionivaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Configuracion\ImpuestoRepositoryInterface;
use App\Repositories\Configuracion\ProvinciaRepositoryInterface;
use App\Repositories\Configuracion\CondicionivaRepositoryInterface;
use App\Models\Compras\Concepto_Ivacompra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Concepto_IvacompraController extends Controller
{
	private $concepto_ivacompraRepository;
    private $concepto_ivacompra_condicionivaRepository;
    private $columna_ivacompraRepository;
    private $cuentacontableRepository;
    private $impuestoRepository;
    private $provinciaRepository;
    private $condicionivaRepository;

	public function __construct(Concepto_IvacompraRepositoryInterface $concepto_ivacomprarepository,
                                Concepto_Ivacompra_CondicionivaRepositoryInterface $concepto_ivacompra_condicionivarepository,
                                Columna_IvacompraRepositoryInterface $columna_ivacomprarepository,
                                CuentacontableRepositoryInterface $cuentacontablerepository,
                                ImpuestoRepositoryInterface $impuestorepository,
                                ProvinciaRepositoryInterface $provinciarepository,
                                CondicionivaRepositoryInterface $condicionivarepository)
    {
        $this->concepto_ivacompraRepository = $concepto_ivacomprarepository;
        $this->concepto_ivacompra_condicionivaRepository = $concepto_ivacompra_condicionivarepository;
        $this->columna_ivacompraRepository = $columna_ivacomprarepository;
        $this->cuentacontableRepository = $cuentacontablerepository;
        $this->impuestoRepository = $impuestorepository;
        $this->provinciaRepository = $provinciarepository;
        $this->condicionivaRepository = $condicionivarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-concepto-iva-compra');
		
		$datas = $this->concepto_ivacompraRepository->all();

        return view('compras.concepto_ivacompra.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-concepto-iva-compra');

		$tipoconcepto_enum = Concepto_Ivacompra::$enumTipoConcepto;
        $retiene_enum = Concepto_Ivacompra::$enumRetiene;
        $cuentacontable_query = $this->cuentacontableRepository->all();
        $impuesto_query = $this->impuestoRepository->all();
        $columna_ivacompra_query = $this->columna_ivacompraRepository->all();
        $provincia_query = $this->provinciaRepository->all();
        $condicioniva_query = $this->condicionivaRepository->all();

        return view('compras.concepto_ivacompra.crear', compact('tipoconcepto_enum', 'retiene_enum', 
                                                                'cuentacontable_query', 'impuesto_query', 
                                                                'columna_ivacompra_query', 'provincia_query',
                                                                'condicioniva_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionConcepto_Ivacompra $request)
    {
        $concepto_ivacompra = $this->concepto_ivacompraRepository->create($request->all());

		if ($concepto_ivacompra)
		{
            $condicioniva_ids = $request->input('condicioniva_ids', []);
    		for ($i_condicion=0; $i_condicion < count($condicioniva_ids); $i_condicion++) {
        		if ($condicioniva_ids[$i_condicion] > 0)
				{
        			$concepto_ivacompra_condicioniva = $this->concepto_ivacompra_condicionivaRepository->create([
					  									'concepto_ivacompra_id' => $concepto_ivacompra->id,
            											'condicioniva_id' => $condicioniva_ids[$i_condicion], 
														]);
        		}
    		}
		}

    	return redirect('compras/concepto_ivacompra')->with('mensaje', 'Concepto iva compras creado con exito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-concepto-iva-compra');

		$data = $this->concepto_ivacompraRepository->find($id);

		$tipoconcepto_enum = Concepto_Ivacompra::$enumTipoConcepto;
        $retiene_enum = Concepto_Ivacompra::$enumRetiene;
        $cuentacontable_query = $this->cuentacontableRepository->all();
        $impuesto_query = $this->impuestoRepository->all();
        $columna_ivacompra_query = $this->columna_ivacompraRepository->all();
        $provincia_query = $this->provinciaRepository->all();
        $condicioniva_query = $this->condicionivaRepository->all();

        return view('compras.concepto_ivacompra.editar', compact('data', 'tipoconcepto_enum', 
                                                                'retiene_enum', 'cuentacontable_query',
                                                                'impuesto_query', 'columna_ivacompra_query',
                                                                'provincia_query', 'condicioniva_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionConcepto_Ivacompra $request, $id)
    {
        can('actualizar-concepto-iva-compra');

        $concepto_ivacompra = $this->concepto_ivacompraRepository->update($request->all(), $id);

        $this->concepto_ivacompra_condicionivaRepository->deletePorConcepto_Ivacompra($id);

		if ($concepto_ivacompra)
		{
            $condicioniva_ids = $request->input('condicioniva_ids', []);
    		for ($i_condicion=0; $i_condicion < count($condicioniva_ids); $i_condicion++) {
        		if ($condicioniva_ids[$i_condicion] > 0)
				{
        			$this->concepto_ivacompra_condicionivaRepository->create([
					  									'concepto_ivacompra_id' => $id,
            											'condicioniva_id' => $condicioniva_ids[$i_condicion], 
														]);
        		}
    		}
		}
		return redirect('compras/concepto_ivacompra')->with('mensaje', 'Concepto iva compras actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-concepto-iva-compra');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->concepto_ivacompraRepository->delete($id))
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
