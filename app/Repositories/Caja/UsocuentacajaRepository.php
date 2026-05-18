<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Usocuentacaja;

class UsocuentacajaRepository implements UsocuentacajaRepositoryInterface
{
    protected $model;

    public function __construct(Usocuentacaja $usocuentacaja)
    {
        $this->model = $usocuentacaja;
    }

    public function all()
    {
        return $this->model->orderBy('nombre', 'ASC')->get();
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
}
