<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\Suss_Presentacion_Config_Cuenta;

class Suss_Presentacion_Config_CuentaRepository implements Suss_Presentacion_Config_CuentaRepositoryInterface
{
    public function __construct(
        private readonly Suss_Presentacion_Config_Cuenta $model,
    ) {
    }

    public function deletePorConfig(int $configId): void
    {
        $this->model->newQuery()
            ->where('suss_presentacion_config_id', $configId)
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
                'suss_presentacion_config_id' => $configId,
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
