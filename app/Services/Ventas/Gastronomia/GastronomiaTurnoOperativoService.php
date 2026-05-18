<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\TurnoGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Models\Ventas\CierreParcialTurnoGastronomia;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class GastronomiaTurnoOperativoService
{
    public function __construct(
        private readonly GastronomiaJornadaService $jornadaService,
    ) {
    }

    public static function requiereHabilitacionTurno(): bool
    {
        return (bool) config('gastronomia.requiere_habilitacion_turno', true);
    }

    public function turnoHabilitadoEnPc(string $identificadorPc): ?TurnoOperativoGastronomia
    {
        if ($identificadorPc === '') {
            return null;
        }

        return TurnoOperativoGastronomia::query()
            ->with(['turno', 'jornada', 'usuarioHabilitado', 'usuarioHabilitacion'])
            ->where('identificador_pc', $identificadorPc)
            ->where('estado', TurnoOperativoGastronomia::ESTADO_HABILITADO)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function estadoParaTerminal(
        ConfiguracionPuntoventaGastronomia $cfg,
        string $identificadorPc,
    ): array {
        $empresaId = (int) $cfg->empresa_id;
        $activo = $this->turnoHabilitadoEnPc($identificadorPc);
        $jornada = $this->jornadaService->jornadaAbierta($empresaId);
        $fechaJornada = $jornada?->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');

        $totalesTurno = null;
        $totalesDia = null;
        if ($activo !== null) {
            $desde = $activo->habilitacion_en;
            $totalesTurno = GastronomiaTurnoOperativoTotalesSupport::calcular(
                $identificadorPc,
                $empresaId,
                $fechaJornada,
                $desde,
            );
            $totalesDia = GastronomiaTurnoOperativoTotalesSupport::calcular(
                $identificadorPc,
                $empresaId,
                $fechaJornada,
                null,
            );
        }

        return [
            'requiere_habilitacion_turno' => self::requiereHabilitacionTurno(),
            'turno_habilitado' => $activo !== null,
            'turno_operativo_id' => $activo?->id,
            'turno_nombre' => $activo?->turno?->nombre,
            'usuario_habilitado' => $activo?->usuarioHabilitado?->nombre,
            'monto_habilitacion' => $activo !== null ? (float) $activo->monto_habilitacion : null,
            'habilitacion_en' => $activo?->habilitacion_en?->format('Y-m-d H:i:s'),
            'jornada_id' => $activo?->jornada_gastronomia_id ?? $jornada?->id,
            'fecha_jornada' => $fechaJornada,
            'cierres_parciales' => $activo
                ? $activo->cierresParciales()->count()
                : 0,
            'totales_turno' => $totalesTurno,
            'totales_dia' => $totalesDia,
            'puede_habilitar' => $activo === null && $jornada !== null,
            'puede_cierre_parcial' => $activo !== null,
            'puede_cerrar_turno' => $activo !== null,
            'errores_cierre' => $activo !== null
                ? $this->erroresAntesDeCerrar($activo)
                : [],
        ];
    }

    public function exigirTurnoHabilitadoSiConfigurado(string $identificadorPc, int $empresaId): void
    {
        if (! self::requiereHabilitacionTurno()) {
            return;
        }

        $turno = $this->turnoHabilitadoEnPc($identificadorPc);
        if ($turno === null) {
            throw new InvalidArgumentException(
                'No hay turno habilitado en esta terminal ('.$identificadorPc.'). '
                .'Habilite el turno en Ventas → Gastronomía → Habilitación de turno.'
            );
        }

        if ((int) $turno->empresa_id !== $empresaId) {
            throw new InvalidArgumentException('El turno habilitado no corresponde a la empresa de esta terminal.');
        }

        $jornada = $this->jornadaService->jornadaAbierta($empresaId);
        if ($jornada === null) {
            throw new InvalidArgumentException('No hay jornada abierta para esta empresa.');
        }

        if ((int) $turno->jornada_gastronomia_id !== (int) $jornada->id) {
            throw new InvalidArgumentException(
                'El turno habilitado pertenece a otra jornada. Cierre el turno actual antes de continuar.'
            );
        }
    }

    public function habilitar(
        ConfiguracionPuntoventaGastronomia $cfg,
        string $identificadorPc,
        int $turnoGastronomiaId,
        float $montoHabilitacion,
        int $usuarioHabilitadoId,
        ?string $observacion = null,
    ): TurnoOperativoGastronomia {
        if (! self::requiereHabilitacionTurno()) {
            throw new InvalidArgumentException(
                'El modo de operación es caja directo; la habilitación de turno no está activa.'
            );
        }

        $empresaId = (int) $cfg->empresa_id;
        $jornada = $this->jornadaService->exigirJornadaAbierta($empresaId);

        if ($this->turnoHabilitadoEnPc($identificadorPc) !== null) {
            throw new InvalidArgumentException(
                'Ya hay un turno habilitado en esta terminal. Ciérrelo antes de habilitar otro.'
            );
        }

        $turno = TurnoGastronomia::query()
            ->where('id', $turnoGastronomiaId)
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->first();

        if ($turno === null) {
            throw new InvalidArgumentException('El turno seleccionado no existe o no está activo para esta empresa.');
        }

        if ($usuarioHabilitadoId <= 0) {
            throw new InvalidArgumentException('Debe indicar el usuario al que se habilita el turno.');
        }

        if (Auth::id() === null) {
            throw new InvalidArgumentException('No hay usuario autenticado.');
        }

        if ($montoHabilitacion < 0) {
            throw new InvalidArgumentException('El monto de habilitación no puede ser negativo.');
        }

        return TurnoOperativoGastronomia::query()->create([
            'empresa_id' => $empresaId,
            'jornada_gastronomia_id' => (int) $jornada->id,
            'turno_gastronomia_id' => (int) $turno->id,
            'configuracion_puntoventa_gastronomia_id' => (int) $cfg->id,
            'identificador_pc' => $identificadorPc,
            'estado' => TurnoOperativoGastronomia::ESTADO_HABILITADO,
            'usuario_habilitacion_id' => (int) Auth::id(),
            'usuario_habilitado_id' => $usuarioHabilitadoId,
            'monto_habilitacion' => round($montoHabilitacion, 2),
            'observacion_habilitacion' => $this->limpiarObservacion($observacion),
            'habilitacion_en' => now(),
        ]);
    }

    public function registrarCierreParcial(
        TurnoOperativoGastronomia $turno,
        string $identificadorPc,
    ): CierreParcialTurnoGastronomia {
        $this->exigirTurnoActivoEnPc($turno, $identificadorPc);

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');

        $totales = GastronomiaTurnoOperativoTotalesSupport::calcular(
            $identificadorPc,
            (int) $turno->empresa_id,
            $fechaJornada,
            $turno->habilitacion_en,
        );

        $numero = (int) CierreParcialTurnoGastronomia::query()
            ->where('turno_operativo_gastronomia_id', $turno->id)
            ->max('numero_parcial') + 1;

        return CierreParcialTurnoGastronomia::query()->create([
            'turno_operativo_gastronomia_id' => (int) $turno->id,
            'numero_parcial' => $numero,
            'identificador_pc' => $identificadorPc,
            'total_facturacion_turno' => $totales['total_general'],
            'totales_json' => $totales,
            'usuario_id' => (int) Auth::id(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array{
     *   redondeo_invitaciones?:float|null,
     *   redondeo_turno?:float|null,
     *   sobrante_faltante?:float|null,
     *   observacion_cierre?:string|null
     * }  $datosCierre
     */
    public function cerrar(
        TurnoOperativoGastronomia $turno,
        string $identificadorPc,
        array $datosCierre = [],
    ): TurnoOperativoGastronomia {
        $this->exigirTurnoActivoEnPc($turno, $identificadorPc);

        $errores = $this->erroresAntesDeCerrar($turno);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');

        $totalesTurno = GastronomiaTurnoOperativoTotalesSupport::calcular(
            $identificadorPc,
            (int) $turno->empresa_id,
            $fechaJornada,
            $turno->habilitacion_en,
        );

        $totalesDia = GastronomiaTurnoOperativoTotalesSupport::calcular(
            $identificadorPc,
            (int) $turno->empresa_id,
            $fechaJornada,
            null,
        );

        $turno->update([
            'estado' => TurnoOperativoGastronomia::ESTADO_CERRADO,
            'usuario_cierre_id' => Auth::id(),
            'cierre_en' => now(),
            'monto_facturacion_turno' => $totalesTurno['total_general'],
            'monto_facturacion_dia' => $totalesDia['total_general'],
            'redondeo_invitaciones' => isset($datosCierre['redondeo_invitaciones'])
                ? round((float) $datosCierre['redondeo_invitaciones'], 2)
                : $totalesTurno['redondeo_invitaciones_sugerido'],
            'redondeo_turno' => round((float) ($datosCierre['redondeo_turno'] ?? 0), 2),
            'sobrante_faltante' => round((float) ($datosCierre['sobrante_faltante'] ?? 0), 2),
            'observacion_cierre' => $this->limpiarObservacion($datosCierre['observacion_cierre'] ?? null),
        ]);

        return $turno->fresh([
            'turno',
            'jornada',
            'usuarioHabilitado',
            'usuarioCierre',
            'cierresParciales',
        ]);
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeCerrar(TurnoOperativoGastronomia $turno): array
    {
        $errores = [];
        $empresaId = (int) $turno->empresa_id;
        $cfgId = (int) $turno->configuracion_puntoventa_gastronomia_id;
        $pc = (string) $turno->identificador_pc;

        $this->validarCierreNoPosteriorAJornadaSiguiente($turno, $errores);

        $cuentasPc = CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', [
                CuentaGastronomia::ESTADO_ABIERTA,
                CuentaGastronomia::ESTADO_CERRADA,
            ])
            ->where(function ($q) use ($cfgId, $pc) {
                $q->where('configuracion_puntoventa_gastronomia_id', $cfgId)
                    ->orWhere('identificador_pc', $pc);
            })
            ->count();

        if ($cuentasPc > 0) {
            $errores[] = 'Hay '.$cuentasPc.' cuenta(s) sin facturar en esta terminal ('.$pc.').';
        }

        return $errores;
    }

    /**
     * @param  list<string>  $errores
     */
    private function validarCierreNoPosteriorAJornadaSiguiente(
        TurnoOperativoGastronomia $turno,
        array &$errores,
    ): void {
        $empresaId = (int) $turno->empresa_id;
        $jornadaAbierta = $this->jornadaService->jornadaAbierta($empresaId);

        if ($jornadaAbierta !== null
            && (int) $jornadaAbierta->id !== (int) $turno->jornada_gastronomia_id) {
            $errores[] = 'Ya se abrió una jornada nueva ('
                .($jornadaAbierta->fecha_jornada?->format('d/m/Y') ?? '')
                .'). El turno noche debe cerrarse antes de abrir la jornada del día siguiente.';

            return;
        }

        $jornadaPosterior = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('id', '>', (int) $turno->jornada_gastronomia_id)
            ->where('apertura_en', '>', $turno->habilitacion_en)
            ->exists();

        if ($jornadaPosterior && $jornadaAbierta === null) {
            $errores[] = 'No puede cerrar este turno: ya existe una jornada posterior a la del turno habilitado.';
        }
    }

    private function exigirTurnoActivoEnPc(TurnoOperativoGastronomia $turno, string $identificadorPc): void
    {
        if ($turno->estado !== TurnoOperativoGastronomia::ESTADO_HABILITADO) {
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
