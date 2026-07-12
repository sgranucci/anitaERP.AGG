<?php

namespace App\Services\Caja\Bingo;

use App\Models\Caja\Bingo\CierreParcialTurnoBingo;
use App\Models\Caja\Bingo\ConfiguracionPuntoventaBingo;
use App\Models\Caja\Bingo\JornadaBingo;
use App\Models\Caja\Bingo\TurnoBingo;
use App\Models\Caja\Bingo\TurnoOperativoBingo;
use App\Support\Caja\Bingo\BingoRendicionCalculoSupport;
use App\Support\Caja\Bingo\BingoTurnoOperativoTotalesSupport;
use App\Support\Ventas\GastronomiaTurnoMediosContadoCierreSupport;
use App\Support\Ventas\GastronomiaTurnoObservacionHabilitacionSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class BingoTurnoOperativoService
{
    public function __construct(
        private readonly JornadaBingoService $jornadaService,
    ) {}

    public static function requiereHabilitacionTurno(): bool
    {
        return (bool) config('bingo.requiere_habilitacion_turno', true);
    }

    public function turnoHabilitadoEnPc(string $identificadorPc, ?int $empresaId = null): ?TurnoOperativoBingo
    {
        if ($identificadorPc === '') {
            return null;
        }

        $query = TurnoOperativoBingo::query()
            ->with(['turno', 'jornada', 'usuarioHabilitado'])
            ->where('identificador_pc', $identificadorPc)
            ->where('estado', TurnoOperativoBingo::ESTADO_HABILITADO);

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function estadoParaTerminal(ConfiguracionPuntoventaBingo $cfg, string $identificadorPc): array
    {
        $empresaId = (int) $cfg->empresa_id;
        $activo = $this->turnoHabilitadoEnPc($identificadorPc, $empresaId);
        $jornada = $this->jornadaService->jornadaAbierta($empresaId);

        $fechaJornada = $activo?->jornada?->fecha_jornada?->format('Y-m-d')
            ?? $jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');

        $erroresHabilitacion = [];
        if ($activo === null && $jornada !== null) {
            $erroresHabilitacion = $this->erroresAntesDeHabilitar($cfg, $identificadorPc, $jornada);
        }

        $turnosHabilitablesIds = ($activo === null && $jornada !== null)
            ? $this->idsTurnosMaestroHabilitablesEnJornada($empresaId, (int) $jornada->id, $identificadorPc)
            : [];

        $totalesTurno = null;
        $totalesDia = null;
        if ($activo !== null) {
            $totalesTurno = BingoTurnoOperativoTotalesSupport::calcular(
                $identificadorPc,
                $empresaId,
                $fechaJornada,
                $activo->habilitacion_en,
            );
            $totalesDia = BingoTurnoOperativoTotalesSupport::calcular(
                $identificadorPc,
                $empresaId,
                $fechaJornada,
            );
        }

        return [
            'requiere_habilitacion_turno' => self::requiereHabilitacionTurno(),
            'jornada_abierta' => $jornada !== null,
            'turno_habilitado' => $activo !== null,
            'turno_operativo_id' => $activo?->id,
            'turno_nombre' => $activo?->turno?->nombre,
            'usuario_habilitado' => $activo?->usuarioHabilitado?->nombre,
            'monto_habilitacion' => $activo !== null ? (float) $activo->monto_habilitacion : null,
            'habilitacion_en_fmt' => $activo?->habilitacion_en?->format('d/m/Y H:i'),
            'fecha_jornada' => $fechaJornada,
            'fecha_jornada_fmt' => Carbon::parse($fechaJornada)->format('d/m/Y'),
            'jornada_id' => $activo?->jornada_bingo_id ?? $jornada?->id,
            'cierres_parciales' => $activo ? $activo->cierresParciales()->count() : 0,
            'totales_turno' => $totalesTurno,
            'totales_dia' => $totalesDia,
            'puede_habilitar' => $activo === null && $jornada !== null && $erroresHabilitacion === []
                && $turnosHabilitablesIds !== [],
            'errores_habilitacion' => $erroresHabilitacion,
            'turnos_bingo_habilitables_ids' => $turnosHabilitablesIds,
            'turnos_bingo_cerrados_ids' => $jornada !== null
                ? $this->idsTurnosMaestroCerradosEnJornada((int) $jornada->id, $identificadorPc)
                : [],
            'puede_cierre_parcial' => $activo !== null,
            'puede_cerrar_turno' => $activo !== null,
            'errores_cierre' => $activo !== null ? $this->erroresAntesDeCerrar($activo) : [],
            'rendicion_cargada' => $activo !== null && ! empty($activo->cartones_rendicion_json),
        ];
    }

    public function habilitar(
        ConfiguracionPuntoventaBingo $cfg,
        string $identificadorPc,
        int $turnoBingoId,
        float $montoHabilitacion,
        int $usuarioHabilitadoId,
        ?string $observacion = null,
    ): TurnoOperativoBingo {
        if (! self::requiereHabilitacionTurno()) {
            throw new InvalidArgumentException('Modo caja directo: habilitación de turno no activa.');
        }

        $empresaId = (int) $cfg->empresa_id;
        $jornada = $this->jornadaService->exigirJornadaAbierta($empresaId);

        if ($this->turnoHabilitadoEnPc($identificadorPc) !== null) {
            throw new InvalidArgumentException('Ya hay un turno habilitado en esta terminal.');
        }

        $turno = TurnoBingo::query()
            ->where('id', $turnoBingoId)
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->first();

        if ($turno === null) {
            throw new InvalidArgumentException('Turno inválido o inactivo.');
        }

        if ($usuarioHabilitadoId <= 0 || Auth::id() === null) {
            throw new InvalidArgumentException('Usuario inválido.');
        }

        if ($montoHabilitacion < 0) {
            throw new InvalidArgumentException('El monto de habilitación no puede ser negativo.');
        }

        if ($this->turnoMaestroYaCerradoEnJornada((int) $jornada->id, $identificadorPc, (int) $turno->id)) {
            throw new InvalidArgumentException('Ese turno ya fue cerrado en esta jornada para la terminal.');
        }

        $habilitables = $this->idsTurnosMaestroHabilitablesEnJornada($empresaId, (int) $jornada->id, $identificadorPc);
        if (! in_array((int) $turno->id, $habilitables, true)) {
            throw new InvalidArgumentException('No puede habilitar ese turno en esta terminal/jornada.');
        }

        return TurnoOperativoBingo::query()->create([
            'empresa_id' => $empresaId,
            'jornada_bingo_id' => (int) $jornada->id,
            'turno_bingo_id' => (int) $turno->id,
            'configuracion_puntoventa_bingo_id' => (int) $cfg->id,
            'identificador_pc' => $identificadorPc,
            'estado' => TurnoOperativoBingo::ESTADO_HABILITADO,
            'usuario_habilitacion_id' => (int) Auth::id(),
            'usuario_habilitado_id' => $usuarioHabilitadoId,
            'monto_habilitacion' => round($montoHabilitacion, 2),
            'observacion_habilitacion' => $this->limpiarObservacion($observacion),
            'habilitacion_en' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $datosCierre
     */
    public function cerrar(
        TurnoOperativoBingo $turno,
        string $identificadorPc,
        array $datosCierre = [],
    ): TurnoOperativoBingo {
        $this->exigirTurnoActivoEnPc($turno, $identificadorPc);

        $errores = $this->erroresAntesDeCerrar($turno);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');
        $cierreEn = now();

        $totalesTurno = BingoTurnoOperativoTotalesSupport::calcular(
            $identificadorPc,
            (int) $turno->empresa_id,
            $fechaJornada,
            $turno->habilitacion_en,
            $cierreEn,
        );

        $totalesDia = BingoTurnoOperativoTotalesSupport::calcular(
            $identificadorPc,
            (int) $turno->empresa_id,
            $fechaJornada,
        );

        $cartones = is_array($datosCierre['cartones'] ?? null) ? $datosCierre['cartones'] : [];
        $conceptosPayload = $this->normalizarPayloadConceptosGuardado($datosCierre);
        $montosManuales = is_array($datosCierre['montos_manuales'] ?? null)
            ? $datosCierre['montos_manuales']
            : (is_array($conceptosPayload['montos_manuales'] ?? null) ? $conceptosPayload['montos_manuales'] : []);

        $montoRendicion = round((float) ($datosCierre['monto_rendicion'] ?? 0), 2);
        if ($cartones !== [] && $montoRendicion <= 0) {
            $calculo = BingoRendicionCalculoSupport::calcular(
                $this->normalizarLineasCartonesCierre($cartones),
                [],
                $montosManuales,
            );
            $montoRendicion = (float) $calculo['saldo_final'];
        }

        $redondeo = round((float) ($datosCierre['redondeo'] ?? 0), 2);
        $sobranteFaltante = round((float) ($datosCierre['sobrante_faltante'] ?? 0), 2);
        $vales = round((float) ($datosCierre['vales'] ?? 0), 2);
        $deposito = round((float) ($datosCierre['deposito'] ?? $montoRendicion), 2);

        $mediosContado = GastronomiaTurnoMediosContadoCierreSupport::normalizarParaGuardar(
            $datosCierre['medios_contado'] ?? null,
            $totalesTurno,
            (int) $turno->empresa_id,
        );
        if ($mediosContado === null || $mediosContado === []) {
            $rawMedios = is_array($datosCierre['medios_contado'] ?? null) ? $datosCierre['medios_contado'] : [];
            if ($rawMedios !== []) {
                $mediosContado = $rawMedios;
            } elseif ($montoRendicion > 0) {
                $mediosContado = [[
                    'medio' => 'Efectivo',
                    'monto' => $montoRendicion,
                ]];
            } else {
                $mediosContado = [];
            }
        }

        DB::transaction(function () use (
            $turno,
            $cierreEn,
            $totalesTurno,
            $totalesDia,
            $montoRendicion,
            $redondeo,
            $sobranteFaltante,
            $vales,
            $deposito,
            $mediosContado,
            $cartones,
            $conceptosPayload,
            $datosCierre,
        ) {
            $max = (int) TurnoOperativoBingo::query()
                ->where('empresa_id', (int) $turno->empresa_id)
                ->where('estado', TurnoOperativoBingo::ESTADO_CERRADO)
                ->whereNotNull('numero_cierre')
                ->lockForUpdate()
                ->max('numero_cierre');

            $turno->update([
                'estado' => TurnoOperativoBingo::ESTADO_CERRADO,
                'usuario_cierre_id' => Auth::id(),
                'cierre_en' => $cierreEn,
                'numero_cierre' => $max + 1,
                'monto_rendicion_turno' => $montoRendicion > 0 ? $montoRendicion : (float) $totalesTurno['total_general'],
                'monto_rendicion_dia' => (float) $totalesDia['total_general'],
                'redondeo' => $redondeo,
                'sobrante_faltante' => $sobranteFaltante,
                'vales' => $vales,
                'deposito' => $deposito,
                'medios_contado_cierre_json' => $mediosContado,
                'cartones_rendicion_json' => $cartones !== [] ? $cartones : null,
                'conceptos_rendicion_json' => $conceptosPayload !== [] ? $conceptosPayload : null,
                'observacion_cierre' => $this->limpiarObservacion($datosCierre['observacion_cierre'] ?? null),
            ]);
        });

        return $turno->fresh(['turno', 'jornada', 'usuarioHabilitado', 'usuarioCierre']);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizarRendicion(TurnoOperativoBingo $turno, array $datos): TurnoOperativoBingo
    {
        if ($turno->estado !== TurnoOperativoBingo::ESTADO_CERRADO) {
            throw new InvalidArgumentException('Solo puede editar rendiciones de turnos cerrados.');
        }

        if ($turno->rendicion_presentada) {
            throw new InvalidArgumentException('La rendición ya fue presentada en caja.');
        }

        $cartones = is_array($datos['cartones'] ?? null) ? $datos['cartones'] : [];
        $conceptosPayload = $this->normalizarPayloadConceptosGuardado($datos);
        $montoRendicion = round((float) ($datos['monto_rendicion'] ?? 0), 2);
        $deposito = round((float) ($datos['deposito'] ?? $montoRendicion), 2);
        $mediosContado = is_array($datos['medios_contado'] ?? null) ? $datos['medios_contado'] : [];

        $turno->update([
            'monto_rendicion_turno' => $montoRendicion,
            'deposito' => $deposito,
            'medios_contado_cierre_json' => $mediosContado !== [] ? $mediosContado : null,
            'cartones_rendicion_json' => $cartones !== [] ? $cartones : null,
            'conceptos_rendicion_json' => $conceptosPayload !== [] ? $conceptosPayload : null,
            'observacion_cierre' => $this->limpiarObservacion($datos['observacion_cierre'] ?? null),
        ]);

        return $turno->fresh(['turno', 'jornada', 'usuarioHabilitado', 'usuarioCierre']);
    }

    /**
     * @param  array<string, mixed>  $datosCierre
     * @return array{lineas?: list<array<string, mixed>>, montos_manuales?: array<int, float>}
     */
    private function normalizarPayloadConceptosGuardado(array $datosCierre): array
    {
        $raw = is_array($datosCierre['conceptos'] ?? null) ? $datosCierre['conceptos'] : [];
        if (isset($raw['lineas']) && is_array($raw['lineas'])) {
            return [
                'lineas' => $raw['lineas'],
                'montos_manuales' => is_array($raw['montos_manuales'] ?? null)
                    ? $raw['montos_manuales']
                    : (is_array($datosCierre['montos_manuales'] ?? null) ? $datosCierre['montos_manuales'] : []),
            ];
        }

        if ($raw !== [] && array_is_list($raw)) {
            return [
                'lineas' => $raw,
                'montos_manuales' => is_array($datosCierre['montos_manuales'] ?? null) ? $datosCierre['montos_manuales'] : [],
            ];
        }

        return [];
    }

    /**
     * @param  list<array<string, mixed>>  $cartones
     * @return list<array{carton_id: int, cantidad: int, precio_unitario: float}>
     */
    private function normalizarLineasCartonesCierre(array $cartones): array
    {
        $out = [];
        foreach ($cartones as $linea) {
            if (! is_array($linea) || ! empty($linea['anulado'])) {
                continue;
            }
            $cantidad = max(0, (int) ($linea['cantidad'] ?? 0));
            if ($cantidad <= 0) {
                continue;
            }
            $out[] = [
                'carton_id' => (int) ($linea['carton_id'] ?? 0),
                'cantidad' => $cantidad,
                'precio_unitario' => (float) ($linea['precio_unitario'] ?? 0),
            ];
        }

        return $out;
    }

    public function registrarCierreParcial(TurnoOperativoBingo $turno, string $identificadorPc): CierreParcialTurnoBingo
    {
        $this->exigirTurnoActivoEnPc($turno, $identificadorPc);

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');
        $totales = BingoTurnoOperativoTotalesSupport::calcular(
            $identificadorPc,
            (int) $turno->empresa_id,
            $fechaJornada,
            $turno->habilitacion_en,
        );

        $numero = (int) CierreParcialTurnoBingo::query()
            ->where('turno_operativo_bingo_id', $turno->id)
            ->max('numero_parcial') + 1;

        return CierreParcialTurnoBingo::query()->create([
            'turno_operativo_bingo_id' => (int) $turno->id,
            'numero_parcial' => $numero,
            'identificador_pc' => $identificadorPc,
            'total_rendicion_turno' => (float) ($totales['total_general'] ?? 0),
            'totales_json' => $totales,
            'usuario_id' => (int) Auth::id(),
            'created_at' => now(),
        ]);
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeCerrar(TurnoOperativoBingo $turno): array
    {
        $errores = [];
        $this->validarCierreNoPosteriorAJornadaSiguiente($turno, $errores);

        return $errores;
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeHabilitar(
        ConfiguracionPuntoventaBingo $cfg,
        string $identificadorPc,
        JornadaBingo $jornada,
    ): array {
        return [];
    }

    public function turnoMaestroYaCerradoEnJornada(int $jornadaBingoId, string $identificadorPc, int $turnoBingoId): bool
    {
        return TurnoOperativoBingo::query()
            ->where('jornada_bingo_id', $jornadaBingoId)
            ->where('identificador_pc', $identificadorPc)
            ->where('turno_bingo_id', $turnoBingoId)
            ->where('estado', TurnoOperativoBingo::ESTADO_CERRADO)
            ->exists();
    }

    /**
     * @return list<int>
     */
    public function idsTurnosMaestroCerradosEnJornada(int $jornadaBingoId, string $identificadorPc): array
    {
        return TurnoOperativoBingo::query()
            ->where('jornada_bingo_id', $jornadaBingoId)
            ->where('identificador_pc', $identificadorPc)
            ->where('estado', TurnoOperativoBingo::ESTADO_CERRADO)
            ->pluck('turno_bingo_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function maxOrdenTurnoUsadoEnJornadaTerminal(int $jornadaBingoId, string $identificadorPc): int
    {
        $maxOrden = TurnoOperativoBingo::query()
            ->where('jornada_bingo_id', $jornadaBingoId)
            ->where('identificador_pc', $identificadorPc)
            ->whereIn('estado', [TurnoOperativoBingo::ESTADO_HABILITADO, TurnoOperativoBingo::ESTADO_CERRADO])
            ->join('turno_bingo', 'turno_bingo.id', '=', 'turno_operativo_bingo.turno_bingo_id')
            ->max('turno_bingo.orden');

        return max(0, (int) $maxOrden);
    }

    /**
     * @return list<int>
     */
    public function idsTurnosMaestroHabilitablesEnJornada(int $empresaId, int $jornadaBingoId, string $identificadorPc): array
    {
        $cerrados = $this->idsTurnosMaestroCerradosEnJornada($jornadaBingoId, $identificadorPc);
        $maxOrdenUsado = $this->maxOrdenTurnoUsadoEnJornadaTerminal($jornadaBingoId, $identificadorPc);

        return TurnoBingo::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->filter(function (TurnoBingo $turno) use ($cerrados, $maxOrdenUsado) {
                if (in_array((int) $turno->id, $cerrados, true)) {
                    return false;
                }

                return $maxOrdenUsado === 0 || (int) $turno->orden > $maxOrdenUsado;
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $errores
     */
    private function validarCierreNoPosteriorAJornadaSiguiente(TurnoOperativoBingo $turno, array &$errores): void
    {
        $empresaId = (int) $turno->empresa_id;
        $jornadaAbierta = $this->jornadaService->jornadaAbierta($empresaId);

        if ($jornadaAbierta !== null
            && (int) $jornadaAbierta->id !== (int) $turno->jornada_bingo_id) {
            $errores[] = 'Ya se abrió una jornada nueva. Cierre el turno habilitado antes de continuar.';
        }
    }

    private function exigirTurnoActivoEnPc(TurnoOperativoBingo $turno, string $identificadorPc): void
    {
        if ($turno->estado !== TurnoOperativoBingo::ESTADO_HABILITADO) {
            throw new InvalidArgumentException('El turno operativo no está habilitado.');
        }

        if ($turno->identificador_pc !== $identificadorPc) {
            throw new InvalidArgumentException('El turno activo pertenece a otra terminal.');
        }
    }

    private function limpiarObservacion(?string $observacion): ?string
    {
        $txt = trim((string) $observacion);

        return $txt === '' ? null : mb_substr($txt, 0, 2000);
    }
}
