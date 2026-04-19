<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Tipoliquidofreno;

class TipoliquidofrenoRepository implements TipoliquidofrenoRepositoryInterface
{
    protected $model;

    public function __construct(Tipoliquidofreno $tipoliquidofreno)
    {
        $this->model = $tipoliquidofreno;
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
