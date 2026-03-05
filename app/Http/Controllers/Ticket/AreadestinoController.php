<?php

namespace App\Http\Controllers\Ticket;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ticket\Areadestino;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionAreadestino;
use App\Repositories\Ticket\Tecnico_TicketRepositoryInterface;
use App\Repositories\Ticket\AreadestinoRepositoryInterface;

class AreadestinoController extends Controller
{
	private $repository;

    public function __construct(AreadestinoRepositoryInterface $repository)
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
        can('listar-area-destino');

		$datas = $this->repository->all();

        return view('ticket.areadestino.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-area-destino');

        return view('ticket.areadestino.crear');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionAreadestino $request)
    {
		$this->repository->create($request->all());

        return redirect('ticket/areadestino')->with('mensaje', 'Area de destino creada con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-area-destino');
        $data = $this->repository->findOrFail($id);

        return view('ticket.areadestino.editar', compact('data'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionAreadestino $request, $id)
    {
        can('actualizar-area-destino');

        $this->repository->update($request->all(), $id);

        return redirect('ticket/areadestino')->with('mensaje', 'Area de destino actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-area-destino');

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
