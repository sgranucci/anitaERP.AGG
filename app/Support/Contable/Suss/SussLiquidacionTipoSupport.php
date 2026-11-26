<?php

declare(strict_types=1);

namespace App\Support\Contable\Suss;

use App\Support\Contable\Sicore\SicoreLiquidacionTipoSupport;
use Carbon\Carbon;

/**
 * Liquidación SUSS (F.2004 / F.996): tipo 1Q (pago a cuenta) o DJ mensual (2Q).
 *
 * 1Q: retenciones del 01 al 15. El VEP a cuenta es el total de la 1Q.
 * DJ: retenciones del mes. Saldo = determinado − saldo a favor anterior − pago a cuenta 1Q.
 * El F2004 de la DJ es solo el de la 2Q (la 1Q ya está en SIRE).
 */
final class SussLiquidacionTipoSupport
{
    public const TIPO_1Q = '1q';

    public const TIPO_DJ = 'dj';

    /** @var list<string> */
    public const TIPOS = [self::TIPO_1Q, self::TIPO_DJ];

    /** Liquidación de SussListadoFiltros: 1 = 1ra quincena, 3 = mes completo. */
    public const LIQUIDACION_1Q = 1;

    public const LIQUIDACION_MES = 3;

    private const DIA_CORTE_1Q = 15;

    public static function normalizarTipo(?string $tipo): string
    {
        $tipo = strtolower(trim((string) $tipo));

        return in_array($tipo, self::TIPOS, true) ? $tipo : self::TIPO_1Q;
    }

    public static function esDj(string $tipo): bool
    {
        return self::normalizarTipo($tipo) === self::TIPO_DJ;
    }

    /**
     * Tipo que corresponde a la quincena elegida en pantalla (1 = 1Q; 2 y 3 = DJ).
     */
    public static function tipoParaLiquidacion(int $liquidacion): string
    {
        return $liquidacion === self::LIQUIDACION_1Q ? self::TIPO_1Q : self::TIPO_DJ;
    }

    public static function liquidacionParaTipo(string $tipo): int
    {
        return self::esDj($tipo) ? self::LIQUIDACION_MES : self::LIQUIDACION_1Q;
    }

    /**
     * Separa registros del mes en 1Q (día ≤ 15) y 2Q (día > 15) por fecha de retención.
     *
     * @param  list<array<string, mixed>>  $registros
     * @return array{q1: list<array<string, mixed>>, q2: list<array<string, mixed>>}
     */
    public static function separarQuincenas(array $registros): array
    {
        $q1 = [];
        $q2 = [];
        foreach ($registros as $reg) {
            $dia = (int) substr((string) ($reg['fecha_retencion'] ?? ''), 8, 2);
            if ($dia > 0 && $dia <= self::DIA_CORTE_1Q) {
                $q1[] = $reg;
            } else {
                $q2[] = $reg;
            }
        }

        return ['q1' => $q1, 'q2' => $q2];
    }

    /**
     * @param  list<array<string, mixed>>  $registros
     */
    public static function sumaImporte(array $registros): float
    {
        return round(array_sum(array_map(static fn (array $r) => (float) ($r['importe'] ?? 0), $registros)), 2);
    }

    public static function parseImporte(mixed $valor): float
    {
        return SicoreLiquidacionTipoSupport::parseImporte($valor);
    }

