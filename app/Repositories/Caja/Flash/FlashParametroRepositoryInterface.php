<?php

namespace App\Repositories\Caja\Flash;

interface FlashParametroRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, \App\Models\Caja\Flash\FlashParametro>
     */
    public function leeFlashParametro($filtros, bool $paginar = false);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);

    public function findPorEmpresaPeriodo(int $empresaId, string $periodo): ?\App\Models\Caja\Flash\FlashParametro;

    /**
     * @param  list<array<string, mixed>>  $indices
     */
    public function sincronizarIndices(\App\Models\Caja\Flash\FlashParametro $parametro, array $indices): void;
}
