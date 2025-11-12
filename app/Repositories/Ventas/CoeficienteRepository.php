<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Coeficiente;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class CoeficienteRepository implements CoeficienteRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'coef';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'coef_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Coeficiente $coeficiente)
    {
        $this->model = $coeficiente;
    }

    public function all()
    {
        $hay_coeficientes = Coeficiente::first();

		if (!$hay_coeficientes)
			self::sincronizarConAnita();

        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $coeficiente = $this->model->create($data);
		
		// Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $coeficiente = $this->model->findOrFail($id)
            ->update($data);
		
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $coeficiente;
    }

    public function delete($id)
    {
    	$coeficiente = Coeficiente::find($id);
		//
		// Elimina anita
		self::eliminarAnita($coeficiente->codigo);

        $coeficiente = $this->model->destroy($id);

		return $coeficiente;
    }

    public function find($id)
    {
        if (null == $coeficiente = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $coeficiente;
    }

    public function findOrFail($id)
    {
        if (null == $coeficiente = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $coeficiente;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Coeficiente::all();
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
			coef_codigo,
    		coef_desc,
            coef_porc_div,
    		coef_tasa
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$arr_campos = [
				"nombre" => $data->coef_desc,
				"codigo" => $data->coef_codigo,
                "porcentajedivision" => $data->coef_porc_div,
				"tasa" => $data->coef_tasa,
            	];
	
        	$coeficiente = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 'sistema' => 'ventas', 
			'acc' => 'insert',
            'campos' => ' 
				coef_codigo,
    			coef_desc,
                coef_porc_div,
    			coef_tasa
				',
            'valores' => " 
				'".$request['codigo']."', 
				'".$request['nombre']."',
                '".$request['porcentajedivision']."',
				'".$request['tasa']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'acc' => 'update', 'sistema' => 'ventas', 'tabla' => $this->tableAnita, 
				'valores' => " 
                coef_codigo 	            = '".$request['codigo']."',
                coef_desc 	                = '".$request['nombre']."',
                coef_porc_div 	            = '".$request['porcentajedivision']."',
                coef_tasa 	                = '".$request['tasa']."' "
					,
				'whereArmado' => " WHERE coef_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'sistema' => 'ventas', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE coef_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	// Devuelve ultimo codigo de coeficientes + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
				'tabla' => $this->tableAnita, 
				'campos' => " max(coef_codigo) as $this->keyFieldAnita "
				);
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (count($dataAnita) > 0) 
		{
			$codigo = ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0');
			$codigo = $codigo + 1;
		}
	}
	
}
