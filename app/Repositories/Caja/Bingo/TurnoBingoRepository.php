<?php

namespace App\Repositories\Caja\Bingo;

use App\Models\Caja\Bingo\TurnoBingo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;

class TurnoBingoRepository implements TurnoBingoRepositoryInterface
{
    public function __construct(
        private readonly TurnoBingo $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function all()
    {
        $query = $this->model->with('empresa')->orderBy('empresa_id')->orderBy('orden')->orderBy('nombre');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        return $query->get();
    }

    public function listarParaSelect(?int $empresaId = null)
    {
        $query = $this->model->where('activo', true)->orderBy('orden')->orderBy('nombre');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

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
}
