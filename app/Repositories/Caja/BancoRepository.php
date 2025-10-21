<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Banco;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Condicioniva;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class BancoRepository implements BancoRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'banmae';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'banm_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Banco $banco)
    {
        $this->model = $banco;
    }

    public function all()
    {
        $hay_bancos = Banco::first();

		if (!$hay_bancos)
			self::sincronizarConAnita();

        return $this->model->with('provincias')->with('localidades')->with('condicionivas')->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $banco = $this->model->create($data);
		//
		// Graba anita
		self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $banco = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $banco;

        //return $this->model->where('id', $id)
         //   ->update($data);
    }

    public function delete($id)
    {
    	$banco = Banco::find($id);
		//
		// Elimina anita
		self::eliminarAnita($banco->codigo);

        $banco = $this->model->destroy($id);

		return $banco;
    }

    public function find($id)
    {
        if (null == $banco = $this->model->with('provincias')
											->with('localidades')
											->with('condicionivas')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $banco;
    }

    public function findOrFail($id)
    {
        if (null == $banco = $this->model->with('provincias')
										->with('localidades')
										->with('condicionivas')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $banco;
    }

	public function findPorCodigo($codigo)
    {
        return $this->model->where('codigo', $codigo)->first();
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'sistema' => 'che_ban',
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Banco::all();
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
			'sistema' => 'che_ban',
            'campos' => '
			banm_codigo,
    		banm_nombre,
    		banm_direccion,
    		banm_localidad,
    		banm_provincia,
    		banm_cod_postal,
    		banm_telefono,
    		banm_cuit,
    		banm_cond_iva,
    		banm_nro_interno
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

        	$provincia = Provincia::select('id', 'nombre')->where('id' , $data->banm_provincia)->first();
			if ($provincia)
				$provincia_id = $provincia->id;
			else
				$provincia_id = NULL;
	
        	$localidad = Localidad::select('id', 'nombre')->where('id' , $data->banm_localidad)->first();
			if ($localidad)
				$localidad_id = $localidad->id;
			else
				$localidad_id = NULL;
	
			$condicioniva_id = 1;
			switch($data->banm_cond_iva)
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
				"nombre" => $data->banm_nombre,
				"codigo" => $data->banm_codigo,
				"domicilio" => $data->banm_direccion,
				"provincia_id" => $provincia_id,
				"localidad_id" => $localidad_id,
				"codigopostal" => $data->banm_cod_postal,
				"telefono" => $data->banm_telefono,
				"email" => '',
				"nroinscripcion" => $data->banm_cuit,
				"condicioniva_id" => $condicioniva_id,
            	];
	
        	$banco = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

		$this->setCondicionIvaAnita($request, $condicioniva_id);

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
			'sistema' => 'che_ban',
            'campos' => ' 
				banm_codigo,
    			banm_nombre,
    			banm_direccion,
    			banm_localidad,
    			banm_provincia,
    			banm_cod_postal,
    			banm_telefono,
    			banm_cuit,
    			banm_cond_iva,
    			banm_nro_interno
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
				'0' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

		$this->setCondicionIvaAnita($request, $condicioniva);

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'sistema' => 'che_ban',
				'valores' => " 
                banm_codigo 	                = '".$request['codigo']."',
                banm_nombre 	                = '".$request['nombre']."',
                banm_direccion 	                = '".$request['domicilio']."',
                banm_localidad 	                = '".$request['desc_localidad']."',
                banm_provincia 	                = '".$request['desc_provincia']."',
                banm_cod_postal 	            = '".$request['codigopostal']."',
                banm_telefono 	                = '".$request['telefono']."',
                banm_cuit 	                    = '".$request['nroinscripcion']."',
                banm_cond_iva 	                = '".$condicioniva."' "
					,
				'whereArmado' => " WHERE banm_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
				'sistema' => 'che_ban',
				'whereArmado' => " WHERE banm_codigo = '".$id."' " );
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

}
