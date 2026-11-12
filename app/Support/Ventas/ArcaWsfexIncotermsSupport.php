<?php

namespace App\Support\Ventas;

/**
 * Incoterms admitidos por WSFEX (FEXAuthorize error [1640]).
 *
 * Obligatorios para Cbte_Tipo 19 + Tipo_expo 1 (Productos).
 * Catálogo ARCA: CFR, CIF, CIP, CPT, DAP, DDP, DPU, EXW, FAS, FCA, FOB.
 * Códigos viejos (DDU, DAF, DES, etc.) ya no sirven.
 */
final class ArcaWsfexIncotermsSupport
{
    /** @var list<string> */
    public const CODIGOS_AFIP = [
        'CFR',
        'CIF',
        'CIP',
        'CPT',
        'DAP',
        'DDP',
        'DPU',
        'EXW',
        'FAS',
        'FCA',
        'FOB',
    ];

    public static function normalizar(?string $abreviatura): string
    {
        return strtoupper(trim((string) $abreviatura));
    }

    public static function esValido(?string $abreviatura): bool
    {
        $codigo = self::normalizar($abreviatura);

        return $codigo !== '' && in_array($codigo, self::CODIGOS_AFIP, true);
    }

    /**
     * Mensaje operativo o null si el código es aceptable para el comprobante.
     */
    public static function mensajeSiInvalidoParaAuthorize(
        ?string $abreviatura,
        int $cbteTipo,
        int $tipoExpo = 1,
    ): ?string {
        if ($cbteTipo !== 19 || $tipoExpo !== 1) {
            return null;
        }

        $codigo = self::normalizar($abreviatura);
        if (self::esValido($codigo)) {
            return null;
        }

        $lista = implode(', ', self::CODIGOS_AFIP);
        if ($codigo === '') {
            return 'Factura de exportación (tipo 19, productos): indique Incoterms'
                .' (valores AFIP: '.$lista.').';
        }

        return 'Incoterms «'.$codigo.'» no es válido para AFIP WSFEX [1640].'
            .' Use uno de: '.$lista.'.';
    }

    /**
     * Validación temprana en facturación (antes de numerar / pedir CAE).
     */
    public static function mensajeSiFaltaEnExportacion(
        bool $esPuntoventaExportacion,
        ?string $abreviatura,
        bool $esFacturaExportacion = true,
    ): ?string {
        if (! $esPuntoventaExportacion || ! $esFacturaExportacion) {
            return null;
        }

        return self::mensajeSiInvalidoParaAuthorize($abreviatura, 19, 1);
    }
}
