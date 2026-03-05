<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Requests\EliminarMasivoCondicionpagoRequest;
use App\Http\Requests\GuardarCondicionpagoRequest;
use App\Http\Requests\ActualizarCondicionpagoRequest;
use App\Models\Compras\Condicionpago;
use App\Repositories\Compras\CondicionpagoRepositoryInterface;
use App\Repositories\Compras\CondicionpagocuotaRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CondicionpagoController extends Controller
{
	private $condicionpagoRepository, $condicionpagocuotaRepository;

	public function __construct(CondicionpagoRepositoryInterface $condicionpagorepository,
								CondicionpagocuotaRepositoryInterface $condicionpagocuotarepository)
    {
        $this->condicionpagoRepository = $condicionpagorepository;
		$this->condicionpagocuotaRepository = $condicionpagocuotarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-condicion-de-pago');
		
		$condicionespago = $this->condicionpagoRepository->all();

		$tipoplazo_enum = Condicionpago::$enumTipoPlazo;

        return view('compras.condicionpago.index', compact('condicionespago', 'tipoplazo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-condicion-de-pago');

		$tipoplazo_enum = Condicionpago::$enumTipoPlazo;

        return view('compras.condicionpago.crear', compact('tipoplazo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(GuardarCondicionpagoRequest $request)
    {
        $condicionpago = $this->condicionpagoRepository->create($request->all());

		if ($condicionpago)
		{
    		$cuotas = $request->input('cuotas', []);
    		$tiposplazo = $request->input('tiposplazo', []);
    		$plazos = $request->input('plazos', []);
    		$fechasvencimiento = $request->input('fechasvencimiento', []);
    		$porcentajes = $request->input('porcentajes', []);
    		$intereses = $request->input('intereses', []);
    		for ($i_cuota=0; $i_cuota < count($cuotas); $i_cuota++) {
        		if ($cuotas[$i_cuota] != '') 
				{
				  	// Si el tipo de plazo es fijo graba la fecha de vencimiento
					$fecha = NULL;
				  	if ($tiposplazo[$i_cuota] == 'F')
						$fecha = Carbon::createFromFormat( 'd-m-Y', $fechasvencimiento[$i_cuota]);
        			$condicionpagocuota = $this->condicionpagocuotaRepository->create([
					  									'condicionpago_id' => $condicionpago->id,
            											'cuota' => $cuotas[$i_cuota], 
														'tipoplazo' => $tiposplazo[$i_cuota], 
														'plazo' => $plazos[$i_cuota],
														'fechavencimiento' => $fecha,
														'porcentaje' => $porcentajes[$i_cuota],
														'interes' => $intereses[$i_cuota],
														]);
        		}
    		}
		}

    	return redirect('compras/condicionpago')->with('mensaje', 'Condicion de pago creada con exito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-condicion-de-pago');

		$condicionpago = Condicionpago::with('condicionpagocuotas')->where('id', $id)->first();
		$tipoplazo_enum = Condicionpago::$enumTipoPlazo;

        return view('compras.condicionpago.editar', compact('condicionpago', 'tipoplazo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ActualizarCondicionpagoRequest $request, $id)
    {
        can('actualizar-condicion-de-pago');

		$this->condicionpagoRepository->update($request->all(), $id);

		$condicionpagocuota = $this->condicionpagocuotaRepository->deletePorCondicionPago($id);

		$cuotas = $request->input('cuotas', []);
		$tiposplazo = $request->input('tiposplazo', []);
		$plazos = $request->input('plazos', []);
		$fechasvencimiento = $request->input('fechasvencimiento', []);
		$porcentajes = $request->input('porcentajes', []);
		$intereses = $request->input('intereses', []);
		for ($i_cuota=0; $i_cuota < count($cuotas); $i_cuota++) {
			if ($cuotas[$i_cuota] != '') 
			{
				// Si el tipo de plazo es fijo graba la fecha de vencimiento
				$fecha = $fechasvencimiento[$i_cuota];
				if ($tiposplazo[$i_cuota] == 'F')
					$fecha = Carbon::createFromFormat( 'd-m-Y', $fechasvencimiento[$i_cuota]);
				$condicionpagocuota = $this->condicionpagocuotaRepository->create([
													'condicionpago_id' => $id,
													'cuota' => $cuotas[$i_cuota], 
													'tipoplazo' => $tiposplazo[$i_cuota], 
													'plazo' => $plazos[$i_cuota],
													'fechavencimiento' => $fecha,
													'porcentaje' => $porcentajes[$i_cuota],
													'interes' => $intereses[$i_cuota],
													]);
			}
		}

		return redirect('compras/condicionpago')->with('mensaje', 'Condicion de pago actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-condicion-de-pago');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->condicionpagoRepository->delete($id))
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
