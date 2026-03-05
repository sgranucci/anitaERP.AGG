<?php

namespace App\Http\Controllers\Receptivo;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionProveedor_Servicioterrestre;
use App\Repositories\Receptivo\Proveedor_ServicioterrestreRepositoryInterface;
use App\Repositories\Receptivo\ServicioterrestreRepositoryInterface;
use App\Queries\Compras\ProveedorQueryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Proveedor_ServicioterrestreController extends Controller
{
	private $proveedor_servicioterrestreRepository;
    private $servicioterrestreRepository;
    private $proveedorQuery;
    private $monedaRepository;

	public function __construct(Proveedor_ServicioterrestreRepositoryInterface $proveedor_servicioterrestrerepository,
                                ServicioterrestreRepositoryInterface $servicioterrestrerepository,
                                ProveedorQueryInterface $proveedorquery,
                                MonedaRepositoryInterface $monedarepository)
    {
        $this->proveedor_servicioterrestreRepository = $proveedor_servicioterrestrerepository;
        $this->servicioterrestreRepository = $servicioterrestrerepository;
        $this->proveedorQuery = $proveedorquery;
        $this->monedaRepository = $monedarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-servicio-por-proveedor');
		
		$datas = $this->proveedor_servicioterrestreRepository->all();

        return view('receptivo.proveedor_servicioterrestre.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-servicio-por-proveedor');

        $servicioterrestre_query = $this->servicioterrestreRepository->all();
        $proveedor_query = $this->proveedorQuery->all();
        $moneda_query = $this->monedaRepository->all();

        return view('receptivo.proveedor_servicioterrestre.crear', compact('servicioterrestre_query', 'proveedor_query', 'moneda_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionProveedor_Servicioterrestre $request)
    {
        $this->proveedor_servicioterrestreRepository->create($request->all());

    	return redirect('receptivo/proveedor_servicioterrestre')->with('mensaje', 'Servicio Terrestre creado con exito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-servicio-por-proveedor');

		$proveedor_servicioterrestre = $this->proveedor_servicioterrestreRepository->find($id);
        $servicioterrestre_query = $this->servicioterrestreRepository->all();
        $moneda_query = $this->monedaRepository->all();
        $proveedor_query = $this->proveedorQuery->allQueryOrdenado(['nombre','codigo','id'], 'nombre');
        return view('receptivo.proveedor_servicioterrestre.editar', compact('proveedor_servicioterrestre', 
                                                                'servicioterrestre_query', 'proveedor_query',
                                                                'moneda_query')); 
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionProveedor_Servicioterrestre $request, $id)
    {
        can('actualizar-servicio-por-proveedor');

		$this->proveedor_servicioterrestreRepository->update($request->all(), $id);

		return redirect('receptivo/proveedor_servicioterrestre')->with('mensaje', 'Servicio Terrestre actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-servicio-por-proveedor');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->proveedor_servicioterrestreRepository->delete($id))
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

    public function leeCosto($servioterrestre_id, $proveedor_id)
    {
        return $this->proveedor_servicioterrestreRepository->leeCosto($servioterrestre_id, $proveedor_id);
    }

    public function leeProveedor($servioterrestre_id)
    {
        return $this->proveedor_servicioterrestreRepository->leeProveedor($servioterrestre_id);
    }
}
