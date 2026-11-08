<?php

namespace App\Support\Caja;

/**
 * Ledger unificado de valores (evolución mínima del relevamiento JL).
 *
 * Maestros ya unificados:
 * - tipo_valor + valor (denominaciones)
 * - tipo_deposito + deposito (bóveda, pre-depósito, caja chica, …)
 *
 * Escritura: los movimientos operativos van a `tesoreria_crown_boveda` (+ líneas).
 * CashBox/slot confirmado asienta INGR en bóveda (`TesoreriaCrownSlotBovedaSupport`).
 * Mesas y cajas siguen con sus tablas de proceso; el kardex de valores lee los
 * lugares de bóveda/pre-depósito/caja chica (mismo repositorio de stock).
 *
 * Esta clase documenta el contrato y apunta al listado canónico.
 */
final class TesoreriaCrownValorLedgerSupport
{
    /**
     * Listado canónico del ledger (bóveda / pre-depósito / caja chica).
     *
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public static function listar(int $salaId, array $filtros, bool $paginar = true)
    {
        return TesoreriaCrownKardexValoresSupport::listar($salaId, $filtros, $paginar);
    }

    /**
     * @return list<string>
     */
    public static function lugaresCodigo(): array
    {
        return TesoreriaCrownBovedaSupport::LUGARES;
    }
}
