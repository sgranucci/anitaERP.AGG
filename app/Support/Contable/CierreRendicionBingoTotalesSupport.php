<?php

namespace App\Support\Contable;

use App\ApiAnita;
use App\Models\Caja\Bingo\BingoConceptoRendicion;
use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Support\Contable\Efe\EfeAnitaBridgeReader;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Totales diarios del cierre bingo (réplica p-vtabingo.c un_dia / lee_premios).
 */
final class CierreRendicionBingoTotalesSupport
{
    /**
     * @param  Collection<int, RendicionBingoCaja>  $rendiciones
     * @return array<string, mixed>
     */
    public static function calcular(Collection $rendiciones, int $empresaId, string $fechaDia): array
    {
        if ($rendiciones->isEmpty()) {
            return self::estructuraVacia();
        }

        $concbIndex = CierreRendicionBingoConcbingoSupport::indicePorConcepto($empresaId);
        $premiosAnita = self::premiosAnitaPorOperacion($rendiciones, $fechaDia);

        $totRecaudacion = 0.0;
        $totCartones = 0;
        $totEfectivo = 0.0;
        $totSobrante = 0.0;
        $totRedondeo = 0.0;
        $totDifCaja = 0.0;
        $totRefuerPrest = 0.0;
        $totPremio = 0.0;
        $totBingo = 0.0;
        $totPantalla = 0.0;
        $totPozo = 0.0;
        $totPozoUltBolaPagado = 0.0;
        $totRealPozoUltBola = 0.0;
        $totPorcRecaud = 0.0;

        /** @var array<int, array{pagado: float, real: float}> $acumConcepto */
        $acumConcepto = [];

        foreach ($rendiciones as $rendicion) {
            $totRecaudacion = round($totRecaudacion + (float) ($rendicion->total_cartones ?? 0), 2);
            $totCartones += (int) ($rendicion->cant_cartones ?? 0);
            $efectivo = round((float) ($rendicion->deposito ?? 0) - (float) ($rendicion->vales ?? 0), 2);
            $totEfectivo = round($totEfectivo + $efectivo, 2);
            $totSobrante = round($totSobrante + (float) ($rendicion->sobrante_faltante ?? 0), 2);
            $totRedondeo = round($totRedondeo + (float) ($rendicion->redondeo ?? 0), 2);
            $totRefuerPrest = round($totRefuerPrest + (float) ($rendicion->refuerzo_prestamo ?? 0), 2);

            $premios = self::premiosDeRendicion($rendicion, $concbIndex, $premiosAnita);
            $totPantallaR = 0.0;
            $totPozoR = 0.0;
            $totBingoR = 0.0;

            foreach ($premios as $premio) {
                $tipo = (string) ($premio['tipo_conc'] ?? '');
                $pagado = round((float) ($premio['pagado'] ?? 0), 2);
                $real = round((float) ($premio['real'] ?? 0), 2);
                $concepto = (int) ($premio['concepto'] ?? 0);

                if ($tipo === CierreRendicionBingoConceptoTipos::PANTALLA) {
                    $totPantalla = round($totPantalla + $real, 2);
                    $totPantallaR = round($totPantallaR + $real, 2);
                } elseif ($tipo === CierreRendicionBingoConceptoTipos::BINGO) {
                    $totBingo = round($totBingo + $real, 2);
                    $totBingoR = round($totBingoR + $real, 2);
                } elseif ($tipo === CierreRendicionBingoConceptoTipos::PREMIO) {
                    $totPremio = round($totPremio + $real, 2);
                } elseif ($tipo === CierreRendicionBingoConceptoTipos::PORC_RECAUD) {
                    $totPorcRecaud = round($totPorcRecaud + $real, 2);
                } elseif ($tipo === CierreRendicionBingoConceptoTipos::PORC_POZO
                    || $tipo === CierreRendicionBingoConceptoTipos::ULT_BOLA) {
                    $totPozo = round($totPozo + $pagado, 2);
                    $totPozoR = round($totPozoR + $pagado, 2);
                    if ($tipo === CierreRendicionBingoConceptoTipos::ULT_BOLA) {
                        $totPozoUltBolaPagado = round($totPozoUltBolaPagado + $pagado, 2);
                        $totRealPozoUltBola = round($totRealPozoUltBola + $real, 2);
                    }
                }

                if ($concepto > 0) {
                    if (! isset($acumConcepto[$concepto])) {
                        $acumConcepto[$concepto] = ['pagado' => 0.0, 'real' => 0.0];
                    }
                    $acumConcepto[$concepto]['pagado'] = round($acumConcepto[$concepto]['pagado'] + $pagado, 2);
                    $acumConcepto[$concepto]['real'] = round($acumConcepto[$concepto]['real'] + $real, 2);
                }
            }

            $difRend = round($totBingoR - $totPozoR - $totPantallaR - $efectivo, 2);
            $totDifCaja = round($totDifCaja + $difRend, 2);
        }

        $canones = self::calcularCanones($concbIndex, $totRecaudacion, $acumConcepto);
        $ventaAcumulada = self::ventaAcumuladaMes($empresaId, $fechaDia, $totRecaudacion, $rendiciones);
        $ventaAcumuladaAnterior = round($ventaAcumulada - $totRecaudacion, 2);
        $pagoHospital = self::calcularPagoHospital($empresaId, $totRecaudacion, $ventaAcumulada, $ventaAcumuladaAnterior);

        $inMonto = round(abs($totEfectivo + $totDifCaja + $totRefuerPrest), 2);
        $otrosPremios = round($totPozoUltBolaPagado - $totRealPozoUltBola, 2);
        $difCajaAsiento = round($totDifCaja + $totRefuerPrest, 2);

        $resultadoFlash = round(
            $totEfectivo - $totSobrante - $totRefuerPrest - $totRedondeo,
            2,
        );

        return [
            'tot_recaudacion' => $totRecaudacion,
            'tot_cartones' => $totCartones,
            'tot_resultado_flash' => $resultadoFlash,
            'acum_concepto' => $acumConcepto,
            'tot_efectivo' => $totEfectivo,
            'tot_sobrante' => $totSobrante,
            'tot_redondeo' => $totRedondeo,
            'tot_dif_caja' => $totDifCaja,
            'tot_refuer_prest' => $totRefuerPrest,
            'tot_premio' => $totPremio,
            'tot_bingo' => $totBingo,
            'tot_pantalla' => $totPantalla,
            'tot_pozo' => $totPozo,
            'tot_pozo_ult_bola_pagado' => $totPozoUltBolaPagado,
            'tot_real_pozo_ult_bola' => $totRealPozoUltBola,
            'tot_porc_recaud' => $totPorcRecaud,
            'tot_pago_hospital' => $pagoHospital,
            'tot_vta_acumulada' => $ventaAcumulada,
            'tot_vta_acumulada_anterior' => $ventaAcumuladaAnterior,
            'in_monto' => $inMonto,
            'otros_premios' => $otrosPremios,
            'dif_caja_asiento' => $difCajaAsiento,
            'canones' => $canones,
        ];
    }

