<?php

namespace App\Services\Caja;

use App\ApiAnita;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownResultadoOnline;
use App\Models\Caja\TesoreriaCrownSlot;
use App\Models\Configuracion\Sala;
use App\Support\Caja\TesoreriaCrownPoderPublicoGenerador;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\GastronomiaSalaSupport;
use RuntimeException;
use Throwable;

/**
 * Importa resultado online de slots desde Anita (ventana slot ON-LINE / resulol).
 * Casino online (a-resonline) dejó de usarse en Anita C: columnas en 0.
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
        $fechas = array_keys($slots);
        sort($fechas);

        $crear = $actualizar = $omitir = 0;
        $filas = [];
        foreach ($fechas as $fecha) {
            $s = $slots[$fecha];
            $coinIn = round((float) ($s['coin_in'] ?? 0), 2);
            $coinOut = round((float) ($s['coin_out'] ?? 0), 2);
            $pagoManual = round((float) ($s['pago_manual'] ?? 0), 2);
            $slotResultado = round((float) ($s['slot_resultado'] ?? ($coinIn - $coinOut - $pagoManual)), 2);
            $subtotal = $this->resolverSubtotalSlot((int) $sala->id, $fecha, $slotResultado);

            $actual = $existentes->get($fecha);
            // Soft count / casino online nativos: si ya hay dato ERP, el import de slots no lo pisa.
            $subtotalConservar = $actual !== null && $actual->subtotal_slot !== null
                ? (float) $actual->subtotal_slot
                : $subtotal;
            $tieneCasinoNativo = $actual !== null && (
                abs((float) $actual->apostado) > 0.001
                || abs((float) $actual->pagado) > 0.001
                || abs((float) $actual->resultado) > 0.001
            );

            $datos = [
                'empresa_id' => (int) $sala->empresa_id,
                'sala_id' => (int) $sala->id,
                'fecha' => $fecha,
                'attendance' => $tieneCasinoNativo ? (int) $actual->attendance : 0,
                'apostado' => $tieneCasinoNativo ? (float) $actual->apostado : 0.0,
                'pagado' => $tieneCasinoNativo ? (float) $actual->pagado : 0.0,
                'drop' => $tieneCasinoNativo ? (float) $actual->drop : 0.0,
                'resultado' => $tieneCasinoNativo ? (float) $actual->resultado : 0.0,
                'porcentaje' => $tieneCasinoNativo ? (float) $actual->porcentaje : 0.0,
                'poder_publico' => $tieneCasinoNativo ? (float) $actual->poder_publico : 0.0,
                'coin_in' => $coinIn,
                'coin_out' => $coinOut,
                'pago_manual' => $pagoManual,
                'cantidad_slots' => (int) ($s['cantidad_slots'] ?? 0),
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
            if ($fila['accion'] === 'crear') {
                TesoreriaCrownResultadoOnline::query()->create($datos);
                $altas++;
            } elseif ($fila['accion'] === 'actualizar' && $fila['id']) {
                $row = TesoreriaCrownResultadoOnline::query()->find($fila['id']);
                if ($row) {
                    $row->fill($datos)->save();
                    $cambios++;
                }
            }
        }

        return ['altas' => $altas, 'cambios' => $cambios];
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

    private function resolverSubtotalSlot(int $salaId, string $fechaYmd, float $slotResultado): ?float
    {
        // Preferir inversión desde ppublico Anita (soft count real de l-anexo1).
        // El recuento de validadoras ERP no es el soft count del poder público.
        try {
            $avisos = [];
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
                $voucher = TesoreriaCrownPoderPublicoGenerador::voucherMesa($salaId, $fechaYmd, $avisos);

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
        foreach (['cantidad_slots'] as $campo) {
            if ((int) $actual->{$campo} !== (int) $datos[$campo]) {
                return true;
            }
        }
        foreach ([
            'attendance', 'apostado', 'pagado', 'drop', 'resultado', 'porcentaje', 'poder_publico',
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
        return $this->consultar($campos, $sql, (string) config('caja_anita.sistema', 'caja'));
    }

    /**
     * @return list<object>
     */
    private function consultarVentas(string $campos, string $sql): array
    {
        return $this->consultar($campos, $sql, 'ventas');
    }

    /**
     * @return list<object>
     */
    private function consultar(string $campos, string $sql, string $sistema): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'customSql',
            'campos' => $campos,
            'customSql' => $sql,
            'sistema' => $sistema,
        ];
        $ifx = trim((string) config('caja_anita.ifx_server', ''));
        if ($ifx !== '') {
            $payload['ifx_server'] = $ifx;
        }
        $path = rtrim(trim((string) config('caja_anita.path_sistema', '')), '/');
        if ($path !== '' && $sistema === (string) config('caja_anita.sistema', 'caja')) {
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
