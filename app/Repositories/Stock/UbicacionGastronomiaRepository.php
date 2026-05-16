<?php

namespace App\Repositories\Stock;

use App\Models\Stock\UbicacionGastronomia;

class UbicacionGastronomiaRepository implements UbicacionGastronomiaRepositoryInterface
{
    protected $model;

    public function __construct(UbicacionGastronomia $ubicacionGastronomia)
    {
        $this->model = $ubicacionGastronomia;
    }

    public function all()
    {
        return $this->model->with('empresa')->orderBy('nombre')->get();
    }

    public function listarParaSelect(?int $empresaId = null)
    {
        $query = $this->model->orderBy('nombre');

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->get();
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

    public function tieneMesasAsociadas(int $id): bool
    {
        return $this->model->findOrFail($id)->mesas()->exists();
    }

    public function resolverId(?string $nombre, int $empresaId): ?int
    {
        $nombre = trim((string) $nombre);
        if ($nombre === '' || $empresaId <= 0) {
            return null;
        }

        $existente = $this->model->query()
            ->where('nombre', $nombre)
            ->where('empresa_id', $empresaId)
            ->value('id');

        if ($existente) {
            return (int) $existente;
        }

        $registro = $this->model->create([
            'nombre' => $nombre,
            'empresa_id' => $empresaId,
        ]);

        return (int) $registro->id;
    }
}
