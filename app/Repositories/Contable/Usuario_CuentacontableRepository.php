<?php

namespace App\Repositories\Contable;

use App\Models\Contable\Usuario_Cuentacontable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Usuario_CuentacontableRepository implements Usuario_CuentacontableRepositoryInterface
{
	protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Usuario_Cuentacontable $usuario_cuentacontable)
    {
        $this->model = $usuario_cuentacontable;
    }

    public function all()
    {
        $usuario_cuentacontable = $this->model->get();

		return $usuario_cuentacontable;
    }

    public function leePorUsuario($usuario_id)
    {
    	$usuario_cuentacontable = $this->model->where('usuario_id', $usuario_id)->get();

		return $usuario_cuentacontable;
    }

    public function leePorUsuarioCuenta($usuario_id, $cuentacontable_id)
    {
    	$usuario_cuentacontable = $this->model->where('usuario_id', $usuario_id)
                                                ->where('cuentacontable_id', $cuentacontable_id)->get();

		return $usuario_cuentacontable;
    }

    public function create(array $data)
    {
        $usuario_cuentacontable = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $usuario_cuentacontable = $this->model->findOrFail($id)->update($data);

        return $condicionpago;
    }

    public function delete($id)
    {
    	$usuario_cuentacontable = $this->model->find($id);

        $usuario_cuentacontable = $this->model->destroy($id);

		return $condicionpago;
    }

    public function deletePorUsuario($usuario_id)
    {
    	$usuario_cuentacontable = $this->model->where('usuario_id', $usuario_id)->delete();

		return $usuario_cuentacontable;
    }

    public function find($id)
    {
        if (null == $usuario_cuentacontable = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $usuario_cuentacontable;
    }

    public function findOrFail($id)
    {
        if (null == $usuario_cuentacontable = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $usuario_cuentacontable;
    }

}
