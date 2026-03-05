<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Ordentrabajo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;
use DB;

class OrdentrabajoRepository implements OrdentrabajoRepositoryInterface
{
    protected $model;
    protected $tableAnita = ['ordtmae','ordtmov'];
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'ordtm_nro_orden';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ordentrabajo $ordentrabajo)
    {
        $this->model = $ordentrabajo;
    }

    public function all()
    {
        //$hay_ordentrabajo = Ordentrabajo::first();

		//if (!$hay_ordentrabajo)
			//self::sincronizarConAnita();

        return $this->model->get();
    }

    public function create(array $data)
    {
		$dataErp = array(
						'fecha' => $data['fecha'],
						'codigo' => $data['nro_orden'],
            			'leyenda' => $data['observacion'],
            			'estado' => $data['estado'],
						'usuario_id' => $data['usuario_id']
						);

        $ordentrabajo = $this->model->create($dataErp);

		return $ordentrabajo;
    }

    public function update(array $data, $id)
    {
        $ordentrabajo = $this->model->findOrFail($id)->update($data);

		return $ordentrabajo;
    }

    public function delete($id)
    {
    	$ordentrabajo = $this->model->find($id);
		
        $ordentrabajo = $this->model->destroy($id);

		return $ordentrabajo;
    }

    public function find($id)
    {
        if (null == $ordentrabajo = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ordentrabajo;
    }

    public function findPorCodigo($codigo)
    {
        if (null == $ordentrabajo = $this->model->where('codigo',$codigo)->first() )
		{
            throw new ModelNotFoundException("OT no encontrada");
        }

        return $ordentrabajo;
    }

	public function deletePorCodigo($codigo)
    {
    	$ordentrabajo = $this->findPorCodigo($codigo);
		
        $ordentrabajo = $this->model->destroy($ordentrabajo->id);

		return $ordentrabajo;
    }

    public function findOrFail($id)
    {
        if (null == $ordentrabajo = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ordentrabajo;
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "$this->keyFieldAnita as $this->keyField, $this->keyFieldAnita", 
            			'whereArmado' => " WHERE ordtm_fecha>20211000 ",
						'tabla' => $this->tableAnita[0] );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = $this->model->get();
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

    private function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
			'tabla' => $this->tableAnita[0], 
            'campos' => '
			ordtm_cliente,
    		ordtm_nro_orden,
    		ordtm_tipo,    
    		ordtm_letra,    
    		ordtm_sucursal,
    		ordtm_nro,     
    		ordtm_nro_renglon,
    		ordtm_fecha,  
    		ordtm_estado,
    		ordtm_observacion,
    		ordtm_alfa_cliente,
			ordtm_articulo,   
			ordtm_color,      
			ordtm_forro,    
			ordtm_alfa_art,
			ordtm_linea,
			ordtm_fondo,
			ordtm_color_fondo,
			ordtm_capellada,
			ordtm_color_cap,
			ordtm_color_forro,
			ordtm_tipo_fact,
			ordtm_letra_fact,
			ordtm_suc_fact,
			ordtm_nro_fact,
			ordtm_aplique,
			ordtm_fl_impresa,
			ordtm_fl_stock
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			$arr_campos = [
				"fecha" => $data->ordtm_fecha,
				"codigo" => $data->ordtm_nro_orden,
				"leyenda" => $data->ordtm_observacion,
				"estado" => $data->ordtm_estado,
				"usuario_id" => $usuario_id,
            	];
	
        	$ordentrabajo = $this->model->create($arr_campos);
        }
    }

	private function guardarAnita($request) {
		return 0;
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita[0], 'acc' => 'insert',
            'campos' => ' 
				ordtm_cliente,
    			ordtm_nro_orden,
    			ordtm_tipo,    
    			ordtm_letra,    
    			ordtm_sucursal,
    			ordtm_nro,     
    			ordtm_nro_renglon,
    			ordtm_fecha,  
    			ordtm_estado,
    			ordtm_observacion,
    			ordtm_alfa_cliente,
				ordtm_articulo,   
				ordtm_color,      
				ordtm_forro,    
				ordtm_alfa_art,
				ordtm_linea,
				ordtm_fondo,
				ordtm_color_fondo,
				ordtm_capellada,
				ordtm_color_cap,
				ordtm_color_forro,
				ordtm_tipo_fact,
				ordtm_letra_fact,
				ordtm_suc_fact,
				ordtm_nro_fact,
				ordtm_aplique,
				ordtm_fl_impresa,
				ordtm_fl_stock
				',
            'valores' => " 
				'".$request['cliente']."',
				'".$request['nro_orden']."', 
				'".$request['tipo']."',
				'".$request['letra']."',
				'".$request['sucursal']."',
				'".$request['nro']."',
				'".$request['nro_renglon']."',
				'".date('Ymd', strtotime($request['fecha']))."',
				'".$request['estado']."',
				'".$request['observacion']."',
				'".$request['alfa_cliente']."',
				'".$request['articulo']."',
				'".$request['color']."',
				'".$request['forro']."',
				'".$request['alfa_art']."',
				'".$request['linea']."',
				'".$request['fondo']."',
				'".$request['color_fondo']."',
				'".$request['capellada']."',
				'".$request['color_cap']."',
				'".$request['color_forro']."',
				'".$request['tipo_fact']."',
				'".$request['letra_fact']."',
				'".$request['suc_fact']."',
				'".$request['nro_fact']."',
				'".$request['aplique']."',
				'".$request['fl_impresa']."',
				'".$request['fl_stock']."' "
        );
        return $apiAnita->apiCall($data);
	}

	private function actualizarAnita($request, $id) {
		return 0;
        $apiAnita = new ApiAnita();

		$this->setCondicionIvaAnita($request, $condicioniva);

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita[0], 
				'valores' => " 
                ordtm_cliente         = '".$request['cliente']."',
    			ordtm_nro_orden       = '".$request['nro_orden']."',
    			ordtm_tipo,           = '".$request['tipo']."',
    			ordtm_letra,          = '".$request['letra']."',
    			ordtm_sucursal        = '".$request['sucursal']."',
    			ordtm_nro,            = '".$request['nro']."',
    			ordtm_nro_renglon     = '".$request['nro_renglon']."',
    			ordtm_fecha,          = '".$request['fecha']."',
    			ordtm_estado          = '".$request['estado']."',
    			ordtm_observacion     = '".$request['observacion']."',
    			ordtm_alfa_cliente    = '".$request['alfa_cliente']."',
				ordtm_articulo,       = '".$request['articulo']."',
				ordtm_color,          = '".$request['color']."',
				ordtm_forro,          = '".$request['forro']."',
				ordtm_alfa_art        = '".$request['alfa_art']."',
				ordtm_linea           = '".$request['linea']."',
				ordtm_fondo           = '".$request['fondo']."',
				ordtm_color_fondo     = '".$request['color_fondo']."',
				ordtm_capellada       = '".$request['capellada']."',
				ordtm_color_cap       = '".$request['color_cap']."',
				ordtm_color_forro     = '".$request['color_forro']."',
				ordtm_tipo_fact       = '".$request['tipo_fact']."',
				ordtm_letra_fact      = '".$request['letra_fact']."',
				ordtm_suc_fact        = '".$request['suc_fact']."',
				ordtm_nro_fact        = '".$request['nro_fact']."',
				ordtm_aplique         = '".$request['aplique']."',
				ordtm_fl_impresa      = '".$request['fl_impresa']."',
				ordtm_fl_stock        = '".$request['fl_stock']."' "
					,
				'whereArmado' => " WHERE ordtm_nro_orden = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	private function eliminarAnita($id) {
		return 0;
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[0], 
				'whereArmado' => " WHERE ordtm_nro_orden = '".$id."' " );
        $apiAnita->apiCall($data);
	}

	// Actualiza numerador de OT en Anita

	public function actualizarNumeradorOtAnita($nro) {
		return 0;
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'update', 
				'tabla' => 'numerador',
				'valores' => "num_ult_numero = '".$nro."' ",
				'whereArmado' => " WHERE num_clave = '031' " 
				);
        $apiAnita->apiCall($data);
	}
}
