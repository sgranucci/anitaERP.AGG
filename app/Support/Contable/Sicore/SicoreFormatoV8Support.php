<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

final class SicoreFormatoV8Support
{
    /** Factura (retenciones IVA sobre FC). */
    public const COD_COMP_FACTURA = 1;

    /** Nota de crédito — proveedores (impuestos 217 / 767). */
    public const COD_COMP_NOTA_CREDITO = 3;

    /** Nota de débito. */
    public const COD_COMP_NOTA_DEBITO = 4;

    /** Orden de pago / retención practicada (proveedores). */
    public const COD_COMP_ORDEN_PAGO = 6;

    /** Liquidación / retención de sueldos (impuesto 787). */
    public const COD_COMP_LIQUIDACION = 7;

    /**
     * Devolución / crédito de retención de 4ta categoría (impuesto 787).
     * No usar en proveedores: ahí va {@see COD_COMP_NOTA_CREDITO}.
     * Va al inicio del registro (posiciones 1–2); los importes se emiten en positivo.
     */
    public const COD_COMP_DEVOLUCION = 8;

    /**
     * Genera una línea del archivo SICORE versión 8 (RG 738/99).
     *
     * @param  array<string, mixed>  $reg
     */
    public static function formatearLinea(array $reg): string
    {
        $fechaComp = self::fechaLegacy((string) ($reg['fecha_comp'] ?? ''));
        $fechaRet = self::fechaLegacy((string) ($reg['fecha_retencion'] ?? ''));
        $fechaBoletin = self::fechaLegacy((string) ($reg['fecha_boletin'] ?? ''));
        if (trim($fechaBoletin) === '') {
            $fechaBoletin = '00/00/0000';
        }

        $nroDocumento = self::normalizarDocumento((string) ($reg['nro_documento'] ?? ''));

        // Importes con punto decimal, independiente del locale del sistema (ARCA/ANITA
        // usan punto; sprintf('%f') respetaría LC_NUMERIC y saldría con coma).
        $importeComp = self::montoDecimal((float) ($reg['importe_comp'] ?? 0), 16);
        $baseCalculo = self::montoDecimal((float) ($reg['base_calculo'] ?? 0), 14);
        $importeRet = self::montoDecimal((float) ($reg['importe'] ?? 0), 14);
        $porcExcl = self::montoDecimal((float) ($reg['porc_excl'] ?? 0), 6);

        return sprintf(
            '%2d%10s%16d%16s%04d%03d%1d%14s%10s%02d %14s%6s%10s%2d%20s%14d',
            (int) ($reg['cod_comp'] ?? self::COD_COMP_ORDEN_PAGO),
            $fechaComp,
            (int) ($reg['nro_comp'] ?? 0),
            $importeComp,
            (int) ($reg['cod_impuesto'] ?? 0),
            (int) ($reg['cod_regimen'] ?? 0),
            (int) ($reg['cod_operacion'] ?? 1),
            $baseCalculo,
            $fechaRet,
            (int) ($reg['cod_condicion'] ?? 1),
            $importeRet,
            $porcExcl,
            $fechaBoletin,
            (int) ($reg['cod_documento'] ?? 80),
            $nroDocumento,
            (int) ($reg['nro_cert'] ?? 0),
        )."\r\n"; // SIAP/SICORE exige terminador CRLF (Windows); con solo LF rebota por longitud.
    }

    /**
     * @param  list<array<string, mixed>>  $registros
     */
    public static function generarArchivo(array $registros): string
    {
        $out = '';
        foreach ($registros as $reg) {
            $out .= self::formatearLinea($reg);
        }

        return $out;
    }

    /**
     * Formatea un importe con dos decimales y punto decimal (sin separador de miles),
     * alineado a la derecha en el ancho fijo del campo SICORE. No depende del locale.
     */
    public static function montoDecimal(float $valor, int $ancho): string
    {
        $texto = number_format(abs($valor), 2, '.', '');

        return str_pad($texto, $ancho, ' ', STR_PAD_LEFT);
    }

    public static function fechaLegacy(string $fechaIso): string
    {
        if ($fechaIso === '') {
            return str_repeat(' ', 10);
        }

        $ts = strtotime($fechaIso);
        if ($ts === false) {
            return str_repeat(' ', 10);
        }

        return date('d/m/Y', $ts);
    }

    public static function fechaLegacyDesdeAnita(int $fechaAnita): string
    {
        if ($fechaAnita <= 0) {
            return '';
        }

        $s = str_pad((string) $fechaAnita, 8, '0', STR_PAD_LEFT);

        return substr($s, 6, 2).'/'.substr($s, 4, 2).'/'.substr($s, 0, 4);
    }

    public static function normalizarCuit(string $cuit): string
    {
        $digits = preg_replace('/\D/', '', $cuit) ?? '';

        return substr(str_pad($digits, 11, '0', STR_PAD_LEFT), 0, 11);
    }

    public static function normalizarDocumento(string $doc): string
    {
        $doc = trim($doc);
        if (preg_match('/^(DNI|CI|LC|LE)/i', $doc)) {
            return str_pad(substr($doc, 0, 20), 20, ' ', STR_PAD_RIGHT);
        }

        $cuit = self::normalizarCuit($doc);

        return str_pad($cuit, 20, ' ', STR_PAD_RIGHT);
    }

    public static function codigoComprobanteDesdeTipo(string $tipoComp): int
    {
        $tipo = str_pad(trim($tipoComp), 2, '0', STR_PAD_LEFT);

        return match ($tipo) {
            '01' => self::COD_COMP_FACTURA,
            '02' => self::COD_COMP_NOTA_DEBITO,
            '03' => self::COD_COMP_NOTA_CREDITO,
            default => self::COD_COMP_ORDEN_PAGO,
        };
    }

    /**
     * Crédito / anulación de retención en el .dat (importes en positivo).
     * Proveedores: nota de crédito (3). Sueldos 4ta cat.: devolución (8).
     */
    public static function esDevolucion(array $reg): bool
    {
        $cod = (int) ($reg['cod_comp'] ?? 0);

        return $cod === self::COD_COMP_NOTA_CREDITO || $cod === self::COD_COMP_DEVOLUCION;
    }

    public static function tolerancia(): float
    {
        return 0.05;
    }

    public static function cuadra(float $a, float $b): bool
    {
        return abs($a - $b) <= self::tolerancia();
    }
}
