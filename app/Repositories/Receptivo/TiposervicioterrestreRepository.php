<?php

namespace App\Repositories\Receptivo;

use App\Models\Receptivo\Tiposervicioterrestre;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class TiposervicioterrestreRepository implements TiposervicioterrestreRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tiposervicioterrestre $tiposervicioterrestre)
    {
        $this->model = $tiposervicioterrestre;
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
    	$tiposervicioterrestre = Tiposervicioterrestre::find($id);

        $tiposervicioterrestre = $this->model->destroy($id);

		return $tiposervicioterrestre;
    }

    public function find($id)
    {
        if (null == $tiposervicioterrestre = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tiposervicioterrestre;
    }

    public function findOrFail($id)
    {
        if (null == $tiposervicioterrestre = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tiposervicioterrestre;
    }

    public function findPorId($id)
    {
		return $this->model->where('id', $id)->first();
    }

}
