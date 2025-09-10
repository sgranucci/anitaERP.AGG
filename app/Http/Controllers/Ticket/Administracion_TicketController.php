<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTicket;
use App\Repositories\Ticket\TicketRepositoryInterface;
use App\Repositories\Ticket\Ticket_EstadoRepositoryInterface;
use App\Repositories\Ticket\Categoria_TicketRepositoryInterface;
use App\Repositories\Ticket\Subcategoria_TicketRepositoryInterface;
use App\Repositories\Ticket\AreadestinoRepositoryInterface;
use App\Repositories\Ticket\Sector_TicketRepositoryInterface;
use App\Repositories\Ticket\Turno_TicketRepositoryInterface;
use App\Repositories\Configuracion\SalaRepositoryInterface;
use App\Services\Ticket\TicketService;
use App\Models\Ticket\Ticket_Estado;
use App\Models\Ticket\Ticket_Tarea_Novedad;
use App\Queries\Ticket\TicketQueryInterface;
use App\Exports\Ticket\TicketExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use DB;
use Exception;

class Administracion_TicketController extends Controller
{
	private $categoria_ticketRepository;
    private $subcategoria_ticketRepository;
    private $areadestinoRepository;
    private $sector_ticketRepository;
    private $ticketRepository;
    private $ticket_estadoRepository;
    private $turno_ticketRepository;
    private $salaRepository;
    private $ticketQuery;
    private $ticketService;

	public function __construct(Categoria_TicketRepositoryInterface $categoria_ticketrepository,
                                Subcategoria_TicketRepositoryInterface $subcategoria_ticketrepository,
                                AreadestinoRepositoryInterface $areadestinorepository,
                                TicketRepositoryInterface $ticketrepository,
                                Ticket_EstadoRepositoryInterface $ticket_estadorepository,
                                SalaRepositoryInterface $salarepository,
                                Sector_TicketRepositoryInterface $sectorrepository,
                                Turno_TicketRepositoryInterface $turno_ticketrepository,
                                TicketService $ticketservice,
                                TicketQueryInterface $ticketquery
                                )
    {
        $this->categoria_ticketRepository = $categoria_ticketrepository;
        $this->subcategoria_ticketRepository = $subcategoria_ticketrepository;
        $this->areadestinoRepository = $areadestinorepository;
        $this->ticketRepository = $ticketrepository;
        $this->ticket_estadoRepository = $ticket_estadorepository;
        $this->sector_ticketRepository = $sectorrepository;
        $this->turno_ticketRepository = $turno_ticketrepository;
        $this->salaRepository = $salarepository;
        $this->ticketService = $ticketservice;
        $this->ticketQuery = $ticketquery;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        can('listar-ticket');
		
        $busqueda = $request->busqueda;

        $ticket = $this->ticketQuery->leeTicketAdministracion($busqueda, 0, true);
        $estado_enum = Ticket_Estado::$enumEstado;
        $datas = ['ticket' => $ticket, 'busqueda' => $busqueda, 'estado_enum' => $estado_enum];

        return view('ticket.administracion_ticket.index', $datas);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-ticket'); 

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        switch($formato)
        {
        case 'PDF':
            $ticket = $this->ticketQuery->leeTicketAdministracion($busqueda, 0, false);

            $view =  \View::make('ticket.administracion.listado', compact('ticket'))
                        ->render();
            $path = storage_path('pdf/listados');
            $nombre_pdf = 'listado_administracion_ticket';

            $pdf = \App::make('dompdf.wrapper');
            $pdf->setPaper('legal','landscape');
            $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

            return response()->download($path.'/'.$nombre_pdf.'.pdf');
            break;

        case 'EXCEL':
            return (new administracionTicketExport($this->ticketQuery))
                        ->parametros($busqueda)
                        ->download('administracion_ticket.xlsx');
            break;

        case 'CSV':
            return (new administracionTicketExport($this->ticketQuery))
                        ->parametros($busqueda)
                        ->download('administracion_ticket.csv', \Maatwebsite\Excel\Excel::CSV);
            break;            
        }   

        $datas = ['ticket' => $caja_movimiento, 'busqueda' => $busqueda];

		return view('ticket.administracion_ticket.indexp', $datas);       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-ticket');

        $areadestino_query = $this->areadestinoRepository->all();
        $sector_query = $this->sector_ticketRepository->all();
        $sala_query = $this->salaRepository->all();
        $turno_query = $this->turno_ticketRepository->all();
        $estado_novedad_enum = Ticket_Tarea_Novedad::$enumEstado;
        $estado_novedad_json = json_encode(Ticket_Tarea_Novedad::$enumEstado);
        $estado_enum = Ticket_Estado::$enumEstado;

        return view('ticket.administracion_ticket.crear', compact('areadestino_query', 'sector_query', 'sala_query',
                                                                'turno_query', 'estado_novedad_enum',
                                                                'estado_novedad_json', 'estado_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionTicket $request)
    {
        $this->ticketService->guardaTicket($request);

        return redirect('ticket/administracion_ticket')->with('mensaje', 'Ticket creado con éxito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-ticket');

		$data = $this->ticketRepository->find($id);
        $areadestino_query = $this->areadestinoRepository->all();
        $sector_query = $this->sector_ticketRepository->all();
        $sala_query = $this->salaRepository->all();
        $turno_query = $this->turno_ticketRepository->all();
        $estado_novedad_enum = Ticket_Tarea_Novedad::$enumEstado;
        $estado_novedad_json = json_encode(Ticket_Tarea_Novedad::$enumEstado);
        $estado_enum = Ticket_Estado::$enumEstado;

        return view('ticket.administracion_ticket.editar', compact('data', 'areadestino_query', 'sector_query', 
                                                                    'sala_query', 'turno_query', 'estado_novedad_enum',
                                                                    'estado_novedad_json', 'estado_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionTicket $request, $id)
    {
        can('actualizar-ticket');

        $this->ticketService->actualizaTicket($request, $id);

        return redirect('ticket/administracion_ticket')->with('mensaje', 'Ticket actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-ticket');

        if ($request->ajax()) 
		{
			$fl_borro = false;
            
			if ($this->ticketRepository->delete($id))
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

    public function guardarTicketTareaNovedad(Request $request)
    {
        return $this->ticketService->grabaTicketTareaNovedad($request->all());
    }

    public function leerTicketTareaNovedad($ticket_tarea_id)
    {
        return $this->ticketService->leeTicketTareaNovedad($ticket_tarea_id);
    }

    public function leerHistoriaTicket($ticket_id)
    {
        return $this->ticketService->leeHistoriaTicket($ticket_id);
    }

    public function cambiarTecnico($ticket_tarea_id, $tecnico_ticket_id)
    {
        return $this->ticketService->cambiarTecnico($ticket_tarea_id, $tecnico_ticket_id);
    }

    public function finalizarTarea($ticket_tarea_id, $fechafinalizacion, $tiempoinsumido)
    {
        return $this->ticketService->finalizarTarea($ticket_tarea_id, $fechafinalizacion, $tiempoinsumido);
    }
}
