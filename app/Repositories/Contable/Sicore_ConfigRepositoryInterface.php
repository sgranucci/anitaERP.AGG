<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\Sicore_Config;
use Illuminate\Support\Collection;

interface Sicore_ConfigRepositoryInterface
{
    public function all(): Collection;

    public function activos(): Collection;

    /**
     * @param  list<string>  $criterios
     */
    public function activosPorCriterios(array $criterios): Collection;

    public function create(array $data): Sicore_Config;

    public function update(array $data, int $id): bool;

    public function delete(int $id): bool;

    public function find(int $id): ?Sicore_Config;

    public function findOrFail(int $id): Sicore_Config;

    /**
     * @return list<int>
     */
    public function cuentaIdsPorConfigEmpresa(int $configId, int $empresaId): array;
}
