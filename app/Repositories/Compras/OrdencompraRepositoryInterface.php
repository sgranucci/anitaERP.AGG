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
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Support\Collection<int, object>|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function listadoIndex($filtros, ?int $sectorUsuarioId, bool $paginar = false);

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Support\LazyCollection<int, object>
     */
    public function listadoIndexCursor($filtros, ?int $sectorUsuarioId);

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Support\Collection<int, \App\Models\Compras\Ordencompra>
     */
    public function listadoExport($filtros, ?int $sectorUsuarioId);

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Support\LazyCollection<int, \App\Models\Compras\Ordencompra>
     */
    public function listadoExportCursor($filtros, ?int $sectorUsuarioId);

    /**
     * Siguiente número de orden de compra (único global, sin filtrar por empresa).
     */
    public function proximoNumeroOrdencompra(): int;

    /**
     * Listado de OC del portal de proveedores.
     *
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function listarPortalProveedor(int $proveedorId, array $filtros = [], bool $paginar = true);

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   cantidad: int,
     *   con_factura: int,
     *   sin_factura: int,
     *   monto_oc: float,
     *   monto_facturado: float
     * }
     */
    public function resumenPortalProveedor(int $proveedorId, array $filtros = []): array;

    /**
     * Detalle de OC para el portal (facturas, precargas y pagos asociados).
     *
     * @return \App\Models\Compras\Ordencompra
     */
    public function findPortalProveedor(int $id, int $proveedorId);
}
