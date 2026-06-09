<?php

namespace App\Repositories\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\DescuentoEstacionamiento;

class DescuentoEstacionamientoRepository implements DescuentoEstacionamientoRepositoryInterface
{
    public function __construct(
        private DescuentoEstacionamiento $model,
    ) {
    }

    public function all()
    {
        return $this->model->with('cliente')->orderBy('nombre')->orderBy('codigo')->get();
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

    public function findPorCodigo(string $codigo): ?DescuentoEstacionamiento
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $descuento = $this->model->newQuery()
            ->with('cliente')
            ->where('codigo', $codigo)
            ->first();

        if ($descuento) {
            return $descuento;
        }

        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            return $this->model->newQuery()
                ->with('cliente')
                ->where('codigo', $alt)
                ->first();
        }

        return null;
    }
}
