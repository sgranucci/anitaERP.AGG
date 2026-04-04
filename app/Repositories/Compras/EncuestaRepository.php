<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Encuesta;
use App\Repositories\Compras\EncuestaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Carbon\Carbon;
use Auth;
use DB;

class EncuestaRepository implements EncuestaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Encuesta $encuesta,
                                )
    {
        $this->model = $encuesta;
    }

	public function all()
    {
        $encuesta = $this->model->with('encuesta_preguntas')->get();

        return $encuesta;
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
		return $this->model->destroy($id);
    }

    public function find($id)
    {
        return $this->model->with('encuesta_preguntas')->find($id);
    }

    public function findOrFail($id)
    {
        if (null == $encuesta = $this->model->with('encuesta_preguntas')->findOrFail($id))
		{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $encuesta;
    }

}    
