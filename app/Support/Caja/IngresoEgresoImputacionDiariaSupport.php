<?php

namespace App\Support\Caja;

/**
 * Control I/E: tesorería ERP (caja + cheques) ↔ tesmov Anita;
 * asiento ERP ↔ ctamov Anita. ING/EGR/TRA también exigen caja ↔ asiento.
 */
final class IngresoEgresoImputacionDiariaSupport
{
    public const TIPOS_IE = ['ING', 'EGR', 'TRA'];

    public const TIPOS_OPP_IE = ['OPP', 'OPA'];

    public const TOLERANCIA = 0.05;

    public static function tipoDesdeAbreviatura(?string $abreviatura): string
    {
        return strtoupper(substr(trim((string) $abreviatura), 0, 3));
    }

    public static function esTipoIePuro(string $tipo): bool
    {
        return in_array($tipo, self::TIPOS_IE, true);
    }

    public static function esTipoOppIe(string $tipo): bool
    {
        return in_array($tipo, self::TIPOS_OPP_IE, true);
    }

    public static function esTipoControl(string $tipo): bool
    {
        return self::esTipoIePuro($tipo) || self::esTipoOppIe($tipo);
    }

    public static function esTransferencia(string $tipo): bool
    {
        return $tipo === IngresoEgresoTransferenciaSupport::ABREV_TRA;
    }

    /**
     * TRA se graba en tesmov como TED/TEH (a-tesmov.c). auxpag.axp_tipo_ap es
     * el tctes de la cuenta (IBI/GPB), no el tipo tesmov.
     * axp_sucursal 0 = TED (debe), 1 = TEH (haber).
     *
     * @return list<string>
     */
    public static function tiposTesmovTraDesdeSucursalAxp(int $sucursalAxp): array
    {
        if ($sucursalAxp === 0) {
            return [IngresoEgresoAnitaTesmovSupport::TIPO_TESMOV_DEBE];
        }
        if ($sucursalAxp === 1) {
            return [IngresoEgresoAnitaTesmovSupport::TIPO_TESMOV_HABER];
        }

        return [
            IngresoEgresoAnitaTesmovSupport::TIPO_TESMOV_DEBE,
            IngresoEgresoAnitaTesmovSupport::TIPO_TESMOV_HABER,
        ];
    }

    /**
     * Elige el tesmov de una pierna TRA.
     * La cuenta numérica (Macro 127 → 00000127) coincide con auxpag.
     * Una cuenta alfanumérica (GMEP) queda en tesmov como 0000GMEP y en auxpag
     * como 00000000, porque la imputación descarta las letras. Si no hay
     * coincidencia exacta y auxpag no tiene dígitos, se toma esa pierna.
     *
     * @param  list<object|array<string, mixed>>  $filas
     * @return list<object|array<string, mixed>>
     */
    public static function elegirFilasTesmovPierna(array $filas, string $cuentaAuxpag): array
    {
        $cuenta = self::normalizarCuentaTesmov($cuentaAuxpag);
        if ($cuenta === '') {
            return $filas;
        }

        $exactas = [];
        foreach ($filas as $fila) {
            if (self::cuentaDeFilaTesmov($fila) === $cuenta) {
                $exactas[] = $fila;
            }
        }
        if ($exactas !== []) {
            return $exactas;
        }
        if (! self::cuentaSinImputacionNumerica($cuenta)) {
            return [];
        }

        $alfanumericas = [];
        foreach ($filas as $fila) {
            $tes = self::cuentaDeFilaTesmov($fila);
            if ($tes !== '' && $tes !== $cuenta && self::cuentaSinImputacionNumerica($tes)) {
                $alfanumericas[] = $fila;
            }
        }

        return $alfanumericas;
    }

    public static function normalizarCuentaTesmov(string $cuenta): string
    {
        $cuenta = strtoupper(trim($cuenta));
        if ($cuenta === '') {
            return '';
        }

        return str_pad($cuenta, 8, '0', STR_PAD_LEFT);
    }

