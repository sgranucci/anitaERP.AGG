<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\Iibb_Presentacion_Config_Cuenta;

interface Iibb_Presentacion_Config_CuentaRepositoryInterface
{
    public function deletePorConfig(int $configId): void;

    /** @param  list<array{empresa_id:int,cuentacontable_id:int}>  $filas */
    public function syncPorConfig(int $configId, array $filas): void;
}
