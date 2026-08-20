<?php

namespace App\Repositories\Compras;

interface Proveedor_CuentacorrienteRepositoryInterface
{
    public function create(array $data);

    public function update(array $data, $id);

    public function find($id);

    public function findOrFail($id);

    public function delete($id);

    public function listarCuentaCorriente($busqueda, $proveedor_id, $paginar = true, array $filtros = []);

    public function listarDeudaProveedor($busqueda, $proveedor_id, $paginar = true, array $filtros = []);

    public function calcularSaldoCuentaCorriente(int $proveedor_id, array $filtros = []): float;

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{moneda_id: int, abreviatura: string, saldo_cc: float}>
     */
    public function calcularSaldosPorMoneda(int $proveedor_id, array $filtros = []): array;

    public function calcularTotalDeudaProveedor(int $proveedor_id, array $filtros = []): float;

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{moneda_id: int, abreviatura: string, deuda: float}>
     */
    public function calcularDeudasPorMoneda(int $proveedor_id, array $filtros = []): array;

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{moneda_id: int, abreviatura: string, saldo_cc: float, deuda: float}>
     */
    public function calcularSaldosYDeudasPorMoneda(int $proveedor_id, array $filtros = []): array;

    public function saldoAnteriorPagina(int $proveedor_id, $primerRegistro, array $filtros = []): float;

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, float>
     */
    public function saldosAnterioresPorMoneda(int $proveedor_id, $primerRegistro, array $filtros = []): array;

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{saldo_cc: float, deuda: float, abreviatura: string}
     */
    public function calcularEquivalentePesos(int $proveedor_id, array $filtros = []): array;

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function saldoAnteriorPaginaEnPesos(int $proveedor_id, $primerRegistro, array $filtros = []): float;

    public function consultarDeuda($proveedor_id, $empresa_id, $comprobante_proveedor_id = null);

    public function consultarAplicacion($proveedor_cuentacorriente_id, $comprobante, $codigoproveedor);

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Compras\Proveedor_Cuentacorriente>
     */
    public function listarPendientesAplicacion(int $proveedor_id, string $lado, ?int $empresa_id = null);

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion>
     */
    public function listarAplicacionesManualesRecientes(int $proveedor_id, ?int $empresa_id = null, int $limite = 30);
}
