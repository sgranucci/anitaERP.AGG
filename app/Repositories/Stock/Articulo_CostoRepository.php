<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Articulo_Costo;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use App\ApiAnita;
use Auth;

class Articulo_CostoRepository implements Articulo_CostoRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'stkptar';
    protected $keyFieldAnita = 'stkpt_articulo';
    protected $keyFieldAnita2 = 'stkpt_tarea';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Articulo_Costo $articulo_costo)
    {
        $this->model = $articulo_costo;
    }

    public function all()
    {
        $ret = $this->model->get();

        return $ret;
    }

    public function findPorArticuloTarea($articulo_id, $tarea_id)
    {
        return $this->model->where('articulo_id', $articulo_id)->where('tarea_id', $tarea_id)->get();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function deletePorArticulo($articulo_id)
    {
        return $this->model->where('articulo_id', $articulo_id)->delete();
	}

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => 'stkpt_articulo,stkpt_tarea', 
                                'tabla' => $this->tableAnita, 
                                'orderBy' => $this->keyFieldAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        foreach ($dataAnita as $value) {
            $this->traerRegistroDeAnita($value->{$this->keyFieldAnita}, $value->{$this->keyFieldAnita2});
        }
    }

    public function traerRegistroDeAnita($articulo, $tarea){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
                stkpt_articulo,
                stkpt_tarea,
                stkpt_costo
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$articulo."' AND ".$this->keyFieldAnita2." = '".$tarea."' "
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

        	$articulo = Articulo::select('id', 'sku')->where('sku' , ltrim($data->stkpt_articulo, '0'))->first();
			if ($articulo)
				$articulo_id = $articulo->id;
			else
				return 0;
	
            $articulo_costo = $this->findPorArticuloTarea($articulo_id, $data->stkpt_tarea);

            if (count($articulo_costo) == 0)
                Articulo_Costo::create([
                    "articulo_id" => $articulo_id,
                    "tarea_id" => $data->stkpt_tarea,
                    "costo" => $data->stkpt_costo
                ]);
        }
    }

}
