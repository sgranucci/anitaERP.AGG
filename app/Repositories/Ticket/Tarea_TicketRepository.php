<?php

namespace App\Repositories\Ticket;

use App\Models\Ticket\Tarea_Ticket;
use App\Repositories\Ticket\Tecnico_TicketRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Tarea_TicketRepository implements Tarea_TicketRepositoryInterface
{
    protected $model;
    protected $tecnico_ticketRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tarea_Ticket $tarea_ticket,
                                Tecnico_TicketRepositoryInterface $tecnico_ticketrepository
                                )
    {
        $this->model = $tarea_ticket;
        $this->tecnico_ticketRepository = $tecnico_ticketrepository;
    }

    public function all()
    {
        $tarea_ticket = $this->model;

        $permiso = chequeaPermisoTicket();

        // Lee el area de destino
        $usuario_id = Auth::user()->id;
        $tecnico_ticket = $this->tecnico_ticketRepository->leePorUsuarioId($usuario_id);

        $areadestino_id = 0;
        if (count($tecnico_ticket)>0)
        {
            $areadestino_id = $tecnico_ticket[0]->areadestino_id;

            if ($areadestino_id != 0)
                $tarea_ticket = $tarea_ticket->with('areadestinos')->where('areadestino_id', $areadestino_id)->get();
            else
                $tarea_ticket = $tarea_ticket->with('areadestinos')->get();
        }
        else
            $tarea_ticket = $tarea_ticket->with('areadestinos')->get();

        return $tarea_ticket;
    }

    public function create(array $data)
    {
        $tarea_ticket = $this->model->create($data);

        return($tarea_ticket);
    }

    public function update(array $data, $id)
    {
        $tarea_ticket = $this->model->findOrFail($id)->update($data);

		return $tarea_ticket;
    }

    public function delete($id)
    {
    	$tarea_ticket = $this->model->find($id);

        $tarea_ticket = $this->model->destroy($id);

		return $tarea_ticket;
    }

    public function find($id)
    {
        if (null == $tarea_ticket = $this->model->with('areadestinos')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tarea_ticket;
    }

    public function findOrFail($id)
    {
        if (null == $tarea_ticket = $this->model->with('areadestinos')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tarea_ticket;
    }

    public function leeTarea_Ticket($consulta, $areadestino_id = null)
    {
		$columns = ['tarea_ticket.id', 'tarea_ticket.nombre', 'areadestino.nombre', 'areadestino.id'];
        $columnsOut = ['id', 'nombre', 'nombreareadestino', 'idareadestino'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('tarea_ticket.id as id',
									'tarea_ticket.nombre as nombre',
                                    'tarea_ticket.areadestino_id as idareadestino',
									'areadestino.nombre as nombreareadestino')
                            ->join('areadestino', 'areadestino.id', '=', 'tarea_ticket.areadestino_id');

        if (isset($areadestino_id))
            $data = $data->where('tarea_ticket.areadestino_id', $areadestino_id);

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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultatarea_ticket">Elegir</a></td>';
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
