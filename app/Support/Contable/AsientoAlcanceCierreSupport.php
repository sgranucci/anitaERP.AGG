<?php

namespace App\Support\Contable;

/**
 * Alcance de cierre que corresponde a un asiento.
 *
 * Un asiento del sistema contable se valida siempre contra el cierre de Contabilidad.
 * La referencia (factura de proveedor, OC, venta, recepción, cobranza, stock) clasifica
 * el comprobante y no cambia el cierre.
 *
 * El circuito que origina el documento (factura, venta, caja, stock) puede mandar
 * alcance_cierre_contable con el cierre de ese subsistema: ese control es del
 * documento, no de la referencia del asiento. Corregir o borrar un asiento ya
 * existente depende solo del cierre de Contabilidad, aunque el circuito lo haya
 * generado con otro alcance.
 */
class AsientoAlcanceCierreSupport
{
    /**
     * Alcance que debe validar la grabación del asiento.
     *
     * @param  array<string, mixed>  $data
     */
    public static function alcanceParaValidar(array $data, bool $esModificacion): string
    {
        if ($esModificacion) {
            return PeriodoContableCierreSupport::ALCANCE_CONTABLE;
        }

        $explicito = trim((string) ($data['alcance_cierre_contable'] ?? ''));
        if ($explicito !== '' && PeriodoContableCierreSupport::alcanceEsValido($explicito)) {
            return $explicito;
        }

        return PeriodoContableCierreSupport::ALCANCE_CONTABLE;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function tieneAlcanceExplicito(array $data): bool
    {
        $explicito = trim((string) ($data['alcance_cierre_contable'] ?? ''));

        return $explicito !== '' && PeriodoContableCierreSupport::alcanceEsValido($explicito);
    }
}
