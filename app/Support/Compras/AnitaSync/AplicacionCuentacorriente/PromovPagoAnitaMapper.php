<?php

namespace App\Support\Compras\AnitaSync\AplicacionCuentacorriente;

use App\Support\Compras\AnitaSync\ComprobanteProveedor\PromovCuotaAnitaMapper;

/**
 * Fila promov de OP / OPA (crédito). Misma estructura que la cuota de factura.
 *
 * @phpstan-import-type Lado from AplicacionCuentacorrienteAnitaLadoSupport
 */
final class PromovPagoAnitaMapper
{
    public static function camposInsert(): string
    {
        return PromovCuotaAnitaMapper::camposInsert();
    }

    /**
     * @param  Lado  $lado
     */
    public static function valoresInsert(array $lado, float $monto, string $fechaYmd): string
    {
        $e = static fn (string $v, int $max = 0) => AplicacionCuentacorrienteAnitaLadoSupport::esc($v, $max);
        $monto = abs($monto);
        $fecha = $e($fechaYmd, 8);
        if ($fecha === '') {
            $fecha = '0';
        }

        return "
            '".$e($lado['proveedor'], 6)."',
            '".$e($lado['tipo'], 3)."',
            '".$e($lado['letra'], 1)."',
            '".(int) $lado['sucursal']."',
            '".(int) $lado['numero']."',
            '   ',
            ' ',
            '0',
            '0',
            '".$fecha."',
            '".$fecha."',
            '".AplicacionCuentacorrienteAnitaLadoSupport::decimal($monto)."',
            '".$e((string) $lado['cod_mon'], 3)."',
            '".AplicacionCuentacorrienteAnitaLadoSupport::decimal((float) $lado['cotizacion'])."',
            '".(int) ($lado['nro_cuota'] ?: 1)."',
            '0',
            '0',
            '".(int) ($lado['nro_interno'] ?? 0)."',
            '".(int) ($lado['empresa'] ?? 0)."',
            '0',
            '',
            ''
        ";
    }
}
