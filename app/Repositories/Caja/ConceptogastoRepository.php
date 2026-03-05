<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Conceptogasto;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ConceptogastoRepository implements ConceptogastoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Conceptogasto $conceptogasto)
    {
        $this->model = $conceptogasto;
    }

    public function all()
    {
        return $this->model->with('conceptogasto_cuentacontables')->orderBy('nombre')->get();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
    	$conceptogasto = $this->model->find($id);

        $conceptogasto = $this->model->destroy($id);

		return $conceptogasto;
    }

    public function find($id)
    {
        if (null == $conceptogasto = $this->model->with('conceptogasto_cuentacontables')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $conceptogasto;
    }

    public function findPorId($id)
    {
		$retencionganancia = $this->model->with('conceptogasto_cuentacontables')->where('id', $id)->first();

		return $retencionganancia;
    }

    public function findOrFail($id)
    {
        if (null == $conceptogasto = $this->model->with('conceptogasto_cuentacontables')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $conceptogasto;
    }

    public function leeConceptogasto($consulta)
    {
		$columns = ['conceptogasto.id', 'conceptogasto.nombre'];
        $columnsOut = ['id', 'nombre'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('conceptogasto.id as id',
									'conceptogasto.nombre as nombre')
							->orWhere(function ($query) use ($count, $consulta, $columns) {
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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultaconceptogasto">Elegir</a></td>';
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
