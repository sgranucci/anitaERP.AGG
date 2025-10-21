<?php

namespace App\Repositories\Contable;

use App\Models\Contable\Centrocosto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class CentrocostoRepository implements CentrocostoRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'ccosto';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'ccos_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Centrocosto $centrocosto)
    {
        $this->model = $centrocosto;
    }

    public function all()
    {
        $hay_centrocosto = Centrocosto::first();

		if (!$hay_centrocosto)
			self::sincronizarConAnita();

        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $centrocosto = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $centrocosto = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $centrocosto;
    }

    public function delete($id)
    {
    	$centrocosto = $this->model->find($id);
		//
		// Elimina anita
		self::eliminarAnita($centrocosto->codigo);

        $centrocosto = $this->model->destroy($id);

		return $centrocosto;
    }

    public function find($id)
    {
        if (null == $centrocosto = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $centrocosto;
    }

    public function findPorId($id)
    {
        $centrocosto = $this->model->where('id', $id)->first();

        return $centrocosto;
    }

    public function findPorCodigo($codigo)
    {
        $centrocosto = $this->model->where('codigo', $codigo)->first();

        return $centrocosto;
    }

    public function findPorNombre($nombre)
    {
        $centrocosto = $this->model->where('nombre', 'LIKE', '%'.$nombre.'%')->pluck('id')->toArray();

        return $centrocosto;
    }

    public function findOrFail($id)
    {
        if (null == $centrocosto = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $centrocosto;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                        'sistema' => 'contab',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Centrocosto::all();
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
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'sistema' => 'contab',
            'campos' => '
			ccos_codigo,
    		ccos_desc,
            ccos_grupo,
            ccos_abreviatura
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$arr_campos = [
				"nombre" => $data->ccos_desc,
				"codigo" => $data->ccos_codigo,
                "abreviatura" => $data->ccos_abreviatura
            	];
	
        	$centrocosto = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'sistema' => 'contab',
            'campos' => ' 
				ccos_codigo,
				ccos_desc,
                ccos_grupo
                ccos_abreviatura
				',
            'valores' => " 
				'".$request['codigo']."', 
                '".$request['nombre']."',
                '0',
				'".$request['abreviatura']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
                'sistema' => 'contab',
				'valores' => " 
                ccos_codigo 	                = '".$request['codigo']."',
                ccos_desc 	                    = '".$request['nombre']."',
                ccos_grupo 	                    = '0',
                ccos_abreviatura 	            = '".$request['abreviatura']."' "
					,
				'whereArmado' => " WHERE ccos_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
                'sistema' => 'contab',
				'whereArmado' => " WHERE ccos_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

}
