<?php

namespace App\Repositories\Produccion;

use App\Models\Produccion\Provienebin;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class ProvienebinRepository implements ProvienebinRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Provienebin $provienebin)
    {
        $this->model = $provienebin;
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
    	$provienebin = Provienebin::find($id);
		//
		// Elimina anita
		self::eliminarAnita($id);

        $provienebin = $this->model->destroy($id);

		return $provienebin;
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function findOrFail($id)
    {
        if (null == $provienebin = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $provienebin;
    }

}
