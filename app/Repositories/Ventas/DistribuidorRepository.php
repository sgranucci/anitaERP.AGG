<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Distribuidor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class DistribuidorRepository implements DistribuidorRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'distribuidor';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'distr_distribuidor';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Distribuidor $distribuidor)
    {
        $this->model = $distribuidor;
    }

    public function all()
    {
        $hay_distribuidores = Distribuidor::first();

		if (!$hay_distribuidores)
			self::sincronizarConAnita();

        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $distribuidor = $this->model->create($data);
		
		// Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $distribuidor = $this->model->findOrFail($id)
            ->update($data);
		
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $distribuidor;
    }

    public function delete($id)
    {
    	$distribuidor = Distribuidor::find($id);
		//
		// Elimina anita
		self::eliminarAnita($distribuidor->codigo);

        $distribuidor = $this->model->destroy($id);

		return $distribuidor;
    }

    public function find($id)
    {
        if (null == $distribuidor = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $distribuidor;
    }

    public function findOrFail($id)
    {
        if (null == $distribuidor = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $distribuidor;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Distribuidor::all();
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
			distr_distribuidor,
    		distr_nombre,
    		distr_porc_com
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$arr_campos = [
				"nombre" => $data->distr_nombre,
				"codigo" => $data->distr_distribuidor,
				"porcentajecomision" => $data->distr_porc_com,
            	];
	
        	$distribuidor = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'sistema' => 'ventas', 
			'acc' => 'insert',
            'campos' => ' 
				distr_distribuidor,
    			distr_nombre,
    			distr_porc_com
				',
            'valores' => " 
				'".$request['codigo']."', 
				'".$request['nombre']."',
				'".$request['porcentajecomision']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'acc' => 'update', 'sistema' => 'ventas', 'tabla' => $this->tableAnita, 
				'valores' => " 
                distr_distribuidor 	                = '".$request['codigo']."',
                distr_nombre 	                = '".$request['nombre']."',
                distr_porc_com 	                = '".$request['porcentajecomision']."' "
					,
				'whereArmado' => " WHERE distr_distribuidor = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'sistema' => 'ventas', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE distr_distribuidor = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	// Devuelve ultimo codigo de distribuidors + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
				'tabla' => $this->tableAnita, 
				'campos' => " max(distr_distribuidor) as $this->keyFieldAnita "
				);
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (count($dataAnita) > 0) 
		{
			$codigo = ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0');
			$codigo = $codigo + 1;
		}
	}
	
}
