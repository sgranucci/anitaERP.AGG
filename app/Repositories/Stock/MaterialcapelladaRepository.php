<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Materialcapellada;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use App\ApiAnita;
use Auth;

class MaterialcapelladaRepository implements MaterialcapelladaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'stkmae';
    protected $keyFieldAnita = 'stkm_articulo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Materialcapellada $materialcapellada)
    {
        $this->model = $materialcapellada;
    }

    public function all()
    {
        $ret = $this->model->get();

        return $ret;
    }

    public function create(array $data)
    {
        $materialcapellada = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $materialcapellada = $this->model->findOrFail($id)
            ->update($data);
		return $materialcapellada;
    }

    public function delete($id)
    {
    	$materialcapellada = Materialcapellada::find($id);

        $materialcapellada = $this->model->destroy($id);

		return $materialcapellada;
    }

    public function find($id)
    {
        if (null == $materialcapellada = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $materialcapellada;
    }

    public function findOrFail($id)
    {
        if (null == $materialcapellada = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $materialcapellada;
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => $this->keyFieldAnita, 
            			'whereArmado' => " WHERE stkm_tipo_articulo='3' ",
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        foreach ($dataAnita as $value) {
        	self::traerRegistroDeAnita($value->{$this->keyFieldAnita});
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
                stkm_articulo,
				stkm_desc
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			// Lee el articulo para sacar el id
			$sku = ltrim($data->stkm_articulo, '0');
			$id = Articulo::select('id')->where('sku', '=', $sku)->first();

			if (!$id)
				$articulo_id = 0;
			else 
				$articulo_id = $id->id;

            Materialcapellada::create([
                "id" => $key,
				"nombre" => $data->stkm_desc,
				"articulo_id" => $articulo_id
            ]);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $fecha = Carbon::now();
		$fecha = $fecha->format('Ymd');

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'campos' => ' 
				stkm_articulo,
    			stkm_desc,
    			stkm_unidad_medida,
    			stkm_unidad_xenv,
    			stkm_proveedor,
    			stkm_agrupacion,
    			stkm_cta_contable,
    			stkm_cod_impuesto,
    			stkm_descuento,
    			stkm_p_rep,
    			stkm_cod_mon_p_rep,
    			stkm_imp_interno,
    			stkm_cta_cont_ii,
    			stkm_cant_compra1,
    			stkm_cant_compra2,
    			stkm_cant_compra3,
    			stkm_pre_compra1,
    			stkm_pre_compra2,
    			stkm_pre_compra3,
    			stkm_usuario,
    			stkm_terminal,
    			stkm_fe_ult_act,
    			stkm_articulo_prod,
    			stkm_peso_aprox,
	    		stkm_marca,
    			stkm_linea,
    			stkm_cta_contablec,
    			stkm_fe_ult_compra,
    			stkm_o_compra,
    			stkm_fl_no_factura,
    			stkm_formula,
    			stkm_ppp,
    			stkm_nombre_foto,
    			stkm_cod_umd,
    			stkm_cod_umd_alter,
    			stkm_fecha_alta,
    			stkm_cod_nomenc,
    			stkm_tipo_articulo,
    			stkm_tipo_corte,
    			stkm_puntera,
    			stkm_contrafuerte,
    			stkm_tipo_cortefo,
    			stkm_forro,
    			stkm_compfondo,
    			stkm_clave_orden,
    			stkm_subcategoria
				',
            'valores' => " 
				'".str_pad($request['sku'], 13, "0", STR_PAD_LEFT)."', 
				'".$request['nombre']."',
    			'".'UNI'."',
				'".'0'."',
				'".'000000'."',
				'".'0000'."',
				'".'0'."',
				'".'03'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
            	'".Auth::user()->nombre."',
				'".'0'."',
				'".$fecha."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'N'."',
				'".'0'."',
				'".'0'."',
				'".' '."',
				'".'3'."',
				'".'0'."',
				'".$fecha."',
				'".' '."',
				'".'3'."',
				'".'0'."' ,
				'".'0'."',
				'".'0'."',
				'".'0'."' ,
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request) {
        $apiAnita = new ApiAnita();
		$articulo = Articulo::select('sku')->where('id', '=', $request['articulo_id'])->first();
		$data = array( 'acc' => 'update', 'tabla' => 'stkmae', 
					'valores' => " stkm_desc = '".$request['nombre']."' ",
					'whereArmado' => " WHERE stkm_articulo = '".str_pad($articulo->sku, 13, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
		$articulo = Articulo::select('sku')->where('id', '=', $id)->first();
        $data = array( 'acc' => 'delete', 'tabla' => 'stkmae', 
					'whereArmado' => " WHERE stkm_articulo = '".str_pad($articulo->sku, 13, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);
	}
}
