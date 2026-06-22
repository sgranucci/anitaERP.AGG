<?php

namespace App\Support\Contable\Efe;

use Carbon\Carbon;

/**
 * Posición financiera mensual (solapa «pos fin …») — port parcial de l-posfinanc.c.
 *
 * Fuentes Anita bridge: saldoposf, rendbingo, rendmaquina.
 * Premios bingo, gastro, egresos y medios de cobro requieren tablas adicionales (concbingo, rendvalor, …).
 */
class EfePosicionFinancieraSupport
{
    /** Turnos parciales excluidos en rendmaquina desde 2010-03 (l-posfinanc.c); se usa turno C. */
    private const TURNOS_MAQUINA_EXCLUIDOS = ['M', 'T', 'N'];

    private const FECHA_CORTE_TURNO_MAQUINA = 20100300;

    public function __construct(
        private readonly EfeAnitaBridgeReader $bridgeReader = new EfeAnitaBridgeReader(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   totales_por_etiqueta: array<string, float>,
     *   saldo_inicial: ?float,
     *   saldo_final: ?float,
     *   bingo: array<string, float>,
     *   premios_bingo: array<string, float>,
     *   maquinas: array<string, float>,
     *   errores_bridge: list<string>
     * }
     */
    public function generar(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);

        if ($empresaId <= 0 || $mes <= 0 || $anio <= 0) {
            return $this->vacio(['Parámetros de período incompletos']);
        }

        $inicioMes = Carbon::createFromDate($anio, $mes, 1);
        $finMes = $inicioMes->copy()->endOfMonth();
        $fechaSaldoInicial = (int) $inicioMes->copy()->subDay()->format('Ymd');
        $fechaDesde = (int) $inicioMes->format('Ymd');
        $fechaHasta = (int) $finMes->format('Ymd');

        $errores = [];
        $totales = [];

        $saldos = $this->bridgeReader->listarSaldoposf($empresaId, $fechaSaldoInicial, $fechaHasta);
        $saldoInicial = $this->saldoEnFecha($saldos, $fechaSaldoInicial);
        $saldoFinal = $this->saldoEnFecha($saldos, $fechaHasta);

        if ($saldoInicial !== null) {
            $totales['Saldo inicial'] = $saldoInicial;
        }
        if ($saldoFinal !== null) {
            $totales['Saldo final'] = $saldoFinal;
        }

        $bingo = $this->agregarRendbingo(
            $this->bridgeReader->listarRendbingo($empresaId, $fechaDesde, $fechaHasta),
        );
        $premios = $this->agregarPremiosBingo(
            $bingo,
            $this->bridgeReader->listarConcbingo(),
            $this->bridgeReader->listarRendbingo($empresaId, $fechaDesde, $fechaHasta),
            $this->bridgeReader->listarRendpremio($fechaDesde, $fechaHasta),
        );
        foreach ($bingo as $etiqueta => $valor) {
            if (abs($valor) > 0.0001) {
                $totales[$etiqueta] = $valor;
            }
        }
        foreach ($premios as $etiqueta => $valor) {
            if (abs($valor) > 0.0001) {
                $totales[$etiqueta] = $valor;
            }
        }

        $maquinas = $this->agregarRendmaquina(
            $this->bridgeReader->listarRendmaquina($empresaId, $fechaDesde, $fechaHasta),
        );
        foreach ($maquinas as $etiqueta => $valor) {
            if (abs($valor) > 0.0001) {
                $totales[$etiqueta] = $valor;
            }
        }

        return [
            'totales_por_etiqueta' => $totales,
            'saldo_inicial' => $saldoInicial,
            'saldo_final' => $saldoFinal,
            'bingo' => $bingo,
            'premios_bingo' => $premios,
            'maquinas' => $maquinas,
            'errores_bridge' => $errores,
        ];
    }

    /**
     * @param  list<object>  $filas
     */
    private function saldoEnFecha(array $filas, int $fecha): ?float
    {
        foreach ($filas as $fila) {
            if ((int) ($fila->salpf_fecha ?? 0) === $fecha) {
                return round((float) ($fila->salpf_saldo ?? 0), 2);
            }
        }

        return null;
    }

    /**
     * @param  list<object>  $filas
     * @return array<string, float>
     */
    private function agregarRendbingo(array $filas): array
    {
        $totales = [
            'VENTA BINGO' => 0.0,
            'SOBRANTES' => 0.0,
            'VALES' => 0.0,
            'REDONDEO' => 0.0,
        ];

        foreach ($filas as $fila) {
            $totales['VENTA BINGO'] += (float) ($fila->rendb_total_carton ?? 0);
            $totales['SOBRANTES'] += (float) ($fila->rendb_sobrante ?? 0);
            $totales['VALES'] += (float) ($fila->rendb_vales ?? 0);
            $totales['REDONDEO'] += (float) ($fila->rendb_redondeo ?? 0);
        }

        return array_map(fn (float $v) => round($v, 2), $totales);
    }

