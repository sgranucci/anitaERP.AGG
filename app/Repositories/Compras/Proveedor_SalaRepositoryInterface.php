<?php

namespace App\Repositories\Compras;

interface Proveedor_SalaRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $proveedorId): void;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data, int $proveedorId): void;

    /**
     * @param  list<int>  $salaIds
     */
    public function reemplazar(int $proveedorId, array $salaIds): void;
}
