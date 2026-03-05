<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Pais;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class PaisRepository implements PaisRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Pais $pais)
    {
        $this->model = $pais;
    }

    public function all()
    {
        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $pais = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $pais = $this->model->findOrFail($id)
            ->update($data);

		return $pais;
    }

    public function delete($id)
    {
    	$pais = $this->model->find($id);

        $pais = $this->model->destroy($id);

		return $pais;
    }

    public function find($id)
    {
        if (null == $pais = $this->model->with('paises:id,nombre')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pais;
    }

    public function findPorId($id)
    {
        $pais = $this->model->where('id', $id)->first();

        return $pais;
    }

    public function findPorCodigo($codigo)
    {
        $pais = $this->model->where('codigo', $codigo)->first();

        return $pais;
    }

    public function findOrFail($id)
    {
        if (null == $pais = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pais;
    }

}
