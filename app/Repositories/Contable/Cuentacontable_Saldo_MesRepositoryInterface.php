<?php

namespace App\Repositories\Contable;

interface Cuentacontable_Saldo_MesRepositoryInterface
{
    /**
     * Saldo acumulado en moneda origen hasta fin de mes (inclusive).
     */
    public function saldoMonedaOrigenHastaMes(
        int $empresaId,
        int $cuentacontableId,
        int $anioMesHasta,
        int $monedaId,
        ?int $centrocostoId = null,
    ): float;

    /**
     * Saldo acumulado en moneda local hasta fin de mes (inclusive).
     */
    public function saldoMonedaLocalHastaMes(
        int $empresaId,
        int $cuentacontableId,
        int $anioMesHasta,
        ?int $centrocostoId = null,
    ): float;

    /**
     * Neto del mes en moneda origen (solo movimientos de ese YYYYMM).
     */
    public function netoMesMonedaOrigen(
        int $empresaId,
        int $cuentacontableId,
        int $anioMes,
        int $monedaId,
        ?int $centrocostoId = null,
    ): float;

    public function reconstruir(?int $empresaId = null): int;
}
