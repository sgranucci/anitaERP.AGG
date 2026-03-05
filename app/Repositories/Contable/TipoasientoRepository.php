<?php

namespace App\Repositories\Contable;

use App\Models\Contable\Tipoasiento;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class TipoasientoRepository implements TipoasientoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tipoasiento $tipoasiento)
    {
        $this->model = $tipoasiento;
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
    	$tipoasiento = Tipoasiento::find($id);

        $tipoasiento = $this->model->destroy($id);

		return $tipoasiento;
    }

    public function find($id)
    {
        if (null == $tipoasiento = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipoasiento;
    }

    public function findOrFail($id)
    {
        if (null == $tipoasiento = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipoasiento;
    }

    public function findPorId($id)
    {
		return $this->model->where('id', $id)->first();
    }

    public function findPorAbreviatura($abreviatura)
    {
		return $this->model->where('abreviatura', $abreviatura)->first();
    }

}
