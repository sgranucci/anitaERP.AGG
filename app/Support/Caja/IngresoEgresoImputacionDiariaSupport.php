<?php

namespace App\Support\Caja;

use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Configuracion\MonedaAnitaCodigoSupport;
use App\Support\Contable\AsientoBalanceSupport;

/**
 * Control I/E: tesorería ERP (caja + cheques) ↔ tesmov Anita;
 * asiento ERP ↔ ctamov Anita. ING/EGR/TRA también exigen caja ↔ asiento.
 * Los importes en moneda extranjera se comparan en pesos con la cotización de cada línea.
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

    /**
     * Una cotización en 1 sobre moneda extranjera compara dólares contra pesos y el control
     * marca un desvío del orden de la cotización. Se resuelve con la del resto del documento
     * y, si no hay, con la vigente de la fecha.
     */
    public static function aPesos(
        float $importe,
        int $monedaId,
        mixed $cotizacion,
        mixed $fecha = null,
        mixed $cotizacionDocumento = null,
    ): float {
        $coeficiente = CotizacionVigenteSupport::ventaEfectiva(
            $cotizacion,
            (int) $monedaId,
            self::fechaYmd($fecha),
            $cotizacionDocumento,
        );

        return round((float) $importe * $coeficiente, 2);
    }

    private static function fechaYmd(mixed $fecha): ?string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        $fecha = trim((string) $fecha);

        return $fecha !== '' ? $fecha : null;
    }

    /**
     * Asiento ERP en pesos. El monto de la línea está en su moneda; la cotización
     * solo se aplica si la moneda no es pesos (igual que la caja).
     *
     * @param  iterable<mixed>  $movimientos
     * @return array{total_debe: float, total_haber: float, diferencia: float, lineas_con_importe: int, balanceado: bool}
     */
    public static function balanceAsientoEnPesos(iterable $movimientos, mixed $fecha = null): array
    {
        $lineas = [];
        foreach ($movimientos as $mov) {
            $monto = is_array($mov)
                ? (float) ($mov['monto'] ?? 0)
                : (float) ($mov->monto ?? 0);
            if (abs($monto) < 0.0001) {
                continue;
            }
            $monedaId = is_array($mov)
                ? (int) ($mov['moneda_id'] ?? 1)
                : (int) ($mov->moneda_id ?: 1);
            $lineas[] = [
                'monto' => $monto,
                'moneda_id' => $monedaId > 0 ? $monedaId : 1,
                'cotizacion' => is_array($mov) ? ($mov['cotizacion'] ?? 1) : ($mov->cotizacion ?? 1),
            ];
        }

        // Todas las líneas se convierten con la misma tasa por moneda: si una quedó sin
        // cotización, tomar otra del asiento evita desbalancearlo al pasarlo a pesos.
        $cotizacionesAsiento = CotizacionVigenteSupport::cotizacionesDeclaradas($lineas);

        $debes = [];
        $haberes = [];
        foreach ($lineas as $linea) {
            $pesos = self::aPesos(
                $linea['monto'],
                $linea['moneda_id'],
                $linea['cotizacion'],
                $fecha,
                $cotizacionesAsiento[$linea['moneda_id']] ?? null,
            );
            $debes[] = $pesos > 0 ? $pesos : 0.0;
            $haberes[] = $pesos < 0 ? abs($pesos) : 0.0;
        }

        return AsientoBalanceSupport::totalesDesdeDebeHaber($debes, $haberes);
    }

    /**
     * Ferli no guarda tesv_cod_mon: el importe queda en la moneda de la línea y, si la
     * cotización no se aplicó, el control compara dólares contra la caja en pesos.
     * Solo se reescala cuando el producto cierra con la tesorería; una cotización
     * decorativa sobre un importe que ya está en pesos no entra.
     *
     * @param  array<int, float>  $cotizacionesPorMoneda
     */
    public static function reconciliarTesmovEnPesos(
        float $tesmovArs,
        float $tesoreriaArs,
        array $cotizacionesPorMoneda,
        float $tolerancia = self::TOLERANCIA,
    ): float {
        $tesmovArs = round($tesmovArs, 2);
        $tesoreriaArs = round($tesoreriaArs, 2);
        if (abs($tesmovArs - $tesoreriaArs) < $tolerancia) {
            return $tesmovArs;
        }

        foreach ($cotizacionesPorMoneda as $monedaId => $cotizacion) {
            if ((int) $monedaId <= 1) {
                continue;
            }
            $cotizacion = (float) $cotizacion;
            if ($cotizacion <= CotizacionVigenteSupport::COTIZACION_MINIMA_EXTRANJERA) {
                continue;
            }
            $enPesos = round($tesmovArs * $cotizacion, 2);
            if (abs($enPesos - $tesoreriaArs) < $tolerancia) {
                return $enPesos;
            }
        }

        return $tesmovArs;
    }

    /**
     * tesmov guarda el importe en la moneda del movimiento (tesv_cod_mon) y la cotización aparte.
     *
     * @param  object|array<string, mixed>  $fila
     */
    public static function tesmovImporteEnPesos(object|array $fila): float
    {
        $row = is_array($fila) ? $fila : get_object_vars($fila);
        $moneda = (int) MonedaAnitaCodigoSupport::normalizar($row['tesv_cod_mon'] ?? 1);

        return abs(self::aPesos(
            (float) ($row['tesv_importe'] ?? 0),
            $moneda > 0 ? $moneda : 1,
            $row['tesv_cotizacion'] ?? 1,
            $row['tesv_fecha'] ?? null,
        ));
    }

    /**
     * ctamov guarda ctav_importe en la moneda de la línea (ctav_cod_mon).
     *
     * @param  iterable<mixed>  $filas
     * @return array{total_debe: float, total_haber: float, diferencia: float, lineas_con_importe: int, balanceado: bool}
     */
    public static function totalesCtamovEnPesos(iterable $filas): array
    {
        $debes = [];
        $haberes = [];
        foreach ($filas as $fila) {
            $row = is_array($fila) ? $fila : get_object_vars($fila);
            $importe = abs((float) ($row['ctav_importe'] ?? 0));
            if ($importe < 0.0001) {
                continue;
            }
            $moneda = (int) MonedaAnitaCodigoSupport::normalizar($row['ctav_cod_mon'] ?? 1);
            $pesos = self::aPesos(
                $importe,
                $moneda > 0 ? $moneda : 1,
                $row['ctav_cotizacion'] ?? 1,
                $row['ctav_fecha'] ?? null,
            );
            if (strtoupper(trim((string) ($row['ctav_d_h'] ?? 'D'))) === 'H') {
                $debes[] = 0.0;
                $haberes[] = $pesos;
            } else {
                $debes[] = $pesos;
                $haberes[] = 0.0;
            }
        }

        return AsientoBalanceSupport::totalesDesdeDebeHaber($debes, $haberes);
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
