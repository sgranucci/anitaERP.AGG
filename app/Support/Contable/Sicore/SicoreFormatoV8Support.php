<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

final class SicoreFormatoV8Support
{
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

        return sprintf(
            '%2d%10s%16d%16.2f%04d%03d%1d%14.2f%10s%02d %14.2f%6.2f%10s%2d%20s%14d',
            (int) ($reg['cod_comp'] ?? 6),
            $fechaComp,
            (int) ($reg['nro_comp'] ?? 0),
            abs((float) ($reg['importe_comp'] ?? 0)),
            (int) ($reg['cod_impuesto'] ?? 0),
            (int) ($reg['cod_regimen'] ?? 0),
            (int) ($reg['cod_operacion'] ?? 1),
            abs((float) ($reg['base_calculo'] ?? 0)),
            $fechaRet,
            (int) ($reg['cod_condicion'] ?? 1),
            abs((float) ($reg['importe'] ?? 0)),
            (float) ($reg['porc_excl'] ?? 0),
            $fechaBoletin,
            (int) ($reg['cod_documento'] ?? 80),
            $nroDocumento,
            (int) ($reg['nro_cert'] ?? 0),
        )."\n";
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
            '01' => 1,
            '02' => 4,
            '03' => 3,
            default => 6,
        };
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
