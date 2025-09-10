<?php

namespace App\Repositories\Ticket;

use App\Models\Ticket\Tecnico_Ticket;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Tecnico_TicketRepository implements Tecnico_TicketRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tecnico_Ticket $tecnico_ticket
                                )
    {
        $this->model = $tecnico_ticket;
    }

    public function all()
    {
        $tecnico_ticket = $this->model;

        $permiso = chequeaPermisoTicket();

        // Lee el area de destino
        $usuario_id = Auth::user()->id;
        $tecnico = $this->leePorUsuarioId($usuario_id);

        $areadestino_id = 0;
        if (count($tecnico)>0)
        {
            $areadestino_id = $tecnico[0]->areadestino_id;

            if ($areadestino_id != 0)
                $tecnico_ticket = $tecnico_ticket->with('areadestinos')->with('usuarios')->where('areadestino_id', $areadestino_id)
                                                ->get();
            else
                $tecnico_ticket = $tecnico_ticket->with('areadestinos')->with('usuarios')->get();
        }
        else
            $tecnico_ticket = $tecnico_ticket->with('areadestinos')->with('usuarios')->get();        

        return $tecnico_ticket;
    }

    public function create(array $data)
    {
        $tecnico_ticket = $this->model->create($data);

        return($tecnico_ticket);
    }

    public function update(array $data, $id)
    {
        $tecnico_ticket = $this->model->findOrFail($id)->update($data);

		return $tecnico_ticket;
    }

    public function delete($id)
    {
    	$tecnico_ticket = $this->model->find($id);

        $tecnico_ticket = $this->model->destroy($id);

		return $tecnico_ticket;
    }

    public function find($id)
    {
        if (null == $tecnico_ticket = $this->model->with('areadestinos')->with('usuarios')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tecnico_ticket;
    }

    public function findOrFail($id)
    {
        if (null == $tecnico_ticket = $this->model->with('areadestinos')->with('usuarios')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tecnico_ticket;
    }

    public function leePorUsuarioId($usuario_id)
    {
        return $this->model->where('usuario_id', $usuario_id)->get();
    }

    public function leeTecnico_Ticket($consulta, $areadestino_id = null)
    {
		$columns = ['tecnico_ticket.id', 'tecnico_ticket.nombre', 'areadestino.nombre', 'areadestino.id'];
        $columnsOut = ['id', 'nombre', 'nombreareadestino', 'idareadestino'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('tecnico_ticket.id as id',
									'tecnico_ticket.nombre as nombre',
                                    'tecnico_ticket.areadestino_id as idareadestino',
									'areadestino.nombre as nombreareadestino')
                            ->join('areadestino', 'areadestino.id', '=', 'tecnico_ticket.areadestino_id');

        if (isset($areadestino_id))
            $data = $data->where('tecnico_ticket.areadestino_id', $areadestino_id);

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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultatecnico_ticket">Elegir</a></td>';
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
