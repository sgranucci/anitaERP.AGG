<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Provincia_Uif;
use App\Repositories\Uif\Provincia_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Provincia_UifRepository implements Provincia_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Provincia_Uif $provincia_uif)
    {
        $this->model = $provincia_uif;
    }

    public function all()
    {
        $provincia_uif = $this->model->get();

        return $provincia_uif;
    }

    public function create(array $data)
    {
        $provincia_uif = $this->model->create($data);

        return($provincia_uif);
    }

    public function update(array $data, $id)
    {
        $provincia_uif = $this->model->findOrFail($id)->update($data);

		return $provincia_uif;
    }

    public function delete($id)
    {
    	$provincia_uif = $this->model->find($id);

        $provincia_uif = $this->model->destroy($id);

		return $provincia_uif;
    }

    public function find($id)
    {
        if (null == $provincia_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $provincia_uif;
    }

    public function findOrFail($id)
    {
        if (null == $provincia_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $provincia_uif;
    }

    public function leeProvincia_Uif($consulta, $provincia_uif_id = null)
    {
		$columns = ['provincia_uif.id', 'provincia_uif.nombre', 'provincia_uif.riesgo', 'provincia_uif.puntaje'];
        $columnsOut = ['id', 'nombre', 'riesgo', 'puntaje'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('provincia_uif.id as id',
									'provincia_uif.nombre as nombre',
                                    'provincia_uif.riesgo as riesgo',
									'provincia_uif.puntaje as puntaje');

        if (isset($provincia_uif_id))
            $data = $data->where('provincia_uif.id', $provincia_uif_id);

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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultaprovincia_uif">Elegir</a></td>';
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
