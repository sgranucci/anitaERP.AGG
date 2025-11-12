<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Codigosenasa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class CodigosenasaRepository implements CodigosenasaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'codsenasa';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'cods_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Codigosenasa $codigosenasa)
    {
        $this->model = $codigosenasa;
    }

    public function all()
    {
        $hay_codigosenasas = Codigosenasa::first();

		if (!$hay_codigosenasas)
			self::sincronizarConAnita();

        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $codigo = '';
		self::ultimoCodigo($codigo);
		$data['codigo'] = $codigo;

        $codigosenasa = $this->model->create($data);
		
		// Graba anita
		self::guardarAnita($data);

        return $codigosenasa;
    }

    public function update(array $data, $id)
    {
        $codigosenasa = $this->model->findOrFail($id)
            ->update($data);
		
		// Actualiza anita
		self::actualizarAnita($data, $data['codigo']);

		return $codigosenasa;
    }

    public function delete($id)
    {
    	$codigosenasa = Codigosenasa::find($id);
		//
		// Elimina anita
		self::eliminarAnita($codigosenasa->codigo);

        $codigosenasa = $this->model->destroy($id);

		return $codigosenasa;
    }

    public function find($id)
    {
        if (null == $codigosenasa = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $codigosenasa;
    }

    public function findOrFail($id)
    {
        if (null == $codigosenasa = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $codigosenasa;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Codigosenasa::all();
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
			cods_codigo,
    		cods_desc,
            cods_registro,
            cods_envase,
            cods_frio,
            cods_prefijo
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

            if ($data->cods_frio == 'S')
                $llevaFrio = 'Lleva Frio';
            else
                $llevaFrio = 'No Lleva Frio';

			$arr_campos = [
				"nombre" => $data->cods_desc,
                "registro" => $data->cods_registro,
                "envasesenasa_id" => $data->cods_envase == 0 ? null : $data->cods_envase,
                "llevafrio" => $llevaFrio,
                "prefijo" => $data->cods_prefijo,
                "codigo" => $data->cods_codigo,
            	];
	
        	$codigosenasa = $this->model->create($arr_campos);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        if ($request['llevafrio'] == 'Lleva Frio')
            $frio = 'S';
        else
            $frio = 'N';

        $data = array( 'tabla' => $this->tableAnita, 'sistema' => 'ventas', 
			'acc' => 'insert',
            'campos' => ' 
				cods_codigo,
    			cods_desc,
                cods_registro,
                cods_envase,
                cods_frio,
                cods_prefijo
				',
            'valores' => " 
				'".$request['codigo']."', 
				'".$request['nombre']."',
                '".$request['envasesenasa_id']."',
                '".$frio."',
                '".$request['prefijo']."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        if ($request['llevafrio'] == 'Lleva Frio')
            $frio = 'S';
        else
            $frio = 'N';

        $data = array( 'acc' => 'update', 
                'sistema' => 'ventas', 
                'tabla' => $this->tableAnita, 
				'valores' => " 
                cods_codigo    = '".$request['codigo']."',
                cods_desc 	   = '".$request['nombre']."',
                cods_registro  = '".$request['registro']."',
                cods_envase    = '".$request['envasesenasa_id']."',
                cods_frio      = '".$frio."',
                cods_prefijo   = '".$request['prefijo']."' "
                ,
				'whereArmado' => " WHERE cods_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'sistema' => 'ventas', 'tabla' => $this->tableAnita, 
				'whereArmado' => " WHERE cods_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}

}
