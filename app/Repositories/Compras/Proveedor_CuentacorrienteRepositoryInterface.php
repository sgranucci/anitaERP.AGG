<?php

namespace App\Repositories\Compras;

interface Proveedor_CuentacorrienteRepositoryInterface
{
    public function create(array $data);

    public function update(array $data, $id);

    public function find($id);

    public function findOrFail($id);

    public function delete($id);

    public function listarCuentaCorriente($busqueda, $proveedor_id);

    public function consultarDeuda($proveedor_id, $empresa_id, $comprobante_proveedor_id = null);

    public function consultarAplicacion($proveedor_cuentacorriente_id, $comprobante, $codigoproveedor);
}
