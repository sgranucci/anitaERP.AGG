<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Estadocheque_Banco;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Estadocheque_BancoRepository implements Estadocheque_BancoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Estadocheque_Banco $estadocheque_banco
                                )
    {
        $this->model = $estadocheque_banco;
    }

    public function all($estado = null)
    {
        $estadocheque_banco = $this->model;

        if ($estado)
            $estadocheque_banco = $estadocheque_banco->wherein('estado', $estado);
        
        return $estadocheque_banco->with('bancos')->get();
    }

    public function create(array $data)
    {
        $estadocheque_banco = $this->model->create($data);

        return($estadocheque_banco);
    }

    public function update(array $data, $id)
    {
        $estadocheque_banco = $this->model->findOrFail($id)->update($data);

		return $estadocheque_banco;
    }

    public function delete($id)
    {
    	$estadocheque_banco = $this->model->find($id);

        $estadocheque_banco = $this->model->destroy($id);

		return $estadocheque_banco;
    }

    public function find($id)
    {
        if (null == $estadocheque_banco = $this->model->with('bancos')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $estadocheque_banco;
    }

    public function findOrFail($id)
    {
        if (null == $estadocheque_banco = $this->model->with('bancos')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $estadocheque_banco;
    }

}
