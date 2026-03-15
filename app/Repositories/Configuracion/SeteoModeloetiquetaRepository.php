<?php

namespace App\Repositories\Configuracion;

use Illuminate\Http\Request;
use App\Models\Configuracion\SeteoModeloetiqueta;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Auth;

class SeteoModeloetiquetaRepository implements SeteoModeloetiquetaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(SeteoModeloetiqueta $seteoModeloetiqueta)
    {
        $this->model = $seteoModeloetiqueta;
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
        $seteoModeloetiqueta = $this->model->findOrFail($id)
            ->update($data);

		return $seteoModeloetiqueta;
    }

    public function delete($id)
    {
    	$seteoModeloetiqueta = Modeloetiqueta::find($id);
		
        $seteoModeloetiqueta = $this->model->destroy($id);

		return $seteoModeloetiqueta;
    }

    public function find($id)
    {
        if (null == $seteoModeloetiqueta = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $seteoModeloetiqueta;
    }

    public function findOrFail($id)
    {
        if (null == $seteoModeloetiqueta = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $seteoModeloetiqueta;
    }

    public function buscaSeteoModeloetiqueta($usuario_id, $opcion = null)
    {
        if ($opcion)
            $opcion = str_replace('/', '_', $opcion);

        $seteoModeloetiqueta = $this->model->where('usuario_id', $usuario_id)
                                    ->where('programa', $opcion)
                                    ->with('modeloetiquetas')
                                    ->first();

        return $seteoModeloetiqueta;
    }

    public function leeSeteo($usuario_id, $programa)
    {
        $seteoModeloetiqueta = $this->model->where('usuario_id', $usuario_id)
                                    ->where('programa', $programa)
                                    ->with('modeloetiquetas')
                                    ->first();
        return $seteoModeloetiqueta;
    }
}
