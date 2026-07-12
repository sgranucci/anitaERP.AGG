<?php

namespace App\Repositories\Caja\Bingo;

use App\Models\Caja\Bingo\ConfiguracionPuntoventaBingo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;

class ConfiguracionPuntoventaBingoRepository implements ConfiguracionPuntoventaBingoRepositoryInterface
{
    public function __construct(
        private readonly ConfiguracionPuntoventaBingo $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function all()
    {
        $query = $this->model->newQuery()
            ->with(['empresa', 'cuentacaja'])
            ->orderBy('empresa_id')
            ->orderBy('identificador_pc');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

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
        return $this->model->with(['empresa', 'cuentacaja'])->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->with(['empresa', 'cuentacaja'])->findOrFail($id);
    }
}
