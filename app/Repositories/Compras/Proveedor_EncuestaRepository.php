<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor_Encuesta;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;
use Auth;

class Proveedor_EncuestaRepository implements Proveedor_EncuestaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Proveedor_Encuesta $proveedor_encuesta)
    {
        $this->model = $proveedor_encuesta;
    }

    public function create(array $data)
    {
		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		return $this->model->findOrFail($id)->update($data);
    }

    public function delete($proveedor_id, $codigo)
    {
        $proveedor_encuesta = $this->model->where('proveedor_id', $proveedor_id)->delete();

		return $proveedor;
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

	public function leePorProveedor($proveedor_id, $busqueda)
	{
		$proveedor_encuesta = $this->model->select('proveedor_encuesta.id as id',
                                                    'encuesta.nombre as nombre',
                                                    'proveedor_encuesta.fecha as fecha',
                                                    'proveedor_encuesta.origen as origen',
                                                    'proveedor.nombre as nombreproveedor',
                                                    'proveedor.codigo as codigoproveedor',
                                                    'proveedor.id as proveedor_id',
                                                    'proveedor_encuesta.comentario as comentario')
                                            ->leftjoin('encuesta', 'encuesta.id', 'proveedor_encuesta.encuesta_id')
                                            ->join('proveedor', 'proveedor.id', 'proveedor_encuesta.proveedor_id')
                                            ->where('proveedor_encuesta.proveedor_id', $proveedor_id)
                                            ->where(function ($query) use ($busqueda) {
                                                $query->orWhere('proveedor_encuesta.id', $busqueda)
                                                        ->orWhere('encuesta.nombre', 'like', '%'.$busqueda.'%')
                                                        ->orWhere('proveedor_encuesta.origen', 'like', '%'.$busqueda.'%')
                                                        ->orWhere('proveedor_encuesta.comentario', 'like', '%'.$busqueda.'%');
                                            })
                                            ->with('proveedor_encuesta_preguntas')
                                            ->paginate(10);

        return $proveedor_encuesta;
	}
	
    public function findOrFail($id)
    {
        if (null == $proveedor_encuesta = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $proveedor;
    }

}
