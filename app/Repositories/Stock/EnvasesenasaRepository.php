<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Envasesenasa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class EnvasesenasaRepository implements EnvasesenasaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'envsenasa';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'envs_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Envasesenasa $envasesenasa)
    {
        $this->model = $envasesenasa;
    }

    public function all()
    {
        $hay_envasesenasas = Envasesenasa::first();

		if (!$hay_envasesenasas)
			self::sincronizarConAnita();

        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $envasesenasa = $this->model->create($data);
		
		// Graba anita
		self::guardarAnita($data, $envsenasa->id);
    }

    public function update(array $data, $id)
    {
        $envasesenasa = $this->model->findOrFail($id)
            ->update($data);
		
		// Actualiza anita
		self::actualizarAnita($data, $id);

		return $envasesenasa;
    }

    public function delete($id)
    {
    	$envasesenasa = Envasesenasa::find($id);
		//
		// Elimina anita
		self::eliminarAnita($id);

        $envasesenasa = $this->model->destroy($id);

		return $envasesenasa;
    }

    public function find($id)
    {
        if (null == $envasesenasa = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $envasesenasa;
    }

    public function findOrFail($id)
    {
        if (null == $envasesenasa = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $envasesenasa;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Envasesenasa::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

        foreach ($dataAnita as $value) {
            if (!in_array(ltrim($value->{$this->keyField}, '0'), $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
		// Formato El Bierzo
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
			'sistema' => 'ventas',
            'campos' => '
			envs_codigo,
    		envs_desc
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$arr_campos = [
				"nombre" => $data->envs_desc
            	];
	
        	$envasesenasa = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'sistema' => 'ventas', 
			'acc' => 'insert',
            'campos' => ' 
				envs_codigo,
    			envs_desc
				',
            'valores' => " 
				'".$id."', 
				'".$request['nombre']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'acc' => 'update', 
                'sistema' => 'ventas', 
                'tabla' => $this->tableAnita, 
				'valores' => " 
                envs_codigo 	                = '".$id."',
                envs_desc 	                = '".$request['nombre']."' "
					,
				'whereArmado' => " WHERE envs_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'sistema' => 'ventas', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE envs_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

}
