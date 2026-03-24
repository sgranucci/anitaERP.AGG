<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Feriado;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;

class FeriadoRepository implements FeriadoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Feriado $feriado
                                )
    {
        $this->model = $feriado;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        $feriado = $this->model->create($data);

        return($feriado);
    }

    public function update(array $data, $id)
    {
        $feriado = $this->model->findOrFail($id)->update($data);

		return $feriado;
    }

    public function delete($id)
    {
    	$feriado = $this->model->find($id);

        $feriado = $this->model->destroy($id);

		return $feriado;
    }

    public function find($id)
    {
        if (null == $feriado = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $feriado;
    }

    public function findOrFail($id)
    {
        if (null == $feriado = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $feriado;
    }

}
