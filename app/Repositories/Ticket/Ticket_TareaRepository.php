<?php

namespace App\Repositories\Ticket;

use App\Models\Ticket\Ticket_Tarea;
use App\Models\Ticket\Ticket_Estado;
use App\Repositories\Ticket\Ticket_EstadoRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Ticket_TareaRepository implements Ticket_TareaRepositoryInterface
{
    protected $model;
	private $ticket_estadoRepository;
	public $ticket_tarea_ids = [];

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ticket_Tarea $ticket_tarea,
								Ticket_EstadoRepositoryInterface $ticket_estadorepository
								)
    {
        $this->model = $ticket_tarea;
		$this->ticket_estadoRepository = $ticket_estadorepository;
    }

    public function create(array $data, $id)
    {
		return Self::guardarTicket_Tarea($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$ticket_tarea = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return Self::guardarTicket_Tarea($data, 'update', $id);
    }

    public function delete($ticket_id, $codigo)
    {
        return $this->model->where('ticket_id', $ticket_id)->delete();
    }

    public function find($id)
    {
        if (null == $ticket_tarea = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ticket_tarea;
    }

    public function findOrFail($id)
    {
        if (null == $ticket_tarea = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ticket_tarea;
    }

	private function guardarTicket_Tarea_ANTERIOR($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$ticket_tarea = $this->model->where('ticket_id', $id)->get()->pluck('id')->toArray();
			$q_ticket_tarea = count($ticket_tarea);
		}
		$this->ticket_tarea_ids = [];
		// Graba tareas
		if (isset($data))
		{
			if (isset($data['tarea_ticket_ids']))
			{
				$tarea_ticket_ids = $data['tarea_ticket_ids'];
				$nombretarea_tickets = $data['nombretarea_tickets'];
				$fechacargas = $data['fechacargas'];
				$fechaprogramaciones = $data['fechaprogramaciones'];
				$fechafinalizaciones = $data['fechafinalizaciones'];
				$tiempoinsumidos = $data['tiempoinsumidos'];
				$tecnico_ids = $data['tecnico_ticket_ids'];
				$turno_ids = $data['turno_ids'];
				$creousuario_ids = $data['creousuario_ids'];
			}
			else
				$tarea_ticket_ids = [];

			if ($funcion == 'update')
			{
				$_id = $ticket_tarea;
				// Borra los que sobran
				if ($q_ticket_tarea > count($tarea_ticket_ids))
				{
					for ($d = count($tarea_ticket_ids); $d < $q_ticket_tarea; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_ticket_tarea && $i < count($tarea_ticket_ids); $i++)
				{
					if ($i < count($tarea_ticket_ids))
					{
						$ticket_tarea = $this->model->findOrFail($_id[$i])->update([
									"ticket_id" => $id,
									"tarea_id" => $tarea_ticket_ids[$i],
									"detalle" => $nombretarea_tickets[$i],
									"fechacarga" => $fechacargas[$i],
									"fechaprogramacion" => $fechaprogramaciones[$i],
									"fechafinalizacion" => $fechafinalizaciones[$i],
									"tiempoinsumido" => $tiempoinsumidos[$i],
									"tecnico_id" => $tecnico_ids[$i],
									"turno_id" => $turno_ids[$i],
									"creousuario_id" => $creousuario_ids[$i]
									]);

						// Asigna tabla de ids de tareas
						$this->ticket_tarea_ids[] = $_id[$i];
					}
				}
				if ($q_ticket_tarea > count($tarea_ticket_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($tarea_ticket_ids); $i_movimiento++)
			{
				if ($tarea_ticket_ids[$i_movimiento] != '') 
				{
					$ticket_tarea = $this->model->create([
						"ticket_id" => $id,
						"tarea_id" => $tarea_ticket_ids[$i_movimiento],
						"detalle" => $nombretarea_tickets[$i_movimiento],
						"fechacarga" => $fechacargas[$i_movimiento],
						"fechaprogramacion" => $fechaprogramaciones[$i_movimiento],
						"fechafinalizacion" => $fechafinalizaciones[$i_movimiento],
						"tiempoinsumido" => $tiempoinsumidos[$i_movimiento],
						"tecnico_id" => $tecnico_ids[$i_movimiento],
						"turno_id" => $turno_ids[$i_movimiento],
						"creousuario_id" => $creousuario_ids[$i_movimiento]
						]);

					// Asigna tabla de ids de tareas
					$this->ticket_tarea_ids[] = $ticket_tarea->id;

					// Agrega estado de tarea asignada
					// Crea estado
					$this->ticket_estadoRepository->creaEstado($id, Carbon::now(), Ticket_Estado::$enumEstado[1]['nombre'],
															Auth::user()->id, 'Asigna tarea '.$nombretarea_tickets[$i_movimiento]);

				}
			}
		}
		else
		{
			$ticket_tarea = $this->model->where('ticket_id', $id)->delete();
		}

		return ['ticket_tarea_ids' => $this->ticket_tarea_ids];
	}

	private function guardarTicket_Tarea($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$array_ticket_tarea_id = $this->model->where('ticket_id', $id)->get()->pluck('id')->toArray();
			$q_ticket_tarea = count($array_ticket_tarea_id);
		}
		$this->ticket_tarea_ids = [];

		// Graba tareas
		if (isset($data))
		{
			if (isset($data['tarea_ticket_ids']))
			{
				$ticket_tarea_ids = $data['ticket_tarea_ids'];
				$tarea_ticket_ids = $data['tarea_ticket_ids'];
				$nombretarea_tickets = $data['nombretarea_tickets'];
				$fechacargas = $data['fechacargas'];
				$fechaprogramaciones = $data['fechaprogramaciones'];
				$fechafinalizaciones = $data['fechafinalizaciones'];
				$tiempoinsumidos = $data['tiempoinsumidos'];
				$tecnico_ids = $data['tecnico_ticket_ids'];
				$turno_ids = $data['turno_ids'];
				$creousuario_ids = $data['creousuario_ids'];
			}
			else
				$tarea_ticket_ids = [];

			if ($funcion == 'update')
			{
				for ($i = 0; $i < count($tarea_ticket_ids); $i++)
				{
					// Si no tiene id crea el registro
					if ($ticket_tarea_ids[$i] == null || $ticket_tarea_ids[$i] == 'undefined')
					{
						if ($tarea_ticket_ids[$i] != '') 
						{
							$ticket_tarea = $this->model->create([
								"ticket_id" => $id,
								"tarea_id" => $tarea_ticket_ids[$i],
								"detalle" => $nombretarea_tickets[$i],
								"fechacarga" => $fechacargas[$i],
								"fechaprogramacion" => $fechaprogramaciones[$i],
								"fechafinalizacion" => $fechafinalizaciones[$i],
								"tiempoinsumido" => $tiempoinsumidos[$i],
								"tecnico_id" => $tecnico_ids[$i],
								"turno_id" => $turno_ids[$i],
								"creousuario_id" => $creousuario_ids[$i]
								]);

							// Asigna tabla de ids de tareas
							$this->ticket_tarea_ids[] = $ticket_tarea->id;

							// Agrega estado de tarea asignada
							// Crea estado
							$this->ticket_estadoRepository->creaEstado($id, Carbon::now(), Ticket_Estado::$enumEstado[1]['nombre'],
																	Auth::user()->id, 'Asigna tarea '.$nombretarea_tickets[$i]);
						}
					}
					else
					{
						$ticket_tarea = $this->model->findOrFail($ticket_tarea_ids[$i])->update([
									"ticket_id" => $id,
									"tarea_id" => $tarea_ticket_ids[$i],
									"detalle" => $nombretarea_tickets[$i],
									"fechacarga" => $fechacargas[$i],
									"fechaprogramacion" => $fechaprogramaciones[$i],
									"fechafinalizacion" => $fechafinalizaciones[$i],
									"tiempoinsumido" => $tiempoinsumidos[$i],
									"tecnico_id" => $tecnico_ids[$i],
									"turno_id" => $turno_ids[$i],
									"creousuario_id" => $creousuario_ids[$i]
									]);

						// Asigna tabla de ids de tareas
						$this->ticket_tarea_ids[] = $ticket_tarea_ids[$i];
					}
				}
			}
			else // crea el registro
			{ 
				for ($i = 0; $i < count($tarea_ticket_ids); $i++)
				{
					// Si no tiene id crea el registro
					if ($ticket_tarea_ids[$i] == null || $ticket_tarea_ids[$i] == 'undefined')
					{
						if ($tarea_ticket_ids[$i] != '') 
						{
							$ticket_tarea = $this->model->create([
								"ticket_id" => $id,
								"tarea_id" => $tarea_ticket_ids[$i],
								"detalle" => $nombretarea_tickets[$i],
								"fechacarga" => $fechacargas[$i],
								"fechaprogramacion" => $fechaprogramaciones[$i],
								"fechafinalizacion" => $fechafinalizaciones[$i],
								"tiempoinsumido" => $tiempoinsumidos[$i],
								"tecnico_id" => $tecnico_ids[$i],
								"turno_id" => $turno_ids[$i],
								"creousuario_id" => $creousuario_ids[$i]
								]);

							// Asigna tabla de ids de tareas
							$this->ticket_tarea_ids[] = $ticket_tarea->id;

							// Agrega estado de tarea asignada
							// Crea estado
							$this->ticket_estadoRepository->creaEstado($id, Carbon::now(), Ticket_Estado::$enumEstado[1]['nombre'],
																	Auth::user()->id, 'Asigna tarea '.$nombretarea_tickets[$i]);
						}
					}
				}
			}
			// Borra registros anteriores que no esten en la tabla actualizada
			for ($i = 0; $i < $q_ticket_tarea; $i++)
			{
				// Busca que no exista en las tareas enviadas para grabar
				for ($j = 0, $flEncontro = false; $j < count($tarea_ticket_ids); $j++)
				{
					if ($array_ticket_tarea_id[$i] == $ticket_tarea_ids[$j])
					{
						$flEncontro = true;
						break;
					}
				}
				// Si no existe la borra
				if (!$flEncontro)
					$this->model->delete($array_ticket_tarea_id[$i]);
			}
		}
		else
		{
			$ticket_tarea = $this->model->where('ticket_id', $id)->delete();
		}

		return ['ticket_tarea_ids' => $this->ticket_tarea_ids];
	}	
}
