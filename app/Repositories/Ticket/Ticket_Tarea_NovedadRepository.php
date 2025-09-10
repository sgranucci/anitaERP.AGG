<?php

namespace App\Repositories\Ticket;

use App\Models\Ticket\Ticket_Tarea_Novedad;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Ticket_Tarea_NovedadRepository implements Ticket_Tarea_NovedadRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ticket_Tarea_Novedad $ticket_tarea_novedad)
    {
        $this->model = $ticket_tarea_novedad;
    }

    public function create(array $data, $id)
    {
		return self::guardarTicket_Tarea_Novedad($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		return $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarTicket_Tarea_Novedad($data, 'update', $id);
    }

	public function updateUnique(array $data, $id)
    {
		return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return $this->model->where('id', $id)->delete();
    }

    public function find($id)
    {
        if (null == $ticket_tarea_novedad = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ticket_tarea_novedad;
    }

    public function findOrFail($id)
    {
        if (null == $ticket_tarea_novedad = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ticket_tarea_novedad;
    }

	public function traeIdPorTicketTarea($ticket_tarea_id)
	{
		return $this->model->where('ticket_tarea_id', $ticket_tarea_id)->get()->pluck('id')->toArray();
	}

	public function leeTicketTareaNovedad($ticket_tarea_id)
	{
		return $this->model->select('id',
							'id as ticket_tarea_novedad_id', 
							'ticket_tarea_id', 
							'desdefecha', 
							'hastafecha', 
							'usuario_id', 
							'comentario',
							'estado')
					->where('ticket_tarea_id', $ticket_tarea_id)
					->where('deleted_at', null)
					->with('usuarios')
					->get();
	}

	// Esta funcion para CRUD de tareas/novedades
	private function guardarTicketTareaNovedad($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$ticket_tarea_novedad = $this->model->where('ticket_tarea_id', $id)->get()->pluck('id')->toArray();
			$q_ticket_tarea_novedad = count($ticket_tarea_novedad);
		}

		// Graba estados
		if (isset($data))
		{
			$desdefechas = $data['desdefechas'];
			$hastafechas = $data['hastafechas'];
			$usuario_ids = $data['usuario_ids'];
			$comentarios = $data['comentarios'];
			$estados = $data['estados'];

			if ($funcion == 'update')
			{
				$_id = $ticket_tarea_novedad;

				// Borra los que sobran
				if ($q_ticket_tarea_novedad > count($desdefechas))
				{
					for ($d = count($desdefechas); $d < $q_ticket_tarea_novedad; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_ticket_tarea_novedad && $i < count($desdefechas); $i++)
				{
					if ($i < count($desdefechas))
					{
						$ticket_tarea_novedad = $this->model->findOrFail($_id[$i])->update([
									"ticket_tarea_id" => $id,
									"desdefecha" => $desdefechas[$i],
									"hastafecha" => $hastafechas[$i],
									"usuario_id" => $usuario_ids[$i],
									"comentario" => $comentarios[$i],
									"estado" => $estados[$i]
									]);
					}
				}
				if ($q_ticket_tarea_novedad > count($desdefechas))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($desdefechas); $i_movimiento++)
			{
				if ($desdefechas[$i_movimiento] != '') 
				{
					$ticket_tarea_novedad = $this->model->create([
						"ticket_tarea_id" => $id,
						"desdefecha" => $desdefechas[$i_movimiento],
						"hastafecha" => $hastafechas[$i_movimiento],
						"usuario_id" => $usuario_ids[$i_movimiento],
						"comentario" => $comentarios[$i_movimiento],
						"estado" => $estados[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$ticket_tarea_novedad = $this->model->where('ticket_tarea_id', $id)->delete();
		}

		return $ticket_tarea_novedad;
	}
}
