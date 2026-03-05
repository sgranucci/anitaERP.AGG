<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Tipodocumento;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class TipodocumentoRepository implements TipodocumentoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Tipodocumento $tipodocumento
                                )
    {
        $this->model = $tipodocumento;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        $tipodocumento = $this->model->create($data);

        return($tipodocumento);
    }

    public function update(array $data, $id)
    {
        $tipodocumento = $this->model->findOrFail($id)->update($data);

		return $tipodocumento;
    }

    public function delete($id)
    {
    	$tipodocumento = $this->model->find($id);

        $tipodocumento = $this->model->destroy($id);

		return $tipodocumento;
    }

    public function find($id)
    {
        if (null == $tipodocumento = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipodocumento;
    }

    public function findOrFail($id)
    {
        if (null == $tipodocumento = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $tipodocumento;
    }

    public function findPorAbreviatura($abreviatura)
    {
        if (null == $tipodocumento = $this->model->where('abreviatura', $abreviatura)->first()) {
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $tipodocumento;
    }

}
