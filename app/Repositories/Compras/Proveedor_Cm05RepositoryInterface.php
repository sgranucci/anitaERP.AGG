<?php

namespace App\Repositories\Compras;

interface Proveedor_Cm05RepositoryInterface
{
    public function create(array $data, int $proveedorId): void;

    public function update(array $data, int $proveedorId): void;

    /**
     * @param  array<string, mixed>  $data
     */
    public function sincronizarDesdeRequest(int $proveedorId, array $data): void;
}
