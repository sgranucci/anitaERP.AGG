<?php

namespace App\Http\Controllers\Ticket;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ticket\Turno_Ticket;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTurno_Ticket;
use App\Repositories\Ticket\Turno_TicketRepositoryInterface;

class Turno_TicketController extends Controller
{
	private $repository;

    public function __construct(Turno_TicketRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-turno-ticket');
		$datas = $this->repository->all();

        return view('ticket.turno_ticket.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-turno-ticket');

        return view('ticket.turno_ticket.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTurno_Ticket $request)
    {
		$this->repository->create($request->all());

        return redirect('ticket/turno_ticket')->with('mensaje', 'Turno creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-turno-ticket');
        $data = $this->repository->findOrFail($id);

        return view('ticket.turno_ticket.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTurno_Ticket $request, $id)
    {
        can('actualizar-turno-ticket');

        $this->repository->update($request->all(), $id);

        return redirect('ticket/turno_ticket')->with('mensaje', 'Turno actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-turno-ticket');

        if ($request->ajax()) {
        	if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }
}
