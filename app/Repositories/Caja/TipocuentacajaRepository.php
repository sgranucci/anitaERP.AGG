<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Tipocuentacaja;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class TipocuentacajaRepository implements TipocuentacajaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tipocuentacaja $tipocuentacaja)
    {
        $this->model = $tipocuentacaja;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        $tipocuentacaja = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $tipocuentacaja = $this->model->findOrFail($id)->update($data);

		return $tipocuentacaja;
    }

    public function delete($id)
    {
    	$tipocuentacaja = $this->model->find($id);

        $tipocuentacaja = $this->model->destroy($id);

		return $tipocuentacaja;
    }

    public function find($id)
    {
        if (null == $tipocuentacaja = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipocuentacaja;
    }

    public function findOrFail($id)
    {
        if (null == $tipocuentacaja = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipocuentacaja;
    }
}
