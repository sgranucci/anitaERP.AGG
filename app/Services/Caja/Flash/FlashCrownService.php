<?php

namespace App\Services\Caja\Flash;

use App\Models\Caja\Flash\FlashCrown;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Configuracion\Sala;
use App\Support\Caja\Flash\FlashCrownCalculoSupport;
use App\Support\Caja\Flash\FlashCrownResultadoAnitaSalasSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class FlashCrownService
{
    public function __construct(
        private readonly FlashCrownResultadoAnitaImportService $anitaImport,
    ) {}

    /**
     * Precalienta el listado con fallback Anita, sala por sala.
     * No arma un plan multi-sala: cada Informix puede devolver mucho JSON y juntas
     * explotan el memory_limit del request web.
     *
     * @param  list<int>  $salaIds
     * @return list<string> avisos
     */
    public function asegurarListadoDesdeAnita(array $salaIds, string $fechaYmd): array
    {
        $fecha = Carbon::parse($fechaYmd)->toDateString();
        $avisos = [];

        foreach ($salaIds as $salaId) {
            $salaId = (int) $salaId;
            $existente = FlashCrown::query()
                ->where('sala_id', $salaId)
                ->whereDate('fecha', $fecha)
                ->first();

            $tieneJornada = TesoreriaCrownJornada::query()
                ->where('sala_id', $salaId)
                ->whereDate('fecha', $fecha)
                ->exists();
            if ($tieneJornada) {
                continue;
            }
            if ($existente && ! $existente->necesitaFallbackAnita()) {
                continue;
            }
            if (! $this->debeReintentarFallbackAnita($existente)) {
                continue;
            }

            $codigo = (string) (Sala::query()->whereKey($salaId)->value('codigo') ?? '');
            if ($codigo === '' || FlashCrownResultadoAnitaSalasSupport::porCodigo($codigo) === null) {
                continue;
            }

            try {
                $importado = $this->asegurarDesdeAnita($salaId, $fecha);
                if ($importado === null || ! $importado->esReplicaAnita()) {
                    // Sin réplica usable: el intento ya quedó marcado en asegurarDesdeAnita.
                    continue;
                }
            } catch (Throwable $e) {
                Log::warning('flash_crown.fallback_anita.listado.error', [
                    'fecha' => $fecha,
                    'sala_id' => $salaId,
                    'codigo' => $codigo,
                    'error' => $e->getMessage(),
                ]);
                $avisos[] = $codigo.': '.$e->getMessage();
                $this->marcarIntentoFallbackAnita($salaId, $fecha, ok: false, detalle: $e->getMessage());
            }
        }

        return $avisos;
    }

    /**
     * Listado / consulta: si el flash ya tiene el día, no lo pisa la jornada
     * (abierta o cerrada: mismo criterio). Solo Recalcular / forzar regenera desde tesorería.
     * Sin flash y sin jornada → intenta Anita (resultado + resulol).
     * Online (Del Río/Betsur/Apuestas) no se persiste: se lee vivo al consolidar/vista.
     */
    public function obtenerParaConsulta(int $salaId, string $fechaYmd, bool $forzarRecalcTesoreria = false): FlashCrown
    {
        $fecha = Carbon::parse($fechaYmd)->toDateString();
        $existente = FlashCrown::query()
            ->with(['sala', 'empresa'])
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fecha)
            ->first();

        // Día ya cargado: conservar; solo completar slots si Anita no trajo resulol.
        // Excepción: slot_r solo (GST) con att/soft vacíos → sigue necesitando Anita.
        if ($existente && ! $forzarRecalcTesoreria && $existente->tieneDiaCargado()
            && ! $existente->necesitaFallbackAnita()) {
            return $this->completarSlotsVaciosPersistiendo($existente, $fecha);
        }

        $tieneJornada = TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fecha)
            ->exists();

        if ($tieneJornada || $forzarRecalcTesoreria) {
            return $this->sincronizar($salaId, $fecha, [], persistir: true, forzar: $forzarRecalcTesoreria);
        }

        // Sin tesorería: fallback Anita si no hay flash o falta fuente resultado_anita
        // (p. ej. hueco origen=auto o solo resultado_online). No bloquear reintento.
        if (! $existente || $existente->necesitaFallbackAnita()) {
            if ($this->debeReintentarFallbackAnita($existente)) {
                $importado = $this->asegurarDesdeAnita($salaId, $fecha);
                if ($importado !== null && $importado->esReplicaAnita()) {
                    return $this->completarSlotsVaciosPersistiendo($importado, $fecha);
                }
                if ($importado !== null) {
                    $existente = $importado;
                }
            }
        }

        // Sin Anita usable: conservar lo que haya (aunque sea hueco).
        if ($existente) {
            return $existente;
        }

        // Último recurso: calc vacío (avisos de sin jornada) + marca para no martillar Anita.
        $vacio = $this->sincronizar($salaId, $fecha, [], persistir: true, forzar: false);
        $this->marcarIntentoFallbackAnita(
            $salaId,
            $fecha,
            ok: false,
            detalle: 'sin filas Anita; hueco local',
            flash: $vacio,
        );

        return $vacio->fresh(['sala', 'empresa']) ?? $vacio;
    }

    /**
     * Calcula desde tesorería y opcionalmente persiste (respeta overrides y no pisa validado salvo forzar).
     *
     * @param  array<string, mixed>  $overrides
     */
    public function sincronizar(
        int $salaId,
        string $fechaYmd,
        array $overrides = [],
        bool $persistir = true,
        bool $forzar = false,
        bool $reemplazarOverrides = false,
    ): FlashCrown {
        $fecha = Carbon::parse($fechaYmd)->toDateString();
        $existente = FlashCrown::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fecha)
            ->first();

        if ($existente && $existente->estaValidado() && ! $forzar) {
            return $existente;
        }

        // Día ya cargado: no recalcular por jornada (abierta o cerrada) salvo --forzar /
        // Recalcular. Sí completa/refresca slots desde resultado_online si corresponde.
        if ($existente && $existente->tieneDiaCargado() && ! $forzar) {
            return $this->completarSlotsVaciosPersistiendo($existente, $fecha);
        }

        $tieneJornada = TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fecha)
            ->exists();

        // Sin jornada: no recalcular desde tesorería vacía (borra mesas ya cargadas).
        if ($existente && ! $tieneJornada && ! $forzar) {
            return $existente->loadMissing(['sala', 'empresa']);
        }

        $calculado = FlashCrownCalculoSupport::deSalaFecha($salaId, $fecha);
        // Por si forzar=true con vivo incompleto: no borrar lo ya importado.
        if ($existente) {
            foreach ([
                'ayb', 'hotel', 'caja_empleado',
                'coin_in', 'coin_out', 'pago_manual', 'slot_r',
                'soft_count', 'hard_count', 'cant_slots', 'slot_d',
            ] as $campo) {
                if (abs((float) ($calculado[$campo] ?? 0)) < 0.001
                    && abs((float) $existente->{$campo}) >= 0.001) {
                    $calculado[$campo] = round((float) $existente->{$campo}, 2);
                }
            }
            $calculado = $this->conservarJuegoAnitaSiVivoVacio($calculado, $existente);
        }
        $overridesPrevios = is_array($existente?->overrides_json) ? $existente->overrides_json : [];
        $overridesMerged = $reemplazarOverrides
            ? $overrides
            : array_merge($overridesPrevios, $overrides);
        if ($overridesMerged !== []) {
            $calculado = FlashCrownCalculoSupport::aplicarOverrides($calculado, $overridesMerged);
        }

        $payload = $this->payloadDesdeCalculo($calculado, $overridesMerged);

        if (! $persistir) {
            $tmp = new FlashCrown($payload);
            $tmp->id = $existente->id ?? 0;

            return $tmp;
        }

        return DB::transaction(function () use ($existente, $payload, $salaId, $fecha) {
            if ($existente) {
                $payload['actualizousuario_id'] = Auth::id();
                $existente->fill($payload);
                $existente->save();

                return $existente->fresh(['sala', 'empresa']);
            }

            $payload['sala_id'] = $salaId;
            $payload['fecha'] = $fecha;
            $payload['creousuario_id'] = Auth::id();

            return FlashCrown::query()->create($payload)->fresh(['sala', 'empresa']);
        });
    }

    /**
     * @param  array<string, mixed>  $calculado
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function payloadDesdeCalculo(array $calculado, array $overrides = []): array
    {
        $payload = [];
        $onlineGlobal = array_flip(FlashCrown::camposOnlineGlobal());
        foreach (FlashCrown::camposNumericos() as $campo) {
            // Online global se lee siempre en vivo desde tesoreria_crown_casino_online.
            // No persistir al sincronizar (evita pisar octubre / multiplicar por sala).
            // Solo se graba si hay override manual explícito.
            if (isset($onlineGlobal[$campo]) && ! array_key_exists($campo, $overrides)) {
                continue;
            }
            $payload[$campo] = $calculado[$campo] ?? 0;
        }
        $payload['empresa_id'] = (int) ($calculado['empresa_id'] ?? 0);
        $payload['comentario'] = $calculado['comentario'] ?? null;
        $payload['overrides_json'] = $overrides === [] ? null : $overrides;
        $payload['detalle_json'] = [
            'avisos' => $calculado['avisos'] ?? [],
            'detalle' => $calculado['detalle'] ?? [],
            'metricas' => FlashCrownCalculoSupport::metricasDerivadas($calculado),
        ];
        $payload['origen'] = $overrides === []
            ? FlashCrown::ORIGEN_AUTO
            : FlashCrown::ORIGEN_MIXTO;
        $payload['calculado_en'] = now();

        return $payload;
    }

    public function validar(FlashCrown $flash, int $usuarioId): FlashCrown
    {
        $flash->validado = true;
        $flash->validado_en = now();
        $flash->validado_usuario_id = $usuarioId;
        $flash->save();

        return $flash->fresh();
    }

    public function quitarValidacion(FlashCrown $flash): FlashCrown
    {
        $flash->validado = false;
        $flash->validado_en = null;
        $flash->validado_usuario_id = null;
        $flash->save();

        return $flash->fresh();
    }

    /**
     * Consolida varias salas para una fecha (suma). Online = bloque empresa (vivo, una vez).
     *
     * @param  list<FlashCrown>  $filas
     * @return array<string, float|int>
     */
    public function consolidar(array $filas): array
    {
        $tot = [];
        $globales = array_flip(FlashCrown::camposOnlineGlobal());
        foreach (FlashCrown::camposNumericos() as $campo) {
            $tot[$campo] = 0;
        }
        foreach ($filas as $fila) {
            foreach (FlashCrown::camposNumericos() as $campo) {
                $valor = (float) ($fila->{$campo} ?? 0);
                if (isset($globales[$campo])) {
                    // Online no viene de la sala; se aplica al final desde la carga diaria.
                    continue;
                }
                $tot[$campo] = round((float) $tot[$campo] + $valor, 2);
            }
        }
        $tot['att'] = (int) round($tot['att']);
        $tot['cant_mesas'] = (int) round($tot['cant_mesas']);
        $tot['cant_slots'] = (int) round($tot['cant_slots']);
        $tot['cant_rul_el'] = (int) round($tot['cant_rul_el']);

        $fecha = null;
        foreach ($filas as $fila) {
            if ($fila->fecha ?? null) {
                $fecha = $fila->fecha instanceof Carbon
                    ? $fila->fecha->toDateString()
                    : Carbon::parse((string) $fila->fecha)->toDateString();
                break;
            }
        }
        if ($fecha !== null) {
            foreach (FlashCrownCalculoSupport::valoresOnlineGlobal($fecha) as $campo => $valor) {
                $tot[$campo] = $valor;
            }
            // Anita: clientes consolidado = attendance salas + OL + BS + AD.
            $tot['att'] = FlashCrownCalculoSupport::attendanceConsolidado($tot);
        }

        return $tot;
    }

    /**
     * Tras corregir resultado_online (GST): empuja slots al flash del día
     * si no está validado ni facturado. No toca mesas/ayb/hotel Anita.
     */
    public function aplicarResultadoOnlineEnFlashSiCorresponde(int $salaId, string $fechaYmd): ?FlashCrown
    {
        $fecha = Carbon::parse($fechaYmd)->toDateString();
        $flash = FlashCrown::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fecha)
            ->first();
        if ($flash === null || $flash->estaValidado()) {
            return $flash;
        }
        foreach (['proc_slot', 'venta_slot_id', 'asiento_slot_id'] as $campo) {
            $valor = $flash->{$campo} ?? null;
            if ($campo === 'proc_slot' && trim((string) $valor) === '*') {
                return $flash;
            }
            if (in_array($campo, ['venta_slot_id', 'asiento_slot_id'], true) && (int) $valor > 0) {
                return $flash;
            }
        }

        return $this->completarSlotsVaciosPersistiendo($flash, $fecha);
    }

    private function completarSlotsVaciosPersistiendo(FlashCrown $flash, string $fechaYmd): FlashCrown
    {
        $antesIn = (float) $flash->coin_in;
        $antesOut = (float) $flash->coin_out;
        $antesPm = (float) $flash->pago_manual;
        $antesSlot = (float) $flash->slot_r;
        $base = FlashCrownCalculoSupport::completarSlotsVaciosDesdeResultadoOnline(
            $flash->toArray(),
            (int) $flash->sala_id,
            $fechaYmd,
        );
        $nuevoIn = (float) ($base['coin_in'] ?? 0);
        $nuevoOut = (float) ($base['coin_out'] ?? 0);
        $nuevoPm = (float) ($base['pago_manual'] ?? 0);
        $nuevoSlot = (float) ($base['slot_r'] ?? 0);
        if (abs($nuevoIn - $antesIn) < 0.02
            && abs($nuevoOut - $antesOut) < 0.02
            && abs($nuevoPm - $antesPm) < 0.02
            && abs($nuevoSlot - $antesSlot) < 0.02) {
            return $flash->loadMissing(['sala', 'empresa']);
        }

        $flash->fill([
            'coin_in' => $nuevoIn,
            'coin_out' => $nuevoOut,
            'pago_manual' => $nuevoPm,
            'slot_r' => $nuevoSlot,
            'cant_slots' => (int) ($base['cant_slots'] ?? $flash->cant_slots),
            'detalle_json' => $base['detalle_json'] ?? $flash->detalle_json,
        ]);
        $flash->save();

        return $flash->fresh(['sala', 'empresa']);
    }

    private function asegurarDesdeAnita(int $salaId, string $fechaYmd): ?FlashCrown
    {
        $codigo = (string) (Sala::query()->whereKey($salaId)->value('codigo') ?? '');
        if ($codigo === '' || FlashCrownResultadoAnitaSalasSupport::porCodigo($codigo) === null) {
            return null;
        }

        try {
            $plan = $this->anitaImport->plan($fechaYmd, $fechaYmd, [$codigo], incluirCip: true);
            $erroresSala = [];
            foreach ($plan['salas'] ?? [] as $bloque) {
                if (! empty($bloque['error'])) {
                    $erroresSala[] = (string) $bloque['error'];
                }
            }
            $movimientos = (int) ($plan['crear'] ?? 0) + (int) ($plan['actualizar'] ?? 0);
            $tuvoFilasAnita = false;
            foreach ($plan['salas'] ?? [] as $bloque) {
                if (! empty($bloque['filas'])) {
                    $tuvoFilasAnita = true;
                    break;
                }
            }
            if ($movimientos > 0) {
                $this->anitaImport->aplicar($plan);
            } elseif ($erroresSala !== []) {
                $this->marcarIntentoFallbackAnita(
                    $salaId,
                    $fechaYmd,
                    ok: false,
                    detalle: implode(' | ', $erroresSala)
                );
            } elseif (! $tuvoFilasAnita) {
                $this->marcarIntentoFallbackAnita(
                    $salaId,
                    $fechaYmd,
                    ok: false,
                    detalle: 'Anita sin filas resultado/resulol para la fecha'
                );
            }
            // Omit-only con filas Anita: no marcar "sin filas" (evita throttle falso).
        } catch (Throwable $e) {
            Log::warning('flash_crown.fallback_anita.error', [
                'sala_id' => $salaId,
                'fecha' => $fechaYmd,
                'codigo' => $codigo,
                'error' => $e->getMessage(),
            ]);
            $this->marcarIntentoFallbackAnita($salaId, $fechaYmd, ok: false, detalle: $e->getMessage());

            return FlashCrown::query()
                ->with(['sala', 'empresa'])
                ->where('sala_id', $salaId)
                ->whereDate('fecha', $fechaYmd)
                ->first();
        }

        $flash = FlashCrown::query()
            ->with(['sala', 'empresa'])
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fechaYmd)
            ->first();

        if ($flash !== null && $flash->esReplicaAnita()) {
            $this->marcarIntentoFallbackAnita($salaId, $fechaYmd, ok: true, detalle: 'ok', flash: $flash);
        }

        return $flash;
    }

    /**
     * Evita martillar Informix en cada F5 si Anita aún no tiene el día.
     * Si el flash sigue incompleto (att/soft en 0), reintenta aunque el último
     * intento se haya marcado ok (pull parcial / omit previo).
     */
    private function debeReintentarFallbackAnita(?FlashCrown $existente): bool
    {
        if ($existente === null) {
            return true;
        }
        $detalle = is_array($existente->detalle_json) ? $existente->detalle_json : [];
        $intento = is_array($detalle['anita_fallback'] ?? null) ? $detalle['anita_fallback'] : null;
        if ($intento === null || empty($intento['en'])) {
            return true;
        }
        $incompleto = $existente->necesitaFallbackAnita();
        if (! empty($intento['ok']) && ! $incompleto) {
            return false;
        }
        try {
            $esperaMin = (! empty($intento['ok']) && $incompleto) ? 5 : 30;

            return Carbon::parse((string) $intento['en'])->lte(now()->subMinutes($esperaMin));
        } catch (Throwable) {
            return true;
        }
    }

    private function marcarIntentoFallbackAnita(
        int $salaId,
        string $fechaYmd,
        bool $ok,
        string $detalle,
        ?FlashCrown $flash = null,
    ): void {
        $flash ??= FlashCrown::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fechaYmd)
            ->first();
        if ($flash === null) {
            return;
        }
        $json = is_array($flash->detalle_json) ? $flash->detalle_json : [];
        $json['anita_fallback'] = [
            'ok' => $ok,
            'en' => now()->toDateTimeString(),
            'detalle' => mb_substr($detalle, 0, 500),
        ];
        $flash->detalle_json = $json;
        $flash->save();
    }

    /**
     * @param  array<string, mixed>  $calc
     * @return array<string, mixed>
     */
    private function conservarJuegoAnitaSiVivoVacio(array $calc, FlashCrown $stored): array
    {
        $storedArr = $stored->toArray();
        $mesaCalc = (float) FlashCrownCalculoSupport::metricasDerivadas($calc)['win_mesas'];
        $mesaStored = (float) FlashCrownCalculoSupport::metricasDerivadas($storedArr)['win_mesas'];
        $slotCalc = (float) ($calc['slot_r'] ?? 0);
        $slotStored = (float) ($stored->slot_r ?? 0);
        $vivoIncompleto = FlashCrownCalculoSupport::vivoJornadaIncompletoRespectoFlash($calc, $storedArr);

        // Réplica Anita o jornada a medias: no pisar juego/clientes/caja emp. del flash.
        if (! $stored->esReplicaAnita() && ! $vivoIncompleto) {
            return $calc;
        }

        if ((abs($mesaStored) >= 0.02 && abs($mesaCalc - $mesaStored) >= 0.02) || $vivoIncompleto) {
            foreach (FlashCrownCalculoSupport::camposJuegoConservarDesdeFlash() as $campo) {
                $calc[$campo] = $stored->{$campo};
            }
        }

        if (abs($slotStored) >= 0.02 && abs($slotCalc) < 0.02) {
            foreach (['slot_d', 'soft_count', 'cant_slots', 'coin_in', 'coin_out', 'pago_manual', 'slot_r'] as $campo) {
                $calc[$campo] = $stored->{$campo};
            }
        }

        return $calc;
    }
}
