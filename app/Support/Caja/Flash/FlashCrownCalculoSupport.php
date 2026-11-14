<?php

namespace App\Support\Caja\Flash;

use App\Models\Caja\Flash\FlashCrown;
use App\Models\Caja\TesoreriaCrownCasinoOnline;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownResultadoOnline;
use App\Models\Configuracion\Sala;
use App\Support\Caja\TesoreriaCrownAnexo1Calculo;
use App\Support\Caja\TesoreriaCrownAnexo2Calculo;
use Carbon\Carbon;
use Throwable;

/**
 * Arma el flash Crown desde Tesorería (mesas, gastro/hotel, clientes)
 * + resultado online (slots por sala) + casino online diario (Del Río / Betsur / Apuestas).
 * Réplica de fuentes de a-resultado / l-consolida (formato Completo).
 */
final class FlashCrownCalculoSupport
{
    /**
     * Del Río / Betsur / Apuestas: un solo bloque de la empresa Crown (no por sala).
     * Fuente: tesoreria_crown_casino_online.
     *
     * @return array{
     *   ol_att: int, ol_drop: float, ol_coin_in: float, ol_cage_bal: float, ol_win: float,
     *   bs_att: int, bs_coin_in: float, bs_cage_bal: float, bs_win: float,
     *   ad_att: int, ad_coin_in: float, ad_cage_bal: float, ad_win: float
     * }
     */
    public static function valoresOnlineGlobal(string $fechaYmd): array
    {
        $cero = [
            'ol_att' => 0,
            'ol_drop' => 0.0,
            'ol_coin_in' => 0.0,
            'ol_cage_bal' => 0.0,
            'ol_win' => 0.0,
            'bs_att' => 0,
            'bs_coin_in' => 0.0,
            'bs_cage_bal' => 0.0,
            'bs_win' => 0.0,
            'ad_att' => 0,
            'ad_coin_in' => 0.0,
            'ad_cage_bal' => 0.0,
            'ad_win' => 0.0,
        ];

        $fecha = Carbon::parse($fechaYmd)->toDateString();
        $global = TesoreriaCrownCasinoOnline::deFecha($fecha);
        if ($global === null) {
            return $cero;
        }

        return [
            'ol_att' => (int) ($global->attendance ?? 0),
            'ol_drop' => round((float) ($global->drop ?? 0), 2),
            'ol_coin_in' => round((float) ($global->apostado ?? 0), 2),
            'ol_cage_bal' => round((float) ($global->pagado ?? 0), 2),
            'ol_win' => round((float) ($global->resultado ?? 0), 2),
            'bs_att' => (int) ($global->betsur_attendance ?? 0),
            'bs_coin_in' => round((float) ($global->betsur_apostado ?? 0), 2),
            'bs_cage_bal' => round((float) ($global->betsur_pagado ?? 0), 2),
            'bs_win' => round((float) ($global->betsur_resultado ?? 0), 2),
            'ad_att' => (int) ($global->ad_attendance ?? 0),
            'ad_coin_in' => round((float) ($global->ad_apostado ?? 0), 2),
            'ad_cage_bal' => round((float) ($global->ad_pagado ?? 0), 2),
            'ad_win' => round((float) ($global->ad_resultado ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public static function aplicarOnlineVivoEnBase(array $base, string $fechaYmd, bool $esBloqueEmpresa): array
    {
        $online = $esBloqueEmpresa
            ? self::valoresOnlineGlobal($fechaYmd)
            : [
                'ol_att' => 0,
                'ol_drop' => 0.0,
                'ol_coin_in' => 0.0,
                'ol_cage_bal' => 0.0,
                'ol_win' => 0.0,
                'bs_att' => 0,
                'bs_coin_in' => 0.0,
                'bs_cage_bal' => 0.0,
                'bs_win' => 0.0,
                'ad_att' => 0,
                'ad_coin_in' => 0.0,
                'ad_cage_bal' => 0.0,
                'ad_win' => 0.0,
            ];

        return array_merge($base, $online);
    }

    /**
     * Fila por sala: mesas/slots del flash; online = 0 (va en consolidado / Resumen empresa).
     *
     * @return array<string, mixed>
     */
    public static function baseVistaDesdeFlash(\App\Models\Caja\Flash\FlashCrown $flash): array
    {
        $base = $flash->toArray();
        $fecha = $flash->fecha instanceof Carbon
            ? $flash->fecha->toDateString()
            : Carbon::parse((string) $flash->fecha)->toDateString();
        $base = self::completarSlotsVaciosDesdeResultadoOnline(
            $base,
            (int) $flash->sala_id,
            $fecha,
        );

        return self::aplicarOnlineVivoEnBase($base, $fecha, false);
    }

    /**
     * Réplica Anita sin resulol aún: completar coin_in/slot_r desde tesorería resultado_online
     * sin pisar soft_count/mesas/ayb/hotel de Anita.
     *
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public static function completarSlotsVaciosDesdeResultadoOnline(array $base, int $salaId, string $fechaYmd): array
    {
        $tieneSlots = abs((float) ($base['coin_in'] ?? 0)) >= 0.02
            || abs((float) ($base['slot_r'] ?? 0)) >= 0.02;
        if ($tieneSlots || $salaId <= 0) {
            return $base;
        }

        $online = TesoreriaCrownResultadoOnline::deSalaFecha($salaId, Carbon::parse($fechaYmd)->toDateString());
        if ($online === null) {
            return $base;
        }

        $coinIn = round((float) ($online->coin_in ?? 0), 2);
        $coinOut = round((float) ($online->coin_out ?? 0), 2);
        $pagoManual = round((float) ($online->pago_manual ?? 0), 2);
        $slotR = round((float) ($online->slot_resultado ?? 0), 2);
        if (abs($slotR) < 0.0001) {
            $slotR = round($coinIn - $coinOut - $pagoManual, 2);
        }
        if (abs($coinIn) < 0.02 && abs($slotR) < 0.02) {
            return $base;
        }

        $base['coin_in'] = $coinIn;
        $base['coin_out'] = $coinOut;
        $base['pago_manual'] = $pagoManual;
        $base['slot_r'] = $slotR;
        if ((int) ($base['cant_slots'] ?? 0) === 0) {
            $base['cant_slots'] = (int) ($online->cantidad_slots ?? 0);
        }

        $detalle = is_array($base['detalle'] ?? null) ? $base['detalle'] : [];
        if (isset($base['detalle_json']) && is_array($base['detalle_json']) && $detalle === []) {
            $detalle = $base['detalle_json'];
        }
        $fuentes = is_array($detalle['fuentes'] ?? null) ? $detalle['fuentes'] : [];
        if (! in_array('resultado_online', $fuentes, true)) {
            $fuentes[] = 'resultado_online';
        }
        $detalle['fuentes'] = $fuentes;
        $base['detalle'] = $detalle;
        $base['detalle_json'] = $detalle;

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    public static function estructuraVacia(): array
    {
        $out = [
            'sala_id' => 0,
            'empresa_id' => 0,
            'fecha' => null,
            'att' => 0,
            'cashdrop' => 0.0,
            'soft_mesas' => 0.0,
            'cant_mesas' => 0,
            'caja_empleado' => 0.0,
            'wheel_r' => 0.0,
            'baccarat_r' => 0.0,
            'blackjack_r' => 0.0,
            'multi_r' => 0.0,
            'crap_r' => 0.0,
            'caribbean_r' => 0.0,
            'poker_r' => 0.0,
            'seven_r' => 0.0,
            'super_peek_r' => 0.0,
            'torneo_poker' => 0.0,
            'master_cup' => 0.0,
            'inscripcion' => 0.0,
            'premio' => 0.0,
            'slot_d' => 0.0,
            'soft_count' => 0.0,
            'hard_count' => 0.0,
            'cant_slots' => 0,
            'coin_in' => 0.0,
            'coin_out' => 0.0,
            'pago_manual' => 0.0,
            'slot_r' => 0.0,
            'cash_less' => 0.0,
            'ruleta_el_d' => 0.0,
            'ruleta_el_r' => 0.0,
            'soft_rul_el' => 0.0,
            'cant_rul_el' => 0,
            'coin_in_rul' => 0.0,
            'ol_att' => 0,
            'ol_drop' => 0.0,
            'ol_coin_in' => 0.0,
            'ol_cage_bal' => 0.0,
            'ol_win' => 0.0,
            'bs_att' => 0,
            'bs_coin_in' => 0.0,
            'bs_cage_bal' => 0.0,
            'bs_win' => 0.0,
            'ad_att' => 0,
            'ad_coin_in' => 0.0,
            'ad_cage_bal' => 0.0,
            'ad_win' => 0.0,
            'ayb' => 0.0,
            'hotel' => 0.0,
            'comentario' => null,
            'avisos' => [],
            'detalle' => [
                'mesas_por_juego' => [],
                'fuentes' => [],
            ],
            'calculado_en' => null,
        ];

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function deSalaFecha(int $salaId, string $fechaYmd): array
    {
        $flash = self::estructuraVacia();
        $fecha = Carbon::parse($fechaYmd)->toDateString();
        $flash['sala_id'] = $salaId;
        $flash['fecha'] = $fecha;
        $flash['calculado_en'] = now()->toDateTimeString();

        $sala = Sala::query()->find($salaId);
        if (! $sala) {
            $flash['avisos'][] = 'Sala inexistente.';

            return $flash;
        }
        $flash['empresa_id'] = (int) ($sala->empresa_id ?? 0);

        $jornada = TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fecha)
            ->first();

        if ($jornada) {
            $flash['empresa_id'] = (int) ($jornada->empresa_id ?: $flash['empresa_id']);
            $flash['detalle']['fuentes'][] = 'jornada';
            self::aplicarClientes($flash, $jornada);
            self::aplicarMesasYTorneos($flash, $jornada);
            self::aplicarGastroHotel($flash, $jornada);
        } else {
            $flash['avisos'][] = 'Sin jornada de tesorería para la fecha; solo resultado online si existe.';
        }

        self::aplicarResultadoOnline($flash, $salaId, $fecha);

        return $flash;
    }

    /**
     * @param  array<string, mixed>  $flash
     */
    private static function aplicarClientes(array &$flash, TesoreriaCrownJornada $jornada): void
    {
        $avisos = [];
        $clientes = TesoreriaCrownAnexo1Calculo::clientesParaFormulario($jornada);
        $flash['att'] = max(0, (int) $clientes);
        if ($avisos !== []) {
            $flash['avisos'] = array_merge($flash['avisos'], $avisos);
        }
        $flash['detalle']['fuentes'][] = 'clientes';
    }

    /**
     * @param  array<string, mixed>  $flash
     */
    private static function aplicarMesasYTorneos(array &$flash, TesoreriaCrownJornada $jornada): void
    {
        $avisos = [];
        try {
            $anexo = TesoreriaCrownAnexo2Calculo::deJornada($jornada, TesoreriaCrownAnexo2Calculo::GRUPO_MESAS);
        } catch (Throwable $e) {
            $flash['avisos'][] = 'No se pudieron leer mesas: '.$e->getMessage();

            return;
        }

        $porJuego = [];
        $cantMesas = 0;
        $cashdrop = 0.0;
        $cajaEmpleado = 0.0;
        $inscripcion = 0.0;
        $premio = 0.0;

        foreach ($anexo['secciones'] ?? [] as $seccion) {
            if (($seccion['tipo'] ?? '') !== 'mesas') {
                continue;
            }
            // El anexo deja el nombre vacío en la 2ª mesa del mismo juego (solo display).
            // Sin arrastrarlo, esa fila no entra: el 1/10/2026 la ruleta 2 (−1.891.000) se perdió
            // y Wheel quedó en +35.500 en vez del neto −1.855.500.
            $juegoVigente = '';
            foreach ($seccion['filas'] ?? [] as $fila) {
                if (! empty($fila['es_subtotal']) || ! empty($fila['es_total'])) {
                    continue;
                }
                $juego = trim((string) ($fila['juego'] ?? ''));
                if ($juego === '') {
                    $juego = $juegoVigente;
                }
                if ($juego === '') {
                    continue;
                }
                $juegoVigente = $juego;
                $clave = FlashCrownJuegoMapeoSupport::claveDesdeDescripcion($juego);
                $resultado = (float) ($fila['resultado'] ?? 0);
                $drop = (float) ($fila['drop'] ?? 0);
                $caja = (float) ($fila['caja_empleado'] ?? 0);
                $pagos = (float) ($fila['pagos_manuales'] ?? 0);

                $porJuego[$clave] = ($porJuego[$clave] ?? 0.0) + $resultado;
                $cashdrop += $drop;
                $cajaEmpleado += $caja;
                $cantMesas++;

                if (FlashCrownJuegoMapeoSupport::esTorneo($clave)) {
                    // l-resmesa: inscripciones = dropbox torneo; premios = pago manual.
                    $inscripcion += $drop;
                    $premio += $pagos;
                }
            }
        }

        foreach ($porJuego as $clave => $importe) {
            if ($clave === FlashCrownJuegoMapeoSupport::CLAVE_OTROS) {
                // Sin columna propia: se suma a poker como residual visible en detalle.
                $flash['poker_r'] = round((float) $flash['poker_r'] + $importe, 2);
                continue;
            }
            if (array_key_exists($clave, $flash)) {
                $flash[$clave] = round((float) $flash[$clave] + $importe, 2);
            }
        }

        $flash['cashdrop'] = round($cashdrop, 2);
        $flash['caja_empleado'] = round($cajaEmpleado, 2);
        $flash['cant_mesas'] = $cantMesas;
        $flash['inscripcion'] = round($inscripcion, 2);
        $flash['premio'] = round($premio, 2);
        $flash['detalle']['mesas_por_juego'] = $porJuego;
        $flash['detalle']['fuentes'][] = 'mesas';
        if (($anexo['avisos'] ?? []) !== []) {
            $flash['avisos'] = array_merge($flash['avisos'], $anexo['avisos']);
        }
        if ($avisos !== []) {
            $flash['avisos'] = array_merge($flash['avisos'], $avisos);
        }
    }

    /**
     * @param  array<string, mixed>  $flash
     */
    private static function aplicarGastroHotel(array &$flash, TesoreriaCrownJornada $jornada): void
    {
        try {
            $anexo = TesoreriaCrownAnexo2Calculo::deJornada($jornada, TesoreriaCrownAnexo2Calculo::GRUPO_GASTRONOMIA);
        } catch (Throwable $e) {
            $flash['avisos'][] = 'No se pudo leer gastronomía/hotel: '.$e->getMessage();

            return;
        }

        $ayb = 0.0;
        $hotel = 0.0;
        foreach ($anexo['secciones'] ?? [] as $seccion) {
            if (($seccion['tipo'] ?? '') !== 'gastronomia') {
                continue;
            }
            foreach ($seccion['filas'] ?? [] as $fila) {
                if (! empty($fila['es_total'])) {
                    continue;
                }
                $origen = strtoupper((string) ($fila['origen'] ?? 'G'));
                $importe = (float) ($fila['rendiciones'] ?? 0);
                if ($origen === 'H') {
                    $hotel += $importe;
                } else {
                    $ayb += $importe;
                }
            }
        }

        $flash['ayb'] = round($ayb, 2);
        $flash['hotel'] = round($hotel, 2);
        $flash['detalle']['fuentes'][] = 'gastro_hotel';
        if (($anexo['avisos'] ?? []) !== []) {
            $flash['avisos'] = array_merge($flash['avisos'], $anexo['avisos']);
        }
    }

    /**
     * @param  array<string, mixed>  $flash
     */
    private static function aplicarResultadoOnline(array &$flash, int $salaId, string $fecha): void
    {
        $online = TesoreriaCrownResultadoOnline::deSalaFecha($salaId, $fecha);
        if ($online) {
            $coinIn = (float) $online->coin_in;
            $coinOut = (float) $online->coin_out;
            $pagoManual = (float) $online->pago_manual;
            $slotR = (float) ($online->slot_resultado ?? 0);
            if (abs($slotR) < 0.0001) {
                $slotR = round($coinIn - $coinOut - $pagoManual, 2);
            }

            $flash['coin_in'] = round($coinIn, 2);
            $flash['coin_out'] = round($coinOut, 2);
            $flash['pago_manual'] = round($pagoManual, 2);
            $flash['slot_r'] = round($slotR, 2);
            $flash['cant_slots'] = (int) ($online->cantidad_slots ?? 0);
            $flash['soft_count'] = round((float) ($online->subtotal_slot ?? 0), 2);
            $flash['slot_d'] = round((float) ($online->subtotal_slot ?: 0), 2);
            $flash['detalle']['fuentes'][] = 'resultado_online';
        } else {
            $flash['avisos'][] = 'Sin resultado online (slots) para la sala/fecha.';
        }

        self::aplicarCasinoOnlineGlobal($flash, $fecha, $online);
    }

    /**
     * Del Río / Betsur / Apuestas: carga diaria global. Fallback legacy desde resultado_online.
     *
     * @param  array<string, mixed>  $flash
     */
    private static function aplicarCasinoOnlineGlobal(array &$flash, string $fecha, ?TesoreriaCrownResultadoOnline $legacy): void
    {
        // Fuente de verdad: carga diaria. No depender de ol_*/bs_*/ad_* en flash_crown.
        $hayFilaDiaria = TesoreriaCrownCasinoOnline::deFecha($fecha) !== null;
        if ($hayFilaDiaria) {
            foreach (self::valoresOnlineGlobal($fecha) as $campo => $valor) {
                $flash[$campo] = $valor;
            }
            $flash['detalle']['fuentes'][] = 'casino_online_diario';
        } elseif ($legacy) {
            // Compatibilidad con cargas anteriores cargados por sala.
            $flash['ol_att'] = (int) ($legacy->attendance ?? 0);
            $flash['ol_drop'] = round((float) ($legacy->drop ?? 0), 2);
            $flash['ol_coin_in'] = round((float) ($legacy->apostado ?? 0), 2);
            $flash['ol_cage_bal'] = round((float) ($legacy->pagado ?? 0), 2);
            $flash['ol_win'] = round((float) ($legacy->resultado ?? 0), 2);

            $flash['bs_att'] = (int) ($legacy->betsur_attendance ?? 0);
            $flash['bs_coin_in'] = round((float) ($legacy->betsur_apostado ?? 0), 2);
            $flash['bs_cage_bal'] = round((float) ($legacy->betsur_pagado ?? 0), 2);
            $flash['bs_win'] = round((float) ($legacy->betsur_resultado ?? 0), 2);
        }

        if (abs((float) $flash['ol_win']) > 0.001 || abs((float) $flash['ol_coin_in']) > 0.001) {
            $flash['detalle']['fuentes'][] = 'del_rio_online';
        }
        if (abs((float) $flash['bs_win']) > 0.001 || abs((float) $flash['bs_coin_in']) > 0.001) {
            $flash['detalle']['fuentes'][] = 'betsur_online';
        }
        if (abs((float) $flash['ad_win']) > 0.001 || abs((float) $flash['ad_coin_in']) > 0.001) {
            $flash['detalle']['fuentes'][] = 'apuestas_deportivas';
        }
        if (! $hayFilaDiaria && ! $legacy) {
            $flash['avisos'][] = 'Sin carga de casino online diario para la fecha.';
        }
    }

    /**
     * Aplica overrides manuales sobre el cálculo online.
     *
     * @param  array<string, mixed>  $calculado
     * @param  array<string, mixed>  $overrides  campo => valor
     * @return array<string, mixed>
     */
    public static function aplicarOverrides(array $calculado, array $overrides): array
    {
        $aplicados = [];
        foreach ($overrides as $campo => $valor) {
            $campo = (string) $campo;
            if (! in_array($campo, \App\Models\Caja\Flash\FlashCrown::camposNumericos(), true)
                && $campo !== 'comentario') {
                continue;
            }
            if ($campo === 'comentario') {
                $calculado['comentario'] = $valor === null || $valor === '' ? null : (string) $valor;
                $aplicados[] = $campo;
                continue;
            }
            if (in_array($campo, ['att', 'cant_mesas', 'cant_slots', 'cant_rul_el', 'ol_att', 'bs_att', 'ad_att'], true)) {
                $calculado[$campo] = (int) $valor;
            } else {
                $calculado[$campo] = round((float) $valor, 2);
            }
            $aplicados[] = $campo;
        }
        $calculado['overrides_aplicados'] = $aplicados;

        return $calculado;
    }

    /**
     * Combined estilo Anita / l-consolida (Comb. Ant.):
     * win mesas + soft_count + F&B + hotel (no usa slot_r de resulol).
     *
     * @param  array<string, mixed>  $flash
     */
    public static function combinedComoAnita(array $flash): float
    {
        $winMesas = (float) self::metricasDerivadas($flash)['win_mesas'];

        return round(
            $winMesas
            + (float) ($flash['soft_count'] ?? 0)
            + (float) ($flash['ayb'] ?? 0)
            + (float) ($flash['hotel'] ?? 0),
            2
        );
    }

    /**
     * Combined del mismo día año anterior (columna “Comb. Ant.”).
     * Usa flash persistido (réplica Anita); no recalcula tesorería vacía.
     */
    public static function combinedAnteriorAnio(int $salaId, string $fechaYmd): float
    {
        $fecha = Carbon::parse($fechaYmd)->startOfDay();
        $anioAnt = $fecha->year - 1;
        $dia = min($fecha->day, Carbon::create($anioAnt, $fecha->month, 1)->daysInMonth);
        $anterior = Carbon::create($anioAnt, $fecha->month, $dia)->toDateString();

        $flash = FlashCrown::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $anterior)
            ->first();
        if ($flash === null) {
            return 0.0;
        }

        return self::combinedComoAnita(self::baseVistaDesdeFlash($flash));
    }

    /**
     * Attendance consolidado Anita = salas + Del Río + Betsur + Apuestas.
     *
     * @param  array<string, mixed>  $fila  Debe traer att + ol_att/bs_att/ad_att
     */
    public static function attendanceConsolidado(array $fila): int
    {
        return (int) ($fila['att'] ?? 0)
            + (int) ($fila['ol_att'] ?? 0)
            + (int) ($fila['bs_att'] ?? 0)
            + (int) ($fila['ad_att'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $flash
     */
    public static function metricasDerivadas(array $flash): array
    {
        $winMesas = round(
            (float) $flash['wheel_r'] + (float) $flash['baccarat_r'] + (float) $flash['blackjack_r']
            + (float) $flash['multi_r'] + (float) $flash['crap_r'] + (float) $flash['caribbean_r']
            + (float) $flash['poker_r'] + (float) $flash['seven_r'] + (float) $flash['super_peek_r'],
            2
        );
        $olWin = (float) ($flash['ol_win'] ?? 0);
        $bsWin = (float) ($flash['bs_win'] ?? 0);
        $adWin = (float) ($flash['ad_win'] ?? 0);
        $slotsFisicos = round(
            (float) $flash['slot_r']
            - (float) $flash['cash_less']
            + (float) $flash['ruleta_el_r'],
            2
        );
        $gaming = round($winMesas + $slotsFisicos + $olWin + $bsWin + $adWin, 2);
        $combined = round($gaming + (float) $flash['ayb'] + (float) $flash['hotel'], 2);
        $att = (int) ($flash['att'] ?? 0);
        $olDrop = (float) ($flash['ol_drop'] ?? 0);

        return [
            'win_mesas' => $winMesas,
            'slots_fisicos' => $slotsFisicos,
            'ol_win' => round($olWin, 2),
            'bs_win' => round($bsWin, 2),
            'ad_win' => round($adWin, 2),
            'gaming' => $gaming,
            'combined' => $combined,
            'win_torneos' => round((float) $flash['torneo_poker'] + (float) $flash['master_cup'], 2),
            'combined_cust' => $att > 0 ? round($combined / $att, 2) : 0.0,
            'hold_slots' => abs((float) $flash['coin_in']) > 0.001
                ? round((float) $flash['slot_r'] / (float) $flash['coin_in'] * 100, 2)
                : 0.0,
            // l-consolida: win/drop de ON-LINE cuando hay drop.
            'ol_hold' => abs($olDrop) > 0.001 ? round($olWin / $olDrop, 2) : 0.0,
            'ol_porcentaje' => abs((float) ($flash['ol_coin_in'] ?? 0)) > 0.001
                ? round($olWin / (float) $flash['ol_coin_in'] * 100, 2)
                : 0.0,
            'bs_porcentaje' => abs((float) ($flash['bs_coin_in'] ?? 0)) > 0.001
                ? round($bsWin / (float) $flash['bs_coin_in'] * 100, 2)
                : 0.0,
            'ad_porcentaje' => abs((float) ($flash['ad_coin_in'] ?? 0)) > 0.001
                ? round($adWin / (float) $flash['ad_coin_in'] * 100, 2)
                : 0.0,
        ];
    }
}
