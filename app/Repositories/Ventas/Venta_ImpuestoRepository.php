<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Venta_Impuesto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Venta_ImpuestoRepository implements Venta_ImpuestoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Venta_Impuesto $venta)
    {
        $this->model = $venta;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
    	return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $venta_impuesto = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $venta;
    }

    public function findOrFail($id)
    {
        if (null == $venta_impuesto = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $venta_impuesto;
    }
}
