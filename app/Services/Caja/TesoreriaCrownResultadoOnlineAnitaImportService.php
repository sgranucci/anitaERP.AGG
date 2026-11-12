<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\TesoreriaCrownCasinoOnline;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownResultadoOnline;
use App\Models\Caja\TesoreriaCrownSlot;
use App\Models\Configuracion\Sala;
use App\Support\Caja\TesoreriaCrownPoderPublicoGenerador;
use App\Support\Caja\TesoreriaCrownResultadoOnlineCalculo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\GastronomiaSalaSupport;
use RuntimeException;
use Throwable;

/**
 * Importa resultado online Crown:
 * - Slots (resulol / sucursal sala, cncadmin)
 * - Casinos del Río online (resonline / suc. 67, ac-server1)
 * - Betsur online (resonline / suc. 68, ac-server1)
 *
 * Porcentaje = resultado / apostado × 100 (a-resonline.c).
 * Persiste subtotal_slot (soft count) para que el Generador no dependa de Anita.
 */
class TesoreriaCrownResultadoOnlineAnitaImportService
{
    /**
     * @return array{crear: int, actualizar: int, omitir: int, filas: list<array<string, mixed>>}
     */
    public function plan(string $desdeYmd, string $hastaYmd): array
    {
        [$sala, $desde, $hasta] = $this->contexto($desdeYmd, $hastaYmd);
        $existentes = TesoreriaCrownResultadoOnline::query()
            ->where('sala_id', $sala->id)
            ->whereDate('fecha', '>=', $desdeYmd)
            ->whereDate('fecha', '<=', $hastaYmd)
            ->get()
            ->keyBy(fn (TesoreriaCrownResultadoOnline $f) => $f->fecha->format('Y-m-d'));

        $slots = $this->slotsPorFecha($desde, $hasta);
        $delRio = $this->casinoOnlinePorFecha(
            (int) config('caja_anita.sucursal_del_rio_online', 67),
            $desde,
            $hasta
        );
        $betsur = $this->casinoOnlinePorFecha(
            (int) config('caja_anita.sucursal_betsur_online', 68),
            $desde,
            $hasta
        );

        $fechas = array_values(array_unique(array_merge(
            array_keys($slots),
            array_keys($delRio),
            array_keys($betsur)
        )));
        sort($fechas);

        $crear = $actualizar = $omitir = 0;
        $filas = [];
        foreach ($fechas as $fecha) {
            $s = $slots[$fecha] ?? null;
            $coinIn = round((float) ($s['coin_in'] ?? 0), 2);
            $coinOut = round((float) ($s['coin_out'] ?? 0), 2);
            $pagoManual = round((float) ($s['pago_manual'] ?? 0), 2);
            $slotResultado = $s !== null
                ? round((float) ($s['slot_resultado'] ?? ($coinIn - $coinOut - $pagoManual)), 2)
                : 0.0;
            $cantidadSlots = (int) ($s['cantidad_slots'] ?? 0);
            $subtotal = $this->resolverSubtotalSlot((int) $sala->id, $fecha, $slotResultado);

            $actual = $existentes->get($fecha);
            // Soft count nativo: si ya hay dato ERP, el import de slots no lo pisa.
            $subtotalConservar = $actual !== null && $actual->subtotal_slot !== null
                ? (float) $actual->subtotal_slot
                : $subtotal;

            $dr = $delRio[$fecha] ?? $this->casinoVacio();
            $bt = $betsur[$fecha] ?? $this->casinoVacio();
            // Si Anita no trae fila ese día y ya hay carga manual, se conserva.
            if ($actual !== null && ! isset($delRio[$fecha]) && $this->tieneCasino($actual, false)) {
                $dr = $this->casinoDesdeModelo($actual, false);
            }
            if ($actual !== null && ! isset($betsur[$fecha]) && $this->tieneCasino($actual, true)) {
                $bt = $this->casinoDesdeModelo($actual, true);
            }

            // Slots: si Anita no tiene resulol ese día, conservar lo ya cargado.
            if ($actual !== null && $s === null) {
                $coinIn = (float) $actual->coin_in;
                $coinOut = (float) $actual->coin_out;
                $pagoManual = (float) $actual->pago_manual;
                $cantidadSlots = (int) $actual->cantidad_slots;
                $slotResultado = (float) $actual->slot_resultado;
            }

            $datos = [
                'empresa_id' => (int) $sala->empresa_id,
                'sala_id' => (int) $sala->id,
                'fecha' => $fecha,
                'attendance' => (int) $dr['attendance'],
                'apostado' => (float) $dr['apostado'],
                'pagado' => (float) $dr['pagado'],
                'drop' => (float) $dr['drop'],
                'resultado' => (float) $dr['resultado'],
                'porcentaje' => (float) $dr['porcentaje'],
                'poder_publico' => (float) $dr['poder_publico'],
                'betsur_attendance' => (int) $bt['attendance'],
                'betsur_apostado' => (float) $bt['apostado'],
                'betsur_pagado' => (float) $bt['pagado'],
                'betsur_drop' => (float) $bt['drop'],
                'betsur_resultado' => (float) $bt['resultado'],
                'betsur_porcentaje' => (float) $bt['porcentaje'],
                'betsur_poder_publico' => (float) $bt['poder_publico'],
                'coin_in' => $coinIn,
                'coin_out' => $coinOut,
                'pago_manual' => $pagoManual,
                'cantidad_slots' => $cantidadSlots,
                'slot_resultado' => $slotResultado,
                'subtotal_slot' => $subtotalConservar,
            ];

            if ($actual === null) {
                $accion = 'crear';
                $crear++;
            } elseif ($this->difiere($actual, $datos)) {
                $accion = 'actualizar';
                $actualizar++;
            } else {
                $accion = 'omitir';
                $omitir++;
            }
            $filas[] = [
                'accion' => $accion,
                'id' => $actual?->id,
                'datos' => $datos,
            ];
        }

        return compact('crear', 'actualizar', 'omitir', 'filas');
    }

