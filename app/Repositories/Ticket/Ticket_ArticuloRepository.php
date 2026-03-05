<?php

namespace App\Repositories\Ticket;

use App\Models\Ticket\Ticket_Articulo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Ticket_ArticuloRepository implements Ticket_ArticuloRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ticket_Articulo $ticket_articulo)
    {
        $this->model = $ticket_articulo;
    }

    public function create(array $data, $id)
    {
		return self::guardarTicket_Articulo($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$ticket_articulo = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarTicket_Articulo($data, 'update', $id);
    }

    public function delete($ticket_id, $codigo)
    {
        return $this->model->where('ticket_id', $ticket_id)->delete();
    }

    public function find($id)
    {
        if (null == $ticket_articulo = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ticket_articulo;
    }

    public function findOrFail($id)
    {
        if (null == $ticket_articulo = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ticket_articulo;
    }

	private function guardarTicket_Articulo($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$ticket_articulo = $this->model->where('ticket_id', $id)->get()->pluck('id')->toArray();
			$q_ticket_articulo = count($ticket_articulo);
		}

		// Graba estados
		if (isset($data))
		{
			if (isset($data['articulo_ids']))
			{
				$articulo_ids = $data['articulo_ids'];
				$cantidades = $data['cantidades'];
				$requisicion_ids = $data['requisicion_ids'];
				$recepcion_ids = $data['recepcion_ids'];
				$creousuario_ids = $data['creousuarioarticulo_ids'];
			}
			else
				$articulo_ids = [];

			if ($funcion == 'update')
			{
				$_id = $ticket_articulo;

				// Borra los que sobran
				if ($q_ticket_articulo > count($articulo_ids))
				{
					for ($d = count($articulo_ids); $d < $q_ticket_articulo; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_ticket_articulo && $i < count($articulo_ids); $i++)
				{
					if ($i < count($articulo_ids))
					{
						$ticket_articulo = $this->model->findOrFail($_id[$i])->update([
									"ticket_id" => $id,
									"articulo_id" => $articulo_ids[$i],
									"cantidad" => $cantidades[$i],
									"requisicion_id" => $requisicion_ids[$i],
									"recepcion_id" => $recepcion_ids[$i],
									"creousuario_id" => $creousuario_ids[$i]
									]);
					}
				}
				if ($q_ticket_articulo > count($articulo_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($articulo_ids); $i_movimiento++)
			{
				if ($articulo_ids[$i_movimiento] != '') 
				{
					$ticket_articulo = $this->model->create([
						"ticket_id" => $id,
						"articulo_id" => $articulo_ids[$i_movimiento],
						"cantidad" => $cantidades[$i_movimiento],
						"requisicion_id" => $requisicion_ids[$i_movimiento],
						"recepcion_id" => $recepcion_ids[$i_movimiento],
						"creousuario_id" => $creousuario_ids[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$ticket_articulo = $this->model->where('ticket_id', $id)->delete();
		}

		return $ticket_articulo;
	}
}
