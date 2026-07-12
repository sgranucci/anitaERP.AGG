<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\Sicore_Config_Cuenta;

interface Sicore_Config_CuentaRepositoryInterface
{
    public function create(array $data): Sicore_Config_Cuenta;

    public function deletePorConfig(int $configId): void;
}
