<?php

declare(strict_types=1);

namespace App\Repositories\Compras;

use App\Models\Compras\Importacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ImportacionRepositoryInterface
{
    /** @param array<string, mixed>|string|null $filtros */
    public function leeImportacion($filtros, bool $paginar = true): LengthAwarePaginator|Collection;

    /**
     * Cortes (conteo + suma de importes) por agrupación sobre el universo filtrado.
     *
     * @param  array<string, mixed>|string|null  $filtros
     * @return array<string, mixed>
     */
    public function cortesImportacion($filtros): array;

    /** @param array<string, mixed> $data */
    public function create(array $data): Importacion;

    /** @param array<string, mixed> $data */
    public function update(array $data, int $id): Importacion;

    public function delete(int $id): bool;

    public function findOrFail(int $id): Importacion;

    public function findConRelaciones(int $id): Importacion;

    public function siguienteCodigo(int $empresaId): string;
}
