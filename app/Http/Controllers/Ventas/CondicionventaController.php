<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\EliminarMasivoCondicionventaRequest;
use App\Http\Requests\GuardarCondicionventaRequest;
use App\Http\Requests\ActualizarCondicionventaRequest;
use App\Models\Ventas\Condicionventa;
use App\Models\Ventas\Condicionventacuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CondicionventaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-condiciones-de-venta');
        $condicionesventa = Condicionventa::with('condicionventacuotas')->orderBy('id')->get();

		if ($condicionesventa->isEmpty())
		{
			$Condicionventa = new Condicionventa();
        	$Condicionventa->sincronizarConAnita();
	
        	$condicionesventa = Condicionventa::orderBy('id')->get();
		}
		
	  	$colTipoPlazo = collect([
							['id' => '1', 'valor' => 'D', 'nombre'  => 'Dias'],
    						['id' => '2', 'valor' => 'F', 'nombre'  => 'Vto. fijo'],
    						['id' => '3', 'valor' => 'O', 'nombre'  => 'Vto. por operacion'],
							['id' => '4', 'valor' => 'R', 'nombre'  => 'Vto. por rangos']
								]);

        return view('ventas.condicionventa.index', compact('condicionesventa', 'colTipoPlazo'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-condiciones-de-venta');

	  	$colTipoPlazo = collect([
							['id' => '1', 'valor' => 'D', 'nombre'  => 'Dias'],
    						['id' => '2', 'valor' => 'F', 'nombre'  => 'Vto. fijo'],
    						['id' => '3', 'valor' => 'O', 'nombre'  => 'Vto. por operacion'],
							]);

        return view('ventas.condicionventa.crear', compact('colTipoPlazo'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(GuardarCondicionventaRequest $request)
    {
        $condicionventa = Condicionventa::create($request->all());

		if ($condicionventa)
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
        			$condicionventacuota = Condicionventacuota::create([
					  									'condicionventa_id' => $condicionventa->id,
            											'cuota' => $cuotas[$i_cuota], 
														'tipoplazo' => $tiposplazo[$i_cuota], 
														'plazo' => $plazos[$i_cuota],
														'fechavencimiento' => $fecha,
														'porcentaje' => $porcentajes[$i_cuota],
														'interes' => $intereses[$i_cuota],
														]);
        		}
    		}

			// Graba anita
			$Condicionventa = new Condicionventa();
        	$Condicionventa->guardarAnita($request, $condicionventa->id, $cuotas, $tiposplazo, $plazos, $fechasvencimiento, $porcentajes, $intereses);
		}

    	return redirect('ventas/condicionventa')->with('mensaje', 'Condicion de venta creada con exito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-condiciones-de-venta');

		$condicionventa = Condicionventa::with('condicionventacuotas')->where('id', $id)->first();

	  	$colTipoPlazo = collect([
							['id' => '1', 'valor' => 'D', 'nombre'  => 'Dias'],
    						['id' => '2', 'valor' => 'F', 'nombre'  => 'Vto. fijo'],
    						['id' => '3', 'valor' => 'O', 'nombre'  => 'Vto. por operacion'],
							]);

        return view('ventas.condicionventa.editar', compact('condicionventa', 'colTipoPlazo'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ActualizarCondicionventaRequest $request, $id)
    {
        can('actualizar-condiciones-de-venta');
        Condicionventa::findOrFail($id)->update($request->all());

		$condicionventa = Condicionventa::findOrFail($id);

		// Borra cuotas
		if ($condicionventa)
		{
        	$condicionventacuota = Condicionventacuota::where('condicionventa_id', $request->id)->delete();
	
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
        			$condicionventacuota = Condicionventacuota::create([
					  									'condicionventa_id' => $id,
            											'cuota' => $cuotas[$i_cuota], 
														'tipoplazo' => $tiposplazo[$i_cuota], 
														'plazo' => $plazos[$i_cuota],
														'fechavencimiento' => $fecha,
														'porcentaje' => $porcentajes[$i_cuota],
														'interes' => $intereses[$i_cuota],
														]);
        		}
			}

			// Actualiza anita
			$Condicionventa = new Condicionventa();
        		$Condicionventa->actualizarAnita($request, $id, $cuotas, $tiposplazo, $plazos, $fechasvencimiento, $porcentajes, $intereses);
		}
        return redirect('ventas/condicionventa')->with('mensaje', 'Condicion de venta actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-condiciones-de-venta');

        if ($request->ajax()) 
		{
			$condicionventa = Condicionventa::findOrFail($id);

			// Elimina anita
			$Condicionventa = new Condicionventa();
        	$Condicionventa->eliminarAnita($condicionventa->id);

			$fl_borro = false;
            if (($condicionventa = Condicionventa::destroy($id)))
				$fl_borro = true;

            if ($fl_borro) {
        		Condicionventacuota::where('condicionventa_id', $id)->delete();

                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function eliminarcompleto(EliminarMasivoCondicionventaRequest $request)
    {
        can('borrar-condiciones-de-venta');

        if ($request->ajax()) {
            if (Condicionventa::whereIn('id', request('ids'))->delete()) {
				Condicionventacuota::whereIn('id', request('ids'))->delete();

                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
