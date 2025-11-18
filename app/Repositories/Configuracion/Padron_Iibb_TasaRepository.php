<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Padron_Iibb;
use App\Models\Configuracion\Padron_Iibb_Tasa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;

class Padron_Iibb_TasaRepository implements Padron_Iibb_TasaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Padron_Iibb_Tasa $padron_iibb_tasa)
    {
        $this->model = $padron_iibb_tasa;
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

    public function deletePorProvinciaId($provincia_id)
    {
        return $this->model->select('id', 'jurisdiccion')->where('id', $provincia_id)->delete();
    }

    public function find($id)
    {
        if (null == $padron_iibb_tasa = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padron_iibb_tasa;
    }

    public function findOrFail($id)
    {
        if (null == $padron_iibb_tasa = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padron_iibb_tasa;
    }

}
