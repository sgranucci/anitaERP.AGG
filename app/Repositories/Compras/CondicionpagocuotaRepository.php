<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Condicionpago;
use App\Models\Compras\Condicionpagocuota;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CondicionpagocuotaRepository implements CondicionpagocuotaRepositoryInterface
{
	protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Condicionpagocuota $condicionpagocuota)
    {
        $this->model = $condicionpagocuota;
    }

    public function all()
    {
        $condicionespago = $this->model->with("condicionespago")->get();

		return $condicionespago;
    }

    public function create(array $data)
    {
        $condicionpago = $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $condicionpago = $this->model->findOrFail($id)->update($data);

        return $condicionpago;
    }

    public function delete($id)
    {
    	$condicionpago = $this->model->find($id);

        $condicionpago = $this->model->destroy($id);

		return $condicionpago;
    }

    public function deletePorCondicionPago($condicionpago_id)
    {
    	$condicionpago = $this->model->where('condicionpago_id', $condicionpago_id)->delete();

		return $condicionpago;
    }

    public function find($id)
    {
        if (null == $condicionpago = $this->model->with("condicionespago")->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicionpago;
    }

    public function findOrFail($id)
    {
        if (null == $condicionpago = $this->model->with("condicionespago")->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $condicionpago;
    }

}
