<?php

namespace App\Http\Controllers\Receptivo;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionReserva;
use App\Repositories\Receptivo\ReservaRepositoryInterface;

class ReservaController extends Controller
{
    private $reservaRepository;

	public function __construct(ReservaRepositoryInterface $reservarepository)
    {
        $this->reservaRepository = $reservarepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-reservas');
        $datas = Reserva::orderBy('id')->get();

		if ($datas->isEmpty())
		{
			$Reserva = new Reserva();
        	$Reserva->sincronizarConAnita();
	
        	$datas = Reserva::orderBy('id')->get();
		}

        return view('configuracion.moneda.index', compact('datas'));
    }

    /*
     * Lee los datos de la reserva
     */

    public function leeReserva($reserva)
    {
        return $this->reservaRepository->find($reserva);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-reservas');
        return view('configuracion.moneda.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionReserva $request)
    {
        $moneda = Reserva::create($request->all());

		// Graba anita
		$Reserva = new Reserva();
        $Reserva->guardarAnita($request, $moneda->id);

        return redirect('configuracion/moneda')->with('mensaje', 'Reserva creada con exito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-reservas');
        $data = Reserva::findOrFail($id);
        return view('configuracion.moneda.editar', compact('data'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionReserva $request, $id)
    {
        can('actualizar-reservas');
        Reserva::findOrFail($id)->update($request->all());

		// Actualiza anita
		$Reserva = new Reserva();
        $Reserva->actualizarAnita($request, $id);

        return redirect('configuracion/moneda')->with('mensaje', 'Reserva actualizada con exito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-reservas');

		// Elimina anita
		$Reserva = new Reserva();
        $Reserva->eliminarAnita($id);

        if ($request->ajax()) {
            if (Reserva::destroy($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function consultaReserva(Request $request)
    {
        return ($this->reservaRepository->leeReserva($request->consulta));
	}

    public function leeReservaPorIdServicioTerrestre($reserva_id, $servicioterrestre_id)
    {
        return ($this->reservaRepository->leeReservaPorIdServicioTerrestre($reserva_id, $servicioterrestre_id));
    }

}
