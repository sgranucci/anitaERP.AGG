<?php

namespace App\Support\Ventas;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * RG 5782 / WSFEv1: CbteFchHsGen (fecha-hora de generación por contingencia CAEA) es obligatorio.
 * Equivalente MTXCA: fechaHoraGen.
 *
 * Formato WSFE: yyyymmddhhmiss (14 dígitos).
 */
final class ArcaCaeaCbteFchHsGenSupport
{
    /**
     * Resuelve 14 dígitos YmdHis desde el payload de informe/emisión CAEA.
     *
     * Prioridad: cbte_fch_hs_gen → fecha_hora_gen → fechacomprobante + 12:00:00.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws InvalidArgumentException
     */
    public static function resolverDigits(array $datos): string
    {
        $raw = trim((string) ($datos['cbte_fch_hs_gen'] ?? $datos['fecha_hora_gen'] ?? ''));
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (strlen($digits) >= 14) {
            return self::assertYmdHis(substr($digits, 0, 14));
        }

        if (strlen($digits) === 8) {
            return self::assertYmdHis($digits.'120000');
        }

        $fch = preg_replace('/\D+/', '', (string) ($datos['fechacomprobante'] ?? '')) ?? '';
        if (strlen($fch) === 8) {
            return self::assertYmdHis($fch.'120000');
        }

        throw new InvalidArgumentException(
            'ARCA CAEA (RG 5782): CbteFchHsGen / fechaHoraGen obligatorio. '
            .'Informe cbte_fch_hs_gen (yyyymmddhhmiss) o fechacomprobante válida.'
        );
    }

    /**
     * Valor para FECAEADetRequest.CbteFchHsGen.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function paraWsfe(array $datos): string
    {
        return self::resolverDigits($datos);
    }

    /**
     * Valor MTXCA dateTime AAAA-MM-DDTHH:MM:SS.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function paraMtxca(array $datos): string
    {
        $d = self::resolverDigits($datos);

        return substr($d, 0, 4).'-'.substr($d, 4, 2).'-'.substr($d, 6, 2)
            .'T'.substr($d, 8, 2).':'.substr($d, 10, 2).':'.substr($d, 12, 2);
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function assertYmdHis(string $digits): string
    {
        if (! preg_match('/^\d{14}$/', $digits)) {
            throw new InvalidArgumentException(
                'ARCA CAEA (RG 5782): CbteFchHsGen inválido (se esperan 14 dígitos yyyymmddhhmiss).'
            );
        }

        $dt = DateTimeImmutable::createFromFormat('YmdHis', $digits);
        $errs = DateTimeImmutable::getLastErrors();
        $tieneErrores = is_array($errs)
            && ((($errs['warning_count'] ?? 0) > 0) || (($errs['error_count'] ?? 0) > 0));
        if ($dt === false || $tieneErrores || $dt->format('YmdHis') !== $digits) {
            throw new InvalidArgumentException(
                'ARCA CAEA (RG 5782): CbteFchHsGen no es una fecha-hora válida ('.$digits.').'
            );
        }

        return $digits;
    }
}
