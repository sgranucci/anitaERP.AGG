<?php

namespace App\Repositories\Ticket;

use App\Models\Ticket\Categoria_Ticket;
use App\Repositories\Ticket\Categoria_TicketRepositoryInterface;
use App\Repositories\Ticket\Tecnico_TicketRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Carbon\Carbon;
use Auth;
use DB;

class Categoria_TicketRepository implements Categoria_TicketRepositoryInterface
{
    protected $model;
    protected $tecnico_ticketRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Categoria_Ticket $categoria_ticket,
                                Tecnico_TicketRepositoryInterface $tecnico_ticketrepository)
    {
        $this->model = $categoria_ticket;
        $this->tecnico_ticketRepository = $tecnico_ticketrepository;
    }

	public function all()
    {
        $categoria_ticket = $this->model;

        $permiso = chequeaPermisoTicket();

        // Lee el area de destino
        $usuario_id = Auth::user()->id;
        $tecnico_ticket = $this->tecnico_ticketRepository->leePorUsuarioId($usuario_id);

        $areadestino_id = 0;
        if (count($tecnico_ticket)>0)
        {
            $areadestino_id = $tecnico_ticket[0]->areadestino_id;

            if ($areadestino_id != 0)
                $categoria_ticket = $categoria_ticket->with('areadestinos')->where('areadestino_id', $areadestino_id)->get();
            else
                $categoria_ticket = $categoria_ticket->with('areadestinos')->get();
        }
        else
            $categoria_ticket = $categoria_ticket->with('areadestinos')->get();

        return $categoria_ticket;
    }

    public function create(array $data)
    {
		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
		return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $categoria_ticket = $this->model->with("subcategoria_tickets")
									->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $categoria_ticket;
    }

    public function findOrFail($id)
    {
        if (null == $categoria_ticket = $this->model->with("subcategoria_tickets")
											->findOrFail($id))
			{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $categoria_ticket;
    }

    public function leeCategoria_Ticket($consulta, $areadestino_id = null)
    {
		$columns = ['categoria_ticket.id', 'categoria_ticket.nombre', 'areadestino.nombre', 'areadestino.id'];
        $columnsOut = ['id', 'nombre', 'nombreareadestino', 'idareadestino'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('categoria_ticket.id as id',
									'categoria_ticket.nombre as nombre',
                                    'categoria_ticket.areadestino_id as idareadestino',
									'areadestino.nombre as nombreareadestino')
                            ->join('areadestino', 'areadestino.id', '=', 'categoria_ticket.areadestino_id');

        if (isset($areadestino_id))
            $data = $data->where('categoria_ticket.areadestino_id', $areadestino_id);

		$data = $data->Where(function ($query) use ($count, $consulta, $columns) {
                        			for ($i = 0; $i < $count; $i++)
                            			$query->orWhere($columns[$i], "LIKE", '%'. $consulta . '%');
                            })	
                            ->get();								

        $output = [];
		$output['data'] = '';	
        $flSinDatos = true;
        $count = count($columns);
		if (count($data) > 0)
		{
			foreach ($data as $row)
			{
                $flSinDatos = false;
                $output['data'] .= '<tr>';
                for ($i = 0; $i < $count; $i++)
                    $output['data'] .= '<td class="'.$columnsOut[$i].'">' . $row->{$columnsOut[$i]} . '</td>';	
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultacategoria_ticket">Elegir</a></td>';
                $output['data'] .= '</tr>';
			}
		}

        if ($flSinDatos)
		{
			$output['data'] .= '<tr>';
			$output['data'] .= '<td>Sin resultados</td>';
			$output['data'] .= '</tr>';
		}
		return(json_encode($output, JSON_UNESCAPED_UNICODE));
    }    
}
