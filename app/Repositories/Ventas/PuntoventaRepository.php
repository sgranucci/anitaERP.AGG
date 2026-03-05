<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Puntoventa;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class PuntoventaRepository implements PuntoventaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Puntoventa $puntoventa)
    {
        $this->model = $puntoventa;
    }

    public function all($estado = null)
    {
        if ($estado == null)
            $puntoventa = $this->model->get();
        else
            $puntoventa = $this->model->where('estado',$estado)->get();

        return $puntoventa;
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
    	$puntoventa = $this->model->find($id);

        if ($puntoventa)
            $puntoventa = $this->model->destroy($id);

		return $puntoventa;
    }

    public function find($id)
    {
        if (null == $puntoventa = $this->model->with('empresas')->with('actividad_arcas')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $puntoventa;
    }

    public function findOrFail($id)
    {
        if (null == $puntoventa = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $puntoventa;
    }
}
