<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\Iibb_Presentacion_Config_Cuenta;

class Iibb_Presentacion_Config_CuentaRepository implements Iibb_Presentacion_Config_CuentaRepositoryInterface
{
    public function __construct(
        private readonly Iibb_Presentacion_Config_Cuenta $model,
    ) {
    }

    public function deletePorConfig(int $configId): void
    {
        $this->model->newQuery()
            ->where('iibb_presentacion_config_id', $configId)
            ->delete();
    }

    public function syncPorConfig(int $configId, array $filas): void
    {
        $this->deletePorConfig($configId);
        $now = now();
        foreach ($filas as $fila) {
            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            $cuentaId = (int) ($fila['cuentacontable_id'] ?? 0);
            if ($empresaId <= 0 || $cuentaId <= 0) {
                continue;
            }
            $this->model->newQuery()->create([
                'iibb_presentacion_config_id' => $configId,
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
