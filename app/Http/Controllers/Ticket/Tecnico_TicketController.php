<?php

namespace App\Http\Controllers\Ticket;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTecnico_Ticket;
use App\Repositories\Ticket\Tecnico_TicketRepositoryInterface;
use App\Repositories\Ticket\AreadestinoRepositoryInterface;

class Tecnico_TicketController extends Controller
{
	private $repository;
    private $areadestinoRepository;

    public function __construct(Tecnico_TicketRepositoryInterface $repository,
                                AreadestinoRepositoryInterface $areadestinorepository)
    {
        $this->repository = $repository;
        $this->areadestinoRepository = $areadestinorepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-tecnico-ticket');
		$datas = $this->repository->all();

        return view('ticket.tecnico_ticket.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tecnico-ticket');

        $areadestino_query = $this->areadestinoRepository->all();
        $usuario_query = Usuario::with('roles:id,nombre')->orderBy('id')->get();

        return view('ticket.tecnico_ticket.crear', compact('areadestino_query', 'usuario_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTecnico_Ticket $request)
    {
		$this->repository->create($request->all());

        return redirect('ticket/tecnico_ticket')->with('mensaje', 'Técnico creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tecnico-ticket');
        $data = $this->repository->findOrFail($id);
        $areadestino_query = $this->areadestinoRepository->all();
        $usuario_query = Usuario::with('roles:id,nombre')->orderBy('id')->get();

        return view('ticket.tecnico_ticket.editar', compact('data', 'areadestino_query', 'usuario_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTecnico_Ticket $request, $id)
    {
        can('actualizar-tecnico-ticket');

        $this->repository->update($request->all(), $id);

        return redirect('ticket/tecnico_ticket')->with('mensaje', 'Técnico actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tecnico-ticket');

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

    public function consultaTecnico_Ticket(Request $request)
    {
        return ($this->repository->leeTecnico_Ticket($request->consulta, $request->areadestino_id));
	}

    public function leeTecnico_Ticket($tarea_id)
    {
        return ($this->repository->find($tarea_id));
	}
    
}
