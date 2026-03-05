<?php

namespace App\Queries\Stock;

use App\Models\Stock\Articulo;
use App\ApiAnita;
use DB;

class ArticuloQuery implements ArticuloQueryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Articulo $cliente)
    {
        $this->model = $cliente;
    }

    public function first()
    {
        return $this->model->first();
    }

    public function all()
    {
        return $this->model->get();
    }

    public function allQuery(array $campos, $campoSort = null)
    {
		if ($campoSort)
        	return $this->model->select($campos)->orderBy($campoSort)->get();
		else
        	return $this->model->select($campos)->get();
    }

    public function allQueryConCombinacion(array $campos, $campoSort = null)
    {
		if ($campoSort)
        	return $this->model->select($campos)->orderBy($campoSort)
                            ->whereExists(function($query)
                            {
                            	$query->select(DB::raw(1))
                            	->from("combinacion")
                            	->whereRaw("combinacion.articulo_id=articulo.id");
                            })->get();
		else
        	return $this->model->select($campos)
                            ->whereExists(function($query)
                            {
                            	$query->select(DB::raw(1))
                            	->from("combinacion")
                            	->whereRaw("combinacion.articulo_id=articulo.id");
                            })->get();
    }

    public function traeArticulosActivos($articulo_ids = null)
    {
		$articulo_query = Articulo::select('id', 'sku', 'descripcion', 'mventa_id')
        					->orderBy('descripcion','ASC')
                            ->whereExists(function($query)
                            {
                            	$query->select(DB::raw(1))
                            	->from("combinacion")
                            	->whereRaw("combinacion.articulo_id=articulo.id and combinacion.estado='A'");
                            });
		if ($articulo_ids)
		{
			$articulo_query->orWhereIn('id', $articulo_ids);
		}

		return $articulo_query->get();
    }

    public function traeArticuloPorSku($sku)
    {
		return $this->model->select('id', 'sku', 'descripcion', 'mventa_id')->where('sku',$sku)->first(); 
	}

    public function traeArticuloPorId($id)
    {
		return $this->model->with('lineas')->with('unidadesdemedidas')->where('id',$id)->first(); 
	}

    public function generaDatosRepCombinacion($estado, $mventa_id,
                                            $desdearticulo, $hastaarticulo,
                                            $desdelinea_id, $hastalinea_id)
    {
		$articulo_query = $this->model->select('articulo.id as articulo_id', 
                            'articulo.sku as sku', 
                            'articulo.descripcion as nombrearticulo', 
                            'combinacion.codigo as codigocombinacion',
                            'combinacion.nombre as nombrecombinacion',
                            'mventa.nombre as nombremarca',
                            'combinacion.estado as estado')
                            ->join('combinacion', 'combinacion.articulo_id', 'articulo.id')
                            ->join('mventa', 'mventa.id', 'articulo.mventa_id')
                            ->whereBetween('articulo.linea_id', [$desdelinea_id, $hastalinea_id])
        					->orderBy('articulo.descripcion','ASC');

        if ($desdearticulo != '' && $hastaarticulo != '')
            $articulo_query = $articulo_query->whereBetween('articulo.descripcion', [$desdearticulo, $hastaarticulo]);
        
        if ($mventa_id != 0)
            $articulo_query = $articulo_query->where('articulo.mventa_id', $mventa_id);

        switch($estado)
        {
        case 'ACTIVAS':
            $articulo_query = $articulo_query->where('combinacion.estado', 'A');
            break;
        case 'INACTIVAS':
            $articulo_query = $articulo_query->where('combinacion.estado', 'I');
            break;
        }

		return $articulo_query->get();
    }

}

