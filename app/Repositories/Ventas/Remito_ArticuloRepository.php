<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Remito_Articulo;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Remito_ArticuloRepository implements Remito_ArticuloRepositoryInterface
{
    protected $model;

    public function __construct(Remito_Articulo $remito_articulo)
    {
        $this->model = $remito_articulo;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update($data, $id)
    {
        $remito_articulo = $this->model->find($id);

        return $remito_articulo->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function deleteporremito($remito_id)
    {
        return EloquentAuditDeleteSupport::each(
            $this->model->newQuery()->where('remito_id', $remito_id)
        );
    }

    public function find($id)
    {
        if (null == $remito_articulo = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $remito_articulo;
    }

    public function findOrFail($id)
    {
        if (null == $remito_articulo = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $remito_articulo;
    }

    public function findPorRemitoId($remito_id)
    {
        return $this->model->where('remito_id', $remito_id)->get();
    }
}
