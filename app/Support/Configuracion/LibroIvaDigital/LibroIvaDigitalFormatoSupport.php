<?php

namespace App\Support\Configuracion\LibroIvaDigital;

/**
 * Formato de registros fijos Libro IVA Digital (RG 4597 / ARCA).
 * Referencia: libro-iva-digital-diseno-registros.pdf
 */
final class LibroIvaDigitalFormatoSupport
{
    public static function importe15(float $valor): string
    {
        $centavos = (int) round(abs($valor) * 100);
        if ($valor < 0) {
            return '-'.str_pad((string) $centavos, 14, '0', STR_PAD_LEFT);
        }

        return str_pad((string) $centavos, 15, '0', STR_PAD_LEFT);
    }

    public static function tipoCambio10(float $valor): string
    {
        $scaled = (int) round(abs($valor) * 1_000_000);

        return str_pad((string) $scaled, 10, '0', STR_PAD_LEFT);
    }

    public static function numerico(int|string $valor, int $longitud): string
    {
        $digits = preg_replace('/\D+/', '', (string) $valor) ?? '';

        return str_pad(substr($digits, -$longitud), $longitud, '0', STR_PAD_LEFT);
    }

    public static function alfanumerico(string $valor, int $longitud): string
    {
        $ascii = self::aAscii($valor);
        $ascii = strtoupper(substr($ascii, 0, $longitud));

        return str_pad($ascii, $longitud, ' ', STR_PAD_RIGHT);
    }

