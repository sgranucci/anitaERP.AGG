<?php

namespace App\Support\Contable\LibroIvaDigital;

/**
 * Nombres oficiales de archivos Libro IVA Digital (RG 4597 / ARCA).
 * Referencia: libro-iva-digital-diseno-registros.pdf
 */
final class LibroIvaDigitalArchivosSupport
{
    public const VENTAS_CBTE = 'LIBRO_IVA_DIGITAL_VENTAS_CBTE.txt';

    public const VENTAS_ALICUOTAS = 'LIBRO_IVA_DIGITAL_VENTAS_ALICUOTAS.txt';

    public const VENTAS_ANULADOS = 'LIBRO_IVA_DIGITAL_CBTES_VENTAS_ANULADOS.txt';

    public const COMPRAS_CBTE = 'LIBRO_IVA_DIGITAL_COMPRAS_CBTE.txt';

    public const COMPRAS_ALICUOTAS = 'LIBRO_IVA_DIGITAL_COMPRAS_ALICUOTAS.txt';

    public const COMPRAS_ANULADOS = 'LIBRO_IVA_DIGITAL_COMPRAS_ANULADOS.txt';

    public const IMPORTACION_BIENES_ALICUOTA = 'LIBRO_IVA_DIGITAL_IMPORTACION_BIENES_ALICUOTA.txt';

    public const IMPORTACION_SERVICIOS_CREDITO_FISCAL = 'LIBRO_IVA_DIGITAL_IMPORTACION_SERVICIOS_CREDITO_FISCAL.txt';

    public const IVA_SIMPLE_DEBITO_FISCAL = 'IVA_SIMPLE_DEBITO_FISCAL.csv';

    public const IVA_SIMPLE_CREDITO_FISCAL = 'IVA_SIMPLE_CREDITO_FISCAL.csv';

    public const IVA_SIMPLE_RESTITUCION_DEBITO_FISCAL = 'IVA_SIMPLE_RESTITUCION_DEBITO_FISCAL.csv';

    public const IVA_SIMPLE_RESTITUCION_CREDITO_FISCAL = 'IVA_SIMPLE_RESTITUCION_CREDITO_FISCAL.csv';

    /**
     * @return array<string, string>
     */
    public static function archivosLibroIvaDigital(array $resultado): array
    {
        return [
            self::VENTAS_CBTE => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['ventas']['ventas_cbte'] ?? ''),
            ),
            self::VENTAS_ALICUOTAS => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['ventas']['ventas_alicuotas'] ?? ''),
            ),
            self::VENTAS_ANULADOS => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['anulados']['ventas'] ?? ''),
            ),
            self::COMPRAS_CBTE => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['compras']['compras_cbte'] ?? ''),
            ),
            self::COMPRAS_ALICUOTAS => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['compras']['compras_alicuotas'] ?? ''),
            ),
            self::COMPRAS_ANULADOS => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['anulados']['compras'] ?? ''),
            ),
            self::IMPORTACION_BIENES_ALICUOTA => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['importaciones']['importacion_bienes_alicuotas'] ?? ''),
            ),
            self::IMPORTACION_SERVICIOS_CREDITO_FISCAL => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['importaciones']['importacion_servicios'] ?? ''),
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function archivosIvaSimple(array $resultado): array
    {
        return [
            self::IVA_SIMPLE_DEBITO_FISCAL => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['iva_simple']['debito_fiscal'] ?? ''),
            ),
            self::IVA_SIMPLE_CREDITO_FISCAL => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['iva_simple']['credito_fiscal'] ?? ''),
            ),
            self::IVA_SIMPLE_RESTITUCION_DEBITO_FISCAL => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['iva_simple']['restitucion_debito'] ?? ''),
            ),
            self::IVA_SIMPLE_RESTITUCION_CREDITO_FISCAL => LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
                (string) ($resultado['iva_simple']['restitucion_credito'] ?? ''),
            ),
        ];
    }
}
