<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Articulo_Caja;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use App\ApiAnita;
use Auth;

class Articulo_CajaRepository implements Articulo_CajaRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'stkcaja';
    protected $keyFieldAnita = 'stkca_articulo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Articulo_Caja $articulo_caja)
    {
        $this->model = $articulo_caja;
    }

    public function all()
    {
        $hay_articulo_cajas = $this->model->first();

        $ret = $this->model->get();

        return $ret;
    }

    public function create(array $data)
    {
        $articulo_caja = $this->model->create($data);
    }

    public function deletePorArticulo($articulo_id, $sku)
    {
        $articulo_caja = $this->model->where('articulo_id', $articulo_id)->delete();

		return $articulo_caja;
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 'tabla' => $this->tableAnita, 'orderBy' => $this->keyField );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Articulo_caja::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }
        
        foreach ($dataAnita as $value) {
            if (!in_array($value->{$this->keyField}, $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyField});
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
                stkca_articulo,
                stkca_codigo_caja
            ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

        	$articulo = Articulo::select('id', 'sku')->where('sku' , ltrim($data->stkca_articulo, '0'))->first();
			if ($articulo)
				$articulo_id = $articulo->id;
			else
				return 0;
	
            Articulo_Caja::create([
                "articulo_id" => $articulo_id,
                "caja_id" => $data->stkca_codigo_caja
            ]);
        }
    }

	public function guardarAnita($sku, $caja_id)
	{
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 
					'acc' => 'insert',
					'campos' => ' 
                					stkca_articulo,
                					stkca_codigo_caja
								',
					'valores' => " 
								'".str_pad($sku, 13, "0", STR_PAD_LEFT)."', 
								'".$caja_id."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($sku, $caja_id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 
					'tabla' => $this->tableAnita, 
					'valores' => " stkca_codigo_caja = '". $caja_id."' ", 
					'whereArmado' => " WHERE stkca_articulo = '".str_pad($sku, 13, "0", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($sku) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => $this->tableAnita, 
						'whereArmado' => " WHERE stkca_articulo = '".str_pad($sku, 13, "-", STR_PAD_LEFT)."' " );
        $apiAnita->apiCall($data);
	}

}
