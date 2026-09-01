<?php

namespace App\Support\Contable\LibroIvaDigital;

/**
 * Campo 6/7 del CBTE (RG 4597): el número puede ser 0 solo si el código es 99
 * (Sin identificar / Venta global diaria). ARCA rechaza "Nro. de documento no puede ser 0"
 * cuando el tipo es 80 (CUIT), 96 (DNI), etc.
 */
final class LibroIvaDigitalIdentificacionSupport
{
    public const CODIGO_SIN_IDENTIFICAR = '99';

    public static function numeroEsCero(string $numero): bool
    {
        $digits = preg_replace('/\D+/', '', $numero) ?? '';

        return $digits === '' || ltrim($digits, '0') === '';
    }

    /**
     * @return array{codigo_documento: string, numero_identificacion: string}
     */
    public static function asegurar(string $codigo, string $numero): array
    {
        $codigo = str_pad(substr(preg_replace('/\D+/', '', $codigo) ?? '', 0, 2), 2, '0', STR_PAD_LEFT);
        if ($codigo === '00') {
            $codigo = self::CODIGO_SIN_IDENTIFICAR;
        }

        $digits = preg_replace('/\D+/', '', $numero) ?? '';
        if ($codigo === '80') {
            $digits = self::cuitOnceDigitos($digits);
        }

        if (self::numeroEsCero($digits) && $codigo !== self::CODIGO_SIN_IDENTIFICAR) {
            return [
                'codigo_documento' => self::CODIGO_SIN_IDENTIFICAR,
                'numero_identificacion' => '0',
            ];
        }

        return [
            'codigo_documento' => $codigo,
            'numero_identificacion' => self::numeroEsCero($digits) ? '0' : $digits,
        ];
    }

    /**
     * @param  array<string, mixed>  $cabecera
     * @return array<string, mixed>
     */
    public static function aplicarACabecera(array $cabecera): array
    {
        $id = self::asegurar(
            (string) ($cabecera['codigo_documento'] ?? self::CODIGO_SIN_IDENTIFICAR),
            (string) ($cabecera['numero_identificacion'] ?? '0'),
        );
        $cabecera['codigo_documento'] = $id['codigo_documento'];
        $cabecera['numero_identificacion'] = $id['numero_identificacion'];

        return $cabecera;
    }

    /**
     * Tipo 80 (CUIT): ARCA rechaza más de 11 dígitos.
     */
    public static function cuitOnceDigitos(string $digits): string
    {
        if (strlen($digits) <= 11) {
            return $digits;
        }

        $ultimo = substr($digits, -11);
        if (LibroIvaDigitalComprasCuitSupport::esCuitValido($ultimo)) {
            return $ultimo;
        }
        $primero = substr($digits, 0, 11);
        if (LibroIvaDigitalComprasCuitSupport::esCuitValido($primero)) {
            return $primero;
        }

        return $ultimo;
    }
}
