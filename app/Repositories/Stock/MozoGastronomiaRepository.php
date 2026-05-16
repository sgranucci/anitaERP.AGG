<?php

namespace App\Repositories\Stock;

use App\Models\Stock\MozoGastronomia;

class MozoGastronomiaRepository implements MozoGastronomiaRepositoryInterface
{
    protected $model;

    public function __construct(MozoGastronomia $mozoGastronomia)
    {
        $this->model = $mozoGastronomia;
    }

    public function all()
    {
        return $this->model->with('empresa')->orderBy('nombre')->orderBy('codigo')->get();
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
        return $this->model->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function existeRegistro(): bool
    {
        return $this->model->query()->exists();
    }
}
