<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Pagoproveedor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PagoproveedorRepositoryInterface
{
    public function create(array $data): Pagoproveedor;

    public function update(array $data, int $id): bool;

    public function delete(int $id): bool;

    public function find(int $id): Pagoproveedor;

    public function findOrFail(int $id): Pagoproveedor;

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function leePagoproveedor(array|string|null $filtros = [], bool $flPaginando = true): LengthAwarePaginator|Collection;

    /**
     * Pagos visibles en el portal de proveedores (sin precargas internas).
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listarPortalProveedor(int $proveedorId, array $filtros = [], bool $paginar = true): LengthAwarePaginator|Collection;

    /**
     * KPIs del período filtrado para el portal.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   cantidad: int,
     *   monto_pagado: float,
     *   monto_retenciones: float,
     *   monto_neto: float,
     *   cantidad_retenciones: int
     * }
     */
    public function resumenPortalProveedor(int $proveedorId, array $filtros = []): array;

    /**
     * Retenciones de pagos del proveedor para el portal.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listarRetencionesPortalProveedor(int $proveedorId, array $filtros = [], bool $paginar = true): LengthAwarePaginator|Collection;

    /**
     * Detalle de OP del portal con ownership por proveedor.
     */
    public function findPortalProveedor(int $id, int $proveedorId): Pagoproveedor;
}
