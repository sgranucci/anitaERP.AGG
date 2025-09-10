<?php

namespace App\Http\Controllers\Ticket;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ticket\Sector_Ticket;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionSector_Ticket;
use App\Repositories\Ticket\Sector_TicketRepositoryInterface;

class Sector_TicketController extends Controller
{
	private $repository;

    public function __construct(Sector_TicketRepositoryInterface $repository)
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
        can('listar-sector-ticket');
		$datas = $this->repository->all();

        return view('ticket.sector_ticket.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-sector-ticket');

        return view('ticket.sector_ticket.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionSector_Ticket $request)
    {
		$this->repository->create($request->all());

        return redirect('ticket/sector_ticket')->with('mensaje', 'Sector creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-sector-ticket');
        $data = $this->repository->findOrFail($id);

        return view('ticket.sector_ticket.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionSector_Ticket $request, $id)
    {
        can('actualizar-sector-ticket');

        $this->repository->update($request->all(), $id);

        return redirect('ticket/sector_ticket')->with('mensaje', 'Sector actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-sector-ticket');

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
