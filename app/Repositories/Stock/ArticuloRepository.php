<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Articulo_Caja;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use App\ApiAnita;
use Auth;

class ArticuloRepository implements ArticuloRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Articulo $articulo)
    {
        $this->model = $articulo;
    }

    public function all()
    {
        $hay_articulo_cajas = $this->model->first();

        $ret = $this->model->get();

        return $ret;
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function find($id)
    {
        if (null == $articulo = $this->model->where('id', $id)->with('unidadesdemedidas')->first()) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $articulo;
    }

   	public function findPorSku($codigo)
    {
        if (null == $articulo = $this->model->where('sku', $codigo)->with('unidadesdemedidas')->first())
		{
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $articulo;
    }

	public function leeArticulo($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        
        $articulo = $this->model->select(
                                'articulo.id as id', 
                                'articulo.sku as codigoarticulo', 
                                'articulo.descripcion as descripcion', 
                                'unidadmedida.nombre as nombreunidadmedida', 
                                'categoria.nombre as nombrecategoria', 
                                'tipoarticulo.nombre as nombretipoarticulo', 
                                'articulo.nofactura')
                                ->leftJoin('categoria','articulo.categoria_id','=','categoria.id')
                                ->leftJoin('unidadmedida','articulo.unidadmedida_id','=','unidadmedida.id')
                                ->leftJoin('tipoarticulo','articulo.tipoarticulo_id','=','tipoarticulo.id')
                                ->where('articulo.id', $busqueda)
                                ->orWhere('articulo.sku', 'like', '%'.$busqueda.'%')
                                ->orWhere('articulo.descripcion', 'like', '%'.$busqueda.'%')
								->orWhere('unidadmedida.nombre', 'like', '%'.$busqueda.'%')
								->orWhere('categoria.nombre', 'like', '%'.$busqueda.'%')
								->orWhere('tipoarticulo.nombre', 'like', '%'.$busqueda.'%')
								->orWhere('articulo.nofactura', 'like', '%'.$busqueda.'%')
                                ->orderby('articulo.sku', 'asc');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $articulo = $articulo->paginate(10);
            else
                $articulo = $articulo->get();
        }
        else
            $articulo = $articulo->get();

        return $articulo;
    }

}
