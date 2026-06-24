<?php

namespace App\Repositories\Compras;

interface Proveedor_CuentacorrienteRepositoryInterface
{
    public function create(array $data);

    public function update(array $data, $id);

    public function find($id);

    public function findOrFail($id);

    public function delete($id);

    public function listarCuentaCorriente($busqueda, $proveedor_id, $paginar = true);

    public function listarDeudaProveedor($busqueda, $proveedor_id, $paginar = true);

    public function calcularSaldoCuentaCorriente(int $proveedor_id): float;

    public function calcularTotalDeudaProveedor(int $proveedor_id): float;

    public function saldoAnteriorPagina(int $proveedor_id, $primerRegistro): float;

    public function consultarDeuda($proveedor_id, $empresa_id, $comprobante_proveedor_id = null);

    public function consultarAplicacion($proveedor_cuentacorriente_id, $comprobante, $codigoproveedor);
}
