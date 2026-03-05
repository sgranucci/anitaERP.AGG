<?php

namespace App\Http\Controllers\Ticket;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Ticket\Tarea_Ticket;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionTarea_Ticket;
use App\Repositories\Ticket\Tarea_TicketRepositoryInterface;
use App\Repositories\Ticket\AreadestinoRepositoryInterface;

class Tarea_TicketController extends Controller
{
	private $repository;
    private $areadestinoRepository;

    public function __construct(Tarea_TicketRepositoryInterface $repository,
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
        can('listar-tarea-ticket');
		$datas = $this->repository->all();
        $tipotarea_enum = Tarea_Ticket::$enumTipoTarea;
        $enviacorreo_enum = Tarea_Ticket::$enumEnviaCorreo;

        return view('ticket.tarea_ticket.index', compact('datas', 'tipotarea_enum', 'enviacorreo_enum'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-tarea-ticket');

        $areadestino_query = $this->areadestinoRepository->all();
        $tipotarea_enum = Tarea_Ticket::$enumTipoTarea;
        $enviacorreo_enum = Tarea_Ticket::$enumEnviaCorreo;

        return view('ticket.tarea_ticket.crear', compact('areadestino_query', 'tipotarea_enum', 'enviacorreo_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTarea_Ticket $request)
    {
		$this->repository->create($request->all());

        return redirect('ticket/tarea_ticket')->with('mensaje', 'Turno creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-tarea-ticket');
        $data = $this->repository->findOrFail($id);

        $areadestino_query = $this->areadestinoRepository->all();
        $tipotarea_enum = Tarea_Ticket::$enumTipoTarea;
        $enviacorreo_enum = Tarea_Ticket::$enumEnviaCorreo;

        return view('ticket.tarea_ticket.editar', compact('data', 'areadestino_query', 'tipotarea_enum', 'enviacorreo_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTarea_Ticket $request, $id)
    {
        can('actualizar-tarea-ticket');

        $this->repository->update($request->all(), $id);

        return redirect('ticket/tarea_ticket')->with('mensaje', 'Turno actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-tarea-ticket');

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

    public function consultaTarea_Ticket(Request $request)
    {
        return ($this->repository->leeTarea_Ticket($request->consulta, $request->areadestino_id));
	}

    public function leeTarea_Ticket($tarea_id)
    {
        return ($this->repository->find($tarea_id));
	}
    
}
