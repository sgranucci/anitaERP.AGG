<?php

namespace App\Repositories\Receptivo;

use App\Models\Receptivo\Guia;
use App\Models\Receptivo\Guia_Idioma;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Guia_IdiomaRepository implements Guia_IdiomaRepositoryInterface
{
	protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Guia_Idioma $guia_idioma)
    {
        $this->model = $guia_idioma;
    }

    public function all()
    {
        return $this->model->get();
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
    	return $this->model->find($id)->destroy();
    }

    public function deletePorGuia($guia_id)
    {
    	return $this->model->where('guia_id', $guia_id)->delete();

		return $guia_idioma;
    }

    public function find($id)
    {
        if (null == $guia_idioma = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicionpago;
    }

    public function findOrFail($id)
    {
        if (null == $guia_idioma = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $guia_idioma;
    }

}