    /**
     * @return array{tipo: string, total_q1: float, total_q2: float, determinado: float, saf: float, pago_cuenta: float, pago_cuenta_fecha: string, vep: float, saldo_favor_sig: float}
     */
    public static function armarCuadro(
        string $tipo,
        float $totalQ1,
        float $totalQ2,
        float $saf,
        float $pagoCuenta,
        string $pagoCuentaFecha,
    ): array {
        $tipo = self::normalizarTipo($tipo);
        $totalQ1 = round($totalQ1, 2);
        $totalQ2 = round($totalQ2, 2);

        if ($tipo === self::TIPO_1Q) {
            return [
                'tipo' => self::TIPO_1Q,
                'total_q1' => $totalQ1,
                'total_q2' => 0.0,
                'determinado' => $totalQ1,
                'saf' => 0.0,
                'pago_cuenta' => 0.0,
                'pago_cuenta_fecha' => '',
                'vep' => $totalQ1,
                'saldo_favor_sig' => 0.0,
            ];
        }

        $saf = round(max(0.0, $saf), 2);
        $pagoCuenta = round(max(0.0, $pagoCuenta), 2);
        $determinado = round($totalQ1 + $totalQ2, 2);
        $neto = round($determinado - $saf - $pagoCuenta, 2);

        return [
            'tipo' => self::TIPO_DJ,
            'total_q1' => $totalQ1,
            'total_q2' => $totalQ2,
            'determinado' => $determinado,
            'saf' => $saf,
            'pago_cuenta' => $pagoCuenta,
            'pago_cuenta_fecha' => $pagoCuentaFecha,
            'vep' => max(0.0, $neto),
            'saldo_favor_sig' => max(0.0, round(-$neto, 2)),
        ];
    }

    /**
     * Control del pago a cuenta de la 1Q dentro de la DJ, contra el mayor del mes.
     *
     * - Campo vacío y el mayor del mes tiene un pago «Retenciones SUSS 1Q»: frena (bloquea).
     * - Campo vacío y la 1Q sin retenciones: correcto, no avisa.
     * - Pago cargado distinto del total de la 1Q (fuera de tolerancia): aviso.
     *
     * @return array{bloquea: bool, mensaje: ?string, avisos: list<string>}
     */
    public static function controlPagoCuentaDj(
        float $pagoCargado,
        float $totalQ1,
        float $pagoEnMayorQ1,
        float $tolerancia,
    ): array {
        $bloquea = false;
        $mensaje = null;
        $avisos = [];

        if ($pagoCargado < 0.005 && $pagoEnMayorQ1 >= 0.005) {
            $bloquea = true;
            $mensaje = 'Falta cargar el pago a cuenta de la 1Q: el mayor del mes tiene un pago «Retenciones SUSS 1Q» por $ '
                .self::fmt($pagoEnMayorQ1).'. Cargue el importe y la fecha en Liquidación completa.';
        }

        if ($pagoCargado >= 0.005 && abs(round($pagoCargado - $totalQ1, 2)) > $tolerancia) {
            $avisos[] = 'El pago a cuenta cargado ($ '.self::fmt($pagoCargado).') no coincide con el total de retenciones de la 1Q ($ '
                .self::fmt($totalQ1).').';
        }

        return ['bloquea' => $bloquea, 'mensaje' => $mensaje, 'avisos' => $avisos];
    }

