<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\CategoriafidelidadGastronomia;

class CategoriafidelidadGastronomiaRepository implements CategoriafidelidadGastronomiaRepositoryInterface
{
    public function __construct(
        private CategoriafidelidadGastronomia $model,
    ) {
    }

    public function all()
    {
        return $this->model
            ->with(['articulos.articulo'])
            ->orderBy('nombre')
            ->orderBy('codigo')
            ->get();
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

    public function findPorCodigo(string $codigo): ?CategoriafidelidadGastronomia
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $categoria = $this->model->newQuery()->where('codigo', $codigo)->first();
        if ($categoria) {
            return $categoria;
        }

        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            return $this->model->newQuery()->where('codigo', $alt)->first();
        }

        return null;
    }
}
