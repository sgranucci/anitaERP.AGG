<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Sala;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Collection;
use Auth;

class SalaRepository implements SalaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Sala $sala
                                )
    {
        $this->model = $sala;
    }

    public function all()
    {
        $sala = $this->model;

        return $sala->with('empresas')->orderBy('nombre')->get();
    }

    public function allFiltrado()
    {
        // Extrae las empresas asignadas
        $empresas = collect(Session::get('usuario_empresas'))->pluck('id')->toArray();

        if (count($empresas) > 1)
            $sala = $this->model->with('empresas')->whereIn('empresa_id', $empresas)->orderBy('nombre')->get();
        else
            $sala = $this->model->with('empresas')->orderBy('nombre')->get();

        return $sala;
    }

    public function create(array $data)
    {
        $sala = $this->model->create($data);

        return($sala);
    }

    public function update(array $data, $id)
    {
        $sala = $this->model->findOrFail($id)->update($data);

		return $sala;
    }

    public function delete($id)
    {
    	$sala = $this->model->find($id);

        $sala = $this->model->destroy($id);

		return $sala;
    }

    public function find($id)
    {
        if (null == $sala = $this->model->with('empresas')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $sala;
    }

    public function findOrFail($id)
    {
        if (null == $sala = $this->model->with('empresas')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $sala;
    }

}
