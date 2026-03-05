<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Concepto_Ivacompra_Condicioniva;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Concepto_Ivacompra_CondicionivaRepository implements Concepto_Ivacompra_CondicionivaRepositoryInterface
{
	protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Concepto_Ivacompra_Condicioniva $concepto_ivacompra_condicioniva)
    {
        $this->model = $concepto_ivacompra_condicioniva;
    }

    public function all()
    {
        $concepto_ivacompras_condicioniva = $this->model->with("concepto_ivacompras")->get();

		return $concepto_ivacompras_condicioniva;
    }

    public function create(array $data)
    {
        $concepto_ivacompras_condicioniva = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $concepto_ivacompras_condicioniva = $this->model->findOrFail($id)->update($data);

        return $concepto_ivacompras_condicioniva;
    }

    public function delete($id)
    {
    	$concepto_ivacompras_condicioniva = $this->model->find($id);

        $concepto_ivacompras_condicioniva = $this->model->destroy($id);

		return $concepto_ivacompras_condicioniva;
    }

    public function deletePorConcepto_Ivacompra($concepto_ivacompra_id)
    {
    	$concepto_ivacompras_condicioniva = $this->model->where('concepto_ivacompra_id', $concepto_ivacompra_id)->delete();

		return $concepto_ivacompras_condicioniva;
    }

    public function find($id)
    {
        if (null == $concepto_ivacompras_condicioniva = $this->model->with("concepto_ivacompras")->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencionganancia;
    }

    public function findOrFail($id)
    {
        if (null == $concepto_ivacompras_condicioniva = $this->model->with("concepto_ivacompras")->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $concepto_ivacompras_condicioniva;
    }

}
