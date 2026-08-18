<?php

namespace App\Support\Contable\LibroIvaDigital;

use App\Models\Ventas\Venta;

/**
 * Identificación comprador Factura B (RG 1415 modificada por RG 5700/2025).
 * Umbral compartido con emisión WSFE ({@see config('arca_wsfe.receptor.consumidor_final_umbral_monto')}).
 */
final class LibroIvaDigitalVentasConsumidorFinalSupport
{
    public static function umbralIdentificacion(): float
    {
        return (float) config('arca_wsfe.receptor.consumidor_final_umbral_monto', 10_000_000);
    }

    public static function esConsumidorFinal(Venta $venta): bool
    {
        $condicion = $venta->clientes?->condicionivas ?? $venta->condicionivas;

        return in_array((int) ($condicion->id ?? 0), [3], true)
            || stripos((string) ($condicion->nombre ?? ''), 'consumidor final') !== false;
    }

    public static function numeroDocumentoComprador(Venta $venta): string
    {
        $desdeVenta = preg_replace(
            '/\D+/',
            '',
            (string) ($venta->nroinscripcion ?? ''),
        ) ?? '';

        if ($desdeVenta !== '') {
            return $desdeVenta;
        }

        if (self::esConsumidorFinal($venta)) {
            // CF anónimo en el comprobante: no usar documento del cliente maestro genérico.
            return '';
        }

        return preg_replace(
            '/\D+/',
            '',
            (string) ($venta->clientes?->numerodocumento ?? ''),
        ) ?? '';
    }

    public static function requiereInformarIdentificacion(float $importeTotal): bool
    {
        return abs($importeTotal) >= self::umbralIdentificacion();
    }

    public static function tieneDocumentoIdentificado(Venta $venta): bool
    {
        if (! self::esConsumidorFinal($venta)) {
            return true;
        }

        $documento = self::numeroDocumentoComprador($venta);

        return strlen($documento) >= 7;
    }

    /**
     * Solo CF anónimo bajo el umbral RG 5700 puede ir a venta global diaria agrupada.
     */
    public static function permiteAgrupacionGlobalDiaria(Venta $venta, float $importeTotal): bool
    {
        if (! self::esConsumidorFinal($venta)) {
            return false;
        }

        if (self::requiereInformarIdentificacion($importeTotal)) {
            return false;
        }

        return ! self::tieneDocumentoIdentificado($venta);
    }

    /**
     * @return array{codigo_documento: string, numero_identificacion: string, nombre: string}
     */
    public static function resolverComprador(Venta $venta, float $importeTotal): array
    {
        $esConsumidorFinal = self::esConsumidorFinal($venta);
        $documento = self::numeroDocumentoComprador($venta);
        $nombre = trim((string) ($venta->nombre ?: $venta->clientes?->nombre ?: ''));

        if (! $esConsumidorFinal) {
            return self::compradorIdentificado($venta, $documento, $nombre);
        }

        if (self::requiereInformarIdentificacion($importeTotal) || strlen($documento) >= 7) {
            if (strlen($documento) === 11) {
                return self::compradorIdentificado($venta, $documento, $nombre);
            }

            if (strlen($documento) >= 7) {
                return [
                    'codigo_documento' => str_pad(
                        (string) ($venta->clientes?->tipodocumentos?->codigoexterno ?: '96'),
                        2,
                        '0',
                        STR_PAD_LEFT,
                    ),
                    'numero_identificacion' => $documento,
                    'nombre' => $nombre !== '' ? $nombre : '-CONSUMIDOR FINAL-',
                ];
            }

            return [
                'codigo_documento' => '96',
                'numero_identificacion' => '0',
                'nombre' => $nombre !== '' ? $nombre : '-CONSUMIDOR FINAL-',
            ];
        }

        return [
            'codigo_documento' => '99',
            'numero_identificacion' => '0',
            'nombre' => stripos((string) $venta->nombre, 'GLOBAL') !== false
                ? '-VENTA GLOBAL DIARIA-'
                : '-CONSUMIDOR FINAL-',
        ];
    }

    /**
     * @return array{codigo_documento: string, numero_identificacion: string, nombre: string}
     */
    private static function compradorIdentificado(Venta $venta, string $documento, string $nombre): array
    {
        $codigoDoc = str_pad(
            (string) ($venta->clientes?->tipodocumentos?->codigoexterno ?: (strlen($documento) === 11 ? '80' : '96')),
            2,
            '0',
            STR_PAD_LEFT,
        );

        return [
            'codigo_documento' => $codigoDoc,
            'numero_identificacion' => $documento !== '' ? $documento : '0',
            'nombre' => $nombre,
        ];
    }
}
