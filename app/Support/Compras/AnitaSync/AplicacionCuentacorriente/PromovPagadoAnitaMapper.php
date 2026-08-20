<?php

namespace App\Support\Compras\AnitaSync\AplicacionCuentacorriente;

/**
 * Actualiza promov.prov_t_pagado (y referencia del último crédito aplicado).
 *
 * @phpstan-import-type Lado from AplicacionCuentacorrienteAnitaLadoSupport
 */
final class PromovPagadoAnitaMapper
{
    /**
     * @param  Lado  $lado
     */
    public static function whereCuota(array $lado): string
    {
        $e = static fn (string $v, int $max = 0) => AplicacionCuentacorrienteAnitaLadoSupport::esc($v, $max);
        $where = " WHERE prov_proveedor = '".$e($lado['proveedor'], 6)."'
            AND prov_tipo = '".$e($lado['tipo'], 3)."'
            AND prov_letra = '".$e($lado['letra'], 1)."'
            AND prov_sucursal = '".(int) $lado['sucursal']."'
            AND prov_nro = '".(int) $lado['numero']."'
            AND prov_nro_cuota = '".(int) $lado['nro_cuota']."' ";

        if ((int) ($lado['nro_interno'] ?? 0) > 0) {
            $where .= " AND prov_nro_interno = '".(int) $lado['nro_interno']."' ";
        }

        return $where;
    }

    /**
     * @param  Lado|null  $refCredito  último comprobante que aplicó; null limpia la referencia
     */
    public static function valoresUpdate(float $tPagado, string $fechaPagoYmd, ?array $refCredito): string
    {
        $pagado = round($tPagado, 4);
        if ($pagado < 0.0001) {
            return "
                prov_t_pagado = '0',
                prov_fecha_pago = '0',
                prov_ref_tipo = '   ',
                prov_ref_letra = ' ',
                prov_ref_sucursal = '0',
                prov_ref_nro = '0'
            ";
        }

        $e = static fn (string $v, int $max = 0) => AplicacionCuentacorrienteAnitaLadoSupport::esc($v, $max);
        $refTipo = $refCredito ? $e((string) $refCredito['tipo'], 3) : '   ';
        $refLetra = $refCredito ? $e((string) $refCredito['letra'], 1) : ' ';
        $refSuc = $refCredito ? (int) $refCredito['sucursal'] : 0;
        $refNro = $refCredito ? (int) $refCredito['numero'] : 0;
        $fecha = $e($fechaPagoYmd, 8);
        if ($fecha === '' || $fecha === '0') {
            $fecha = '0';
        }

        return "
            prov_t_pagado = '".AplicacionCuentacorrienteAnitaLadoSupport::decimal($pagado)."',
            prov_fecha_pago = '".$fecha."',
            prov_ref_tipo = '".$refTipo."',
            prov_ref_letra = '".$refLetra."',
            prov_ref_sucursal = '".$refSuc."',
            prov_ref_nro = '".$refNro."'
        ";
    }
}