    /**
     * Conciliación de la DJ: total del mes − saldo a favor anterior − pago a cuenta 1Q (si la fecha es ≤ hasta)
     * contra el mayor (con el pago posterior ya separado por el conciliador).
     *
     * @param  array<string, mixed>  $conciliacion
     * @return array<string, mixed>
     */
    public static function ajustarConciliacionDj(
        array $conciliacion,
        float $saf,
        float $pagoCuenta,
        bool $pagoAplicaHasta,
        float $tolerancia,
    ): array {
        if (empty($conciliacion['habilitada'])) {
            return $conciliacion;
        }

        $restaPago = $pagoAplicaHasta ? round($pagoCuenta, 2) : 0.0;
        $items = [];
        foreach ($conciliacion['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $bruto = round((float) ($item['total_suss'] ?? 0), 2);
            $comparable = round($bruto - round($saf, 2) - $restaPago, 2);
            $mayor = round((float) ($item['total_mayor'] ?? 0), 2);
            $pendiente = round((float) ($item['pago_posterior'] ?? 0), 2);
            $dif = round($comparable - ($mayor - $pendiente), 2);

            $item['total_suss_bruto'] = $bruto;
            $item['saf'] = round($saf, 2);
            $item['pago_cuenta_1q'] = $restaPago;
            $item['total_suss'] = $comparable;
            $item['diferencia'] = $dif;
            $item['cuadra'] = abs($dif) <= $tolerancia;
            $item['ajustada_dj'] = true;
            $items[] = $item;
        }
        $conciliacion['items'] = $items;

        return $conciliacion;
    }

    /**
     * Texto del mes para el detalle y nombres: «10.2026» (punto) o «10-2026» (guión).
     */
    public static function periodoMmYyyy(string $periodoYm, string $separador = '.'): string
    {
        if (! preg_match('/^(\d{4})(\d{2})$/', $periodoYm, $m)) {
            return $periodoYm;
        }

        return $m[2].$separador.$m[1];
    }

    /**
     * Lee el período y la quincena de un detalle de pago SUSS del mayor. Acepta las variantes que
     * aparecen en el mayor real: «Retenciones SUSS 1Q 10.2026», «RETENCIONES SUSS 1Q 10.26»,
     * «Retenciones SUSS  1Q 10.2026» (doble espacio), «Retenciones SUSS 10.2026 2Q», «Retenciones SuSS 2Q 0926».
     *
     * @return array{quincena: ?string, anio: int, mes: int}|null  null si no es un detalle SUSS con período
     */
    public static function periodoDeDetalleSuss(string $detalle): ?array
    {
        if (! preg_match('/retenciones\s+suss/iu', $detalle)) {
            return null;
        }

        $quincena = null;
        if (preg_match('/\b([12])\s*Q\b/iu', $detalle, $q)) {
            $quincena = $q[1].'Q';
        }

        if (preg_match('/\b(\d{1,2})\s*[.\/-]\s*(\d{4}|\d{2})\b/u', $detalle, $m)) {
            $mes = (int) $m[1];
            $anio = strlen($m[2]) === 2 ? 2000 + (int) $m[2] : (int) $m[2];
        } elseif (preg_match('/\b(\d{2})(\d{2})\b/u', $detalle, $m)) {
            $mes = (int) $m[1];
            $anio = 2000 + (int) $m[2];
        } else {
            return null;
        }

        if ($mes < 1 || $mes > 12) {
            return null;
        }

        return ['quincena' => $quincena, 'anio' => $anio, 'mes' => $mes];
    }

    /**
     * Clave comparable AAAAMM de un período YYYYMM (o null si no es válido).
     */
    public static function claveMes(string $periodoYm): ?int
    {
        return preg_match('/^(\d{4})(\d{2})$/', $periodoYm, $m) ? ((int) $m[1] * 100 + (int) $m[2]) : null;
    }

    public static function etiquetaPeriodo(string $periodoYm): string
    {
        if (! preg_match('/^(\d{4})(\d{2})$/', $periodoYm, $m)) {
            return $periodoYm;
        }
        try {
            return Carbon::createFromDate((int) $m[1], (int) $m[2], 1)->locale('es')->translatedFormat('F Y');
        } catch (\Throwable) {
            return $m[2].'/'.$m[1];
        }
    }

    /**
     * Sufijo de quincena del F2004 y del detalle: 1Q para pago a cuenta, 2Q para la DJ.
     */
    public static function sufijoQuincena(string $tipo): string
    {
        return self::esDj($tipo) ? '2Q' : '1Q';
    }

    public static function etiquetaTipo(string $tipo): string
    {
        return self::esDj($tipo) ? 'DJ' : '1Q';
    }

    public static function detalleSolicitud(string $tipo, string $periodoYm, string $sigla): string
    {
        return trim('Retenciones SUSS '.self::sufijoQuincena($tipo).' '.self::periodoMmYyyy($periodoYm).' | '.$sigla);
    }

    public static function nombreZip(string $sigla, string $tipo, string $periodoYm): string
    {
        $emp = preg_replace('/[^A-Za-z0-9_-]+/', '', $sigla) ?: 'EMPRESA';

        return sprintf('SUSS %s %s %s.zip', $emp, self::etiquetaTipo($tipo), self::periodoMmYyyy($periodoYm, '-'));
    }

    public static function fmt(float $n): string
    {
        return number_format($n, 2, ',', '.');
    }
}
