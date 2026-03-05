<?php

namespace App\Repositories\Produccion;

use App\Models\Produccion\Sectorsellado;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class SectorselladoRepository implements SectorselladoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Sectorsellado $sectorsellado)
    {
        $this->model = $sectorsellado;
    }

    public function all()
    {
        return $this->model->orderBy('nombre','ASC')->get();
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
    	$sectorsellado = Sectorsellado::find($id);
		//
		// Elimina anita
		self::eliminarAnita($id);

        $sectorsellado = $this->model->destroy($id);

		return $sectorsellado;
    }

    public function find($id)
    {
        if (null == $sectorsellado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $sectorsellado;
    }

    public function findOrFail($id)
    {
        if (null == $sectorsellado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $sectorsellado;
    }

}
