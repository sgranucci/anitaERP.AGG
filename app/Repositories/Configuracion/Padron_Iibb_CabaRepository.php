<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Padron_Iibb_Caba;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;

class Padron_Iibb_CabaRepository implements Padron_Iibb_CabaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Padron_Iibb_Caba $padron_iibb_caba)
    {
        $this->model = $padron_iibb_caba;
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
        if (null == $padron_iibb_caba = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padron_iibb_caba;
    }

    public function findOrFail($id)
    {
        if (null == $padron_iibb_caba = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padron_iibb_caba;
    }

    public function findPorCuit($cuit)
    {
        return $this->model->where('cuit', $cuit)->first();
    }

}
