<?php

namespace App\Repositories\Ticket;

use App\Models\Ticket\Subcategoria_Ticket;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Subcategoria_TicketRepository implements Subcategoria_TicketRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Subcategoria_Ticket $subcategoria_ticket)
    {
        $this->model = $subcategoria_ticket;
    }

    public function create(array $data, $id)
    {
		return self::guardarSubcategoria_Ticket($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
		$subcategoria_ticket = $this->model->create($data);
	}

    public function update(array $data, $id)
    {
		return self::guardarSubcategoria_Ticket($data, 'update', $id);
    }

    public function delete($categoria_ticket_id)
    {
        return $this->model->where('categoria_ticket_id', $categoria_ticket_id)->delete();
    }

    public function find($id)
    {
        if (null == $subcategoria_ticket = $this->model->with('categoria_tickets')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $subcategoria_ticket;
    }

    public function findOrFail($id)
    {
        if (null == $subcategoria_ticket = $this->model->with('categoria_tickets')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $subcategoria_ticket;
    }

	private function guardarSubcategoria_Ticket($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$subcategoria_ticket = $this->model->where('categoria_ticket_id', $id)->get()->pluck('id')->toArray();
			$q_subcategoria_ticket = count($subcategoria_ticket);
		}

		// Graba subcategorias
		if (isset($data))
		{
			$nombre_subcategorias = $data['nombre_subcategorias'];
			
			if ($funcion == 'update')
			{
				$_id = $subcategoria_ticket;

				// Borra los que sobran
				if ($q_subcategoria_ticket > count($nombre_subcategorias))
				{
					for ($d = count($nombre_subcategorias); $d < $q_subcategoria_ticket; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_subcategoria_ticket && $i < count($nombre_subcategorias); $i++)
				{
					if ($i < count($nombre_subcategorias))
					{
						$subcategoria_ticket = $this->model->findOrFail($_id[$i])->update([
									"categoria_ticket_id" => $id,
									"nombre" => $nombre_subcategorias[$i]
									]);
					}
				}
				if ($q_subcategoria_ticket > count($nombre_subcategorias))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($nombre_subcategorias); $i_movimiento++)
			{
				if ($nombre_subcategorias[$i_movimiento] != '') 
				{
					$subcategoria_ticket = $this->model->create([
						"categoria_ticket_id" => $id,
						"nombre" => $nombre_subcategorias[$i_movimiento]
						]);
				}
			}
		}
		else
		{
			$subcategoria_ticket = $this->model->where('categoria_ticket_id', $id)->delete();
		}

		return $subcategoria_ticket;
	}

    public function leeSubcategoria_Ticket($consulta, $categoria_ticket_id = null, $areadestino_id = null)
    {
		$columns = ['subcategoria_ticket.id', 'subcategoria_ticket.nombre', 'categoria_ticket.nombre', 'categoria_ticket.id', 'areadestino.nombre',
					'areadestino.id'];
        $columnsOut = ['id', 'nombre', 'nombrecategoria_ticket', 'idcategoria_ticket', 'nombreareadestino', 'idareadestino'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('subcategoria_ticket.id as id',
									'subcategoria_ticket.nombre as nombre',
									'categoria_ticket.nombre as nombrecategoria_ticket',
									'categoria_ticket.id as idcategoria_ticket',
									'categoria_ticket.areadestino_id as idareadestino',
									'areadestino.nombre as nombreareadestino'
									)
							->join('categoria_ticket', 'categoria_ticket.id', '=', 'subcategoria_ticket.categoria_ticket_id')
							->join('areadestino', 'areadestino.id', '=', 'categoria_ticket.areadestino_id');

        if (isset($categoria_ticket_id))
            $data = $data->where('categoria_ticket.id', $categoria_ticket_id);

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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultasubcategoria_ticket">Elegir</a></td>';
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
