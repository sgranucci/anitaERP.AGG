<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Condicioncompra;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class CondicioncompraRepository implements CondicioncompraRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'condcmae';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'concm_condicion';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Condicioncompra $condicioncompra)
    {
        $this->model = $condicioncompra;
    }

    public function all()
    {
        $hay_condicioncompra = Condicioncompra::first();

		if (!$hay_condicioncompra)
			self::sincronizarConAnita();

        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $condicioncompra = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $condicioncompra = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $condicioncompra;
    }

    public function delete($id)
    {
    	$condicioncompra = Condicioncompra::find($id);
		//
		// Elimina anita
		self::eliminarAnita($condicioncompra->codigo);

        $condicioncompra = $this->model->destroy($id);

		return $condicioncompra;
    }

    public function find($id)
    {
        if (null == $condicioncompra = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicioncompra;
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
        if (null == $condicioncompra = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicioncompra;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                        'sistema' => 'compras',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Condicioncompra::all();
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
			concm_condicion,
    		concm_desc
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$arr_campos = [
				"nombre" => $data->concm_desc,
				"codigo" => $data->concm_condicion,
            	];
	
        	$condicioncompra = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'sistema' => 'compras',
            'campos' => ' 
				concm_condicion,
				concm_desc
				',
            'valores' => " 
				'".$request['codigo']."', 
				'".$request['nombre']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'valores' => " 
                concm_condicion 	                = '".$request['codigo']."',
                concm_desc 	               			= '".$request['nombre']."' "
					,
				'whereArmado' => " WHERE concm_condicion = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'whereArmado' => " WHERE concm_condicion = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	// Devuelve ultimo codigo de clientes + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
                'sistema' => 'compras',
				'tabla' => $this->tableAnita, 
				'campos' => " max(concm_condicion) as $this->keyFieldAnita "
				);
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (count($dataAnita) > 0) 
		{
			$codigo = ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0');
			$codigo = $codigo + 1;
		}
	}
	
}
