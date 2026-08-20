<?php

namespace App\Support\Compras\AnitaSync\AplicacionCuentacorriente;

/**
 * Fila Anita aplmovp: aplvp_* = deuda (factura), aplvp_*_cob y aplvp_ref_* = crédito (OP/NC).
 *
 * En Anita nativo la referencia copia el comprobante que aplica (OPP/OPA/NC) y
 * aplvp_nro_interno es el de la factura. En OP aplvp_ref_interno va en 0.
 *
 * @phpstan-import-type Lado from AplicacionCuentacorrienteAnitaLadoSupport
 */
final class AplmovpAnitaMapper
{
    public static function camposInsert(): string
    {
        return '
            aplvp_proveedor,
            aplvp_tipo,
            aplvp_letra,
            aplvp_sucursal,
            aplvp_nro,
            aplvp_nro_cuota,
            aplvp_fecha,
            aplvp_monto,
            aplvp_tipo_cob,
            aplvp_letra_cob,
            aplvp_sucursal_cob,
            aplvp_nro_cob,
            aplvp_ref_tipo,
            aplvp_ref_letra,
            aplvp_ref_sucursal,
            aplvp_ref_nro,
            aplvp_nro_interno,
            aplvp_ref_interno,
            aplvp_cod_mon,
            aplvp_cotizacion
        ';
    }

    /**
     * @param  Lado  $deuda
     * @param  Lado  $credito
     */
    public static function valoresInsert(array $deuda, array $credito, string $fechaYmd, float $monto): string
    {
        $e = static fn (string $v, int $max = 0) => AplicacionCuentacorrienteAnitaLadoSupport::esc($v, $max);

        return "
            '".$e($deuda['proveedor'], 6)."',
            '".$e($deuda['tipo'], 3)."',
            '".$e($deuda['letra'], 1)."',
            '".(int) $deuda['sucursal']."',
            '".(int) $deuda['numero']."',
            '".self::nroCuota($deuda)."',
            '".$e($fechaYmd, 8)."',
            '".AplicacionCuentacorrienteAnitaLadoSupport::decimal($monto)."',
            '".$e($credito['tipo'], 3)."',
            '".$e($credito['letra'], 1)."',
            '".(int) $credito['sucursal']."',
            '".(int) $credito['numero']."',
            '".$e($credito['tipo'], 3)."',
            '".$e($credito['letra'], 1)."',
            '".(int) $credito['sucursal']."',
            '".(int) $credito['numero']."',
            '".(int) ($deuda['nro_interno'] ?? 0)."',
            '".(int) ($credito['nro_interno'] ?? 0)."',
            '".self::codMon($deuda)."',
            '".self::cotizacion($deuda)."'
        ";
    }

    /**
     * @param  Lado  $deuda
     * @param  Lado  $credito
     */
    public static function valoresUpdate(array $deuda, array $credito): string
    {
        $e = static fn (string $v, int $max = 0) => AplicacionCuentacorrienteAnitaLadoSupport::esc($v, $max);

        return "
            aplvp_nro_cuota = '".self::nroCuota($deuda)."',
            aplvp_tipo_cob = '".$e($credito['tipo'], 3)."',
            aplvp_letra_cob = '".$e($credito['letra'], 1)."',
            aplvp_sucursal_cob = '".(int) $credito['sucursal']."',
            aplvp_nro_cob = '".(int) $credito['numero']."',
            aplvp_ref_tipo = '".$e($credito['tipo'], 3)."',
            aplvp_ref_letra = '".$e($credito['letra'], 1)."',
            aplvp_ref_sucursal = '".(int) $credito['sucursal']."',
            aplvp_ref_nro = '".(int) $credito['numero']."',
            aplvp_nro_interno = '".(int) ($deuda['nro_interno'] ?? 0)."',
            aplvp_ref_interno = '".(int) ($credito['nro_interno'] ?? 0)."',
            aplvp_cod_mon = '".self::codMon($deuda)."',
            aplvp_cotizacion = '".self::cotizacion($deuda)."'
        ";
    }

    /**
     * @param  Lado  $deuda
     */
    public static function nroCuota(array $deuda): int
    {
        $cuota = (int) ($deuda['nro_cuota'] ?? 1);

        return $cuota > 0 ? $cuota : 1;
    }

    /**
     * @param  Lado  $deuda
     */
    public static function codMon(array $deuda): string
    {
        $codigo = trim((string) ($deuda['cod_mon'] ?? ''));

        return $codigo !== '' ? AplicacionCuentacorrienteAnitaLadoSupport::esc($codigo, 3) : '1';
    }

    /**
     * @param  Lado  $deuda
     */
    public static function cotizacion(array $deuda): string
    {
        $cot = (float) ($deuda['cotizacion'] ?? 1);

        return AplicacionCuentacorrienteAnitaLadoSupport::decimal($cot > 0 ? $cot : 1.0);
    }

    /**
     * @param  Lado  $deuda
     * @param  Lado  $credito
     */
    public static function wherePar(array $deuda, array $credito, string $fechaYmd): string
    {
        $e = static fn (string $v, int $max = 0) => AplicacionCuentacorrienteAnitaLadoSupport::esc($v, $max);

        return " WHERE aplvp_proveedor = '".$e($deuda['proveedor'], 6)."'
            AND aplvp_tipo = '".$e($deuda['tipo'], 3)."'
            AND aplvp_letra = '".$e($deuda['letra'], 1)."'
            AND aplvp_sucursal = '".(int) $deuda['sucursal']."'
            AND aplvp_nro = '".(int) $deuda['numero']."'
            AND aplvp_fecha = '".$e($fechaYmd, 8)."'
            AND aplvp_tipo_cob = '".$e($credito['tipo'], 3)."'
            AND aplvp_letra_cob = '".$e($credito['letra'], 1)."'
            AND aplvp_sucursal_cob = '".(int) $credito['sucursal']."'
            AND aplvp_nro_cob = '".(int) $credito['numero']."' ";
    }

    /**
     * @param  Lado  $deuda
     * @param  Lado  $credito
     */
    public static function whereFila(array $deuda, array $credito, string $fechaYmd, float $monto): string
    {
        return self::wherePar($deuda, $credito, $fechaYmd)
            ." AND aplvp_monto = '".AplicacionCuentacorrienteAnitaLadoSupport::decimal($monto)."' ";
    }
}
