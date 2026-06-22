<?php

namespace App\Repositories\Compras;

interface Comprobante_Proveedor_ConceptoRepositoryInterface
{
    public function create(array $data);

    public function deletePorComprobanteProveedor(int $comprobanteProveedorId): void;
}