    /**
     * @param  array{filas: list<array<string, mixed>>}  $plan
     * @return array{altas: int, cambios: int}
     */
    public function aplicar(array $plan, ?int $usuarioId = null): array
    {
        $altas = 0;
        $cambios = 0;
        foreach ($plan['filas'] as $fila) {
            $datos = $fila['datos'];
            if ($usuarioId) {
                $datos['usuario_id'] = $usuarioId;
            }
            // Slots por sala: sin pisar Del Río/Betsur (viven en carga diaria global).
            $slots = [
                'empresa_id' => $datos['empresa_id'],
                'sala_id' => $datos['sala_id'],
                'fecha' => $datos['fecha'],
                'coin_in' => $datos['coin_in'],
                'coin_out' => $datos['coin_out'],
                'pago_manual' => $datos['pago_manual'],
                'cantidad_slots' => $datos['cantidad_slots'],
                'slot_resultado' => $datos['slot_resultado'],
                'subtotal_slot' => $datos['subtotal_slot'],
                'usuario_id' => $datos['usuario_id'] ?? null,
            ];
            if ($fila['accion'] === 'crear') {
                TesoreriaCrownResultadoOnline::query()->create($slots);
                $altas++;
            } elseif ($fila['accion'] === 'actualizar' && $fila['id']) {
                $row = TesoreriaCrownResultadoOnline::query()->find($fila['id']);
                if ($row) {
                    $row->fill($slots)->save();
                    $cambios++;
                }
            }
            $this->upsertCasinoOnlineDiario($datos, $usuarioId);
        }

        return ['altas' => $altas, 'cambios' => $cambios];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function upsertCasinoOnlineDiario(array $datos, ?int $usuarioId): void
    {
        $fecha = (string) ($datos['fecha'] ?? '');
        if ($fecha === '') {
            return;
        }
        $payload = [
            'empresa_id' => $datos['empresa_id'] ?? null,
            'fecha' => $fecha,
            'attendance' => (int) ($datos['attendance'] ?? 0),
            'apostado' => (float) ($datos['apostado'] ?? 0),
            'pagado' => (float) ($datos['pagado'] ?? 0),
            'drop' => (float) ($datos['drop'] ?? 0),
            'resultado' => (float) ($datos['resultado'] ?? 0),
            'porcentaje' => (float) ($datos['porcentaje'] ?? 0),
            'poder_publico' => (float) ($datos['poder_publico'] ?? 0),
            'betsur_attendance' => (int) ($datos['betsur_attendance'] ?? 0),
            'betsur_apostado' => (float) ($datos['betsur_apostado'] ?? 0),
            'betsur_pagado' => (float) ($datos['betsur_pagado'] ?? 0),
            'betsur_drop' => (float) ($datos['betsur_drop'] ?? 0),
            'betsur_resultado' => (float) ($datos['betsur_resultado'] ?? 0),
            'betsur_porcentaje' => (float) ($datos['betsur_porcentaje'] ?? 0),
            'betsur_poder_publico' => (float) ($datos['betsur_poder_publico'] ?? 0),
            'usuario_id' => $usuarioId,
        ];
        $actual = TesoreriaCrownCasinoOnline::deFecha($fecha);
        if ($actual) {
            // Conserva apuestas deportivas ya cargadas a mano.
            $actual->fill($payload)->save();

            return;
        }
        $payload['ad_attendance'] = 0;
        $payload['ad_apostado'] = 0;
        $payload['ad_pagado'] = 0;
        $payload['ad_drop'] = 0;
        $payload['ad_resultado'] = 0;
        $payload['ad_porcentaje'] = 0;
        $payload['ad_poder_publico'] = 0;
        TesoreriaCrownCasinoOnline::query()->create($payload);
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    private function slotsPorFecha(int $desde, int $hasta): array
    {
        $out = [];
        $sucursal = (int) config('caja_anita.sucursal_resultado_online', 15);
        try {
            // Informix acorta identificadores: coin_outol / pagomanual (resulol.sql).
            foreach ($this->consultarVentas(
                'f,cin,cout,pm,cs,sr',
                'SELECT resulol_fecha f, resulol_coin_in_ol cin, resulol_coin_outol cout,'
                .' resulol_pagomanual pm, resulol_cant_slots cs, resulol_slot_r sr'
                .' FROM resulol WHERE resulol_sucursal = '.$sucursal
                .' AND resulol_fecha BETWEEN '.$desde.' AND '.$hasta
            ) as $fila) {
                $fecha = $this->ymd((int) ($fila->f ?? 0));
                if ($fecha === '') {
                    continue;
                }
                $out[$fecha] = [
                    'coin_in' => (float) ($fila->cin ?? 0),
                    'coin_out' => (float) ($fila->cout ?? 0),
                    'pago_manual' => (float) ($fila->pm ?? 0),
                    'cantidad_slots' => (int) ($fila->cs ?? 0),
                    'slot_resultado' => (float) ($fila->sr ?? 0),
                ];
            }
        } catch (Throwable $e) {
            // sin slots online
        }

        return $out;
    }

    /**
     * @return array<string, array{attendance: int, apostado: float, pagado: float, drop: float, resultado: float, porcentaje: float, poder_publico: float}>
     */
    private function casinoOnlinePorFecha(int $sucursal, int $desde, int $hasta): array
    {
        if ($sucursal <= 0) {
            return [];
        }
        $out = [];
        try {
            // drop puede no estar en el SQL antiguo; si falla el SELECT con drop, reintentar sin él.
            $filas = $this->consultarCasinoOnline(
                'f,att,ap,pg,res,porc,pp,dr',
                'SELECT resol_fecha f, resol_att att, resol_apostado ap, resol_pagado pg,'
                .' resol_resultado res, resol_porc porc, resol_ppublico pp, resol_drop dr'
                .' FROM resonline WHERE resol_sucursal = '.$sucursal
                .' AND resol_fecha BETWEEN '.$desde.' AND '.$hasta
            );
        } catch (Throwable $e) {
            try {
                $filas = $this->consultarCasinoOnline(
                    'f,att,ap,pg,res,porc,pp',
                    'SELECT resol_fecha f, resol_att att, resol_apostado ap, resol_pagado pg,'
                    .' resol_resultado res, resol_porc porc, resol_ppublico pp'
                    .' FROM resonline WHERE resol_sucursal = '.$sucursal
                    .' AND resol_fecha BETWEEN '.$desde.' AND '.$hasta
                );
            } catch (Throwable $e2) {
                return [];
            }
        }

        foreach ($filas as $fila) {
            $fecha = $this->ymd((int) ($fila->f ?? 0));
            if ($fecha === '') {
                continue;
            }
            $apostado = round((float) ($fila->ap ?? 0), 2);
            $pagado = round((float) ($fila->pg ?? 0), 2);
            $calc = TesoreriaCrownResultadoOnlineCalculo::desdeApostadoPagado($apostado, $pagado);
            $out[$fecha] = [
                'attendance' => (int) ($fila->att ?? 0),
                'apostado' => $apostado,
                'pagado' => $pagado,
                'drop' => round((float) ($fila->dr ?? 0), 2),
                'resultado' => $calc['resultado'],
                'porcentaje' => $calc['porcentaje'],
                'poder_publico' => round((float) ($fila->pp ?? 0), 2),
            ];
        }

        return $out;
    }

    /**
     * @return array{attendance: int, apostado: float, pagado: float, drop: float, resultado: float, porcentaje: float, poder_publico: float}
     */
    private function casinoVacio(): array
    {
        return [
            'attendance' => 0,
            'apostado' => 0.0,
            'pagado' => 0.0,
            'drop' => 0.0,
            'resultado' => 0.0,
            'porcentaje' => 0.0,
            'poder_publico' => 0.0,
        ];
    }

    /**
     * @return array{attendance: int, apostado: float, pagado: float, drop: float, resultado: float, porcentaje: float, poder_publico: float}
     */
    private function casinoDesdeModelo(TesoreriaCrownResultadoOnline $fila, bool $betsur): array
    {
        if ($betsur) {
            return [
                'attendance' => (int) $fila->betsur_attendance,
                'apostado' => (float) $fila->betsur_apostado,
                'pagado' => (float) $fila->betsur_pagado,
                'drop' => (float) $fila->betsur_drop,
                'resultado' => (float) $fila->betsur_resultado,
                'porcentaje' => (float) $fila->betsur_porcentaje,
                'poder_publico' => (float) $fila->betsur_poder_publico,
            ];
        }

        return [
            'attendance' => (int) $fila->attendance,
            'apostado' => (float) $fila->apostado,
            'pagado' => (float) $fila->pagado,
            'drop' => (float) $fila->drop,
            'resultado' => (float) $fila->resultado,
            'porcentaje' => (float) $fila->porcentaje,
            'poder_publico' => (float) $fila->poder_publico,
        ];
    }

    private function tieneCasino(TesoreriaCrownResultadoOnline $fila, bool $betsur): bool
    {
        if ($betsur) {
            return abs((float) $fila->betsur_apostado) > 0.001
                || abs((float) $fila->betsur_pagado) > 0.001
                || abs((float) $fila->betsur_resultado) > 0.001;
        }

        return abs((float) $fila->apostado) > 0.001
            || abs((float) $fila->pagado) > 0.001
            || abs((float) $fila->resultado) > 0.001;
    }

    private function resolverSubtotalSlot(int $salaId, string $fechaYmd, float $slotResultado): ?float
    {
        // Preferir inversión desde ppublico Anita (soft count real de l-anexo1).
        // El recuento de validadoras ERP no es el soft count del poder público.
        try {
            $filas = $this->consultarCaja(
                'slot_dia',
                'SELECT ppub_pp_slot FROM ppublico WHERE ppub_fecha = '.(int) str_replace('-', '', $fechaYmd)
            );
            if ($filas !== []) {
                $anitaLong = (float) ($filas[0]->slot_dia ?? 0);
                $frac = round(abs($slotResultado - (float) (int) $slotResultado), 2);
                if ($frac < 0.001) {
                    $ppTrue = round($anitaLong, 2);
                } elseif ($anitaLong < 0) {
                    $ppTrue = round($anitaLong - $frac, 2);
                } else {
                    $ppTrue = round($anitaLong + (1.0 - $frac), 2);
                }
                $voucher = TesoreriaCrownPoderPublicoGenerador::voucherMesa($salaId, $fechaYmd, $avisos = []);

                return round($ppTrue + $slotResultado + $voucher, 2);
            }
        } catch (Throwable $e) {
            // sin Anita
        }

        $jornada = TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fechaYmd)
            ->first();
        if ($jornada) {
            $validadoras = round((float) TesoreriaCrownSlot::query()
                ->where('jornada_id', $jornada->id)
                ->where('estado', TesoreriaCrownSlot::RECIBIDO)
                ->where('tipo', '!=', TesoreriaCrownSlot::DROP)
                ->sum('total'), 2);
            if ($validadoras > 0.001) {
                return $validadoras;
            }
        }

        return null;
    }

    private function difiere(TesoreriaCrownResultadoOnline $actual, array $datos): bool
    {
        foreach (['cantidad_slots', 'attendance', 'betsur_attendance'] as $campo) {
            if ((int) $actual->{$campo} !== (int) $datos[$campo]) {
                return true;
            }
        }
        foreach ([
            'apostado', 'pagado', 'drop', 'resultado', 'porcentaje', 'poder_publico',
            'betsur_apostado', 'betsur_pagado', 'betsur_drop', 'betsur_resultado',
            'betsur_porcentaje', 'betsur_poder_publico',
            'coin_in', 'coin_out', 'pago_manual', 'slot_resultado',
        ] as $campo) {
            if (abs((float) $actual->{$campo} - (float) $datos[$campo]) > 0.001) {
                return true;
            }
        }
        $subActual = $actual->subtotal_slot;
        $subNuevo = $datos['subtotal_slot'];
        if ($subActual === null && $subNuevo === null) {
            return false;
        }
        if ($subActual === null || $subNuevo === null) {
            return true;
        }

        return abs((float) $subActual - (float) $subNuevo) > 0.001;
    }

    /**
     * @return array{0: Sala, 1: int, 2: int}
     */
    private function contexto(string $desdeYmd, string $hastaYmd): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new RuntimeException('La importación de resultado online solo corre en Crown.');
        }
        $codigo = strtoupper(trim((string) config('caja_anita.sala_codigo', 'CIP')));
        $salaId = GastronomiaSalaSupport::idPorCodigo($codigo);
        $sala = $salaId ? Sala::query()->find($salaId) : null;
        if ($sala === null) {
            throw new RuntimeException('No está la sala '.$codigo.'.');
        }
        $desde = (int) str_replace('-', '', $desdeYmd);
        $hasta = (int) str_replace('-', '', $hastaYmd);
        if ($desde <= 0 || $hasta < $desde) {
            throw new RuntimeException('Rango de fechas inválido.');
        }

