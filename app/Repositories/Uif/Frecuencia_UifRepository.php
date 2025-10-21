<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Frecuencia_Uif;
use App\Repositories\Uif\Frecuencia_UifRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class Frecuencia_UifRepository implements Frecuencia_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Frecuencia_Uif $frecuencia_uif)
    {
        $this->model = $frecuencia_uif;
    }

    public function all()
    {
        $frecuencia_uif = $this->model->get();

        return $frecuencia_uif;
    }

    public function create(array $data)
    {
        $frecuencia_uif = $this->model->create($data);

        return($frecuencia_uif);
    }

    public function update(array $data, $id)
    {
        $frecuencia_uif = $this->model->findOrFail($id)->update($data);

		return $frecuencia_uif;
    }

    public function delete($id)
    {
    	$frecuencia_uif = $this->model->find($id);

        $frecuencia_uif = $this->model->destroy($id);

		return $frecuencia_uif;
    }

    public function find($id)
    {
        if (null == $frecuencia_uif = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $frecuencia_uif;
    }

    public function findOrFail($id)
    {
        if (null == $frecuencia_uif = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $frecuencia_uif;
    }

    public function findPorFrecuencia($frecuencia)
    {
        return $this->model->select('puntaje')
                            ->where('desdeoperacion', '<=', $frecuencia)
                            ->where('hastaoperacion', '>=', $frecuencia)
                            ->get();
    }    
}
