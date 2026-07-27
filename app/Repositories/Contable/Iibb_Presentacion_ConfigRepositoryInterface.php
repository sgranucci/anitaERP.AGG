<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\Iibb_Presentacion_Config;
use Illuminate\Support\Collection;

interface Iibb_Presentacion_ConfigRepositoryInterface
{
    public function all(): Collection;

    public function find(int $id): ?Iibb_Presentacion_Config;

    public function findOrFail(int $id): Iibb_Presentacion_Config;

    public function findActivoPorProvinciaTipo(int $provinciaId, string $tipo): ?Iibb_Presentacion_Config;

    public function create(array $data): Iibb_Presentacion_Config;

    public function update(array $data, int $id): bool;

    public function delete(int $id): bool;

    /** @return list<int> */
    public function cuentaIdsPorConfigEmpresa(int $configId, int $empresaId): array;
}
