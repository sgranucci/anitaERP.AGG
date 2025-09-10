<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Localidad_Uif;
use App\Repositories\Uif\Localidad_UifRepositoryInterface;
use App\Repositories\Uif\Provincia_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;
use App\ApiAnita;

class Localidad_UifRepository implements Localidad_UifRepositoryInterface
{
    protected $model;
    protected $table = 'localidad';
    protected $keyFieldAnita = 'loc_localidad';
    protected $provincia_uifRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Localidad_Uif $localidad_uif,
                                Provincia_UifRepositoryInterface $provincia_uifrepository)
    {
        $this->model = $localidad_uif;
        $this->provincia_uifRepository = $provincia_uifrepository;
    }

    public function all()
    {
        $localidad_uif = $this->model->with('provincias')->get();

        return $localidad_uif;
    }

    public function create(array $data)
    {
        $localidad_uif = $this->model->create($data);

        return($localidad_uif);
    }

    public function update(array $data, $id)
    {
        $localidad_uif = $this->model->findOrFail($id)->update($data);

		return $localidad_uif;
    }

    public function delete($id)
    {
    	$localidad_uif = $this->model->find($id);

        $localidad_uif = $this->model->destroy($id);

		return $localidad_uif;
    }

    public function find($id)
    {
        if (null == $localidad_uif = $this->model->with('provincias')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $localidad_uif;
    }

    public function findOrFail($id)
    {
        if (null == $localidad_uif = $this->model->with('provincias')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $localidad_uif;
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                    'sistema' => 'base_admin',
					'campos' => $this->keyFieldAnita, 
					'orderBy' => $this->keyFieldAnita, 
					'tabla' => $this->table );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		if ($dataAnita)
		{
		    for ($_ii = 0; $_ii < count($dataAnita); $_ii++)
		    {
            	$this->traerRegistroDeAnita($_ii);
	    	}
		}
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->table, 
            'sistema' => 'base_admin',
            'campos' => '
                loc_localidad,
                loc_desc,
				loc_cod_postal,
				loc_partido,
				loc_provincia,
				loc_cod_part
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0)
        {
            $data = $dataAnita[0];

            // Lee la provincia */
            if ($data->loc_provincia == 2)
                $provincia_uif_id = 2;
            else
                if ($data->loc_provincia == 1)
                    $provincia_uif_id = 1;
                else    
                    $provincia_uif_id = NULL;

            $this->model->create([
                "nombre" => $data->loc_desc,
                "codigopostal" => $data->loc_cod_postal,
                "codigo" => $data->loc_localidad,
                "provincia_uif_id" => $provincia_uif_id
            ]);
        }
    }

    public function leeLocalidad_Uif($consulta, $localidad_uif_id = null)
    {
		$columns = ['localidad_uif.id', 'localidad_uif.nombre', 'provincia_uif.nombre', 'localidad_uif.codigopostal', 
                    'localidad_uif.codigo'];
        $columnsOut = ['id', 'nombre', 'nombreprovincia', 'codigopostal', 'codigoanita'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('localidad_uif.id as id',
									'localidad_uif.nombre as nombre',
                                    'provincia_uif.nombre as nombreprovincia',
									'localidad_uif.codigopostal as codigopostal',
                                    'localidad_uif.codigo as codigoanita')
                            ->leftjoin('provincia_uif', 'provincia_uif.id', '=', 'localidad_uif.provincia_id');

        if (isset($localidad_uif_id))
            $data = $data->where('localidad_uif.id', $localidad_uif_id);

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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultalocalidad_uif">Elegir</a></td>';
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
