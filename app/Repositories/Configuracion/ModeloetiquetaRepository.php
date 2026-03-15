<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Modeloetiqueta;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class ModeloetiquetaRepository implements ModeloetiquetaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Modeloetiqueta $modeloetiqueta)
    {
        $this->model = $modeloetiqueta;
    }

    public function all()
    {
        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $modeloetiqueta = $this->model->create($data);

        return ($modeloetiqueta);
    }

    public function update(array $data, $id)
    {
        $modeloetiqueta = $this->model->findOrFail($id)
            ->update($data);
		
		return $modeloetiqueta;
    }

    public function delete($id)
    {
        $modeloetiqueta = $this->model->destroy($id);

		return $modeloetiqueta;
    }

    public function find($id)
    {
        if (null == $modeloetiqueta = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $modeloetiqueta;
    }

    public function findOrFail($id)
    {
        if (null == $modeloetiqueta = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $modeloetiqueta;
    }

}
