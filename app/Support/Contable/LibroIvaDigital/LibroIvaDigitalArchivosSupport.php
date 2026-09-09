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

    /** Informe interno (no se importa en ARCA): compras omitidas del TXT. */
    public const COMPRAS_OMITIDOS = 'INFORME_COMPRAS_OMITIDOS.csv';

    /**
     * @return array<string, string>
     */
    public static function archivosLibroIvaDigital(array $resultado): array
    {
        $archivos = [
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

        $omitidosCsv = self::csvComprasOmitidos($resultado['compras']['omitidos'] ?? []);
        if ($omitidosCsv !== '') {
            $archivos[self::COMPRAS_OMITIDOS] = $omitidosCsv;
        }

        return $archivos;
    }

    /**
     * @param  list<array<string, mixed>>  $omitidos
     */
    public static function csvComprasOmitidos(array $omitidos): string
    {
        if ($omitidos === []) {
            return '';
        }

        $lineas = [
            'Motivo;Origen;Fecha;Tipo AFIP;PV;Número;Letra;Tipo Anita;Proveedor;CUIT;Vendedor;Importe;Detalle',
        ];
        foreach ($omitidos as $fila) {
            $fecha = (string) ($fila['fecha'] ?? '');
            if (preg_match('/^\d{8}$/', $fecha)) {
                $fecha = substr($fecha, 6, 2).'/'.substr($fecha, 4, 2).'/'.substr($fecha, 0, 4);
            }
            $importe = number_format((float) ($fila['importe_total'] ?? 0), 2, ',', '');
            $lineas[] = implode(';', [
                self::csvCampo((string) ($fila['motivo_texto'] ?? $fila['motivo'] ?? '')),
                self::csvCampo((string) ($fila['origen'] ?? '')),
                self::csvCampo($fecha),
                self::csvCampo((string) ($fila['tipo_comprobante'] ?? '')),
                self::csvCampo((string) ($fila['punto_venta'] ?? '')),
                self::csvCampo((string) ($fila['numero_comprobante'] ?? '')),
                self::csvCampo((string) ($fila['letra'] ?? '')),
                self::csvCampo((string) ($fila['tipo_abrev'] ?? '')),
                self::csvCampo((string) ($fila['proveedor_codigo'] ?? '')),
                self::csvCampo((string) ($fila['numero_identificacion'] ?? '')),
                self::csvCampo((string) ($fila['nombre_vendedor'] ?? '')),
                self::csvCampo($importe),
                self::csvCampo((string) ($fila['motivo'] ?? '')),
            ]);
        }

        return LibroIvaDigitalFormatoSupport::lineasDesdeContenido(
            LibroIvaDigitalFormatoSupport::aAscii(implode("\r\n", $lineas)),
        );
    }

    private static function csvCampo(string $valor): string
    {
        $valor = str_replace(["\r", "\n", ';'], [' ', ' ', ','], $valor);

        return $valor;
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
