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

        if ($activo !== null) {
            $activo->loadMissing('jornada');
            $fechaJornada = $activo->jornada?->fecha_jornada?->format('Y-m-d')
                ?? $jornada?->fecha_jornada?->format('Y-m-d')
                ?? Carbon::today()->format('Y-m-d');
        } else {
            $fechaJornada = $jornada?->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');
        }

        $fechaJornadaFmt = $activo?->jornada?->fecha_jornada?->format('d/m/Y')
            ?? $jornada?->fecha_jornada?->format('d/m/Y')
            ?? Carbon::parse($fechaJornada)->format('d/m/Y');

        $erroresHabilitacion = [];
        if ($activo === null && $jornada !== null) {
            $erroresHabilitacion = $this->erroresAntesDeHabilitar($cfg, $identificadorPc, $jornada);
        }

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
            'habilitacion_en_fmt' => $activo?->habilitacion_en?->format('d/m/Y H:i') ?? null,
            'jornada_id' => $activo?->jornada_gastronomia_id ?? $jornada?->id,
            'fecha_jornada' => $fechaJornada,
            'fecha_jornada_fmt' => $fechaJornadaFmt,
            'cierres_parciales' => $activo
                ? $activo->cierresParciales()->count()
                : 0,
            'cierres_parciales_lista' => $activo
                ? $activo->cierresParciales()
                    ->orderByDesc('numero_parcial')
                    ->get()
                    ->map(fn (CierreParcialTurnoGastronomia $p) => [
                        'id' => $p->id,
                        'numero_parcial' => $p->numero_parcial,
                        'fecha' => $p->created_at?->format('d/m/Y H:i'),
                        'total' => (float) $p->total_facturacion_turno,
                        'solo_totales_mozo' => ! empty(is_array($p->totales_json) ? ($p->totales_json['solo_totales_mozo'] ?? false) : false),
                    ])
                    ->values()
                    ->all()
                : [],
            'totales_turno' => $totalesTurno,
            'totales_dia' => $totalesDia,
            'puede_habilitar' => $activo === null && $jornada !== null && $erroresHabilitacion === []
                && $this->hayTurnoMaestroPendienteDeHabilitar($empresaId, (int) $jornada->id, $identificadorPc),
            'errores_habilitacion' => $erroresHabilitacion,
            'turnos_gastronomia_cerrados_ids' => $jornada !== null
                ? $this->idsTurnosMaestroCerradosEnJornada((int) $jornada->id, $identificadorPc)
                : [],
            'facturas_huerfanas' => $activo === null && $jornada !== null
                ? $this->detalleFacturasHuerfanas($identificadorPc, $empresaId, $jornada)['cantidad']
                : 0,
            'puede_cierre_parcial' => $activo !== null,
            'puede_cerrar_turno' => $activo !== null,
            'es_ultimo_turno_dia' => $activo !== null
                ? $this->esUltimoTurnoDelDia($activo)
                : false,
            'errores_cierre' => $activo !== null
                ? $this->erroresAntesDeCerrar($activo)
                : [],
            'cuentas_sin_facturar' => $activo !== null
                ? $this->contarCuentasSinFacturarEnTerminal($activo)
                : 0,
            'url_facturas_dia' => route('gastronomia_facturas_dia', ['empresa_id' => $empresaId]),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CuentaGastronomia>
     */
    public function listarCuentasSinFacturarEnTerminal(TurnoOperativoGastronomia $turno)
    {
        $cfgId = (int) $turno->configuracion_puntoventa_gastronomia_id;
        $pc = (string) $turno->identificador_pc;

        return CuentaGastronomia::query()
            ->with(['mesa', 'mozo', 'lineas'])
            ->where('empresa_id', (int) $turno->empresa_id)
            ->whereIn('estado', [
                CuentaGastronomia::ESTADO_ABIERTA,
                CuentaGastronomia::ESTADO_CERRADA,
            ])
            ->where(function ($q) use ($cfgId, $pc) {
                $q->where('configuracion_puntoventa_gastronomia_id', $cfgId)
                    ->orWhere('identificador_pc', $pc);
            })
            ->orderBy('id')
            ->get();
    }

    public function contarCuentasSinFacturarEnTerminal(TurnoOperativoGastronomia $turno): int
    {
        return $this->listarCuentasSinFacturarEnTerminal($turno)->count();
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

        if ($this->turnoMaestroYaCerradoEnJornada((int) $jornada->id, $identificadorPc, (int) $turno->id)) {
            throw new InvalidArgumentException(
                'El turno "'.$turno->nombre.'" ya fue habilitado y cerrado en esta jornada para la terminal '
                .$identificadorPc.'. Elija otro turno del día.'
            );
        }

        $erroresHabilitacion = $this->erroresAntesDeHabilitar($cfg, $identificadorPc, $jornada);
        if ($erroresHabilitacion !== []) {
            throw new InvalidArgumentException(implode(' ', $erroresHabilitacion));
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
        bool $soloTotalesMozo = false,
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
        if ($soloTotalesMozo) {
            $totales['solo_totales_mozo'] = true;
        }

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

        $cierreEn = now();

        $totalesTurno = GastronomiaTurnoOperativoTotalesSupport::calcular(
            $identificadorPc,
            (int) $turno->empresa_id,
            $fechaJornada,
            $turno->habilitacion_en,
            $cierreEn,
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
            'cierre_en' => $cierreEn,
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

        if ($this->esUltimoTurnoDelDia($turno)) {
            $cuentasPc = $this->contarCuentasSinFacturarEnTerminal($turno);

            if ($cuentasPc > 0) {
                $errores[] = 'Hay '.$cuentasPc.' cuenta(s) o mesa(s) sin facturar en esta terminal ('.$pc.'). '
                    .'Al cerrar el último turno del día deben quedar todas facturadas o cerradas sin consumo.';
            }
        }

        return $errores;
    }

    public function esUltimoTurnoDelDia(TurnoOperativoGastronomia $turno): bool
    {
        $turno->loadMissing('turno');
        $ordenActual = (int) ($turno->turno?->orden ?? 0);
        $maxOrden = (int) TurnoGastronomia::query()
            ->where('empresa_id', (int) $turno->empresa_id)
            ->where('activo', true)
            ->max('orden');

        if ($maxOrden <= 0) {
            return true;
        }

        return $ordenActual >= $maxOrden;
    }

    /**
     * @return array{cantidad:int, ejemplos:list<array{venta_id:int, codigo:string, hora:string}>}
     */
    public function detalleFacturasHuerfanas(
        string $identificadorPc,
        int $empresaId,
        JornadaGastronomia $jornada,
    ): array {
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');

        return GastronomiaTurnoOperativoTotalesSupport::facturasHuerfanasDelDia(
            $identificadorPc,
            $empresaId,
            $fechaJornada,
            (int) $jornada->id,
        );
    }

    /**
     * @return list<int>
     */
    public function idsTurnosMaestroCerradosEnJornada(int $jornadaGastronomiaId, string $identificadorPc): array
    {
        if ($identificadorPc === '') {
            return [];
        }

        return TurnoOperativoGastronomia::query()
            ->where('jornada_gastronomia_id', $jornadaGastronomiaId)
            ->where('identificador_pc', $identificadorPc)
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->pluck('turno_gastronomia_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function turnoMaestroYaCerradoEnJornada(
        int $jornadaGastronomiaId,
        string $identificadorPc,
        int $turnoGastronomiaId,
    ): bool {
        if ($identificadorPc === '' || $turnoGastronomiaId <= 0) {
            return false;
        }

        return TurnoOperativoGastronomia::query()
            ->where('jornada_gastronomia_id', $jornadaGastronomiaId)
            ->where('identificador_pc', $identificadorPc)
            ->where('turno_gastronomia_id', $turnoGastronomiaId)
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->exists();
    }

    public function hayTurnoMaestroPendienteDeHabilitar(
        int $empresaId,
        int $jornadaGastronomiaId,
        string $identificadorPc,
    ): bool {
        $cerrados = $this->idsTurnosMaestroCerradosEnJornada($jornadaGastronomiaId, $identificadorPc);
        $activos = TurnoGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        foreach ($activos as $id) {
            if (! in_array($id, $cerrados, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeHabilitar(
        ConfiguracionPuntoventaGastronomia $cfg,
        string $identificadorPc,
        JornadaGastronomia $jornada,
    ): array {
        if (! self::requiereHabilitacionTurno()) {
            return [];
        }

        $errores = [];
        $huerfanas = $this->detalleFacturasHuerfanas($identificadorPc, (int) $cfg->empresa_id, $jornada);

        if ($huerfanas['cantidad'] > 0) {
            $detalle = '';
            $ejemplos = $huerfanas['ejemplos'];
            if ($ejemplos !== []) {
                $refs = array_map(
                    fn (array $e) => trim(($e['codigo'] !== '' ? $e['codigo'] : '#'.$e['venta_id']).' '.$e['hora']),
                    $ejemplos,
                );
                $detalle = ' Ej.: '.implode(', ', $refs).'.';
            }

            $errores[] = 'Hay '.$huerfanas['cantidad'].' factura(s) de la jornada en esta terminal '
                .'fuera de cualquier turno cerrado. Concilie o revise Facturas del día antes de habilitar un nuevo turno.'.$detalle;
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
