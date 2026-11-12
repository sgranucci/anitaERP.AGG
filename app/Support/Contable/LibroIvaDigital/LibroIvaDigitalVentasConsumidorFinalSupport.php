<?php

namespace App\Support\Contable\LibroIvaDigital;

use App\Models\Ventas\Venta;
use App\Support\Configuracion\ParametroSistemaSupport;

/**
 * Identificación comprador Factura B (RG 1415 modificada por RG 5700/2025).
 * Umbral compartido con emisión WSFE ({@see ParametroSistemaSupport::topeConsumidorFinal()}).
 *
 * Factura/ND/NC A y M: ARCA exige CUIT (80). El import Pxsol deja el CUIT en leyenda
 * y el cliente genérico CF; no informar 99 en esos tipos.
 */
final class LibroIvaDigitalVentasConsumidorFinalSupport
{
    /** Letras que no admiten documento 99 / CF anónimo en el Libro IVA Digital. */
    private const LETRAS_EXIGEN_CUIT = ['A', 'M'];

    public static function umbralIdentificacion(): float
    {
        return ParametroSistemaSupport::topeConsumidorFinal();
    }

    public static function esConsumidorFinal(Venta $venta): bool
    {
        $condicion = $venta->clientes?->condicionivas ?? $venta->condicionivas;

        return in_array((int) ($condicion->id ?? 0), [3], true)
            || stripos((string) ($condicion->nombre ?? ''), 'consumidor final') !== false;
    }

    public static function letraExigeCuit(string $letra): bool
    {
        return in_array(strtoupper(trim($letra)), self::LETRAS_EXIGEN_CUIT, true);
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

        $desdeLeyenda = LibroIvaDigitalIdentificacionSupport::cuitDesdeLeyenda(
            (string) ($venta->leyenda ?? ''),
        );
        if ($desdeLeyenda !== '') {
            return $desdeLeyenda;
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
    public static function resolverComprador(Venta $venta, float $importeTotal, ?string $letra = null): array
    {
        $letraNorm = strtoupper(trim((string) $letra));
        $nombre = trim((string) ($venta->nombre ?: $venta->clientes?->nombre ?: ''));

        if (self::letraExigeCuit($letraNorm)) {
            return self::compradorConCuitObligatorio($venta, $nombre);
        }

        $esConsumidorFinal = self::esConsumidorFinal($venta);
        $documento = self::numeroDocumentoComprador($venta);

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

            // Sin DNI no se puede informar 96/0: ARCA rechaza documento 0 si el código no es 99.
            return [
                'codigo_documento' => '99',
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
    private static function compradorConCuitObligatorio(Venta $venta, string $nombre): array
    {
        $cuit = self::cuitComprador($venta);
        $nombreMostrar = $nombre !== '' && stripos($nombre, 'CONSUMIDOR FINAL') === false
            ? $nombre
            : trim((string) ($venta->nombre ?? ''));
        if ($nombreMostrar === '' || stripos($nombreMostrar, 'CONSUMIDOR FINAL') !== false) {
            $nombreMostrar = 'SIN DENOMINACION';
        }

        $id = LibroIvaDigitalIdentificacionSupport::asegurar(
            LibroIvaDigitalIdentificacionSupport::CODIGO_CUIT,
            $cuit,
        );

        return [
            'codigo_documento' => $id['codigo_documento'],
            'numero_identificacion' => $id['numero_identificacion'],
            'nombre' => $nombreMostrar,
        ];
    }

    public static function cuitComprador(Venta $venta): string
    {
        $candidatos = [
            (string) ($venta->nroinscripcion ?? ''),
            LibroIvaDigitalIdentificacionSupport::cuitDesdeLeyenda((string) ($venta->leyenda ?? '')),
            (string) ($venta->clientes?->numerodocumento ?? ''),
        ];

        foreach ($candidatos as $raw) {
            $digits = LibroIvaDigitalIdentificacionSupport::cuitOnceDigitos(
                LibroIvaDigitalComprasCuitSupport::soloDigitos($raw),
            );
            if (strlen($digits) === 11 && LibroIvaDigitalComprasCuitSupport::esCuitValido($digits)) {
                return $digits;
            }
        }

        return '';
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

        $id = LibroIvaDigitalIdentificacionSupport::asegurar($codigoDoc, $documento);

        return [
            'codigo_documento' => $id['codigo_documento'],
            'numero_identificacion' => $id['numero_identificacion'],
            'nombre' => $nombre,
        ];
    }
}
