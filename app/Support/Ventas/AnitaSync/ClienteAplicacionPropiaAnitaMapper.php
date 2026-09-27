<?php

namespace App\Support\Ventas\AnitaSync;

/**
 * Fila propia del crédito en aplmov y, si es un anticipo, el climov APA.
 * Misma forma que en proveedores: sin esta fila el listado de deuda sigue
 * mostrando el anticipo o la nota aunque la factura ya esté aplicada.
 */
final class ClienteAplicacionPropiaAnitaMapper
{
    public static function camposAplmov(): string
    {
        return '
            aplv_tipo,
            aplv_letra,
            aplv_sucursal,
            aplv_nro,
            aplv_nro_cuota,
            aplv_ref_tipo,
            aplv_ref_letra,
            aplv_ref_sucursal,
            aplv_ref_nro,
            aplv_fecha,
            aplv_monto,
            aplv_cod_mon,
            aplv_cotizacion,
            aplv_tipo_cob,
            aplv_letra_cob,
            aplv_sucursal_cob,
            aplv_nro_cob,
            aplv_fecha_aplic';
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, numero: int, cod_mon?: string, cotizacion?: float}  $credito
     */
    public static function valoresAplmov(
        array $credito,
        int $cuota,
        string $refTipo,
        int $refSucursal,
        int $refNro,
        string $fechaYmd,
        float $monto,
    ): string {
        $e = static fn (string $v) => str_replace("'", '', $v);
        $montoTxt = number_format(abs($monto), 4, '.', '');
        $cot = (float) ($credito['cotizacion'] ?? 1);
        if ($cot <= 0) {
            $cot = 1.0;
        }

        return "
            '".$e((string) $credito['tipo'])."',
            '".$e((string) $credito['letra'])."',
            '".(int) $credito['sucursal']."',
            '".(int) $credito['numero']."',
            '".$cuota."',
            '".$e($refTipo)."',
            '".$e((string) $credito['letra'])."',
            '".$refSucursal."',
            '".$refNro."',
            '".$e($fechaYmd)."',
            '".$montoTxt."',
            '".$e((string) ($credito['cod_mon'] ?? '1'))."',
            '".number_format($cot, 4, '.', '')."',
            '".$e((string) $credito['tipo'])."',
            '".$e((string) $credito['letra'])."',
            '".(int) $credito['sucursal']."',
            '".(int) $credito['numero']."',
            '".$e($fechaYmd)."'";
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, numero: int}  $credito
     */
    public static function whereAplmov(
        array $credito,
        int $cuota,
        string $refTipo,
        string $fechaYmd,
        float $monto,
    ): string {
        $e = static fn (string $v) => str_replace("'", '', $v);

        return " WHERE aplv_tipo = '".$e((string) $credito['tipo'])."'
            AND aplv_letra = '".$e((string) $credito['letra'])."'
            AND aplv_sucursal = '".(int) $credito['sucursal']."'
            AND aplv_nro = '".(int) $credito['numero']."'
            AND aplv_nro_cuota = '".$cuota."'
            AND aplv_tipo_cob = '".$e((string) $credito['tipo'])."'
            AND aplv_nro_cob = '".(int) $credito['numero']."'
            AND aplv_ref_tipo = '".$e($refTipo)."'
            AND aplv_fecha_aplic = '".$e($fechaYmd)."'
            AND aplv_monto = '".number_format(abs($monto), 4, '.', '')."' ";
    }

    public static function camposClimovApa(bool $conEmpresa): string
    {
        $campos = '
            cliv_cliente,
            cliv_tipo,
            cliv_letra,
            cliv_sucursal,
            cliv_nro,
            cliv_ref_tipo,
            cliv_ref_letra,
            cliv_ref_sucursal,
            cliv_ref_nro,
            cliv_fecha,
            cliv_fecha_vto,
            cliv_monto,
            cliv_cod_mon,
            cliv_cotizacion,
            cliv_nro_cuota,
            cliv_t_cobrado,
            cliv_fecha_cobro,
            cliv_cedio_a,
            cliv_estado';
        if ($conEmpresa) {
            $campos .= ',
            cliv_empresa';
        }

        return $campos;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, numero: int, cod_mon?: string, cotizacion?: float, empresa?: int}  $coa
     */
    public static function valoresClimovApa(
        array $coa,
        string $cliente,
        int $nroApa,
        float $monto,
        string $fechaYmd,
        bool $conEmpresa,
    ): string {
        $e = static fn (string $v) => str_replace("'", '', $v);
        $montoTxt = number_format(abs($monto), 4, '.', '');
        $cot = (float) ($coa['cotizacion'] ?? 1);
        if ($cot <= 0) {
            $cot = 1.0;
        }
        $valores = "
            '".$e($cliente)."',
            'APA',
            '".$e((string) $coa['letra'])."',
            '0',
            '".$nroApa."',
            '".$e((string) $coa['tipo'])."',
            '".$e((string) $coa['letra'])."',
            '".(int) $coa['sucursal']."',
            '".(int) $coa['numero']."',
            '".$e($fechaYmd)."',
            '0',
            '".$montoTxt."',
            '".$e((string) ($coa['cod_mon'] ?? '1'))."',
            '".number_format($cot, 4, '.', '')."',
            '0',
            '".$montoTxt."',
            '".$e($fechaYmd)."',
            '0',
            'C'";
        if ($conEmpresa) {
            $valores .= ",
            '".(int) ($coa['empresa'] ?? 0)."'";
        }

        return $valores;
    }
}
