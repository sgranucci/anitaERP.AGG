<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Oficinacompra;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class OficinacompraRepository implements OficinacompraRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Oficinacompra $oficinacompra)
    {
        $this->model = $oficinacompra;
    }

    public function all()
    {
        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $oficinacompra = $this->model->create($data);

        return ($oficinacompra);
    }

    public function update(array $data, $id)
    {
        $oficinacompra = $this->model->findOrFail($id)
            ->update($data);
		
		return $oficinacompra;
    }

    public function delete($id)
    {
        $oficinacompra = $this->model->destroy($id);

		return $oficinacompra;
    }

    public function find($id)
    {
        if (null == $oficinacompra = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $oficinacompra;
    }

    public function findOrFail($id)
    {
        if (null == $oficinacompra = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $oficinacompra;
    }

}