    public static function codigoOperacion(?string $codigo): string
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '' || $codigo === '0') {
            return ' ';
        }

        return strtoupper(substr($codigo, 0, 1));
    }

    /**
     * @param  array<string, mixed>  $campos
     */
    public static function registroVentasCbte(array $campos): string
    {
        return self::numerico($campos['fecha'], 8)
            .self::numerico($campos['tipo_comprobante'], 3)
            .self::numerico($campos['punto_venta'], 5)
            .self::numerico($campos['numero_comprobante'], 20)
            .self::numerico($campos['numero_hasta'], 20)
            .self::numerico($campos['codigo_documento'], 2)
            .self::numerico($campos['numero_identificacion'], 20)
            .self::alfanumerico((string) $campos['nombre_comprador'], 30)
            .self::importe15((float) $campos['importe_total'])
            .self::importe15((float) ($campos['no_integra_neto'] ?? 0))
            .self::importe15((float) ($campos['percepcion_no_categorizados'] ?? 0))
            .self::importe15((float) ($campos['operaciones_exentas'] ?? 0))
            .self::importe15((float) ($campos['percepciones_nacionales'] ?? 0))
            .self::importe15((float) ($campos['percepciones_iibb'] ?? 0))
            .self::importe15((float) ($campos['percepciones_municipales'] ?? 0))
            .self::importe15((float) ($campos['impuestos_internos'] ?? 0))
            .self::alfanumerico((string) ($campos['codigo_moneda'] ?? 'PES'), 3)
            .self::tipoCambio10((float) ($campos['tipo_cambio'] ?? 1))
            .self::numerico($campos['cantidad_alicuotas'] ?? 0, 1)
            .self::codigoOperacion($campos['codigo_operacion'] ?? null)
            .self::importe15((float) ($campos['otros_tributos'] ?? 0))
            .self::numerico($campos['fecha_vencimiento'] ?? '00000000', 8);
    }

    /**
     * @param  array<string, mixed>  $campos
     */
    public static function registroVentasAlicuota(array $campos): string
    {
        return self::numerico($campos['tipo_comprobante'], 3)
            .self::numerico($campos['punto_venta'], 5)
            .self::numerico($campos['numero_comprobante'], 20)
            .self::importe15((float) $campos['neto_gravado'])
            .self::numerico($campos['alicuota_iva'], 4)
            .self::importe15((float) $campos['impuesto_liquidado']);
    }

    /**
     * @param  array<string, mixed>  $campos
     */
    public static function registroComprasCbte(array $campos): string
    {
        return self::numerico($campos['fecha'], 8)
            .self::numerico($campos['tipo_comprobante'], 3)
            .self::numerico($campos['punto_venta'], 5)
            .self::numerico($campos['numero_comprobante'], 20)
            .self::alfanumerico((string) ($campos['despacho_importacion'] ?? ''), 16)
            .self::numerico($campos['codigo_documento'], 2)
            .self::numerico($campos['numero_identificacion'], 20)
            .self::alfanumerico((string) $campos['nombre_vendedor'], 30)
            .self::importe15((float) $campos['importe_total'])
            .self::importe15((float) ($campos['no_integra_neto'] ?? 0))
            .self::importe15((float) ($campos['operaciones_exentas'] ?? 0))
            .self::importe15((float) ($campos['percepciones_iva'] ?? 0))
            .self::importe15((float) ($campos['percepciones_nacionales'] ?? 0))
            .self::importe15((float) ($campos['percepciones_iibb'] ?? 0))
            .self::importe15((float) ($campos['percepciones_municipales'] ?? 0))
            .self::importe15((float) ($campos['impuestos_internos'] ?? 0))
            .self::alfanumerico((string) ($campos['codigo_moneda'] ?? 'PES'), 3)
            .self::tipoCambio10((float) ($campos['tipo_cambio'] ?? 1))
            .self::numerico($campos['cantidad_alicuotas'] ?? 0, 1)
            .self::codigoOperacion($campos['codigo_operacion'] ?? null)
            .self::importe15((float) ($campos['credito_fiscal_computable'] ?? 0))
            .self::importe15((float) ($campos['otros_tributos'] ?? 0))
            .self::numerico($campos['cuit_emisor_corredor'] ?? '0', 11)
            .self::alfanumerico((string) ($campos['denominacion_emisor_corredor'] ?? ''), 30)
            .self::importe15((float) ($campos['iva_comision'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $campos
     */
    public static function registroComprasAlicuota(array $campos): string
    {
        return self::numerico($campos['tipo_comprobante'], 3)
            .self::numerico($campos['punto_venta'], 5)
            .self::numerico($campos['numero_comprobante'], 20)
            .self::numerico($campos['codigo_documento'], 2)
            .self::numerico($campos['numero_identificacion'], 20)
            .self::importe15((float) $campos['neto_gravado'])
            .self::numerico($campos['alicuota_iva'], 4)
            .self::importe15((float) $campos['impuesto_liquidado']);
    }

    /** Registro anulado ventas/compras — 44 posiciones. */
    public static function registroComprobanteAnulado(array $campos): string
    {
        return self::numerico($campos['fecha_comprobante'], 8)
            .self::numerico($campos['tipo_comprobante'], 3)
            .self::numerico($campos['punto_venta'], 5)
            .self::numerico($campos['numero_comprobante'], 20)
            .self::numerico($campos['fecha_anulacion'], 8);
    }

    /** Importación bienes — alícuotas — 50 posiciones. */
    public static function registroImportacionBienAlicuota(array $campos): string
    {
        return self::alfanumerico((string) $campos['despacho'], 16)
            .self::importe15((float) $campos['neto_gravado'])
            .self::numerico($campos['alicuota_iva'], 4)
            .self::importe15((float) $campos['impuesto_liquidado']);
    }

    /** Importación servicios — crédito fiscal — 211 posiciones. */
    public static function registroImportacionServicio(array $campos): string
    {
        return self::numerico($campos['tipo_comprobante'], 1)
            .self::alfanumerico((string) ($campos['descripcion'] ?? ''), 20)
            .self::alfanumerico((string) $campos['identificacion_comprobante'], 20)
            .self::numerico($campos['fecha_operacion'], 8)
            .self::importe15((float) $campos['monto_moneda_original'])
            .self::alfanumerico((string) ($campos['codigo_moneda'] ?? 'PES'), 3)
            .self::tipoCambio10((float) ($campos['tipo_cambio'] ?? 1))
            .self::numerico($campos['cuit_prestador'], 11)
            .self::alfanumerico((string) ($campos['nif_prestador'] ?? ''), 20)
            .self::alfanumerico((string) $campos['nombre_prestador'], 30)
            .self::numerico($campos['alicuota_iva'], 4)
            .self::numerico($campos['fecha_ingreso_impuesto'], 8)
            .self::importe15((float) $campos['monto_impuesto_ingresado'])
            .self::importe15((float) $campos['impuesto_computable'])
            .self::alfanumerico((string) ($campos['identificacion_pago'] ?? ''), 20)
            .self::numerico($campos['cuit_entidad_pago'] ?? '0', 11);
    }

    public static function lineasDesdeContenido(string $contenido): string
    {
        $contenido = rtrim($contenido, "\r\n");

        return $contenido === '' ? '' : $contenido."\r\n";
    }

    public static function aAscii(string $texto): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $texto);
        if ($converted === false) {
            return preg_replace('/[^\x20-\x7E]/', ' ', $texto) ?? $texto;
        }

        return $converted;
    }
}
