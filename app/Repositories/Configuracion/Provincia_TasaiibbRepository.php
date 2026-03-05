<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Provincia_Tasaiibb;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Provincia_TasaiibbRepository implements Provincia_TasaiibbRepositoryInterface
{
	protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Provincia_Tasaiibb $provincia_tasaiibb)
    {
        $this->model = $provincia_tasaiibb;
    }

    public function all()
    {
        $provincia_tasaiibb = $this->model->get();

		return $provincia_tasaiibb;
    }

    public function leePorProvincia($provincia_id)
    {
    	$provincia_tasaiibb = $this->model->where('provincia_id', $provincia_id)->get();

		return $provincia_tasaiibb;
    }

    public function leePorProvinciaCondicioniibb($provincia_id, $condicioniibb_id)
    {
    	$provincia_tasaiibb = $this->model->where('provincia_id', $provincia_id)
                                                ->where('condicioniibb_id', $condicioniibb_id)->get();

		return $provincia_tasaiibb;
    }

    public function create(array $data)
    {
        $provincia_tasaiibb = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $provincia_tasaiibb = $this->model->findOrFail($id)->update($data);

        return $condicionpago;
    }

    public function delete($id)
    {
    	$provincia_tasaiibb = $this->model->find($id);

        $provincia_tasaiibb = $this->model->destroy($id);

		return $condicionpago;
    }

    public function deletePorProvincia($provincia_id)
    {
    	$provincia_tasaiibb = $this->model->where('provincia_id', $provincia_id)->delete();

		return $provincia_tasaiibb;
    }

    public function find($id)
    {
        if (null == $provincia_tasaiibb = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $provincia_tasaiibb;
    }

    public function findOrFail($id)
    {
        if (null == $provincia_tasaiibb = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $provincia_tasaiibb;
    }

}