    /**
     * Premios bingo (concbingo + rendpremio) — l-posfinanc.c lee_premios().
     *
     * @param  array<string, float>  $bingoBase
     * @param  list<object>  $concbingo
     * @param  list<object>  $rendbingo
     * @param  list<object>  $rendpremio
     * @return array<string, float>
     */
    private function agregarPremiosBingo(
        array $bingoBase,
        array $concbingo,
        array $rendbingo,
        array $rendpremio,
    ): array {
        $totales = [];
        $ventaBingo = (float) ($bingoBase['VENTA BINGO'] ?? 0);

        $mapConcb = [];
        foreach ($concbingo as $row) {
            $mapConcb[(int) ($row->concb_concepto ?? 0)] = $row;
        }

        foreach ($concbingo as $row) {
            $tipo = trim((string) ($row->concb_tipo_conc ?? ''));
            $desc = trim((string) ($row->concb_desc ?? ''));
            if ($desc === '') {
                continue;
            }

            if (in_array($tipo, ['0', '1'], true)) {
                $pct = (float) ($row->concb_porcentaje ?? 0);
                if ($ventaBingo > 0 && $pct > 0) {
                    $totales[$desc] = round(-$ventaBingo * ($pct / 100), 2);
                }

                continue;
            }
        }

        $opsRendb = [];
        foreach ($rendbingo as $row) {
            $opsRendb[(int) ($row->rendb_nro_oper ?? 0).'|'.trim((string) ($row->rendb_tipo_oper ?? ''))] = true;
        }

        foreach ($rendpremio as $row) {
            $claveOp = (int) ($row->rendp_nro_oper ?? 0).'|'.trim((string) ($row->rendp_tipo_oper ?? ''));
            if (! isset($opsRendb[$claveOp])) {
                continue;
            }

            $conceptoId = (int) ($row->rendp_concepto ?? 0);
            if (! isset($mapConcb[$conceptoId])) {
                continue;
            }

            $concb = $mapConcb[$conceptoId];
            $tipo = trim((string) ($concb->concb_tipo_conc ?? ''));
            if ($tipo === '0') {
                continue;
            }

            $desc = trim((string) ($concb->concb_desc ?? ''));
            if ($desc === '') {
                continue;
            }

            $usaReal = in_array($tipo, ['3', '4', '5'], true);
            $importe = $usaReal
                ? (float) ($row->rendp_real ?? 0)
                : (float) ($row->rendp_pagado ?? 0);

            if ($importe <= 0) {
                continue;
            }

            $totales[$desc] = round(($totales[$desc] ?? 0) - $importe, 2);
        }

        return $totales;
    }

    /**
     * @param  list<object>  $filas
     * @return array<string, float>
     */
    private function agregarRendmaquina(array $filas): array
    {
        $totales = [
            'MAQUINAS VENTAS' => 0.0,
            'MAQUINAS CAJA' => 0.0,
            'Vales fondo fijo' => 0.0,
            'Vales administracion' => 0.0,
            'Variacion de FF' => 0.0,
            'Diferencia de caja' => 0.0,
            'Caja en transito' => 0.0,
        ];

        foreach ($filas as $fila) {
            if (! $this->incluirRendmaquina($fila)) {
                continue;
            }

            $venta = $this->ventaMaquinasDesdeFila($fila);
            $deposito = (float) ($fila->rendm_deposito ?? 0);
            $difCajaRaw = (float) ($fila->rendm_dif_caja ?? 0);
            $variacionFf = (float) ($fila->rendm_variacion_ff ?? 0);

            $totales['MAQUINAS VENTAS'] += $venta;
            $totales['MAQUINAS CAJA'] += $deposito;
            $totales['Vales administracion'] += (float) ($fila->rendm_vales ?? 0);
            $totales['Vales fondo fijo'] += (float) ($fila->rendm_reintegros ?? 0);
            $totales['Variacion de FF'] += $variacionFf;
            $totales['Diferencia de caja'] += $difCajaRaw + $variacionFf;

            if ($venta > $deposito) {
                $cajaTransito = ($venta + $difCajaRaw) - $deposito;
            } else {
                $cajaTransito = $deposito - ($venta + $difCajaRaw);
                $cajaTransito *= -1;
            }
            $totales['Caja en transito'] += $cajaTransito;
        }

        return array_map(fn (float $v) => round($v, 2), $totales);
    }

    private function incluirRendmaquina(object $fila): bool
    {
        $fecha = (int) ($fila->rendm_fecha ?? 0);
        if ($fecha >= self::FECHA_CORTE_TURNO_MAQUINA) {
            $turno = strtoupper(trim((string) ($fila->rendm_turno ?? '')));
            if (in_array($turno, self::TURNOS_MAQUINA_EXCLUIDOS, true)) {
                return false;
            }
        }

        return true;
    }

    private function ventaMaquinasDesdeFila(object $fila): float
    {
        $ingresoRodillos = (float) ($fila->rendm_venta_ficha ?? 0)
            + (float) ($fila->rendm_drop_billete ?? 0)
            + (float) ($fila->rendm_billem_rod ?? 0);
        $salidaRodillos = (float) ($fila->rendm_pago_manual ?? 0)
            + (float) ($fila->rendm_tito ?? 0)
            + (float) ($fila->rendm_hopper ?? 0);

        $ingresoRuleta = (float) ($fila->rendm_venta_ruleta ?? 0)
            + (float) ($fila->rendm_drop_ruleta ?? 0)
            + (float) ($fila->rendm_billem_rul ?? 0);
        $salidaRuleta = (float) ($fila->rendm_salida_rul ?? $fila->rendm_salida_ruleta ?? 0)
            + (float) ($fila->rendm_tito_ruleta ?? 0);

        return ($ingresoRodillos - $salidaRodillos) + ($ingresoRuleta - $salidaRuleta);
    }

    /**
     * @param  list<string>  $errores
     * @return array{
     *   totales_por_etiqueta: array<string, float>,
     *   saldo_inicial: ?float,
     *   saldo_final: ?float,
     *   bingo: array<string, float>,
     *   premios_bingo: array<string, float>,
     *   maquinas: array<string, float>,
     *   errores_bridge: list<string>
     * }
     */
    private function vacio(array $errores): array
    {
        return [
            'totales_por_etiqueta' => [],
            'saldo_inicial' => null,
            'saldo_final' => null,
            'bingo' => [],
            'premios_bingo' => [],
            'maquinas' => [],
            'errores_bridge' => $errores,
        ];
    }
}
