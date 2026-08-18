<?php

namespace App\Repositories\Caja\Flash;

interface FlashCajaRepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, \App\Models\Caja\Flash\FlashCaja>
     */
    public function leeFlashCaja($filtros, bool $paginar = false);

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);

    public function findPorEmpresaFecha(int $empresaId, string $fecha, bool $forUpdate = false): ?\App\Models\Caja\Flash\FlashCaja;

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Caja\Flash\FlashCaja>
     */
    public function leeFlashPorRango(int $empresaId, string $fechaDesde, string $fechaHasta);
}
