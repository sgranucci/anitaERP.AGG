<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Condicionentrega;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class CondicionentregaRepository implements CondicionentregaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'condemae';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'conem_condicion';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Condicionentrega $condicionentrega)
    {
        $this->model = $condicionentrega;
    }

    public function all()
    {
        $hay_condicionentrega = Condicionentrega::first();

		if (!$hay_condicionentrega)
			self::sincronizarConAnita();

        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $condicionentrega = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $condicionentrega = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $condicionentrega;
    }

    public function delete($id)
    {
    	$condicionentrega = Condicionentrega::find($id);
		//
		// Elimina anita
		self::eliminarAnita($condicionentrega->codigo);

        $condicionentrega = $this->model->destroy($id);

		return $condicionentrega;
    }

    public function find($id)
    {
        if (null == $condicionentrega = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicionentrega;
    }

    public function findPorId($id)
    {
		$retencionganancia = $this->model->where('id', $id)->first();

		return $retencionganancia;
    }

    public function findPorCodigo($codigo)
    {
		return $this->model->where('codigo', $codigo)->first();
    }
    
    public function findOrFail($id)
    {
        if (null == $condicionentrega = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicionentrega;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                        'sistema' => 'compras',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Condicionentrega::all();
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
            'sistema' => 'compras',
            'campos' => '
			conem_condicion,
    		conem_desc,
            conem_dias
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$arr_campos = [
				"nombre" => $data->conem_desc,
				"codigo" => $data->conem_condicion,
                "dias" => $data->conem_dias
            	];
	
        	$condicionentrega = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'sistema' => 'compras',
            'campos' => ' 
				conem_condicion,
				conem_desc,
                conem_dias
				',
            'valores' => " 
				'".$request['codigo']."', 
                '".$request['nombre']."',
				'".$request['dias']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'valores' => " 
                conem_condicion 	                = '".$request['codigo']."',
                conem_dias 	                        = '".$request['dias']."',
                conem_desc 	               			= '".$request['nombre']."' "
					,
				'whereArmado' => " WHERE conem_condicion = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'whereArmado' => " WHERE conem_condicion = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	// Devuelve ultimo codigo de clientes + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
                'sistema' => 'compras',
				'tabla' => $this->tableAnita, 
				'campos' => " max(conem_condicion) as $this->keyFieldAnita "
				);
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (count($dataAnita) > 0) 
		{
			$codigo = ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0');
			$codigo = $codigo + 1;
		}
	}
	
}
