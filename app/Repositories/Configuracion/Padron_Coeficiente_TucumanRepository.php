<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Padron_Coeficiente_Tucuman;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;

class Padron_Coeficiente_TucumanRepository implements Padron_Coeficiente_TucumanRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Padron_Coeficiente_Tucuman $padron_coeficiente_tucuman)
    {
        $this->model = $padron_coeficiente_tucuman;
    }

    public function all()
    {
        return $this->model->all();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)
            ->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function deletePorCuit($cuit)
    {
        return $this->model->where('cuit', $cuit)->delete();
    }

    public function find($id)
    {
        if (null == $padron_coeficiente_tucuman = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padron_coeficiente_tucuman;
    }

    public function findOrFail($id)
    {
        if (null == $padron_coeficiente_tucuman = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padron_coeficiente_tucuman;
    }

    public function findPorCuit($cuit)
    {
        return $this->model->select('id')->where('cuit', $cuit)->first();
    }

}
