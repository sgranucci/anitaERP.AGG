<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Tiposuspensioncliente;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class TiposuspensionclienteRepository implements TiposuspensionclienteRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tiposuspensioncliente $tiposuspensioncliente)
    {
        $this->model = $tiposuspensioncliente;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        $tiposuspensioncliente = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $tiposuspensioncliente = $this->model->findOrFail($id)->update($data);

		return $tiposuspensioncliente;
    }

    public function delete($id)
    {
    	$tiposuspensioncliente = $this->model->find($id);

        $tiposuspensioncliente = $this->model->destroy($id);

		return $tiposuspensioncliente;
    }

    public function find($id)
    {
        if (null == $tiposuspensioncliente = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tiposuspensioncliente;
    }

    public function findOrFail($id)
    {
        if (null == $tiposuspensioncliente = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tiposuspensioncliente;
    }
}
