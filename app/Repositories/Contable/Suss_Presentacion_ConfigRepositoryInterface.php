<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\Suss_Presentacion_Config;
use Illuminate\Support\Collection;

interface Suss_Presentacion_ConfigRepositoryInterface
{
    public function all(): Collection;

    public function find(int $id): ?Suss_Presentacion_Config;

    public function findOrFail(int $id): Suss_Presentacion_Config;

    public function findActivo(): ?Suss_Presentacion_Config;

    public function create(array $data): Suss_Presentacion_Config;

    public function update(array $data, int $id): bool;

    public function delete(int $id): bool;

    /** @return list<int> */
    public function cuentaIdsPorConfigEmpresa(int $configId, int $empresaId): array;
}
