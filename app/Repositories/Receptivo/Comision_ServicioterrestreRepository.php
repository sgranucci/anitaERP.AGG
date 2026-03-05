<?php

namespace App\Repositories\Receptivo;

use App\Models\Receptivo\Comision_Servicioterrestre;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;

class Comision_ServicioterrestreRepository implements Comision_ServicioterrestreRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Comision_Servicioterrestre $comision_servicioterrestre)
    {
        $this->model = $comision_servicioterrestre;
    }

    public function all()
    {
		return $this->model->with('servicioterrestres')->with('formapagos')->orderBy('servicioterrestre_id')->get();
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
    	return $this->model->find($id)->delete();
    }

    public function find($id)
    {
        if (null == $comision_servicioterrestre = $this->model->with('servicioterrestres')->with('formapagos')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $comision_servicioterrestre;
    }

    public function findOrFail($id)
    {
        if (null == $comision_servicioterrestre = $this->model->with('servicioterrestres')->with('formapagos')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $comision_servicioterrestre;
    }

    public function findComision($formapago_id, $tipocomision, $servicioterrestre_id)
    {
        return $this->model->select('porcentajecomision')->where('formapago_id', $formapago_id)
                                                ->where('tipocomision', $tipocomision)
                                                ->where('servicioterrestre_id', $servicioterrestre_id)->first();
    }

    public function findComisionPorServicioTerrestre($servicioterrestre_id, $tipocomision)
    {
        return $this->model->select('porcentajecomision')
                                                ->where('tipocomision', $tipocomision)
                                                ->where('servicioterrestre_id', $servicioterrestre_id)->first();
    }
}
