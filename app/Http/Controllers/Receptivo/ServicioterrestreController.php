<?php

namespace App\Http\Controllers\Receptivo;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionServicioterrestre;
use App\Models\Receptivo\Servicioterrestre;
use App\Models\Configuracion\Impuesto;
use App\Models\Configuracion\Moneda;
use App\Repositories\Receptivo\ServicioterrestreRepositoryInterface;
use App\Repositories\Receptivo\TiposervicioterrestreRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ServicioterrestreController extends Controller
{
	private $servicioterrestreRepository;
    private $tiposervicioterrestreRepository;

	public function __construct(ServicioterrestreRepositoryInterface $servicioterrestrerepository,
                                TiposervicioterrestreRepositoryInterface $tiposervicioterrestrerepository)
    {
        $this->servicioterrestreRepository = $servicioterrestrerepository;
        $this->tiposervicioterrestreRepository = $tiposervicioterrestrerepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-servicio-terrestre');
		
		$serviciosterrestres = $this->servicioterrestreRepository->all();

		$ubicacion_enum = Servicioterrestre::$enumUbicacion;
        $modoexento_enum = Servicioterrestre::$enumModoExento;
        $prepago_enum = Servicioterrestre::$enumPrepago;
        $tiposervicioterrestre_query = $this->tiposervicioterrestreRepository->all();
        $moneda_query = Moneda::get();
        $impuesto_query = Impuesto::all();

        return view('receptivo.servicioterrestre.index', compact('serviciosterrestres', 'ubicacion_enum', 
                                                                'modoexento_enum', 'prepago_enum',
                                                                'tiposervicioterrestre_query', 'moneda_query',
                                                                'impuesto_query'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-servicio-terrestre');

        $ubicacion_enum = Servicioterrestre::$enumUbicacion;
        $modoexento_enum = Servicioterrestre::$enumModoExento;
        $prepago_enum = Servicioterrestre::$enumPrepago;
        $tiposervicioterrestre_query = $this->tiposervicioterrestreRepository->all();
        $moneda_query = Moneda::get();
        $impuesto_query = Impuesto::all();

        return view('receptivo.servicioterrestre.crear', compact('ubicacion_enum', 
                                                                'modoexento_enum', 'prepago_enum',
                                                                'tiposervicioterrestre_query', 'moneda_query',
                                                                'impuesto_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionServicioterrestre $request)
    {
        $servicioterrestre = $this->servicioterrestreRepository->create($request->all());

    	return redirect('receptivo/servicioterrestre')->with('mensaje', 'Servicio Terrestre creado con exito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-servicio-terrestre');

		$servicioterrestre = $this->servicioterrestreRepository->find($id);
        $ubicacion_enum = Servicioterrestre::$enumUbicacion;
        $modoexento_enum = Servicioterrestre::$enumModoExento;
        $prepago_enum = Servicioterrestre::$enumPrepago;
        $tiposervicioterrestre_query = $this->tiposervicioterrestreRepository->all();
        $moneda_query = Moneda::get();
        $impuesto_query = Impuesto::all();
        
        return view('receptivo.servicioterrestre.editar', compact('servicioterrestre', 'ubicacion_enum', 
                                                                'modoexento_enum', 'prepago_enum', 'moneda_query',
                                                                'tiposervicioterrestre_query', 'moneda_query',
                                                                'impuesto_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionServicioterrestre $request, $id)
    {
        can('actualizar-servicio-terrestre');

		$this->servicioterrestreRepository->update($request->all(), $id);

		return redirect('receptivo/servicioterrestre')->with('mensaje', 'Servicio Terrestre actualizado con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-servicio-terrestre');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->servicioterrestreRepository->delete($id))
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

    public function consultaServicioTerrestre(Request $request)
    {
        return ($this->servicioterrestreRepository->leeServicioTerrestre($request->consulta));
	}

    public function leeServicioTerrestre($codigoservicioterrestre)
    {
        return ($this->servicioterrestreRepository->findPorCodigo($codigoservicioterrestre));
	}
}