    private static function cuentaSinImputacionNumerica(string $cuenta): bool
    {
        $digits = preg_replace('/\D+/', '', $cuenta) ?? '';

        return ltrim($digits, '0') === '';
    }

    private static function cuentaDeFilaTesmov(object|array $fila): string
    {
        $raw = is_array($fila)
            ? (string) ($fila['tesv_cuenta'] ?? '')
            : (string) ($fila->tesv_cuenta ?? '');

        return self::normalizarCuentaTesmov($raw);
    }

    public static function aPesos(float $importe, int $monedaId, mixed $cotizacion): float
    {
        $importe = (float) $importe;
        if ((int) $monedaId > 1) {
            $cot = (float) ($cotizacion ?: 0);

            return round($importe * ($cot > 1.0001 ? $cot : 1), 2);
        }

        return round($importe, 2);
    }

    /**
     * @return array{
     *     ok: bool,
     *     alertas: list<string>,
     *     diff_caja_asiento: float,
     *     diff_caja_tesmov: float,
     *     diff_asiento_ctamov: float
     * }
     */
    public static function evaluar(
        float $cajaArs,
        float $chequesArs,
        float $asientoArs,
        float $tesoreriaVsAsiento,
        float $tesmovArs,
        float $ctamovDebe,
        float $ctamovHaber,
        float $asientoDebe,
        float $asientoHaber,
        bool $tieneAsiento,
        bool $asientoBalanceado,
        bool $tieneTesmov,
        bool $tieneCtamov,
        bool $tienePagoAnita,
        string $tipo,
        int $chequesEmitidos,
        int $chequesSinCpromae,
        int $chequesSinTesmov,
        float $tolerancia = self::TOLERANCIA,
    ): array {
        $alertas = [];
        $tesoreriaArs = round($cajaArs + $chequesArs, 2);
        $tesoreriaVsAsiento = round($tesoreriaVsAsiento, 2);

        if (! $tieneAsiento) {
            $alertas[] = 'Sin asiento';
        } elseif (! $asientoBalanceado) {
            $alertas[] = 'Asiento desbalanceado';
        }

        if (self::esTipoIePuro($tipo) && $tieneAsiento
            && abs($tesoreriaVsAsiento - $asientoArs) >= $tolerancia) {
            $alertas[] = 'Caja ≠ asiento';
        }

        if (! $tieneTesmov) {
            $alertas[] = 'Sin tesmov Anita';
        } elseif (abs($tesmovArs - $tesoreriaArs) >= $tolerancia) {
            $alertas[] = 'Caja ≠ tesmov';
        }

        if ($chequesEmitidos > 0 && $chequesSinCpromae > 0) {
            $alertas[] = 'Cheque sin cpromae';
        }
        if ($chequesEmitidos > 0 && $chequesSinTesmov > 0) {
            $alertas[] = 'Cheque sin tesmov CHP';
        }

        if (! $tienePagoAnita) {
            $alertas[] = 'Sin pago Anita';
        }

        if (! $tieneCtamov) {
            $alertas[] = 'Sin ctamov Anita';
        } elseif ($tieneAsiento) {
            if (abs($ctamovDebe - $asientoDebe) >= $tolerancia
                || abs($ctamovHaber - $asientoHaber) >= $tolerancia) {
                $alertas[] = 'Asiento ≠ ctamov';
            }
        }

        return [
            'ok' => $alertas === [],
            'alertas' => $alertas,
            'diff_caja_asiento' => round($asientoArs - $tesoreriaVsAsiento, 2),
            'diff_caja_tesmov' => round($tesmovArs - $tesoreriaArs, 2),
            'diff_asiento_ctamov' => round(($ctamovDebe + $ctamovHaber) - ($asientoDebe + $asientoHaber), 2),
        ];
    }
}