        return [$sala, $desde, $hasta];
    }

    private function ymd(int $fechaInt): string
    {
        if ($fechaInt <= 0) {
            return '';
        }

        return sprintf(
            '%04d-%02d-%02d',
            intdiv($fechaInt, 10000),
            intdiv($fechaInt % 10000, 100),
            $fechaInt % 100
        );
    }

    /**
     * @return list<object>
     */
    private function consultarCaja(string $campos, string $sql): array
    {
        return $this->consultar($campos, $sql, (string) config('caja_anita.sistema', 'caja'), [
            'ifx_server' => (string) config('caja_anita.ifx_server', ''),
            'path_sistema' => (string) config('caja_anita.path_sistema', ''),
        ]);
    }

    /**
     * @return list<object>
     */
    private function consultarVentas(string $campos, string $sql): array
    {
        return $this->consultar($campos, $sql, 'ventas', [
            'ifx_server' => (string) config('caja_anita.ifx_server', ''),
            'path_sistema' => (string) config('caja_anita.path_sistema', ''),
        ]);
    }

    /**
     * Casino online solo en ac-server1 (acncadmin).
     *
     * @return list<object>
     */
    private function consultarCasinoOnline(string $campos, string $sql): array
    {
        return $this->consultar($campos, $sql, 'ventas', [
            'ifx_server' => (string) config('caja_anita.ifx_server_casino_online', 'acncadmin'),
            'path_sistema' => (string) config('caja_anita.path_sistema_casino_online', '/usr2/casino'),
        ]);
    }

    /**
     * @param  array{ifx_server?: string, path_sistema?: string}  $opts
     * @return list<object>
     */
    private function consultar(string $campos, string $sql, string $sistema, array $opts = []): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'customSql',
            'campos' => $campos,
            'customSql' => $sql,
            'sistema' => $sistema,
        ];
        $ifx = trim((string) ($opts['ifx_server'] ?? ''));
        if ($ifx !== '') {
            $payload['ifx_server'] = $ifx;
        }
        $path = rtrim(trim((string) ($opts['path_sistema'] ?? '')), '/');
        if ($path !== '') {
            $payload['path_sistema'] = $path;
        }
        $raw = $api->apiCall($payload);
        $error = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($error !== null && $error !== '') {
            throw new RuntimeException($error);
        }
        $filas = json_decode(is_string($raw) ? $raw : '[]');

        return is_array($filas) ? $filas : [];
    }
}
