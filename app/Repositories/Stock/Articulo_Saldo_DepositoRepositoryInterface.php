<?php

namespace App\Repositories\Stock;

interface Articulo_Saldo_DepositoRepositoryInterface
{
    /**
     * Saldo total del artículo en el depósito (suma todas las variantes color/talle).
     */
    public function saldo(int $articuloId, int $depositoId): float;

    /**
     * Saldo de una variante exacta (null/0 = sin color o talle).
     */
    public function saldoVariante(int $articuloId, int $depositoId, ?int $colorId, ?int $talleId): float;

    /**
     * Saldo acumulado a una fecha inclusive (suma articulo_movimiento.cantidad).
     */
    public function saldoAFecha(int $articuloId, int $depositoId, string $fecha): float;

    /**
     * Saldo de una variante a una fecha inclusive.
     */
    public function saldoVarianteAFecha(
        int $articuloId,
        int $depositoId,
        string $fecha,
        ?int $colorId,
        ?int $talleId
    ): float;

    /**
     * Suma de movimientos de una variante con fecha estrictamente posterior a $fecha.
     */
    public function sumaVariantePosteriorAFecha(
        int $articuloId,
        int $depositoId,
        string $fecha,
        ?int $colorId,
        ?int $talleId
    ): float;

    /**
     * @param  array<int, int>  $depositoIds
     * @return array<int, float>  map deposito_id => saldo (suma variantes)
     */
    public function saldosArticuloPorDeposito(int $articuloId, array $depositoIds = []): array;

    /**
     * Saldos de todos los artículos en un depósito (una fila por variante).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Stock\Articulo_Saldo_Deposito>
     */
    public function saldosDeposito(int $depositoId);

    /**
     * Recalcula la tabla de saldos a partir de articulo_movimiento.
     */
    public function reconstruir(?int $depositoId = null): int;
}
