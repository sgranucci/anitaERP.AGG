<?php

namespace App\Repositories\Compras;

interface OrdencompraRepositoryInterface
{
    public function create(array $data);

    /**
     * Alta desde Anita (id auto); numeroordencompra debe venir en $data (penmp_nro).
     */
    public function createDesdeAnita(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);

    public function existeRegistro(): bool;

    /**
     * @return \Illuminate\Support\Collection<int, object>|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listadoIndex(?string $busqueda, ?int $sectorUsuarioId, bool $paginar = false);

    /**
     * @return \Illuminate\Support\LazyCollection<int, object>
     */
    public function listadoIndexCursor(?string $busqueda, ?int $sectorUsuarioId);

    /**
     * Listado completo para exportaciones PDF/Excel (columnas estándar + ítems).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Compras\Ordencompra>
     */
    public function listadoExport(?string $busqueda, ?int $sectorUsuarioId);

    /**
     * Cursor del listado de exportación (PDF por lotes sin cargar todo en memoria).
     *
     * @return \Illuminate\Support\LazyCollection<int, \App\Models\Compras\Ordencompra>
     */
    public function listadoExportCursor(?string $busqueda, ?int $sectorUsuarioId);

    /**
     * Siguiente número de orden de compra (único global, sin filtrar por empresa).
     */
    public function proximoNumeroOrdencompra(): int;
}
