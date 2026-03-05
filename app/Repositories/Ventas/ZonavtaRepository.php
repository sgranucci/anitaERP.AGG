<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Zonavta;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class ZonavtaRepository implements ZonavtaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Zonavta $zonavta)
    {
        $this->model = $zonavta;
    }

    public function all()
    {
        return $this->model->with('provincias:id,nombre')->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $zonavta = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $zonavta = $this->model->findOrFail($id)
            ->update($data);

		return $zonavta;
    }

    public function delete($id)
    {
    	$zonavta = $this->model->find($id);

        $zonavta = $this->model->destroy($id);

		return $zonavta;
    }

    public function find($id)
    {
        if (null == $zonavta = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $zonavta;
    }

    public function findPorId($id)
    {
        $zonavta = $this->model->where('id', $id)->first();

        return $zonavta;
    }

    public function findPorCodigo($codigo)
    {
        $zonavta = $this->model->where('codigo', $codigo)->first();

        return $zonavta;
    }

    public function findOrFail($id)
    {
        if (null == $zonavta = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $zonavta;
    }

	public function leeZonavta($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $zonavta = Zonavta::select('zonavta.id as id',
                        'zonavta.nombre as nombre',
                        'zonavta.codigo as codigo')
                ->where('zonavta.id', $busqueda)
                ->orWhere('zonavta.nombre', 'like', '%'.$busqueda.'%')
                ->orWhere('zonavta.codigo', 'like', '%'.$busqueda,'%')
                ->orderby('id', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $zonavta = $zonavta->paginate(10);
            else
                $zonavta = $zonavta->get();
        }
        else
            $zonavta = $zonavta->get();

        return $zonavta;
    }

    public function consultaZonavta($consulta)
    {
        ini_set('max_execution_time', '300');
	  	ini_set('memory_limit', '512M');

        $columns = ['zonavta.id', 'zonavta.nombre',  'zonavta.codigo'];
        $columnsOut = ['id', 'nombre', 'codigo'];

		$consulta = strtoupper($consulta);

		$count = count($columns);

        $data = $this->model->select('zonavta.id as id',
									'zonavta.nombre as nombre',
									'zonavta.codigo as codigo')
							->where(function ($query) use ($count, $consulta, $columns) {
                        			for ($i = 0; $i < $count; $i++)
                                    {
                           			    $query->orWhere($columns[$i], "LIKE", '%'. $consulta . '%');
                                    }
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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultazonavta">Elegir</a></td>';
                $output['data'] .= '<td><a class="btn btn-warning btn-sm consultaunazonavta">Consultar</a></td>';
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
