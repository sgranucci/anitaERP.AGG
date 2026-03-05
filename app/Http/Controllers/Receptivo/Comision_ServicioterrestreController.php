<?php

namespace App\Http\Controllers\Receptivo;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionComision_Servicioterrestre;
use App\Models\Receptivo\Comision_Servicioterrestre;
use App\Repositories\Receptivo\Comision_ServicioterrestreRepositoryInterface;
use App\Repositories\Receptivo\ServicioterrestreRepositoryInterface;
use App\Repositories\Ventas\FormapagoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Comision_ServicioterrestreController extends Controller
{
    private $comision_servicioterrestreRepository;
	private $servicioterrestreRepository;
    private $formapagoRepository;

	public function __construct(Comision_ServicioterrestreRepositoryInterface $comision_servicioterrestrerepository,
                                ServicioterrestreRepositoryInterface $servicioterrestrerepository,
                                FormapagoRepositoryInterface $formapagorepository)
    {
        $this->comision_servicioterrestreRepository = $comision_servicioterrestrerepository;
        $this->servicioterrestreRepository = $servicioterrestrerepository;
        $this->formapagoRepository = $formapagorepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-comision-servicio-terrestre');
		
		$comision_serviciosterrestres = $this->comision_servicioterrestreRepository->all();

		$tipocomision_enum = Comision_Servicioterrestre::$enumTipoComision;

        return view('receptivo.comision_servicioterrestre.index', compact('comision_serviciosterrestres', 'tipocomision_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-comision-servicio-terrestre');

        $tipocomision_enum = Comision_Servicioterrestre::$enumTipoComision;
        $servicioterrestre_query = $this->servicioterrestreRepository->all();
        $formapago_query = $this->formapagoRepository->all();

        return view('receptivo.comision_servicioterrestre.crear', compact('tipocomision_enum', 'formapago_query', 'servicioterrestre_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionComision_Servicioterrestre $request)
    {
        $comision_servicioterrestre = $this->comision_servicioterrestreRepository->create($request->all());

    	return redirect('receptivo/comision_servicioterrestre')->with('mensaje', 'Comision de Servicio Terrestre creada con éxito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-comision-servicio-terrestre');

		$comision_servicioterrestre = $this->comision_servicioterrestreRepository->find($id);

        $tipocomision_enum = Comision_Servicioterrestre::$enumTipoComision;
        $servicioterrestre_query = $this->servicioterrestreRepository->all();
        $formapago_query = $this->formapagoRepository->all();
        
        return view('receptivo.comision_servicioterrestre.editar', compact('comision_servicioterrestre', 
                                                                        'tipocomision_enum', 'formapago_query', 'servicioterrestre_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionComision_Servicioterrestre $request, $id)
    {
        can('actualizar-comision-servicio-terrestre');

		$this->comision_servicioterrestreRepository->update($request->all(), $id);

		return redirect('receptivo/comision_servicioterrestre')->with('mensaje', 'Comision de Servicio Terrestre actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-comision-servicio-terrestre');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->comision_servicioterrestreRepository->delete($id))
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

    // lee comision por forma de pago / tipo de comision y servicio terrestre

    public function leeComision($formapago_id, $tipocomision, $servicioterrestre_id)
    {
        $comision_servicioterrestre = $this->comision_servicioterrestreRepository
                                        ->findComision($formapago_id, $tipocomision, $servicioterrestre_id);

        $porcentajeComision = 0;
        if ($comision_servicioterrestre)
            $porcentajeComision = $comision_servicioterrestre->porcentajecomision;                    

        return ['porcentajecomision' => $porcentajeComision];
    }

    public function leeComision_Servicioterrestre($servicioterrestre_id, $tipocomision)
    {
        $comision_servicioterrestre = $this->comision_servicioterrestreRepository
                                        ->findComisionPorServicioTerrestre($servicioterrestre_id, $tipocomision);

        $porcentajeComision = 0;
        if ($comision_servicioterrestre)
            $porcentajeComision = $comision_servicioterrestre->porcentajecomision;                    

        return ['porcentajecomision' => $porcentajeComision];        
    }
}
