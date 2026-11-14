<?php

declare(strict_types=1);

namespace App\Services\Caja\Flash;

use App\ApiAnita;
use App\Models\Caja\Flash\FlashCrown;
use App\Models\Configuracion\Sala;
use App\Support\Caja\Flash\FlashCrownResultadoAnitaSalasSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Prepara flash_crown para facturación juego (metodología 1/10 CIP):
 * - Mesas / ruleta EL: ventas.resultado Anita
 * - Slots (slot_r): ventas.resulol (resultado online), no soft_count de resultado
 *
 * No pisa filas validadas ni días ya facturados (proc_* = * / venta_*).
 */
final class FlashCrownResultadoAnitaImportService
{
    /**
     * Campos estables en todas las salas (schema mínimo + soft/peek/multi).
     * Se evita customSql con columnas opcionales que no existen en todos los Informix.
     */
    /**
     * Campos de ventas.resultado que el flash debe replicar 1:1 (salvo slots → resulol).
     * No hardcodear a 0 un campo que exista acá: si Anita lo trae, mapearlo en mapearPayload.
     */
    private const CAMPOS_LIST = 'resul_sucursal,resul_fecha,resul_att,resul_blackjack_r,resul_wheel_r,'
        .'resul_baccarat_r,resul_caribbean_r,resul_poker_r,resul_crap_r,resul_seven_r,'
        .'resul_cashdrop,resul_cant_mesas,resul_caja_emp,resul_soft_mesas,resul_ayb,resul_tot_hotel,'
        .'resul_slot_d,resul_slot_r,resul_cant_slots,resul_hard_count,resul_soft_count,'
        .'resul_ruleta_el_d,resul_ruleta_el_r,resul_soft_rul_el,resul_cant_rul_el,resul_coin_in_rul,'
        .'resul_cash_less,resul_torneo_poker,resul_master_cup,resul_inscripcion,resul_premio,'
        .'resul_proc_mesa,resul_proc_slot,resul_super_peek_r,resul_multi_r';