    /**
     * Evolución SI pozo AC (p-vtabingo.c un_dia): (pozo_ac * 0.99) + (recaudación * 0.05) − real PORC_POZO.
     *
     * @param  array<int, array<string, mixed>>  $concbIndex
     * @param  array<int, array{pagado: float, real: float}>  $acumConcepto
     */
    public static function evolSiPozoAc(
        float $pozoAcAnterior,
        float $recaudacion,
        array $concbIndex,
        array $acumConcepto,
    ): float {
        $pozos = 0.0;
        foreach ($concbIndex as $concepto => $meta) {
            if (($meta['tipo_conc'] ?? '') !== CierreRendicionBingoConceptoTipos::PORC_POZO) {
                continue;
            }
            $pozos = round($pozos - (float) ($acumConcepto[(int) $concepto]['real'] ?? 0), 2);
        }

        return round(($pozoAcAnterior * 0.99) + ($recaudacion * 0.05) + $pozos, 2);
    }

    /**
     * Importe a mostrar de un concepto PAGO: real de premios, o % de recaudación si no hay premio.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<int, array{pagado: float, real: float}>  $acumConcepto
     */
    public static function importePagoConcepto(array $meta, array $acumConcepto, float $recaudacion): float
    {
        $concepto = (int) ($meta['concepto'] ?? 0);
        $real = round((float) ($acumConcepto[$concepto]['real'] ?? 0), 2);
        if (abs($real) > 0.0001) {
            return $real;
        }

        $porc = (float) ($meta['porcentaje'] ?? 0);
        if ($porc > 0 && $recaudacion > 0) {
            return round($recaudacion * $porc / 100, 2);
        }

        return 0.0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $concbIndex
     * @param  array<int, array{pagado: float, real: float}>  $acumConcepto
     * @return list<array{concepto: int, desc: string, total: float, cuenta_debe_id: int, cuenta_haber_id: int}>
     */
    private static function calcularCanones(array $concbIndex, float $recaudacion, array $acumConcepto): array
    {
        $canones = [];

        foreach ($concbIndex as $meta) {
            if (($meta['tipo_conc'] ?? '') !== CierreRendicionBingoConceptoTipos::PAGO) {
                continue;
            }

            $concepto = (int) ($meta['concepto'] ?? 0);
            $realPremio = round((float) ($acumConcepto[$concepto]['real'] ?? 0), 2);
            $total = $realPremio;

            if (abs($total) <= 0.0001) {
                $porc = (float) ($meta['porcentaje'] ?? 0);
                if ($porc > 0 && $recaudacion > 0) {
                    $total = round($recaudacion * $porc / 100, 2);
                }
            }

            if (abs($total) <= 0.0001) {
                continue;
            }

            $debeId = (int) ($meta['cuenta_debe_id'] ?? 0);
            $haberId = (int) ($meta['cuenta_haber_id'] ?? 0);
            if ($debeId <= 0 || $haberId <= 0) {
                continue;
            }

            $canones[] = [
                'concepto' => $concepto,
                'desc' => (string) ($meta['desc'] ?? 'Canon'),
                'total' => $total,
                'cuenta_debe_id' => $debeId,
                'cuenta_haber_id' => $haberId,
            ];
        }

        return $canones;
    }

    private static function calcularPagoHospital(
        int $empresaId,
        float $recaudacion,
        float $ventaAcumulada,
        float $ventaAcumuladaAnterior,
    ): float {
        if ($empresaId === 1) {
            if ($ventaAcumulada <= 1500000) {
                return round($recaudacion * 0.02, 2);
            }

            $ajusteAnterior = $ventaAcumuladaAnterior <= 1500000
                ? round(($ventaAcumuladaAnterior - 1500000) * 0.02, 2)
                : round(($ventaAcumuladaAnterior - 1500000) * 0.0325, 2);

            return round((($ventaAcumulada - 1500000) * 0.0325) - $ajusteAnterior, 2);
        }

        return round($recaudacion * 0.01, 2);
    }

    /**
     * @param  Collection<int, RendicionBingoCaja>  $rendiciones
     */
    private static function ventaAcumuladaMes(
        int $empresaId,
        string $fechaDia,
        float $recaudacionDia,
        Collection $rendiciones,
    ): float {
        $fecha = Carbon::parse($fechaDia);
        $inicioMes = $fecha->copy()->startOfMonth()->toDateString();

        $acumErp = (float) RendicionBingoCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', '>=', $inicioMes)
            ->whereDate('fecha_jornada', '<=', $fechaDia)
            ->sum('total_cartones');

        $acum = round($acumErp, 2);

        if (filter_var(config('bingo.cierre_rendicion_contable.pozo_acumulado_desde_anita', true), FILTER_VALIDATE_BOOLEAN)) {
            $acumAnita = self::recaudacionAnitaMesHasta($empresaId, $inicioMes, $fecha->copy()->subDay()->toDateString());
            if ($acumAnita > 0 && $acumAnita > $acum - $recaudacionDia) {
                $acum = round($acumAnita + $recaudacionDia, 2);
            }
        }

        return $acum;
    }

    private static function recaudacionAnitaMesHasta(int $empresaId, string $desde, string $hasta): float
    {
        if ($desde > $hasta) {
            return 0.0;
        }

        $empresaAnita = $empresaId;
        $fechaDesde = (int) Carbon::parse($desde)->format('Ymd');
        $fechaHasta = (int) Carbon::parse($hasta)->format('Ymd');

        try {
            $reader = new EfeAnitaBridgeReader;
            $filas = $reader->listarRendbingo($empresaAnita, $fechaDesde, $fechaHasta);
            $total = 0.0;
            foreach ($filas as $row) {
                $total = round($total + (float) ($row->rendb_total_carton ?? 0), 2);
            }

            return $total;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $concbIndex
     * @param  array<string, list<array{concepto:int,pagado:float,real:float,tipo_conc:string}>>  $premiosAnita
     * @return list<array{concepto:int,pagado:float,real:float,tipo_conc:string}>
     */
    private static function premiosDeRendicion(
        RendicionBingoCaja $rendicion,
        array $concbIndex,
        array $premiosAnita,
    ): array {
        $clave = self::claveOperacion($rendicion);
        if ($clave !== '' && isset($premiosAnita[$clave]) && $premiosAnita[$clave] !== []) {
            return $premiosAnita[$clave];
        }

        return self::premiosDesdeErp($rendicion, $concbIndex);
    }

    /**
     * @param  array<int, array<string, mixed>>  $concbIndex
     * @return list<array{concepto:int,pagado:float,real:float,tipo_conc:string}>
     */
    private static function premiosDesdeErp(RendicionBingoCaja $rendicion, array $concbIndex): array
    {
        $lineas = is_array($rendicion->conceptos_json) ? $rendicion->conceptos_json : [];
        if ($lineas === []) {
            return [];
        }

        $conceptoIds = array_values(array_filter(array_map(
            static fn ($l) => (int) ($l['concepto_id'] ?? 0),
            $lineas,
        )));

        $codigosAnita = $conceptoIds === []
            ? collect()
            : BingoConceptoRendicion::query()->whereIn('id', $conceptoIds)->pluck('codigo_anita', 'id');

        $acum = [];
        foreach ($lineas as $linea) {
            $conceptoId = (int) ($linea['concepto_id'] ?? 0);
            $codigoAnita = (int) ($codigosAnita[$conceptoId] ?? 0);
            if ($codigoAnita <= 0) {
                continue;
            }

            $monto = round((float) ($linea['monto'] ?? 0), 2);
            $pagado = round((float) ($linea['pagado'] ?? $monto), 2);
            $real = round((float) ($linea['real'] ?? $monto), 2);
            $tipo = (string) ($concbIndex[$codigoAnita]['tipo_conc'] ?? '');

            if (! isset($acum[$codigoAnita])) {
                $acum[$codigoAnita] = [
                    'concepto' => $codigoAnita,
                    'pagado' => 0.0,
                    'real' => 0.0,
                    'tipo_conc' => $tipo,
                ];
            }
            $acum[$codigoAnita]['pagado'] = round($acum[$codigoAnita]['pagado'] + $pagado, 2);
            $acum[$codigoAnita]['real'] = round($acum[$codigoAnita]['real'] + $real, 2);
        }

        return array_values($acum);
    }

    /**
     * @param  Collection<int, RendicionBingoCaja>  $rendiciones
     * @return array<string, list<array{concepto:int,pagado:float,real:float,tipo_conc:string}>>
     */
    private static function premiosAnitaPorOperacion(Collection $rendiciones, string $fechaDia): array
    {
        $fecha = (int) Carbon::parse($fechaDia)->format('Ymd');
        $concbIndex = [];

        try {
            $api = new ApiAnita;
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'caja',
                'tabla' => 'rendpremio',
                'campos' => 'rendp_nro_oper,rendp_tipo_oper,rendp_concepto,rendp_pagado,rendp_real,rendp_fecha',
                'whereArmado' => ' WHERE rendp_fecha = '.$fecha,
            ]);
            $filas = ApiAnita::decodificarListaFilas($raw);
        } catch (\Throwable) {
            return [];
        }

        if ($filas === []) {
            return [];
        }

        $empresaId = (int) ($rendiciones->first()?->empresa_id ?? 0);
        $concbIndex = CierreRendicionBingoConcbingoSupport::indicePorConcepto($empresaId);

        /** @var array<int, string> $operPorNro */
        $operPorNro = [];
        foreach ($rendiciones as $r) {
            $nro = (int) ($r->nro_oper_anita ?? 0);
            if ($nro > 0) {
                $operPorNro[$nro] = self::claveOperacion($r);
            }
        }

        /** @var array<string, list<array{concepto:int,pagado:float,real:float,tipo_conc:string}>> $out */
        $out = [];

        foreach ($filas as $row) {
            $nro = (int) ($row->rendp_nro_oper ?? 0);
            $clave = $operPorNro[$nro] ?? '';
            if ($clave === '') {
                continue;
            }

            $concepto = (int) ($row->rendp_concepto ?? 0);
            if ($concepto <= 0) {
                continue;
            }

            if (! isset($out[$clave])) {
                $out[$clave] = [];
            }

            $tipo = (string) ($concbIndex[$concepto]['tipo_conc'] ?? '');
            $pagado = round((float) ($row->rendp_pagado ?? 0), 2);
            $real = round((float) ($row->rendp_real ?? 0), 2);

            $out[$clave][] = [
                'concepto' => $concepto,
                'pagado' => $pagado,
                'real' => $real,
                'tipo_conc' => $tipo,
            ];
        }

        return $out;
    }

    private static function claveOperacion(RendicionBingoCaja $rendicion): string
    {
        $nro = (int) ($rendicion->nro_oper_anita ?? 0);
        $tipo = substr((string) config('rendicion_bingo_anita.tipo_oper', 'F'), 0, 1);
        if ($nro <= 0) {
            return '';
        }

        return $nro.'|'.$tipo;
    }

    /**
     * @return array<string, mixed>
     */
    private static function estructuraVacia(): array
    {
        return [
            'tot_recaudacion' => 0.0,
            'tot_cartones' => 0,
            'tot_resultado_flash' => 0.0,
            'acum_concepto' => [],
            'tot_efectivo' => 0.0,
            'tot_sobrante' => 0.0,
            'tot_redondeo' => 0.0,
            'tot_dif_caja' => 0.0,
            'tot_refuer_prest' => 0.0,
            'tot_premio' => 0.0,
            'tot_bingo' => 0.0,
            'tot_pantalla' => 0.0,
            'tot_pozo' => 0.0,
            'tot_pozo_ult_bola_pagado' => 0.0,
            'tot_real_pozo_ult_bola' => 0.0,
            'tot_porc_recaud' => 0.0,
            'tot_pago_hospital' => 0.0,
            'tot_vta_acumulada' => 0.0,
            'tot_vta_acumulada_anterior' => 0.0,
            'in_monto' => 0.0,
            'otros_premios' => 0.0,
            'dif_caja_asiento' => 0.0,
            'canones' => [],
        ];
    }
}
