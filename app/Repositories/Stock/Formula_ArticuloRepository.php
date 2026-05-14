<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Formula_Articulo;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Formula_ArticuloRepository implements Formula_ArticuloRepositoryInterface
{
    protected $model;

    public function __construct(Formula_Articulo $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        return $this->model->orderBy('id', 'desc')->get();
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
        $q = $this->model->with([
            'articulos',
            'usuarios',
            'formula_articulo_estados.usuarios',
            'formula_articulo_archivos',
            'formula_articulo_hijos.articulos',
            'formula_articulo_hijos.formula_hija.articulos',
            'formula_articulo_hijos.depositos',
        ]);

        if (null === $row = $q->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $row;
    }

    public function findOrFail($id)
    {
        return $this->find($id);
    }
}
