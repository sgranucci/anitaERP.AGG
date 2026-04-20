<?php

namespace App\Repositories\Produccion;

use App\Models\Produccion\Ordenproduccion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class OrdenproduccionRepository implements OrdenproduccionRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Ordenproduccion $ordenproduccion)
    {
        $this->model = $ordenproduccion;
    }

    public function all()
    {
        return $this->model->orderBy('numeroordenproduccion','ASC')->get();
    }

    public function create(array $data)
    {
        $data['numeroordenproduccion'] = self::ultimaOrdenproduccion();

        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
    	$ordenproduccion = Ordenproduccion::find($id);
		//
		// Elimina anita
		self::eliminarAnita($id);

        $ordenproduccion = $this->model->destroy($id);

		return $ordenproduccion;
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function findOrFail($id)
    {
        if (null == $ordenproduccion = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $ordenproduccion;
    }

	public function leeOrdenProduccion($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $ordenproduccion = $this->model->select('ordenproduccion.id as id',
                                                'ordenproduccion.fechainicio as fechainicio',
                                                'ordenproduccion.fechafinalizacion as fechafinalizacion',
                                                'lineallenado.nombre as nombrelineallenado',
                                                'ordenproduccion.numeroordenproduccion as numeroordenproduccion',
                                                'tipoproducto.nombre as nombretipoproducto',
                                                'tipoliquido.nombre as nombretipoliquido',
                                                'capacidad.nombre as nombrecapacidad',
                                                'mventa.nombre as nombremarca',
                                                'color.nombre as nombrecolor',
                                                'ordenproduccion.cantidad as cantidad',
                                                'provienebin.nombre as nombreprovienebin',
                                                'ordenproduccion.lote as lote',
                                                'ordenproduccion.observacion as observacion',
                                                'usuario.nombre as nombreusuario')
                                ->leftjoin('articulo', 'articulo.id', 'ordenproduccion.articulo_id')
                                ->leftjoin('lineallenado', 'lineallenado.id', 'ordenproduccion.lineallenado_id')
								->leftjoin('tipoproducto', 'tipoproducto.id', 'articulo.tipoproducto_id')
								->leftjoin('tipoliquido', 'tipoliquido.id', 'articulo.tipoliquido_id')
                                ->leftjoin('capacidad', 'capacidad.id', 'articulo.capacidad_id')
                                ->leftjoin('mventa', 'mventa.id', 'articulo.mventa_id')
                                ->leftjoin('color', 'color.id', 'articulo.color_id')
                                ->leftjoin('provienebin', 'provienebin.id', 'ordenproduccion.provienebin_id')
                                ->leftjoin('usuario', 'usuario.id', 'ordenproduccion.usuario_id');
		
		$ordenproduccion = $ordenproduccion->where(function ($query) use ($busqueda) {
                	$query->orWhere('ordenproduccion.id', $busqueda)
                            ->orWhere('ordenproduccion.fechainicio', 'like', '%'.$busqueda.'%')
                            ->orWhere('ordenproduccion.fechafinalizacion', 'like', '%'.$busqueda.'%')
							->orWhere('lineallenado.nombre', 'like', '%'.$busqueda.'%')
							->orWhere('ordenproduccion.numeroordenproduccion', 'like', '%'.$busqueda.'%')
							->orWhere('tipoproducto.nombre', 'like', '%'.$busqueda.'%')
							->orWhere('tipoliquido.nombre', 'like', '%'.$busqueda.'%')
							->orWhere('capacidad.nombre', 'like', '%'.$busqueda.'%')
							->orWhere('mventa.nombre', 'like', '%'.$busqueda.'%')
							->orWhere('ordenproduccion.cantidad', '=', $busqueda)
							->orWhere('provienebin.nombre', 'like', '%'.$busqueda.'%')
                            ->orWhere('ordenproduccion.observacion', 'like', '%'.$busqueda.'%')
                            ->orWhere('usuario.nombre', 'like', '%'.$busqueda.'%');
					});

		$ordenproduccion = $ordenproduccion->orderby('id', 'DESC');
                                
		//dd($permisos['permisos']);
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $ordenproduccion = $ordenproduccion->paginate(10);
            else
                $ordenproduccion = $ordenproduccion->get();
        }
        else
            $ordenproduccion = $ordenproduccion->get();

        return $ordenproduccion;
    }
 
	// Devuelve ultimo numero de ordenproduccion + 1
	private function ultimaOrdenproduccion()
	{
		$ordenproduccion = $this->model->select('numeroordenproduccion')->orderBy('numeroordenproduccion', 'desc')->first();
		
		$numeroordenproduccion = 0;
        if ($ordenproduccion) 
		{
			$numeroordenproduccion = $ordenproduccion->numeroordenproduccion;
			$numeroordenproduccion = $numeroordenproduccion + 1;
		}
		else	
			$numeroordenproduccion = 1;

		return $numeroordenproduccion;
	}	
    
}
