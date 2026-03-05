<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Actividad_Arca;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class Actividad_ArcaRepository implements Actividad_ArcaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Actividad_Arca $actividad_arca)
    {
        $this->model = $actividad_arca;
    }

    public function all()
    {
        return $this->model->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $actividad_arca = $this->model->create($data);

        return ($actividad_arca);
    }

    public function update(array $data, $id)
    {
        $actividad_arca = $this->model->findOrFail($id)
            ->update($data);
		
		return $actividad_arca;
    }

    public function delete($id)
    {
        $actividad_arca = $this->model->destroy($id);

		return $actividad_arca;
    }

    public function find($id)
    {
        if (null == $actividad_arca = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $actividad_arca;
    }

    public function findOrFail($id)
    {
        if (null == $actividad_arca = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $actividad_arca;
    }

}
