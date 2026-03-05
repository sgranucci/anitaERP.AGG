<?php

namespace App\Repositories\Ticket;

use App\Models\Ticket\Ticket_Estado;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Ticket_EstadoRepository implements Ticket_EstadoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ticket_Estado $ticket_estado)
    {
        $this->model = $ticket_estado;
    }

    public function create(array $data, $id)
    {
		return self::guardarTicket_Estado($data, 'create', $id);
    }

	public function creaEstado($id, $fecha, $estado, $usuario_id, $observacion)
	{
		Self::createUnique(["ticket_id" => $id,
						"fecha" => $fecha,
						"estado" => $estado,
						"usuario_id" => $usuario_id,
						"observacion" => $observacion]);
	}

	public function createUnique(array $data)
	{
		$ticket_estado = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarTicket_Estado($data, 'update', $id);
    }

    public function delete($ticket_id, $codigo)
    {
        return $this->model->where('ticket_id', $ticket_id)->delete();
    }

    public function find($id)
    {
        if (null == $ticket_estado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ticket_estado;
    }

    public function findOrFail($id)
    {
        if (null == $ticket_estado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ticket_estado;
    }

	private function guardarTicket_Estado($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$ticket_estado = $this->model->where('ticket_id', $id)->get()->pluck('id')->toArray();
			$q_ticket_estado = count($ticket_estado);
		}

		// Graba estados
		if (isset($data))
		{
			$fechas = $data['fechas'];
			$estados = $data['estados'];
			$usuario_ids = $data['usuario_ids'];
			$observaciones = $data['observacionestados'];

			if ($funcion == 'update')
			{
				$_id = $ticket_estado;

				// Borra los que sobran
				if ($q_ticket_estado > count($fechas))
				{
					for ($d = count($fechas); $d < $q_ticket_estado; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_ticket_estado && $i < count($fechas); $i++)
				{
					if ($i < count($fechas))
					{
						$ticket_estado = $this->model->findOrFail($_id[$i])->update([
									"ticket_id" => $id,
									"fecha" => $fechas[$i],
									"estado" => $estados[$i],
									"usuario_id" => $usuario_ids[$i],
									"observacion" => $observaciones[$i]
									]);
					}
				}
				if ($q_ticket_estado > count($fechas))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($fechas); $i_movimiento++)
			{
				if ($fechas[$i_movimiento] != '') 
				{
					$ticket_estado = $this->model->create([
						"ticket_id" => $id,
						"fecha" => $fechas[$i_movimiento],
						"estado" => $estados[$i_movimiento],
						"usuario_id" => $usuario_ids[$i],
						"observacion" => $observaciones[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$ticket_estado = $this->model->where('ticket_id', $id)->delete();
		}

		return $ticket_estado;
	}

	public function leeHistoriaTicket($ticket_id)
	{
		return $this->model->select('id',
							'ticket_id',
							'fecha', 
							'estado', 
							'usuario_id',
							'observacion')
					->where('ticket_id', $ticket_id)
					->where('deleted_at', null)
					->with('usuarios')
					->get();
	}

}
