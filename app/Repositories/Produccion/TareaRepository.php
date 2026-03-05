<?php

namespace App\Repositories\Produccion;

use App\Models\Produccion\Tarea;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class TareaRepository implements TareaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'tarea';
    protected $keyField = 'id';
    protected $keyFieldAnita = 'tar_tarea';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tarea $tarea)
    {
        $this->model = $tarea;
    }

    public function all()
    {
        $hay_tareas = Tarea::first();

		if (!$hay_tareas)
			self::sincronizarConAnita();

        return $this->model->orderBy('nombre')->get();
    }

    public function create(array $data)
    {
        $tarea = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data, $tarea->id);
    }

    public function update(array $data, $id)
    {
        $tarea = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $id);

		return $tarea;
    }

    public function delete($id)
    {
    	$tarea = Tarea::find($id);

        try{
        
            $tarea = $this->model->destroy($id);

        } catch (\Exception $e) 
		{
		    dd($e->getMessage());
			return $e->getMessage();
		}

        return $tarea;
    }

    public function find($id)
    {
        if (null == $tarea = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tarea;
    }

    public function findOrFail($id)
    {
        if (null == $tarea = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tarea;
    }

    private function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Tarea::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

        foreach ($dataAnita as $value) {
            if (!in_array($value->{$this->keyField}, $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    private function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
			tar_desc
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$arr_campos = [
				"nombre" => $data->tar_desc,
            	];
	
        	$tarea = $this->model->create($arr_campos);
        }
    }

	private function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'campos' => ' 
				tar_tarea,
    			tar_desc
				',
            'valores' => " 
				'".$id."', 
				'".$request['nombre']."' "
        );
        $apiAnita->apiCall($data);
	}

	private function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'valores' => " 
                tar_desc 	                = '".$request['nombre']."' "
					,
				'whereArmado' => " WHERE tar_tarea = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	private function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE tar_tarea = '".$id."' " );
        $apiAnita->apiCall($data);
	}

}
