<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Monto_Uif;
use App\Repositories\Uif\Monto_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Monto_UifRepository implements Monto_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Monto_Uif $monto_uif)
    {
        $this->model = $monto_uif;
    }

    public function all()
    {
        $monto_uif = $this->model->get();

        return $monto_uif;
    }

    public function create(array $data)
    {
        $monto_uif = $this->model->create($data);

        return($monto_uif);
    }

    public function update(array $data, $id)
    {
        $monto_uif = $this->model->findOrFail($id)->update($data);

		return $monto_uif;
    }

    public function delete($id)
    {
    	$monto_uif = $this->model->find($id);

        $monto_uif = $this->model->destroy($id);

		return $monto_uif;
    }

    public function find($id)
    {
        if (null == $monto_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $monto_uif;
    }

    public function findOrFail($id)
    {
        if (null == $monto_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $monto_uif;
    }

}
