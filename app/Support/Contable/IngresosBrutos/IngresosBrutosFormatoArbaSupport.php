<?php

declare(strict_types=1);

namespace App\Support\Contable\IngresosBrutos;

/**
 * Formato archivo ARBA (Anita p-ingbruto opciones 7 y 8).
 *
 * Importante: Anita escribe con "\n" (LF). No usar CRLF como SICORE/SIAP;
 * ARBA espera el mismo terminador que genera el C.
 */
final class IngresosBrutosFormatoArbaSupport
{
    /** Actividad en nombre de lote: retenciones. */
    public const ACTIVIDAD_RETENCIONES = 6;

    /** Actividad en nombre de lote: percepciones. */
    public const ACTIVIDAD_PERCEPCIONES = 7;

    /** Terminador de registro: LF como fprintf(...\n) en Anita. */
    public const EOL = "\n";

    public static function tolerancia(): float
    {
        return 0.05;
    }

    public static function cuadra(float $a, float $b): bool
    {
        return abs($a - $b) <= self::tolerancia();
    }

    public static function normalizarCuit(string $cuit): string
    {
        $digits = preg_replace('/\D/', '', $cuit) ?? '';

        return substr(str_pad($digits, 11, '0', STR_PAD_LEFT), 0, 11);
    }

    public static function cuitConGuiones(string $cuit): string
    {
        $d = self::normalizarCuit($cuit);
        if (strlen($d) !== 11) {
            return str_pad($d, 13, ' ', STR_PAD_RIGHT);
        }

        return substr($d, 0, 2).'-'.substr($d, 2, 8).'-'.substr($d, 10, 1);
    }

    public static function fechaArba(string $fechaIso): string
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

    public static function fechaIsoDesdeAnita(int $ymd): string
    {
        if ($ymd <= 0) {
            return '';
        }
        $s = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }

    /**
     * Opción 7 — Retenciones ARBA (línea fija + LF).
     * Anita: %020ld%11.11s%05ld%10.10s%05.2lf%016.2lf\n
     * Último importe = base sujeto (ingb_monto), no el monto retenido.
     *
     * @param  array<string, mixed>  $reg
     */
    public static function formatearLineaRetencion(array $reg): string
    {
        $nroRet = (int) ($reg['nro_cert'] ?? 0);
        $cuit = self::normalizarCuit((string) ($reg['nro_documento'] ?? ''));
        $sucursal = (int) ($reg['sucursal'] ?? 1);
        $fecha = self::fechaArba((string) ($reg['fecha_retencion'] ?? ''));
        $alicuota = self::montoPunto((float) ($reg['alicuota'] ?? 0), 5);
        // Misma posición que ingb_monto en Anita (base sujeto).
        $base = self::montoPunto((float) ($reg['base_calculo'] ?? 0), 16);

        return sprintf(
            '%020d%11s%05d%10s%5s%16s',
            $nroRet,
            $cuit,
            $sucursal,
            $fecha,
            $alicuota,
            $base,
        ).self::EOL;
    }

    /**
     * Opción 8 — Percepciones ARBA (línea fija + LF).
     * Anita: %13.13s%10.10s%c%c%04ld%08ld%012.2lf%011.2lfA\n
     *
     * @param  array<string, mixed>  $reg
     */
    public static function formatearLineaPercepcion(array $reg): string
    {
        $cuit = self::cuitConGuiones((string) ($reg['nro_documento'] ?? ''));
        $fecha = self::fechaArba((string) ($reg['fecha_retencion'] ?? $reg['fecha_comp'] ?? ''));
        $tipoDoc = substr((string) ($reg['tipo_documento'] ?? 'F'), 0, 1);
        $letra = substr((string) ($reg['letra'] ?? ' '), 0, 1);
        if ($letra === '') {
            $letra = ' ';
        }
        $sucursal = (int) ($reg['sucursal'] ?? 0);
        $nro = (int) ($reg['nro_comp'] ?? 0);
        $base = self::montoPunto((float) ($reg['base_calculo'] ?? 0), 12);
        $importe = self::montoPunto(abs((float) ($reg['importe'] ?? 0)), 11);

        return sprintf(
            '%13s%10s%1s%1s%04d%08d%12s%11sA',
            $cuit,
            $fecha,
            $tipoDoc,
            $letra,
            $sucursal,
            $nro,
            $base,
            $importe,
        ).self::EOL;
    }

    /**
     * @param  list<array<string, mixed>>  $registros
     */
    public static function generarArchivo(array $registros, string $tipo): string
    {
        $out = '';
        foreach ($registros as $reg) {
            $out .= $tipo === IngresosBrutosListadoFiltros::TIPO_PERCEPCIONES
                ? self::formatearLineaPercepcion($reg)
                : self::formatearLineaRetencion($reg);
        }

        return $out;
    }

    /**
     * Nombre de lote Anita arma_lote_arba:
     * ER-{CUIT}-{YYYYMM}{quincena}-{actividad}-LOTE{n}.txt
     */
    public static function nombreLote(
        string $cuitAgente,
        string $periodoYm,
        int $quincena,
        int $actividad,
        int $lote,
    ): string {
        $cuit = self::normalizarCuit($cuitAgente);

        return sprintf(
            'ER-%11s-%06d%d-%d-LOTE%d.txt',
            $cuit,
            (int) $periodoYm,
            $quincena,
            $actividad,
            max(1, $lote),
        );
    }

    private static function montoPunto(float $valor, int $ancho): string
    {
        $texto = number_format(abs($valor), 2, '.', '');

        return str_pad($texto, $ancho, '0', STR_PAD_LEFT);
    }
}
