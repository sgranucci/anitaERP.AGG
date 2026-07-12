<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\Sicore_Config_Cuenta;

class Sicore_Config_CuentaRepository implements Sicore_Config_CuentaRepositoryInterface
{
    public function __construct(
        private readonly Sicore_Config_Cuenta $model,
    ) {
    }

    public function create(array $data): Sicore_Config_Cuenta
    {
        return $this->model->create($data);
    }

    public function deletePorConfig(int $configId): void
    {
        $this->model->newQuery()->where('sicore_config_id', $configId)->delete();
    }
}
