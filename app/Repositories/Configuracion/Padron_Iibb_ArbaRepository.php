<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Padron_Iibb_Arba;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;

class Padron_Iibb_ArbaRepository implements Padron_Iibb_ArbaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Padron_Iibb_Arba $padron_iibb_arba)
    {
        $this->model = $padron_iibb_arba;
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
        if (null == $padron_iibb_arba = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padron_iibb_arba;
    }

    public function findOrFail($id)
    {
        if (null == $padron_iibb_arba = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $padron_iibb_arba;
    }

    public function findPorCuit($cuit, $fecha = null)
    {
        if (!$fecha)
            $fecha = date('Y-m-d');

        return $this->model->where('cuit', $cuit)->where('desdefecha', '<=', $fecha)
                ->where('hastafecha', '>=', $fecha)->first();
    }

}
