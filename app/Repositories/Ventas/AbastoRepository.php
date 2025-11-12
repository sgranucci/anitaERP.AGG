<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Abasto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class AbastoRepository implements AbastoRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'abasto';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'aba_abasto';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Abasto $abasto)
    {
        $this->model = $abasto;
    }

    public function all()
    {
        $hay_abastos = Abasto::first();

		if (!$hay_abastos)
			self::sincronizarConAnita();

        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $abasto = $this->model->create($data);
		
		// Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $abasto = $this->model->findOrFail($id)
            ->update($data);
		
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $abasto;
    }

    public function delete($id)
    {
    	$abasto = Abasto::find($id);
		//
		// Elimina anita
		self::eliminarAnita($abasto->codigo);

        $abasto = $this->model->destroy($id);

		return $abasto;
    }

    public function find($id)
    {
        if (null == $abasto = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $abasto;
    }

    public function findOrFail($id)
    {
        if (null == $abasto = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $abasto;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Abasto::all();
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
			aba_abasto,
    		aba_desc,
    		aba_tasa
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$arr_campos = [
				"nombre" => $data->aba_desc,
				"codigo" => $data->aba_abasto,
				"tasa" => $data->aba_tasa,
            	];
	
        	$abasto = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'sistema' => 'ventas', 
			'acc' => 'insert',
            'campos' => ' 
				aba_abasto,
    			aba_desc,
    			aba_tasa
				',
            'valores' => " 
				'".$request['codigo']."', 
				'".$request['nombre']."',
				'".$request['tasa']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'acc' => 'update', 'sistema' => 'ventas', 'tabla' => $this->tableAnita, 
				'valores' => " 
                aba_abasto 	                = '".$request['codigo']."',
                aba_desc 	                = '".$request['nombre']."',
                aba_tasa 	                = '".$request['tasa']."' "
					,
				'whereArmado' => " WHERE aba_abasto = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'sistema' => 'ventas', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE aba_abasto = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	// Devuelve ultimo codigo de abastos + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
				'tabla' => $this->tableAnita, 
				'campos' => " max(aba_abasto) as $this->keyFieldAnita "
				);
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (count($dataAnita) > 0) 
		{
			$codigo = ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0');
			$codigo = $codigo + 1;
		}
	}
	
}
