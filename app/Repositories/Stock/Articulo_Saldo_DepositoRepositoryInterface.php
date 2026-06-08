<?php

namespace App\Repositories\Stock;

interface Articulo_Saldo_DepositoRepositoryInterface
{
    public function saldo(int $articuloId, int $depositoId): float;

    /**
     * Saldo acumulado a una fecha inclusive (suma articulo_movimiento.cantidad).
     */
    public function saldoAFecha(int $articuloId, int $depositoId, string $fecha): float;

    /**
     * @param  array<int, int>  $depositoIds
     * @return array<int, float>  map deposito_id => saldo
     */
    public function saldosArticuloPorDeposito(int $articuloId, array $depositoIds = []): array;

    /**
     * Saldos de todos los artículos en un depósito.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Stock\Articulo_Saldo_Deposito>
     */
    public function saldosDeposito(int $depositoId);

    /**
     * Recalcula la tabla de saldos a partir de articulo_movimiento.
     */
    public function reconstruir(?int $depositoId = null): int;
}
