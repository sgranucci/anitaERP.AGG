<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Tiposervicio_Proveedor;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Tiposervicio_ProveedorRepository implements Tiposervicio_ProveedorRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tiposervicio_Proveedor $tiposervicio_proveedor)
    {
        $this->model = $tiposervicio_proveedor;
    }

    public function all()
    {
        return $this->model->orderBy('nombre','ASC')->get();
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
    	$tiposervicio_proveedor = Tiposervicio_Proveedor::find($id);

        $tiposervicio_proveedor = $this->model->destroy($id);

		return $tiposervicio_proveedor;
    }

    public function find($id)
    {
        if (null == $tiposervicio_proveedor = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tiposervicio_proveedor;
    }

    public function findOrFail($id)
    {
        if (null == $tiposervicio_proveedor = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tiposervicio_proveedor;
    }

}
