<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Tipoempresa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class TipoempresaRepository implements TipoempresaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'tipoemp';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'tipoe_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tipoempresa $tipoempresa)
    {
        $this->model = $tipoempresa;
    }

    public function all()
    {
        $hay_tipoempresa = Tipoempresa::first();

		if (!$hay_tipoempresa)
			self::sincronizarConAnita();

        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $tipoempresa = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $tipoempresa = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $tipoempresa;
    }

    public function delete($id)
    {
    	$tipoempresa = Tipoempresa::find($id);
		//
		// Elimina anita
		self::eliminarAnita($tipoempresa->codigo);

        $tipoempresa = $this->model->destroy($id);

		return $tipoempresa;
    }

    public function find($id)
    {
        if (null == $tipoempresa = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipoempresa;
    }

    public function findOrFail($id)
    {
        if (null == $tipoempresa = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipoempresa;
    }

    public function findPorCodigo($codigo)
    {
		return $this->model->where('codigo', $codigo)->first();
    }

    public function findPorId($id)
    {
		return $this->model->where('id', $id)->first();
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                        'sistema' => 'compras',
						'campos' => "
                        			tipoe_codigo as codigo,
    		                        tipoe_codigo",
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Tipoempresa::all();
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
			tipoe_codigo,
    		tipoe_desc
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$arr_campos = [
				"nombre" => $data->tipoe_desc,
				"codigo" => $data->tipoe_codigo,
            	];
	
        	$tipoempresa = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'sistema' => 'compras',
            'campos' => ' 
				tipoe_codigo,
				tipoe_desc
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
                tipoe_codigo 	                = '".$request['codigo']."',
                tipoe_desc 	               		= '".$request['nombre']."' "
					,
				'whereArmado' => " WHERE tipoe_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'whereArmado' => " WHERE tipoe_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	// Devuelve ultimo codigo de clientes + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
                'sistema' => 'compras',
				'tabla' => $this->tableAnita, 
				'campos' => " max(tipoe_codigo) as $this->keyFieldAnita "
				);
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (count($dataAnita) > 0) 
		{
			$codigo = ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0');
			$codigo = $codigo + 1;
		}
        else    
            $codigo = 1;
	}
	
}
