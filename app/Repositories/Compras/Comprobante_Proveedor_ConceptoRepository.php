<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Comprobante_Proveedor_Concepto;

class Comprobante_Proveedor_ConceptoRepository implements Comprobante_Proveedor_ConceptoRepositoryInterface
{
    public function __construct(
        private Comprobante_Proveedor_Concepto $model,
    ) {}

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function deletePorComprobanteProveedor(int $comprobanteProveedorId): void
    {
        $this->model->where('comprobante_proveedor_id', $comprobanteProveedorId)->delete();
    }
}
