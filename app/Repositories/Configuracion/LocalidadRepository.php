<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Localidad;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class LocalidadRepository implements LocalidadRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'localidad';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'provi_localidad';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Localidad $localidad)
    {
        $this->model = $localidad;
    }

    public function all()
    {
        $hay_localidad = Localidad::first();

        //if (!$hay_localidad)
		//	self::sincronizarConAnita();

        return $this->model->with('provincias:id,nombre')->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $localidad = $this->model->create($data);
		//
		// Graba anita
		//self::guardarAnita($data, $data['codigo']);
    }

    public function update(array $data, $id)
    {
        $localidad = $this->model->findOrFail($id)
            ->update($data);

        // Actualiza anita
		//self::actualizarAnita($data, $data['codigo']);

		return $localidad;
    }

    public function delete($id)
    {
    	$localidad = $this->model->find($id);
		// Elimina anita
		//self::eliminarAnita($localidad->codigo);

        $localidad = $this->model->destroy($id);

		return $localidad;
    }

    public function find($id)
    {
        if (null == $localidad = $this->model->with('provincias:id,nombre')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $localidad;
    }

    public function findPorId($id)
    {
        $localidad = $this->model->with('provincias:id,nombre')->where('id', $id)->first();

        return $localidad;
    }

    public function findPorCodigo($codigo)
    {
        $localidad = $this->model->with('provincias:id,nombre')->where('codigo', $codigo)->first();

        return $localidad;
    }

    public function findOrFail($id)
    {
        if (null == $localidad = $this->model->with('provincias:id,nombre')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $localidad;
    }

	public function leeLocalidad($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $localidad = Localidad::select('localidad.id as id',
                                        'localidad.nombre as nombre',
										'localidad.codigopostal as codigopostal',
                                        'provincia.nombre as nombreprovincia',
										'localidad.codigo as codigo',
                                        'localidad.codigosenasa as codigosenasa')
                                ->join('provincia', 'provincia.id', '=', 'localidad.provincia_id')
                                ->where('localidad.id', $busqueda)
                                ->orWhere('localidad.nombre', 'like', '%'.$busqueda.'%')
                                ->orWhere('localidad.codigopostal', 'like', '%'.$busqueda.'%')
								->orWhere('provincia.nombre', 'like', '%'.$busqueda,'%')
                                ->orWhere('localidad.codigo', 'like', '%'.$busqueda,'%')
                                ->orderby('id', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $localidad = $localidad->paginate(10);
            else
                $localidad = $localidad->get();
        }
        else
            $localidad = $localidad->get();

        return $localidad;
    }

    public function consultaLocalidad($consulta, $provincia_id)
    {
        ini_set('max_execution_time', '300');
	  	ini_set('memory_limit', '512M');

		$columns = ['localidad.id', 'localidad.nombre', 'localidad.codigopostal', 'localidad.codigo', 'localidad.provincia_id', 'provincia.nombre', 'localidad.codigosenasa'];
        $columnsOut = ['id', 'nombre', 'codigopostal', 'codigo', 'provincia_id', 'nombreprovincia', 'codigosenasa'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('localidad.id as id',
									'localidad.nombre as nombre',
									'localidad.codigopostal as codigopostal',
									'localidad.codigo as codigo',
									'localidad.provincia_id as provincia_id',
                                    'provincia.nombre as nombreprovincia', 
                                    'localidad.codigosenasa as codigosenasa')
                            ->join('provincia', 'provincia.id', 'localidad.provincia_id')
                            ->where('localidad.provincia_id', $provincia_id)
							->where(function ($query) use ($count, $consulta, $columns) {
                        			for ($i = 0; $i < $count; $i++)
                                    {
                                        if ($columns[$i] != 'provincia.nombre')
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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultalocalidad">Elegir</a></td>';
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
