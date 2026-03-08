<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Presupuesto_Escenario;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Presupuesto_EscenarioRepository implements Presupuesto_EscenarioRepositoryInterface
{
	protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Presupuesto_Escenario $presupuesto_escenario)
    {
        $this->model = $presupuesto_escenario;
    }

    public function all()
    {
        $presupuesto_escenario = $this->model->get();

		return $presupuesto_escenario;
    }

    public function leePorPresupuesto($presupuesto_id)
    {
        return $this->model->where('presupuesto_id', $presupuesto_id)->get();
    }

    public function create(array $data)
    {
        $presupuesto_escenario = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $presupuesto_escenario = $this->model->findOrFail($id)->update($data);

        return $presupuesto_escenario;
    }

    public function delete($id)
    {
    	$presupuesto_escenario = $this->model->find($id);

        $presupuesto_escenario = $this->model->destroy($id);

		return $presupuesto_escenario;
    }
 
    public function deletePorPresupuesto($presupuesto_id)
    {
    	$presupuesto_escenario = $this->model->where('presupuesto_id', $presupuesto_id)->delete();

		return $presupuesto_escenario;
    }

    public function find($id)
    {
        if (null == $presupuesto_escenario = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $presupuesto_escenario;
    }

    public function findOrFail($id)
    {
        if (null == $presupuesto_escenario = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $presupuesto_escenario;
    }

    public function findPorCodigo($codigo)
    {
        return $this->model->with('creousuarios')->where('codigo',$codigo)->first();
    }

}