    /**
     * @param  list<string>|null  $codigosSala  null = todas excepto CIP
     * @return array{
     *   desde: string,
     *   hasta: string,
     *   salas: list<array<string, mixed>>,
     *   crear: int,
     *   actualizar: int,
     *   omitir: int,
     *   errores: list<string>
     * }
     */
    public function plan(string $desdeYmd, string $hastaYmd, ?array $codigosSala = null, bool $incluirCip = false): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new RuntimeException('Solo en EMPRESA=CROWN.');
        }

        $desde = Carbon::parse($desdeYmd)->startOfDay();
        $hasta = Carbon::parse($hastaYmd)->startOfDay();
        if ($hasta->lt($desde)) {
            throw new RuntimeException('--hasta debe ser >= --desde.');
        }
        if ($desde->diffInDays($hasta) > 62) {
            throw new RuntimeException('El rango máximo es 62 días.');
        }

        $mapa = $this->resolverSalas($codigosSala, $incluirCip);
        $resumen = [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'salas' => [],
            'crear' => 0,
            'actualizar' => 0,
            'omitir' => 0,
            'errores' => [],
        ];

        $desdeInt = (int) $desde->format('Ymd');
        $hastaInt = (int) $hasta->format('Ymd');

        foreach ($mapa as $meta) {
            $sala = Sala::query()->where('codigo', $meta['codigo'])->first();
            if ($sala === null) {
                $resumen['errores'][] = $meta['codigo'].': sala ERP inexistente';
                continue;
            }

            $bloque = [
                'codigo' => $meta['codigo'],
                'sala_id' => (int) $sala->id,
                'sucursal' => (int) $meta['sucursal'],
                'ifx_server' => $meta['ifx_server'],
                'crear' => 0,
                'actualizar' => 0,
                'omitir' => 0,
                'filas' => [],
                'error' => null,
            ];

            $ifxUsado = (string) $meta['ifx_server'];
            $fallback = trim((string) ($meta['ifx_fallback'] ?? ''));
            try {
                $filasAnita = $this->leerResultado(
                    $ifxUsado,
                    (int) $meta['sucursal'],
                    $desdeInt,
                    $hastaInt,
                );
            } catch (Throwable $e) {
                if ($fallback === '') {
                    $bloque['error'] = $e->getMessage();
                    $resumen['errores'][] = $meta['codigo'].': '.$e->getMessage();
                    $resumen['salas'][] = $bloque;
                    continue;
                }
                try {
                    $filasAnita = $this->leerResultado(
                        $fallback,
                        (int) $meta['sucursal'],
                        $desdeInt,
                        $hastaInt,
                    );
                    $ifxUsado = $fallback;
                    $bloque['ifx_server'] = $fallback.' (fallback)';
                } catch (Throwable $e2) {
                    $bloque['error'] = $e->getMessage().' | fallback '.$fallback.': '.$e2->getMessage();
                    $resumen['errores'][] = $meta['codigo'].': '.$bloque['error'];
                    $resumen['salas'][] = $bloque;
                    continue;
                }
            }

            // LGR (y similares): la fila local puede existir con hotel=0 mientras AC ya tiene
            // resul_tot_hotel (l-consolida lee el consolidado). Completar hotel/ayb desde fallback.
            if ($fallback !== '' && $fallback !== $ifxUsado && $filasAnita !== []) {
                try {
                    $filasFallback = $this->leerResultado(
                        $fallback,
                        (int) $meta['sucursal'],
                        $desdeInt,
                        $hastaInt,
                    );
                    $enriquecidas = $this->completarHotelAybDesdeFallback($filasAnita, $filasFallback);
                    if ($enriquecidas > 0) {
                        $bloque['ifx_server'] = $ifxUsado.' + hotel/ayb@'.$fallback.' ('.$enriquecidas.')';
                    }
                } catch (Throwable) {
                    // Sin fallback usable: se mantiene lo del IFX primario.
                }
            }

            $onlinePorFecha = $this->leerResulol(
                $ifxUsado,
                (int) $meta['sucursal'],
                $desdeInt,
                $hastaInt,
            );
            // Si el IFX primario falló en resulol pero hay fallback, reintentar ahí.
            if ($onlinePorFecha === [] && isset($meta['ifx_fallback'])) {
                $fb = trim((string) $meta['ifx_fallback']);
                if ($fb !== '' && $fb !== $ifxUsado) {
                    $onlinePorFecha = $this->leerResulol($fb, (int) $meta['sucursal'], $desdeInt, $hastaInt);
                    if ($onlinePorFecha !== []) {
                        $bloque['ifx_server'] = $ifxUsado.' + resulol@'.$fb;
                    }
                }
            }

            $existentes = FlashCrown::query()
                ->where('sala_id', $sala->id)
                ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
                ->get()
                ->keyBy(fn (FlashCrown $f) => Carbon::parse((string) $f->fecha)->format('Y-m-d'));

            foreach ($filasAnita as $anita) {
                $fecha = $this->fechaYmd($this->entero($anita, 'resul_fecha'));
                if ($fecha === null) {
                    continue;
                }
                $payload = $this->mapearPayload(
                    $anita,
                    (int) $sala->id,
                    (int) ($sala->empresa_id ?? 1),
                    $onlinePorFecha[$fecha] ?? null,
                );
                $erp = $existentes->get($fecha);
                $decision = $this->decidir($erp, $payload);
                $bloque['filas'][] = [
                    'fecha' => $fecha,
                    'accion' => $decision['accion'],
                    'motivo' => $decision['motivo'],
                    'mesa' => $payload['_mesa'],
                    'slot' => $payload['_slot'],
                    'soft' => $payload['soft_count'],
                    'ruleta' => $payload['ruleta_el_r'],
                    'payload' => $payload,
                    'flash_id' => $erp?->id,
                ];
                if ($decision['accion'] === 'crear') {
                    $bloque['crear']++;
                } elseif (in_array($decision['accion'], ['actualizar', 'actualizar_revenue'], true)) {
                    $bloque['actualizar']++;
                } else {
                    $bloque['omitir']++;
                }
            }

            $resumen['crear'] += $bloque['crear'];
            $resumen['actualizar'] += $bloque['actualizar'];
            $resumen['omitir'] += $bloque['omitir'];
            $resumen['salas'][] = $bloque;
        }

        return $resumen;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{altas: int, cambios: int, omitidos: int}
     */
    public function aplicar(array $plan): array
    {
        $altas = 0;
        $cambios = 0;
        $omitidos = 0;

        foreach ($plan['salas'] as $bloque) {
            foreach ($bloque['filas'] as $fila) {
                if ($fila['accion'] === 'omitir') {
                    $omitidos++;
                    continue;
                }
                $payload = $fila['payload'];
                unset($payload['_mesa'], $payload['_slot']);

                DB::transaction(function () use ($fila, $payload, &$altas, &$cambios) {
                    if ($fila['accion'] === 'crear') {
                        FlashCrown::query()->create($payload);
                        $altas++;

                        return;
                    }
                    $flash = FlashCrown::query()->find($fila['flash_id']);
                    if ($flash === null) {
                        FlashCrown::query()->create($payload);
                        $altas++;

                        return;
                    }
                    if ($fila['accion'] === 'actualizar_revenue') {
                        $detallePrev = is_array($flash->detalle_json) ? $flash->detalle_json : [];
                        $detallePrev['anita_revenue'] = [
                            'ayb' => $payload['ayb'],
                            'hotel' => $payload['hotel'],
                            'caja_empleado' => $payload['caja_empleado'],
                            'fuente' => 'resultado.resul_ayb/resul_tot_hotel/resul_caja_emp',
                        ];
                        $flash->fill([
                            'ayb' => $payload['ayb'],
                            'hotel' => $payload['hotel'],
                            'caja_empleado' => $payload['caja_empleado'],
                            'detalle_json' => $detallePrev,
                        ]);
                        $flash->save();
                        $cambios++;

                        return;
                    }
                    // Conserva proc/ventas/asientos/validación y online (Del Río/Betsur/Apuestas) ya cargados.
                    // coin_in/out/pago_manual/slot_r sí se actualizan (vienen de resulol).
                    unset(
                        $payload['proc_mesa'],
                        $payload['proc_slot'],
                        $payload['proc_ruleta_el'],
                        $payload['proc_del_rio'],
                        $payload['proc_betsur'],
                        $payload['proc_apuestas'],
                        $payload['venta_mesa_id'],
                        $payload['venta_slot_id'],
                        $payload['venta_ruleta_el_id'],
                        $payload['venta_del_rio_id'],
                        $payload['venta_betsur_id'],
                        $payload['venta_apuestas_id'],
                        $payload['asiento_mesa_id'],
                        $payload['asiento_slot_id'],
                        $payload['asiento_ruleta_el_id'],
                        $payload['asiento_del_rio_id'],
                        $payload['asiento_betsur_id'],
                        $payload['asiento_apuestas_id'],
                        $payload['validado'],
                        $payload['validado_en'],
                        $payload['validado_usuario_id'],
                        $payload['ol_att'],
                        $payload['ol_drop'],
                        $payload['ol_coin_in'],
                        $payload['ol_cage_bal'],
                        $payload['ol_win'],
                        $payload['bs_att'],
                        $payload['bs_coin_in'],
                        $payload['bs_cage_bal'],
                        $payload['bs_win'],
                        $payload['ad_att'],
                        $payload['ad_coin_in'],
                        $payload['ad_cage_bal'],
                        $payload['ad_win'],
                    );
                    $detallePrev = is_array($flash->detalle_json) ? $flash->detalle_json : [];
                    $detalleNuevo = is_array($payload['detalle_json'] ?? null) ? $payload['detalle_json'] : [];
                    $payload['detalle_json'] = array_merge($detallePrev, $detalleNuevo);
                    $flash->fill($payload);
                    $flash->save();
                    $cambios++;
                });
            }
        }

        return compact('altas', 'cambios', 'omitidos');
    }

    /**
     * @param  list<string>|null  $codigos
     * @return list<array{codigo: string, sucursal: int, ifx_server: string}>
     */
    private function resolverSalas(?array $codigos, bool $incluirCip): array
    {
        $todas = FlashCrownResultadoAnitaSalasSupport::todas();
        if ($codigos === null || $codigos === []) {
            return array_values(array_filter(
                $todas,
                static fn (array $s) => $incluirCip || $s['codigo'] !== 'CIP'
            ));
        }

        $out = [];
        foreach ($codigos as $codigo) {
            $meta = FlashCrownResultadoAnitaSalasSupport::porCodigo((string) $codigo);
            if ($meta === null) {
                throw new RuntimeException('Sala desconocida: '.$codigo);
            }
            if (! $incluirCip && $meta['codigo'] === 'CIP') {
                continue;
            }
            $out[] = $meta;
        }

        return $out;
    }

    /**
     * @return list<object>
     */
    private function leerResultado(string $ifxServer, int $sucursal, int $desdeInt, int $hastaInt): array
    {
        $api = new ApiAnita;
        // customSql con rango: `list` + where del bridge suele ignorar el filtro y
        // descarga todo el historial de la sucursal → OOM en el request web (512M).
        $sql = 'SELECT '.self::CAMPOS_LIST
            .' FROM resultado WHERE resul_sucursal = '.$sucursal
            .' AND resul_fecha BETWEEN '.$desdeInt.' AND '.$hastaInt
            .' ORDER BY resul_fecha';
        $raw = $api->apiCall([
            'acc' => 'customSql',
            'campos' => self::CAMPOS_LIST,
            'customSql' => $sql,
            'sistema' => 'ventas',
            'path_sistema' => (string) config('caja_anita.path_sistema', '/usr2/casino'),
            'ifx_server' => $ifxServer,
        ]);
        $error = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($error !== null && $error !== '') {
            throw new RuntimeException($error);
        }
        $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : '');
        unset($raw);
        $out = [];
        foreach ($filas as $fila) {
            if ((int) ($fila->resul_sucursal ?? 0) !== $sucursal) {
                continue;
            }
            $f = (int) ($fila->resul_fecha ?? 0);
            if ($f < $desdeInt || $f > $hastaInt) {
                continue;
            }
            $out[] = $fila;
        }
        unset($filas);
        usort($out, static fn ($a, $b) => (int) $a->resul_fecha <=> (int) $b->resul_fecha);

        return $out;
    }

    /**
     * Si el IFX local trae hotel en cero y el fallback (p. ej. acncadmin) tiene valor,
     * completa resul_tot_hotel / resul_ayb para alinear con l-consolida.
     *
     * @param  list<object>  $primarias
     * @param  list<object>  $fallback
     */
    private function completarHotelAybDesdeFallback(array $primarias, array $fallback): int
    {
        $porFecha = [];
        foreach ($fallback as $fila) {
            $f = (int) ($fila->resul_fecha ?? 0);
            if ($f > 0) {
                $porFecha[$f] = $fila;
            }
        }

        $n = 0;
        foreach ($primarias as $fila) {
            $f = (int) ($fila->resul_fecha ?? 0);
            $alt = $porFecha[$f] ?? null;
            if ($alt === null) {
                continue;
            }
            $hotelLocal = $this->num($fila, 'resul_tot_hotel');
            $hotelAlt = $this->num($alt, 'resul_tot_hotel');
            if (abs($hotelLocal) >= 0.02 || abs($hotelAlt) < 0.02) {
                continue;
            }
            $fila->resul_tot_hotel = $alt->resul_tot_hotel;
            // Ayb del consolidado solo si el local está vacío y el fallback no.
            if (abs($this->num($fila, 'resul_ayb')) < 0.02 && abs($this->num($alt, 'resul_ayb')) >= 0.02) {
                $fila->resul_ayb = $alt->resul_ayb;
            }
            $n++;
        }

        return $n;
    }

    /**
     * @return array<string, array{coin_in: float, coin_out: float, pago_manual: float, cant_slots: int, slot_resultado: float}>
     */
    private function leerResulol(string $ifxServer, int $sucursal, int $desdeInt, int $hastaInt): array
    {
        $api = new ApiAnita;
        $sql = 'SELECT resulol_fecha f, resulol_coin_in_ol cin, resulol_coin_outol cout,'
            .' resulol_pagomanual pm, resulol_cant_slots cs, resulol_slot_r sr'
            .' FROM resulol WHERE resulol_sucursal = '.$sucursal
            .' AND resulol_fecha BETWEEN '.$desdeInt.' AND '.$hastaInt;
        try {
            $raw = $api->apiCall([
                'acc' => 'customSql',
                'campos' => 'f,cin,cout,pm,cs,sr',
                'customSql' => $sql,
                'sistema' => 'ventas',
                'path_sistema' => (string) config('caja_anita.path_sistema', '/usr2/casino'),
                'ifx_server' => $ifxServer,
            ]);
            $error = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
            if ($error !== null && $error !== '') {
                return [];
            }
            $filas = json_decode(is_string($raw) ? $raw : '[]');
            if (! is_array($filas)) {
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        $out = [];
        foreach ($filas as $fila) {
            $fecha = $this->fechaYmd((int) ($fila->f ?? 0));
            if ($fecha === null) {
                continue;
            }
            $coinIn = round((float) ($fila->cin ?? 0), 2);
            $coinOut = round((float) ($fila->cout ?? 0), 2);
            $pagoManual = round((float) ($fila->pm ?? 0), 2);
            $slotR = round((float) ($fila->sr ?? 0), 2);
            if (abs($slotR) < 0.0001) {
                $slotR = round($coinIn - $coinOut - $pagoManual, 2);
            }
            $out[$fecha] = [
                'coin_in' => $coinIn,
                'coin_out' => $coinOut,
                'pago_manual' => $pagoManual,
                'cant_slots' => (int) ($fila->cs ?? 0),
                'slot_resultado' => $slotR,
            ];
        }

        return $out;
    }

    private function num(object $anita, string $campo): float
    {
        return round((float) ($anita->{$campo} ?? 0), 2);
    }

    private function entero(object $anita, string $campo): int
    {
        return (int) ($anita->{$campo} ?? 0);
    }

    /**
     * @param  array{coin_in: float, coin_out: float, pago_manual: float, cant_slots: int, slot_resultado: float}|null  $online
     * @return array<string, mixed>
     */
    private function mapearPayload(object $anita, int $salaId, int $empresaId, ?array $online): array
    {
        $fecha = $this->fechaYmd($this->entero($anita, 'resul_fecha'));
        $soft = $this->num($anita, 'resul_soft_count');
        $slotRAnita = $this->num($anita, 'resul_slot_r');

        // Metodología 1/10: facturar slots con win online (resulol), no soft_count.
        $tieneOnline = $online !== null;
        $slotR = $tieneOnline
            ? round((float) $online['slot_resultado'], 2)
            : $slotRAnita;
        $coinIn = $tieneOnline ? round((float) $online['coin_in'], 2) : 0.0;
        $coinOut = $tieneOnline ? round((float) $online['coin_out'], 2) : 0.0;
        $pagoManual = $tieneOnline ? round((float) $online['pago_manual'], 2) : 0.0;
        $cantSlots = $tieneOnline
            ? (int) $online['cant_slots']
            : $this->entero($anita, 'resul_cant_slots');

        $blackjack = $this->num($anita, 'resul_blackjack_r');
        $wheel = $this->num($anita, 'resul_wheel_r');
        $baccarat = $this->num($anita, 'resul_baccarat_r');
        $caribbean = $this->num($anita, 'resul_caribbean_r');
        $poker = $this->num($anita, 'resul_poker_r');
        $crap = $this->num($anita, 'resul_crap_r');
        $seven = $this->num($anita, 'resul_seven_r');
        $peek = $this->num($anita, 'resul_super_peek_r');
        $multi = $this->num($anita, 'resul_multi_r');
        $mesa = round($blackjack + $wheel + $baccarat + $caribbean + $poker + $crap + $seven + $peek + $multi, 2);

        $fuentes = ['resultado_anita'];
        if ($tieneOnline) {
            $fuentes[] = 'resulol';
        }

        return [
            'sala_id' => $salaId,
            'empresa_id' => $empresaId,
            'fecha' => $fecha,
            'att' => $this->entero($anita, 'resul_att'),
            'cashdrop' => $this->num($anita, 'resul_cashdrop'),
            'soft_mesas' => $this->num($anita, 'resul_soft_mesas'),
            'cant_mesas' => $this->entero($anita, 'resul_cant_mesas'),
            'caja_empleado' => $this->num($anita, 'resul_caja_emp'),
            'wheel_r' => $wheel,
            'baccarat_r' => $baccarat,
            'blackjack_r' => $blackjack,
            'multi_r' => $multi,
            'crap_r' => $crap,
            'caribbean_r' => $caribbean,
            'poker_r' => $poker,
            'seven_r' => $seven,
            'super_peek_r' => $peek,
            'torneo_poker' => $this->num($anita, 'resul_torneo_poker'),
            'master_cup' => $this->num($anita, 'resul_master_cup'),
            'inscripcion' => $this->num($anita, 'resul_inscripcion'),
            'premio' => $this->num($anita, 'resul_premio'),
            'slot_d' => $this->num($anita, 'resul_slot_d'),
            'soft_count' => $soft,
            'hard_count' => $this->num($anita, 'resul_hard_count'),
            'cant_slots' => $cantSlots,
            'coin_in' => $coinIn,
            'coin_out' => $coinOut,
            'pago_manual' => $pagoManual,
            'slot_r' => $slotR,
            'cash_less' => $this->num($anita, 'resul_cash_less'),
            'ruleta_el_d' => $this->num($anita, 'resul_ruleta_el_d'),
            'ruleta_el_r' => $this->num($anita, 'resul_ruleta_el_r'),
            'soft_rul_el' => $this->num($anita, 'resul_soft_rul_el'),
            'cant_rul_el' => $this->entero($anita, 'resul_cant_rul_el'),
            'coin_in_rul' => $this->num($anita, 'resul_coin_in_rul'),
            'ayb' => $this->num($anita, 'resul_ayb'),
            // l-flashcasino / a-resultado: Hotel = resul_tot_hotel (todas las salas, no solo CIP).
            'hotel' => $this->num($anita, 'resul_tot_hotel'),
            'proc_mesa' => ' ',
            'proc_slot' => ' ',
            'proc_ruleta_el' => ' ',
            'origen' => FlashCrown::ORIGEN_AUTO,
            'calculado_en' => now(),
            'detalle_json' => [
                'fuentes' => $fuentes,
                'anita' => [
                    'slot_r_resultado' => $slotRAnita,
                    'soft_count' => $soft,
                    'slot_r_facturable' => $slotR,
                    'slot_fuente' => $tieneOnline ? 'resulol' : 'resultado.slot_r',
                    'sucursal' => $this->entero($anita, 'resul_sucursal'),
                ],
            ],
            '_mesa' => $mesa,
            '_slot' => $slotR,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{accion: string, motivo: string}
     */
    private function decidir(?FlashCrown $erp, array $payload): array
    {
        if ($erp === null) {
            return ['accion' => 'crear', 'motivo' => 'sin flash'];
        }

        $diffHotel = abs((float) $erp->hotel - (float) $payload['hotel']) >= 0.02;
        $diffAyb = abs((float) $erp->ayb - (float) $payload['ayb']) >= 0.02;
        $diffCajaEmp = abs((float) $erp->caja_empleado - (float) $payload['caja_empleado']) >= 0.02;

        // Validado / ya facturado: igual se puede alinear Hotel/F&B/caja emp. (no tocan proc_*/ventas).
        if ($erp->estaValidado() || $this->yaFacturado($erp)) {
            if ($diffHotel || $diffAyb || $diffCajaEmp) {
                return [
                    'accion' => 'actualizar_revenue',
                    'motivo' => $erp->estaValidado() ? 'validado+revenue' : 'facturado+revenue',
                ];
            }

            return ['accion' => 'omitir', 'motivo' => $erp->estaValidado() ? 'validado' : 'ya facturado'];
        }

        $mesaErp = round(
            (float) $erp->blackjack_r + (float) $erp->wheel_r + (float) $erp->baccarat_r
            + (float) $erp->caribbean_r + (float) $erp->poker_r + (float) $erp->crap_r
            + (float) $erp->seven_r + (float) $erp->super_peek_r + (float) $erp->multi_r,
            2
        );
        // Misma base de facturación: solo slot_r (online), no soft_count.
        $slotErp = round((float) $erp->slot_r, 2);
        $igualJuego = abs($mesaErp - (float) $payload['_mesa']) < 0.02
            && abs($slotErp - (float) $payload['_slot']) < 0.02
            && abs((float) $erp->ruleta_el_r - (float) $payload['ruleta_el_r']) < 0.02;

        if ($igualJuego && ! $diffHotel && ! $diffAyb && ! $diffCajaEmp) {
            return ['accion' => 'omitir', 'motivo' => 'igual'];
        }
        if ($igualJuego && ($diffHotel || $diffAyb || $diffCajaEmp)) {
            return ['accion' => 'actualizar_revenue', 'motivo' => 'hotel/ayb/caja_emp'];
        }

        return ['accion' => 'actualizar', 'motivo' => 'diff'];
    }

    private function yaFacturado(FlashCrown $flash): bool
    {
        foreach (['proc_mesa', 'proc_slot', 'proc_ruleta_el', 'proc_del_rio', 'proc_betsur', 'proc_apuestas'] as $flag) {
            if (trim((string) ($flash->{$flag} ?? '')) === '*') {
                return true;
            }
        }
        foreach ([
            'venta_mesa_id', 'venta_slot_id', 'venta_ruleta_el_id',
            'venta_del_rio_id', 'venta_betsur_id', 'venta_apuestas_id',
            'asiento_mesa_id', 'asiento_slot_id', 'asiento_ruleta_el_id',
            'asiento_del_rio_id', 'asiento_betsur_id', 'asiento_apuestas_id',
        ] as $campo) {
            if ((int) ($flash->{$campo} ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    private function fechaYmd(int $yyyymmdd): ?string
    {
        if ($yyyymmdd < 19000101 || $yyyymmdd > 29991231) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Ymd', (string) $yyyymmdd)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
