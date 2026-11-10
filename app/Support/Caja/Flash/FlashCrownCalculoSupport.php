<?php

namespace App\Support\Caja\Flash;

use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownResultadoOnline;
use App\Models\Configuracion\Sala;
use App\Support\Caja\TesoreriaCrownAnexo1Calculo;
use App\Support\Caja\TesoreriaCrownAnexo2Calculo;
use Carbon\Carbon;
use Throwable;

/**
 * Arma el flash Crown desde Tesorería (mesas, gastro/hotel, clientes)
 * + resultado online (slots + Del Río 67 + Betsur 68).
 * Réplica de fuentes de a-resultado / l-consolida (formato Completo).
 */
final class FlashCrownCalculoSupport
{
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
        if (! $online) {
            $flash['avisos'][] = 'Sin resultado online para la sala/fecha.';

            return;
        }

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
        // Drop financiero slots ≈ soft count cuando no hay slot_d aparte.
        $flash['slot_d'] = round((float) ($online->subtotal_slot ?: 0), 2);

        // l-consolida COMPLETO: suc. 67 Del Río → ON-LINE; suc. 68 → BETSUR.
        // Coin in = apostado, Cage bal. = pagado, Win = resultado.
        $flash['ol_att'] = (int) ($online->attendance ?? 0);
        $flash['ol_drop'] = round((float) ($online->drop ?? 0), 2);
        $flash['ol_coin_in'] = round((float) ($online->apostado ?? 0), 2);
        $flash['ol_cage_bal'] = round((float) ($online->pagado ?? 0), 2);
        $flash['ol_win'] = round((float) ($online->resultado ?? 0), 2);

        $flash['bs_att'] = (int) ($online->betsur_attendance ?? 0);
        $flash['bs_coin_in'] = round((float) ($online->betsur_apostado ?? 0), 2);
        $flash['bs_cage_bal'] = round((float) ($online->betsur_pagado ?? 0), 2);
        $flash['bs_win'] = round((float) ($online->betsur_resultado ?? 0), 2);

        $flash['detalle']['fuentes'][] = 'resultado_online';
        if (abs((float) $flash['ol_win']) > 0.001 || abs((float) $flash['ol_coin_in']) > 0.001) {
            $flash['detalle']['fuentes'][] = 'del_rio_online';
        }
        if (abs((float) $flash['bs_win']) > 0.001 || abs((float) $flash['bs_coin_in']) > 0.001) {
            $flash['detalle']['fuentes'][] = 'betsur_online';
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
            if (in_array($campo, ['att', 'cant_mesas', 'cant_slots', 'cant_rul_el', 'ol_att', 'bs_att'], true)) {
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
     * Combined del mismo día año anterior (columna “Anterior” de l-consolida).
     */
    public static function combinedAnteriorAnio(int $salaId, string $fechaYmd): float
    {
        $anterior = Carbon::parse($fechaYmd)->subYear()->toDateString();
        $calc = self::deSalaFecha($salaId, $anterior);
        $gaming = (float) $calc['wheel_r'] + (float) $calc['baccarat_r'] + (float) $calc['blackjack_r']
            + (float) $calc['multi_r'] + (float) $calc['crap_r'] + (float) $calc['caribbean_r']
            + (float) $calc['poker_r'] + (float) $calc['seven_r'] + (float) $calc['super_peek_r']
            + (float) $calc['slot_r'] - (float) $calc['cash_less'] + (float) $calc['ruleta_el_r']
            + (float) ($calc['ol_win'] ?? 0) + (float) ($calc['bs_win'] ?? 0);

        return round($gaming + (float) $calc['ayb'] + (float) $calc['hotel'], 2);
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
        $gaming = round(
            $winMesas
            + (float) $flash['slot_r']
            - (float) $flash['cash_less']
            + (float) $flash['ruleta_el_r']
            + $olWin
            + $bsWin,
            2
        );
        $combined = round($gaming + (float) $flash['ayb'] + (float) $flash['hotel'], 2);
        $att = (int) ($flash['att'] ?? 0);
        $olDrop = (float) ($flash['ol_drop'] ?? 0);

        return [
            'win_mesas' => $winMesas,
            'ol_win' => round($olWin, 2),
            'bs_win' => round($bsWin, 2),
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
        ];
    }
}
