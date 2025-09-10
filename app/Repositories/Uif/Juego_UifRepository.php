<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Juego_Uif;
use App\Repositories\Uif\Juego_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Juego_UifRepository implements Juego_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Juego_Uif $juego_uif)
    {
        $this->model = $juego_uif;
    }

    public function all()
    {
        $juego_uif = $this->model->get();

        return $juego_uif;
    }

    public function create(array $data)
    {
        $juego_uif = $this->model->create($data);

        return($juego_uif);
    }

    public function update(array $data, $id)
    {
        $juego_uif = $this->model->findOrFail($id)->update($data);

		return $juego_uif;
    }

    public function delete($id)
    {
    	$juego_uif = $this->model->find($id);

        $juego_uif = $this->model->destroy($id);

		return $juego_uif;
    }

    public function find($id)
    {
        if (null == $juego_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $juego_uif;
    }

    public function findOrFail($id)
    {
        if (null == $juego_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $juego_uif;
    }

}
