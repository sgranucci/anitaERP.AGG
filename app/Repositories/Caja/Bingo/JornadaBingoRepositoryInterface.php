<?php

namespace App\Repositories\Caja\Bingo;

use App\Models\Caja\Bingo\JornadaBingo;
use Illuminate\Support\Collection;

interface JornadaBingoRepositoryInterface
{
    public function jornadaAbiertaPorEmpresa(int $empresaId): ?JornadaBingo;

    public function ultimaJornadaPorEmpresa(int $empresaId): ?JornadaBingo;

    public function historialPorEmpresa(int $empresaId, int $limite = 30): Collection;

    public function create(array $data): JornadaBingo;

    public function update(array $data, int $id): bool;

    public function findOrFail(int $id): JornadaBingo;

    public function delete(int $id): bool;
}
