<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Actividad_Uif;
use App\Repositories\Uif\Actividad_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Actividad_UifRepository implements Actividad_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Actividad_Uif $actividad_uif)
    {
        $this->model = $actividad_uif;
    }

    public function all()
    {
        $actividad_uif = $this->model->get();

        return $actividad_uif;
    }

    public function create(array $data)
    {
        $actividad_uif = $this->model->create($data);

        return($actividad_uif);
    }

    public function update(array $data, $id)
    {
        $actividad_uif = $this->model->findOrFail($id)->update($data);

		return $actividad_uif;
    }

    public function delete($id)
    {
    	$actividad_uif = $this->model->find($id);

        $actividad_uif = $this->model->destroy($id);

		return $actividad_uif;
    }

    public function find($id)
    {
        if (null == $actividad_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $actividad_uif;
    }

    public function findOrFail($id)
    {
        if (null == $actividad_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $actividad_uif;
    }

    public function leeActividad_Uif($consulta, $actividad_uif_id = null)
    {
		$columns = ['actividad_uif.id', 'actividad_uif.nombre', 'actividad_uif.riesgo', 'actividad_uif.puntaje'];
        $columnsOut = ['id', 'nombre', 'riesgo', 'puntaje'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('actividad_uif.id as id',
									'actividad_uif.nombre as nombre',
                                    'actividad_uif.riesgo as riesgo',
									'actividad_uif.puntaje as puntaje');

        if (isset($actividad_uif_id))
            $data = $data->where('actividad_uif.id', $actividad_uif_id);

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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultaactividad_uif">Elegir</a></td>';
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
