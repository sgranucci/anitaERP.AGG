<?php

namespace App\Support\Contable;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Numerico\NumeroDecimalLocalSupport;
use Illuminate\Support\Facades\DB;

/**
 * Validación Debe = Haber para asientos antes de grabar ERP / Anita ctamov.
 *
 * Incidente Biyemas 363199–363201: el ABM escribía ctamov línea a línea y, ante
 * error posterior, el rollback MySQL dejaba ctamov parcial (huérfano y desbalanceado).
 *
 * Incidente Ferli OPP 46358: una línea con Debe y Haber a la vez hacía pasar el
 * balance (suma ambos lados) pero ERP guardaba solo el Haber y Anita combinaba
 * abs(debe+haber) → asiento desbalanceado y ctamov distinto.
 */
final class AsientoBalanceSupport
{
    public const TOLERANCIA = 0.009;

    /**
     * Índices 0-based de líneas con importe en Debe y en Haber a la vez.
     *
     * @param  array<int, mixed>  $debes
     * @param  array<int, mixed>  $haberes
     * @return list<int>
     */
    public static function indicesLineasConDebeYHaber(array $debes, array $haberes): array
    {
        $debes = array_values($debes);
        $haberes = array_values($haberes);
        $q = max(count($debes), count($haberes));
        $out = [];

        for ($i = 0; $i < $q; $i++) {
            $debe = self::parseMonto($debes[$i] ?? null);
            $haber = self::parseMonto($haberes[$i] ?? null);
            if ($debe > 0 && $haber > 0) {
                $out[] = $i;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload  debes/haberes o debeasientos/haberasientos
     * @return list<int> índices 0-based
     */
    public static function indicesLineasConDebeYHaberDesdePayload(array $payload): array
    {
        $debes = $payload['debes'] ?? $payload['debeasientos'] ?? [];
        $haberes = $payload['haberes'] ?? $payload['haberasientos'] ?? [];

        return self::indicesLineasConDebeYHaber(
            is_array($debes) ? $debes : [],
            is_array($haberes) ? $haberes : [],
        );
    }

    /**
     * @param  list<int>  $indices0
     */
    public static function mensajeLineasConDebeYHaber(array $indices0, string $contexto = 'asiento'): string
    {
        $nros = array_map(static fn (int $i): int => $i + 1, $indices0);
        $lista = implode(', ', $nros);
        $plural = count($nros) > 1;

        return 'El '.$contexto.' tiene '
            .($plural ? 'líneas' : 'una línea')
            .' con importe en Debe y en Haber a la vez'
            .' (línea'.($plural ? 's' : '').' '.$lista.').'
            .' Deje solo un lado en cada movimiento.';
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws \InvalidArgumentException
     */
    public static function assertLineasSinDebeYHaber(array $payload, string $contexto = 'asiento'): void
    {
        $indices = self::indicesLineasConDebeYHaberDesdePayload($payload);
        if ($indices !== []) {
            throw new \InvalidArgumentException(self::mensajeLineasConDebeYHaber($indices, $contexto));
        }
    }

    /**
     * Totales desde arrays del formulario ABM / payload Anita (debes[] / haberes[]).
     *
     * @param  array<int, mixed>  $debes
     * @param  array<int, mixed>  $haberes
     * @return array{total_debe: float, total_haber: float, diferencia: float, lineas_con_importe: int, balanceado: bool, lineas_debe_y_haber: list<int>}
     */
    public static function totalesDesdeDebeHaber(array $debes, array $haberes): array
    {
        $q = max(count($debes), count($haberes));
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $lineas = 0;
        $ambos = [];

        for ($i = 0; $i < $q; $i++) {
            $debe = self::parseMonto($debes[$i] ?? null);
            $haber = self::parseMonto($haberes[$i] ?? null);

            // Misma regla que AsientoRepository::guardarAnita: línea sin importe se omite.
            if ($debe <= 0 && $haber <= 0) {
                continue;
            }

            $lineas++;
            if ($debe > 0 && $haber > 0) {
                $ambos[] = $i;
            }
            if ($debe > 0) {
                $totalDebe += $debe;
            }
            if ($haber > 0) {
                $totalHaber += $haber;
            }
        }

        $totalDebe = round($totalDebe, 4);
        $totalHaber = round($totalHaber, 4);
        $diferencia = round($totalDebe - $totalHaber, 4);

        return [
            'total_debe' => $totalDebe,
            'total_haber' => $totalHaber,
            'diferencia' => $diferencia,
            'lineas_con_importe' => $lineas,
            'balanceado' => $ambos === [] && abs($diferencia) <= self::TOLERANCIA,
            'lineas_debe_y_haber' => $ambos,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload  Debe incluir debes / haberes (arrays).
     * @return array{total_debe: float, total_haber: float, diferencia: float, lineas_con_importe: int, balanceado: bool}
     */
    public static function totalesDesdePayload(array $payload): array
    {
        $debes = $payload['debes'] ?? [];
        $haberes = $payload['haberes'] ?? [];
        $monedas = $payload['moneda_ids'] ?? [];
        $cotizaciones = $payload['cotizaciones'] ?? [];

        return self::totalesEnMonedaPrimeraLinea(
            is_array($debes) ? $debes : [],
            is_array($haberes) ? $haberes : [],
            is_array($monedas) ? $monedas : [],
            is_array($cotizaciones) ? $cotizaciones : []
        );
    }

    /**
     * Totales expresados en la moneda del primer movimiento con importe.
     * Sin mezcla de monedas = suma directa ({@see totalesDesdeDebeHaber}).
     * Con mezcla, cada línea se convierte con su propia cotización; una línea en
     * moneda extranjera sin cotización (≤ 1) no se puede convertir y el asiento no balancea.
     *
     * @param  array<int, mixed>  $debes
     * @param  array<int, mixed>  $haberes
     * @param  array<int, mixed>  $monedas  ids (1/2) o códigos (PES/DOL)
     * @param  array<int, mixed>  $cotizaciones
     * @return array{total_debe: float, total_haber: float, diferencia: float, lineas_con_importe: int, balanceado: bool, monedas_mezcladas: bool, sin_cotizacion: bool}
     */
    public static function totalesEnMonedaPrimeraLinea(array $debes, array $haberes, array $monedas, array $cotizaciones): array
    {
        $debes = array_values($debes);
        $haberes = array_values($haberes);
        $monedas = array_values($monedas);
        $cotizaciones = array_values($cotizaciones);

        $q = max(count($debes), count($haberes));
        $lineas = [];
        for ($i = 0; $i < $q; $i++) {
            $debe = self::parseMonto($debes[$i] ?? null);
            $haber = self::parseMonto($haberes[$i] ?? null);
            if ($debe <= 0 && $haber <= 0) {
                continue;
            }
            $lineas[] = [
                'debe' => max($debe, 0.0),
                'haber' => max($haber, 0.0),
                'moneda' => self::monedaIdDesdeValor($monedas[$i] ?? null),
                'cotizacion' => self::parseMonto($cotizaciones[$i] ?? null),
            ];
        }

        $base = self::totalesDesdeDebeHaber($debes, $haberes);
        $base['monedas_mezcladas'] = false;
        $base['sin_cotizacion'] = false;

        $monedasPresentes = array_unique(array_filter(array_column($lineas, 'moneda')));
        $cotizacionesDistintas = [];
        foreach ($lineas as $linea) {
            if ((int) ($linea['moneda'] ?: 1) === 1) {
                continue;
            }
            $cotizacionesDistintas[(string) round((float) $linea['cotizacion'], 6)] = true;
        }
        $cotizacionesMezcladas = count($cotizacionesDistintas) > 1;
        if (count($monedasPresentes) <= 1 && ! $cotizacionesMezcladas) {
            return $base;
        }

        $monedaAsiento = (int) ($lineas[0]['moneda'] ?: 1);
        $cotAsiento = (float) $lineas[0]['cotizacion'];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $tolerancia = self::TOLERANCIA;
        $sinCotizacion = false;

        foreach ($lineas as $linea) {
            $monedaLinea = (int) ($linea['moneda'] ?: $monedaAsiento);
            $coef = 1.0;
            if ($monedaLinea !== $monedaAsiento) {
                // Pesos → moneda extranjera usa la cotización de la moneda del asiento.
                $cot = $monedaLinea === 1 ? $cotAsiento : (float) $linea['cotizacion'];
                if ($cot <= 1.0001) {
                    $sinCotizacion = true;
                    $cot = 1.0;
                }
                $coef = (float) calculaCoeficienteMoneda($monedaAsiento, $monedaLinea, $cot);
                $tolerancia += 0.005 * $coef;
            } elseif ($monedaLinea !== 1 && $cotAsiento > 1.0001 && (float) $linea['cotizacion'] > 1.0001
                && abs((float) $linea['cotizacion'] - $cotAsiento) > 0.0000005) {
                $coef = (float) $linea['cotizacion'] / $cotAsiento;
            }
            $totalDebe += $linea['debe'] * $coef;
            $totalHaber += $linea['haber'] * $coef;
        }

        $totalDebe = round($totalDebe, 4);
        $totalHaber = round($totalHaber, 4);
        $diferencia = round($totalDebe - $totalHaber, 4);
        $ambos = self::indicesLineasConDebeYHaber($debes, $haberes);

        return [
            'total_debe' => $totalDebe,
            'total_haber' => $totalHaber,
            'diferencia' => $diferencia,
            'lineas_con_importe' => count($lineas),
            'balanceado' => $ambos === [] && ! $sinCotizacion && abs($diferencia) <= $tolerancia,
            'lineas_debe_y_haber' => $ambos,
            'monedas_mezcladas' => true,
            'sin_cotizacion' => $sinCotizacion,
        ];
    }

    private static function monedaIdDesdeValor(mixed $valor): int
    {
        if ($valor === null || $valor === '') {
            return 0;
        }
        if (is_numeric($valor)) {
            return (int) $valor;
        }

        $canonico = MayorPlanoCuentaSupport::codigoMonedaCanonico((string) $valor);
        if (is_numeric($canonico)) {
            return (int) $canonico;
        }

        static $porCodigo = [];

        return $porCodigo[$canonico] ??= (int) (DB::table('moneda')->where('codigo', $canonico)->value('id') ?? 0);
    }

    /**
     * Exige asiento con al menos 2 líneas con importe y Debe = Haber.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws \InvalidArgumentException
     */
    public static function assertBalanceadoDesdePayload(array $payload, string $contexto = 'asiento'): void
    {
        // Primero: Debe+Haber en la misma línea engaña al total (incidente OPP 46358).
        self::assertLineasSinDebeYHaber($payload, $contexto);

        $totales = self::totalesDesdePayload($payload);

        if ($totales['lineas_con_importe'] < 2) {
            throw new \InvalidArgumentException(
                'El '.$contexto.' necesita al menos dos movimientos con importe.'
            );
        }

        if (! empty($totales['sin_cotizacion'])) {
            throw new \InvalidArgumentException(
                'El '.$contexto.' mezcla monedas y tiene un movimiento en moneda extranjera sin cotización.'
            );
        }

        if (! $totales['balanceado']) {
            throw new \InvalidArgumentException(self::mensajeDesbalance($totales, $contexto));
        }
    }

    /**
     * Validación del ABM Contable (CRUD asiento): balance + moneda única.
     * No usar en imports Anita, Excel, OP/TES u otros orígenes de proceso:
     * ahí solo {@see assertBalanceadoDesdePayload}.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws \InvalidArgumentException
     */
    public static function assertValidoParaCrudAsiento(array $payload, string $contexto = 'asiento'): void
    {
        self::assertBalanceadoDesdePayload($payload, $contexto);
        self::assertMonedaUnicaDesdePayload($payload, $contexto);
    }

    /**
     * @deprecated Usar {@see assertValidoParaCrudAsiento}; se mantiene como alias del CRUD.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws \InvalidArgumentException
     */
    public static function assertValidoParaGrabar(array $payload, string $contexto = 'asiento'): void
    {
        self::assertValidoParaCrudAsiento($payload, $contexto);
    }

    /**
     * Solo ABM Contable: todas las líneas con importe = moneda del primer movimiento.
     * Imports / otros sistemas pueden mezclar monedas (ej. OP con banco USD + IIBB PES).
     *
     * @param  array<string, mixed>  $payload  moneda_ids[] + debes[] / haberes[]
     *
     * @throws \InvalidArgumentException
     */
    public static function assertMonedaUnicaDesdePayload(array $payload, string $contexto = 'asiento'): void
    {
        $monedaIds = $payload['moneda_ids'] ?? [];
        $debes = $payload['debes'] ?? [];
        $haberes = $payload['haberes'] ?? [];

        if (! is_array($monedaIds)) {
            $monedaIds = [];
        }
        if (! is_array($debes)) {
            $debes = [];
        }
        if (! is_array($haberes)) {
            $haberes = [];
        }

        $q = max(count($monedaIds), count($debes), count($haberes));
        $monedaReferencia = null;

        for ($i = 0; $i < $q; $i++) {
            $debe = self::parseMonto($debes[$i] ?? null);
            $haber = self::parseMonto($haberes[$i] ?? null);
            if ($debe <= 0 && $haber <= 0) {
                continue;
            }

            $monedaRaw = $monedaIds[$i] ?? null;
            if ($monedaRaw === null || $monedaRaw === '') {
                throw new \InvalidArgumentException(
                    'El '.$contexto.' tiene un movimiento sin moneda.'
                );
            }

            $monedaClave = is_numeric($monedaRaw)
                ? (string) (int) $monedaRaw
                : trim((string) $monedaRaw);

            if ($monedaClave === '' || $monedaClave === '0') {
                throw new \InvalidArgumentException(
                    'El '.$contexto.' tiene un movimiento sin moneda.'
                );
            }

            if ($monedaReferencia === null) {
                $monedaReferencia = $monedaClave;
                continue;
            }

            if ($monedaClave !== $monedaReferencia) {
                throw new \InvalidArgumentException(
                    'El '.$contexto.' no puede mezclar monedas. '
                    .'La moneda la fija el primer movimiento; todas las líneas deben usar la misma.'
                );
            }
        }
    }

    /**
     * @param  array{total_debe: float, total_haber: float, diferencia: float}  $totales
     */
    public static function mensajeDesbalance(array $totales, string $contexto = 'asiento'): string
    {
        return 'El '.$contexto.' no balancea: Debe '
            .AsientoImportColumnasSupport::formatearImporte((float) $totales['total_debe'])
            .' vs Haber '
            .AsientoImportColumnasSupport::formatearImporte((float) $totales['total_haber'])
            .' (diferencia '
            .AsientoImportColumnasSupport::formatearImporte(abs((float) $totales['diferencia']))
            .').';
    }

    public static function parseMonto(mixed $valor): float
    {
        return NumeroDecimalLocalSupport::aFloat($valor, 0.0);
    }
}
