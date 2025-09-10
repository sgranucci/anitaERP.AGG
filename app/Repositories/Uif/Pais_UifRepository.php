<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Pais_Uif;
use App\Repositories\Uif\Pais_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Pais_UifRepository implements Pais_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Pais_Uif $pais_uif)
    {
        $this->model = $pais_uif;
    }

    public function all()
    {
        $pais_uif = $this->model->get();

        return $pais_uif;
    }

    public function create(array $data)
    {
        $pais_uif = $this->model->create($data);

        return($pais_uif);
    }

    public function update(array $data, $id)
    {
        $pais_uif = $this->model->findOrFail($id)->update($data);

		return $pais_uif;
    }

    public function delete($id)
    {
    	$pais_uif = $this->model->find($id);

        $pais_uif = $this->model->destroy($id);

		return $pais_uif;
    }

    public function find($id)
    {
        if (null == $pais_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pais_uif;
    }

    public function findOrFail($id)
    {
        if (null == $pais_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pais_uif;
    }

    public function leePais_Uif($consulta, $pais_uif_id = null)
    {
		$columns = ['pais_uif.id', 'pais_uif.nombre', 'pais_uif.riesgo', 'pais_uif.puntaje'];
        $columnsOut = ['id', 'nombre', 'riesgo', 'puntaje'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('pais_uif.id as id',
									'pais_uif.nombre as nombre',
                                    'pais_uif.riesgo as riesgo',
									'pais_uif.puntaje as puntaje');

        if (isset($pais_uif_id))
            $data = $data->where('pais_uif.id', $pais_uif_id);

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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultapais_uif">Elegir</a></td>';
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
