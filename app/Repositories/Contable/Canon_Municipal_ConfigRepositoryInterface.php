<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\Canon_Municipal_Config;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

interface Canon_Municipal_ConfigRepositoryInterface
{
    public function all(): Collection;

    public function find(int $id): ?Canon_Municipal_Config;

    public function findOrFail(int $id): Canon_Municipal_Config;

    public function findPorEmpresa(int $empresaId): ?Canon_Municipal_Config;

    public function create(array $data): Canon_Municipal_Config;

    public function update(array $data, int $id): bool;

    public function delete(int $id): bool;
}
