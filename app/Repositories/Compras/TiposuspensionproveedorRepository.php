<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Tiposuspensionproveedor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class TiposuspensionproveedorRepository implements TiposuspensionproveedorRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tiposuspensionproveedor $tiposuspensionproveedor)
    {
        $this->model = $tiposuspensionproveedor;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        $tiposuspensionproveedor = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $tiposuspensionproveedor = $this->model->findOrFail($id)->update($data);

		return $tiposuspensionproveedor;
    }

    public function delete($id)
    {
    	$tiposuspensionproveedor = $this->model->find($id);

        $tiposuspensionproveedor = $this->model->destroy($id);

		return $tiposuspensionproveedor;
    }

    public function find($id)
    {
        if (null == $tiposuspensionproveedor = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tiposuspensionproveedor;
    }

    public function findOrFail($id)
    {
        if (null == $tiposuspensionproveedor = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tiposuspensionproveedor;
    }
}
