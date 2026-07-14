<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Remito;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RemitoRepository implements RemitoRepositoryInterface
{
    protected $model;

    public function __construct(Remito $remito)
    {
        $this->model = $remito;
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

    public function all()
    {
        return $this->model->get();
    }

    public function find($id)
    {
        if (null == $remito = $this->model->with('remito_articulos')->with('clientes')->with('ventas')->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $remito;
    }

    public function findOrFail($id)
    {
        if (null == $remito = $this->model->with('remito_articulos')->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $remito;
    }
}
