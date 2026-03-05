<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Profesion_Uif;
use App\Repositories\Uif\Profesion_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;
use App\ApiAnita;

class Profesion_UifRepository implements Profesion_UifRepositoryInterface
{
    protected $model;
    protected $table = 'profesion';
    protected $keyFieldAnita = 'iprofesionid';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Profesion_Uif $profesion_uif)
    {
        $this->model = $profesion_uif;
    }

    public function all()
    {
        $profesion_uif = $this->model->get();

        return $profesion_uif;
    }

    public function create(array $data)
    {
        $profesion_uif = $this->model->create($data);

        return($profesion_uif);
    }

    public function update(array $data, $id)
    {
        $profesion_uif = $this->model->findOrFail($id)->update($data);

		return $profesion_uif;
    }

    public function delete($id)
    {
    	$profesion_uif = $this->model->find($id);

        $profesion_uif = $this->model->destroy($id);

		return $profesion_uif;
    }

    public function find($id)
    {
        if (null == $profesion_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $profesion_uif;
    }

    public function findOrFail($id)
    {
        if (null == $profesion_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $profesion_uif;
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
                iprofesionid,
                cdesc
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0)
        {
            $data = $dataAnita[0];

            $this->model->create([
                "nombre" => $data->cdesc,
                "codigo" => $data->iprofesionid
            ]);
        }
    }

    public function leeProfesion_Uif($consulta, $profesion_uif_id = null)
    {
		$columns = ['profesion_uif.id', 'profesion_uif.nombre', 'profesion_uif.codigo'];
        $columnsOut = ['id', 'nombre', 'codigoanita'];

		$consulta = strtoupper($consulta);

		$count = count($columns);
		$data = $this->model->select('profesion_uif.id as id',
									'profesion_uif.nombre as nombre',
                                    'profesion_uif.codigo as codigoanita');

        if (isset($profesion_uif_id))
            $data = $data->where('profesion_uif.id', $profesion_uif_id);

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
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultaprofesion_uif">Elegir</a></td>';
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
