<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionCategoria_Ticket;
use App\Repositories\Ticket\Categoria_TicketRepositoryInterface;
use App\Repositories\Ticket\Subcategoria_TicketRepositoryInterface;
use App\Repositories\Ticket\AreadestinoRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use DB;
use Exception;

class Categoria_TicketController extends Controller
{
	private $categoria_ticketRepository;
    private $subcategoria_ticketRepository;
    private $areadestinoRepository;

	public function __construct(Categoria_TicketRepositoryInterface $categoria_ticketrepository,
                                Subcategoria_TicketRepositoryInterface $subcategoria_ticketrepository,
                                AreadestinoRepositoryInterface $areadestinorepository)
    {
        $this->categoria_ticketRepository = $categoria_ticketrepository;
        $this->subcategoria_ticketRepository = $subcategoria_ticketrepository;
        $this->areadestinoRepository = $areadestinorepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-categoria-ticket');
		
		$datas = $this->categoria_ticketRepository->all();

        return view('ticket.categoria_ticket.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-categoria-ticket');
        $areadestino_query = $this->areadestinoRepository->all();

        return view('ticket.categoria_ticket.crear', compact('areadestino_query'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCategoria_Ticket $request)
    {
        DB::beginTransaction();
        try
        {
            $categoria = $this->categoria_ticketRepository->create($request->all());

            if ($categoria == 'Error')
                throw new Exception('Error en grabacion');

            // Guarda tablas asociadas
            if ($categoria)
                $subcategoria = $this->subcategoria_ticketRepository->create($request->all(), $categoria->id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            // Borra el asiento creado

            return ['errores' => $e->getMessage()];
        }
    	return redirect('ticket/categoria_ticket')->with('mensaje', 'Categoría creada con éxito');
	}

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        can('editar-categoria-ticket');

		$data = $this->categoria_ticketRepository->find($id);
        $areadestino_query = $this->areadestinoRepository->all();

        return view('ticket.categoria_ticket.editar', compact('data', 'areadestino_query'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCategoria_Ticket $request, $id)
    {
        can('actualizar-categoria-ticket');

        DB::beginTransaction();
        try
        {
            $categoria = $this->categoria_ticketRepository->update($request->all(), $id);

            if (!$categoria)
                throw new Exception('Error en grabacion');

            // Guarda tablas asociadas
            if ($categoria)
                $subcategoria = $this->subcategoria_ticketRepository->update($request->all(), $id);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            // Borra el asiento creado

            return ['errores' => $e->getMessage()];
        }
		return redirect('ticket/categoria_ticket')->with('mensaje', 'Categoría actualizada con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-categoria-ticket');

        if ($request->ajax()) 
		{
			$fl_borro = false;
			if ($this->categoria_ticketRepository->delete($id))
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

    public function consultaCategoria_Ticket(Request $request)
    {
        return ($this->categoria_ticketRepository->leeCategoria_Ticket($request->consulta, $request->areadestino_id));
	}

    public function leeCategoria_Ticket($categoria_id)
    {
        return ($this->categoria_ticketRepository->find($categoria_id));
	}

    public function consultaSubcategoria_Ticket(Request $request)
    {
        return ($this->subcategoria_ticketRepository->leeSubcategoria_Ticket($request->consulta, $request->categoria_ticket_id, $request->areadestino_id));
	}

    public function leeSubcategoria_Ticket($categoria_id)
    {
        return ($this->subcategoria_ticketRepository->find($categoria_id));
	}
}
