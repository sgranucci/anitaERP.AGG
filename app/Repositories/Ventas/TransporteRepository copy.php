<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Transporte;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Condicioniva;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class TransporteRepository implements TransporteRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'expreso';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'expr_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Transporte $transporte)
    {
        $this->model = $transporte;
    }

    public function all()
    {
        $hay_transportes = Transporte::first();

		if (!$hay_transportes)
			self::sincronizarConAnita();

        return $this->model->with('provincias:id,nombre')->with('localidades:id,nombre')->with('condicionivas:id,nombre')->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
		$codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $transporte = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $transporte = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $transporte;

        //return $this->model->where('id', $id)
         //   ->update($data);
    }

    public function delete($id)
    {
    	$transporte = Transporte::find($id);
		//
		// Elimina anita
		self::eliminarAnita($transporte->codigo);

        $transporte = $this->model->destroy($id);

		return $transporte;
    }

    public function find($id)
    {
        if (null == $transporte = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $transporte;
    }

    public function findOrFail($id)
    {
        if (null == $transporte = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $transporte;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Transporte::all();
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
            'campos' => '
			expr_codigo,
    		expr_nombre,
    		expr_direccion,
    		expr_localidad,
    		expr_provincia,
    		expr_cod_postal,
    		expr_telefono,
    		expr_cuit,
    		expr_cond_iva,
    		expr_nro_interno,
			expr_pat_vehiculo,
			expr_pag_acoplado,
			expr_hs_entrega
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

        	$provincia = Provincia::select('id', 'nombre')->where('id' , $data->expr_provincia)->first();
			if ($provincia)
				$provincia_id = $provincia->id;
			else
				$provincia_id = NULL;
	
        	$localidad = Localidad::select('id', 'nombre')->where('id' , $data->expr_localidad)->first();
			if ($localidad)
				$localidad_id = $localidad->id;
			else
				$localidad_id = NULL;
	
			$condicioniva_id = 1;
			switch($data->expr_cond_iva)
			{
			case '0':
				$condicioniva_id = 1;
				break;
			case '3':
				$condicioniva_id = 3;
				break;
			case '4':
				$condicioniva_id = 2;
				break;
			case '5':
				$condicioniva_id = 4;
				break;
			}

			$arr_campos = [
				"nombre" => $data->expr_nombre,
				"codigo" => $data->expr_codigo,
				"domicilio" => $data->expr_direccion,
				"provincia_id" => $provincia_id,
				"localidad_id" => $localidad_id,
				"codigopostal" => $data->expr_cod_postal,
				"telefono" => $data->expr_telefono,
				"email" => '',
				"nroinscripcion" => $data->expr_cuit,
				"condicioniva_id" => $condicioniva_id,
				"patentevehiculo" => $data->expr_pat_vehiculo,
				"patenteacoplado" => $data->expr_pag_acoplado,
				"horarioentrega" => $data->expr_hs_entrega,
            	];
	
        	$transporte = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

		$this->setCondicionIvaAnita($request, $condicioniva_id);

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'campos' => ' 
				expr_codigo,
    			expr_nombre,
    			expr_direccion,
    			expr_localidad,
    			expr_provincia,
    			expr_cod_postal,
    			expr_telefono,
    			expr_cuit,
    			expr_cond_iva,
    			expr_nro_interno,
				expr_pat_vehiculo,
				expr_pag_acoplado,
				expr_hs_entrega
				',
            'valores' => " 
				'".$request['codigo']."', 
				'".$request['nombre']."',
				'".$request['domicilio']."',
				'".$request['desc_localidad']."',
				'".$request['desc_provincia']."',
				'".$request['codigopostal']."',
				'".$request['telefono']."',
				'".$request['nroinscripcion']."',
				'".$condicioniva_id."',
				'0',
				'".$request['patentevehiculo']."',
				'".$request['patenteacoplado']."',
				'".$request['horarioentrega']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		$this->setCondicionIvaAnita($request, $condicioniva);

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'valores' => " 
                expr_codigo 	                = '".$request['codigo']."',
                expr_nombre 	                = '".$request['nombre']."',
                expr_direccion 	                = '".$request['domicilio']."',
                expr_localidad 	                = '".$request['desc_localidad']."',
                expr_provincia 	                = '".$request['desc_provincia']."',
                expr_cod_postal 	            = '".$request['codigopostal']."',
                expr_telefono 	                = '".$request['telefono']."',
                expr_cuit 	                    = '".$request['nroinscripcion']."',
                expr_cond_iva 	                = '".$condicioniva."',
                expr_pat_vehiculo 	            = '".$request['patentevehiculo']."',
                expr_pag_acoplado 	            = '".$request['patenteacoplado']."',
                expr_hs_entrega	                = '".$request['horarioentrega']."' "
					,
				'whereArmado' => " WHERE expr_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE expr_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	private function setCondicionIvaAnita($data, &$condicioniva)
	{
		$condicioniva = '0';
		switch($data['condicioniva_id'])
		{
		case '1':
			$condicioniva = '0';
			break;
		case '2':
			$condicioniva = '4';
			break;
		case '3':
			$condicioniva = '3';
			break;
		case '4':
			$condicioniva = '5';
			break;
		}
	}

	// Devuelve ultimo codigo de clientes + 1 para agregar nuevos en Anita

	private function ultimoCodigo(&$codigo) {
		$apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
				'tabla' => $this->tableAnita, 
				'campos' => " max(expr_codigo) as $this->keyFieldAnita "
				);
		$dataAnita = json_decode($apiAnita->apiCall($data));

		if (count($dataAnita) > 0) 
		{
			$codigo = ltrim($dataAnita[0]->{$this->keyFieldAnita}, '0');
			$codigo = $codigo + 1;
		}
	}
	
}
