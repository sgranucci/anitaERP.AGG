<?php

namespace App\Services\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\CierreParcialTurnoEstacionamiento;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Caja\Estacionamiento\CuentaEstacionamiento;
use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Models\Caja\Estacionamiento\TicketEstacionamiento;
use App\Models\Caja\Estacionamiento\TurnoEstacionamiento;
use App\Models\Caja\Estacionamiento\TurnoOperativoEstacionamiento;
use App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision;
use App\Support\Caja\Estacionamiento\EstacionamientoCuentacajaEfectivo;
use App\Support\Caja\Estacionamiento\EstacionamientoTurnoNumeracionComprobanteSupport;
use App\Support\Caja\Estacionamiento\EstacionamientoTurnoOperativoTotalesSupport;
use App\Support\Ventas\GastronomiaTurnoMediosContadoCierreSupport;
use App\Support\Ventas\GastronomiaTurnoObservacionHabilitacionSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class EstacionamientoTurnoOperativoService
{
    public function __construct(
        private readonly JornadaEstacionamientoService $jornadaService,
    ) {
    }

    public static function requiereHabilitacionTurno(): bool
    {
        return (bool) config('estacionamiento.requiere_habilitacion_turno', true);
    }

    /**
     * Bloqueo por ticket_estacionamiento en estado ingreso al cerrar turno/jornada.
     * Desactivado por defecto hasta implementar emisión de ticket de ingreso de autos.
     */
    public static function validarTicketsIngresoAlCerrar(): bool
    {
        return (bool) config('estacionamiento.validar_tickets_ingreso_al_cerrar', false);
    }

    public function turnoHabilitadoEnPc(string $identificadorPc): ?TurnoOperativoEstacionamiento
    {
        if ($identificadorPc === '') {
            return null;
        }

        return TurnoOperativoEstacionamiento::query()
            ->with(['turno', 'jornada', 'usuarioHabilitado', 'usuarioHabilitacion'])
            ->where('identificador_pc', $identificadorPc)
            ->where('estado', TurnoOperativoEstacionamiento::ESTADO_HABILITADO)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function estadoParaTerminal(
        ConfiguracionPuntoventaEstacionamiento $cfg,
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

        $turnosHabilitablesIds = ($activo === null && $jornada !== null)
            ? $this->idsTurnosMaestroHabilitablesEnJornada($empresaId, (int) $jornada->id, $identificadorPc)
            : [];

        $totalesTurno = null;
        $totalesDia = null;
        $numeracionFiscal = ['filas' => []];
        if ($activo !== null) {
            $desde = $activo->habilitacion_en;
            $totalesTurno = EstacionamientoTurnoOperativoTotalesSupport::calcular(
                $identificadorPc,
                $empresaId,
                $fechaJornada,
                $desde,
            );
            $totalesDia = EstacionamientoTurnoOperativoTotalesSupport::calcular(
                $identificadorPc,
                $empresaId,
                $fechaJornada,
                null,
            );
            $numeracionFiscal = EstacionamientoTurnoNumeracionComprobanteSupport::paraTurno($activo);
        }

        return [
            'requiere_habilitacion_turno' => self::requiereHabilitacionTurno(),
            'jornada_abierta' => $jornada !== null,
            'turno_habilitado' => $activo !== null,
            'turno_operativo_id' => $activo?->id,
            'turno_nombre' => $activo?->turno?->nombre,
            'usuario_habilitado' => $activo?->usuarioHabilitado?->nombre,
            'monto_habilitacion' => $activo !== null ? (float) $activo->monto_habilitacion : null,
            'habilitacion_en' => $activo?->habilitacion_en?->format('Y-m-d H:i:s'),
            'habilitacion_en_fmt' => $activo?->habilitacion_en?->format('d/m/Y H:i') ?? null,
            'jornada_id' => $activo?->jornada_estacionamiento_id ?? $jornada?->id,
            'fecha_jornada' => $fechaJornada,
            'fecha_jornada_fmt' => $fechaJornadaFmt,
            'jornada_usuario_apertura' => $jornada?->usuarioApertura?->nombre,
            'jornada_apertura_en' => $jornada?->apertura_en?->format('d/m/Y H:i'),
            'cierres_parciales' => $activo
                ? $activo->cierresParciales()->count()
                : 0,
            'cierres_parciales_lista' => $activo
                ? $activo->cierresParciales()
                    ->orderByDesc('numero_parcial')
                    ->get()
                    ->map(fn (CierreParcialTurnoEstacionamiento $p) => [
                        'id' => $p->id,
                        'numero_parcial' => $p->numero_parcial,
                        'fecha' => $p->created_at?->format('d/m/Y H:i'),
                        'total' => (float) $p->total_facturacion_turno,
                    ])
                    ->values()
                    ->all()
                : [],
            'totales_turno' => $totalesTurno,
            'totales_dia' => $totalesDia,
            'numeracion_fiscal' => $numeracionFiscal,
            'puede_habilitar' => $activo === null && $jornada !== null && $erroresHabilitacion === []
                && $turnosHabilitablesIds !== [],
            'errores_habilitacion' => $erroresHabilitacion,
            'turnos_estacionamiento_habilitables_ids' => $turnosHabilitablesIds,
            'sin_turnos_por_abrir' => $activo === null
                && $jornada !== null
                && $erroresHabilitacion === []
                && $turnosHabilitablesIds === [],
            'turnos_estacionamiento_cerrados_ids' => $jornada !== null
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
                ? $this->contarCuentasAbiertasConItemsEnTerminal($activo)
                : 0,
            'cuentas_abiertas_con_items' => $activo !== null
                ? $this->contarCuentasAbiertasConItemsEnTerminal($activo)
                : 0,
            'cuentas_abiertas_vacias' => $activo !== null
                ? $this->contarCuentasAbiertasVaciasEnTerminal($activo)
                : 0,
            'cuentas_cerradas_sin_facturar' => $activo !== null
                ? $this->contarCuentasCerradasSinFacturarEnTerminal($activo)
                : 0,
            'tickets_pendientes_ingreso' => $activo !== null
                ? $this->contarTicketsPendientesIngresoEnTerminal($activo)
                : 0,
            'url_facturas_dia' => route('estacionamiento_facturas_dia', ['empresa_id' => $empresaId]),
            'url_saneamiento_turno' => route('estacionamiento_saneamiento_turno', ['empresa_id' => $empresaId]),
            'cuentacaja_efectivo_id' => (int) (EstacionamientoCuentacajaEfectivo::idParaEmpresa($empresaId) ?? 0),
        ];
    }

    public function contarTicketsPendientesIngresoEnTerminal(TurnoOperativoEstacionamiento $turno): int
    {
        if (! self::validarTicketsIngresoAlCerrar()) {
            return 0;
        }

        return $this->queryTicketsPendientesIngresoTerminal($turno)->count();
    }

    public function contarTicketsPendientesIngresoJornada(int $empresaId, ?JornadaEstacionamiento $jornada = null): int
    {
        if (! self::validarTicketsIngresoAlCerrar()) {
            return 0;
        }

        if ($empresaId <= 0) {
            return 0;
        }

        $jornada ??= $this->jornadaService->jornadaAbierta($empresaId);
        if ($jornada === null) {
            return 0;
        }

        return (int) TicketEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('jornada_estacionamiento_id', (int) $jornada->id)
            ->where('estado', TicketEstacionamiento::ESTADO_INGRESO)
            ->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, TicketEstacionamiento>
     */
    public function listarTicketsPendientesIngresoParaPuntoventa(int $empresaId, int $jornadaId, int $cfgId, string $pc)
    {
        if (! self::validarTicketsIngresoAlCerrar()) {
            return TicketEstacionamiento::query()->whereRaw('1 = 0')->get();
        }

        return $this->queryTicketsPendientesIngresoParaPuntoventa($empresaId, $jornadaId, $cfgId, $pc)->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CuentaEstacionamiento>
     */
    public function listarCuentasSinFacturarEnTerminal(TurnoOperativoEstacionamiento $turno)
    {
        return $this->listarCuentasSinFacturarParaPuntoventa(
            (int) $turno->empresa_id,
            (int) $turno->configuracion_puntoventa_estacionamiento_id,
            (string) $turno->identificador_pc,
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CuentaEstacionamiento>
     */
    public function listarCuentasAbiertasEnTerminal(TurnoOperativoEstacionamiento $turno)
    {
        return $this->listarCuentasAbiertasParaPuntoventa(
            (int) $turno->empresa_id,
            (int) $turno->configuracion_puntoventa_estacionamiento_id,
            (string) $turno->identificador_pc,
        );
    }

    public function contarCuentasAbiertasConItemsEnTerminal(TurnoOperativoEstacionamiento $turno): int
    {
        return $this->queryCuentasNoFacturadasParaPuntoventa(
            (int) $turno->empresa_id,
            (int) $turno->configuracion_puntoventa_estacionamiento_id,
            (string) $turno->identificador_pc,
            [CuentaEstacionamiento::ESTADO_ABIERTA],
        )->whereHas('lineas')->count();
    }

    public function contarCuentasAbiertasVaciasEnTerminal(TurnoOperativoEstacionamiento $turno): int
    {
        return $this->queryCuentasNoFacturadasParaPuntoventa(
            (int) $turno->empresa_id,
            (int) $turno->configuracion_puntoventa_estacionamiento_id,
            (string) $turno->identificador_pc,
            [CuentaEstacionamiento::ESTADO_ABIERTA],
        )->whereDoesntHave('lineas')->count();
    }

    public function autoDescartarCuentasAbiertasVaciasEnTerminal(TurnoOperativoEstacionamiento $turno): int
    {
        return $this->queryCuentasNoFacturadasParaPuntoventa(
            (int) $turno->empresa_id,
            (int) $turno->configuracion_puntoventa_estacionamiento_id,
            (string) $turno->identificador_pc,
            [CuentaEstacionamiento::ESTADO_ABIERTA],
        )->whereDoesntHave('lineas')->update(['estado' => CuentaEstacionamiento::ESTADO_CERRADA]);
    }

    public function contarCuentasCerradasSinFacturarEnTerminal(TurnoOperativoEstacionamiento $turno): int
    {
        return $this->queryCuentasNoFacturadasParaPuntoventa(
            (int) $turno->empresa_id,
            (int) $turno->configuracion_puntoventa_estacionamiento_id,
            (string) $turno->identificador_pc,
            [CuentaEstacionamiento::ESTADO_CERRADA],
        )->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CuentaEstacionamiento>
     */
    public function listarCuentasSinFacturarParaPuntoventa(int $empresaId, int $cfgId, string $pc)
    {
        return $this->queryCuentasNoFacturadasParaPuntoventa(
            $empresaId,
            $cfgId,
            $pc,
            [CuentaEstacionamiento::ESTADO_ABIERTA, CuentaEstacionamiento::ESTADO_CERRADA],
        )->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CuentaEstacionamiento>
     */
    public function listarCuentasAbiertasParaPuntoventa(int $empresaId, int $cfgId, string $pc)
    {
        return $this->queryCuentasNoFacturadasParaPuntoventa(
            $empresaId,
            $cfgId,
            $pc,
            [CuentaEstacionamiento::ESTADO_ABIERTA],
        )->get();
    }

    /**
     * @param  list<int>  $idsCubiertas
     * @return \Illuminate\Database\Eloquent\Collection<int, CuentaEstacionamiento>
     */
    public function listarCuentasNoFacturadasNoCubiertas(int $empresaId, array $idsCubiertas)
    {
        return CuentaEstacionamiento::query()
            ->with(['lineas', 'cliente', 'categoriaAutomovil'])
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', [CuentaEstacionamiento::ESTADO_ABIERTA, CuentaEstacionamiento::ESTADO_CERRADA])
            ->when($idsCubiertas !== [], fn ($q) => $q->whereNotIn('id', $idsCubiertas))
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<int>  $idsCubiertas
     * @return \Illuminate\Database\Eloquent\Collection<int, TicketEstacionamiento>
     */
    public function listarTicketsPendientesNoCubiertos(int $empresaId, int $jornadaId, array $idsCubiertas)
    {
        return TicketEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('jornada_estacionamiento_id', $jornadaId)
            ->where('estado', TicketEstacionamiento::ESTADO_INGRESO)
            ->when($idsCubiertas !== [], fn ($q) => $q->whereNotIn('id', $idsCubiertas))
            ->orderBy('numero_ticket')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, TicketEstacionamiento>
     */
    public function listarTicketsPendientesIngresoEnTerminal(TurnoOperativoEstacionamiento $turno)
    {
        if (! self::validarTicketsIngresoAlCerrar()) {
            return TicketEstacionamiento::query()->whereRaw('1 = 0')->get();
        }

        return $this->queryTicketsPendientesIngresoTerminal($turno)->get();
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
                .'Habilite el turno en Caja → Estacionamiento → Habilitación de turno.'
            );
        }

        if ((int) $turno->empresa_id !== $empresaId) {
            throw new InvalidArgumentException('El turno habilitado no corresponde a la empresa de esta terminal.');
        }

        $jornada = $this->jornadaService->jornadaAbierta($empresaId);
        if ($jornada === null) {
            throw new InvalidArgumentException('No hay jornada abierta para esta empresa.');
        }

        if ((int) $turno->jornada_estacionamiento_id !== (int) $jornada->id) {
            throw new InvalidArgumentException(
                'El turno habilitado pertenece a otra jornada. Cierre el turno actual antes de continuar.'
            );
        }
    }

    public function habilitar(
        ConfiguracionPuntoventaEstacionamiento $cfg,
        string $identificadorPc,
        int $turnoEstacionamientoId,
        float $montoHabilitacion,
        int $usuarioHabilitadoId,
        ?string $observacion = null,
    ): TurnoOperativoEstacionamiento {
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

        $turno = TurnoEstacionamiento::query()
            ->where('id', $turnoEstacionamientoId)
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

        $habilitables = $this->idsTurnosMaestroHabilitablesEnJornada(
            $empresaId,
            (int) $jornada->id,
            $identificadorPc,
        );
        if (! in_array((int) $turno->id, $habilitables, true)) {
            $maxOrdenUsado = $this->maxOrdenTurnoUsadoEnJornadaTerminal((int) $jornada->id, $identificadorPc);
            if ($maxOrdenUsado > 0 && (int) $turno->orden <= $maxOrdenUsado) {
                throw new InvalidArgumentException(
                    'No puede habilitar el turno "'.$turno->nombre.'" porque ya se utilizó un turno posterior '
                    .'en esta terminal. No hay más turnos anteriores pendientes que puedan abrirse.'
                );
            }

            throw new InvalidArgumentException(
                'No hay más turnos por abrir en esta terminal para la jornada actual.'
            );
        }

        $erroresHabilitacion = $this->erroresAntesDeHabilitar($cfg, $identificadorPc, $jornada);
        if ($erroresHabilitacion !== []) {
            throw new InvalidArgumentException(implode(' ', $erroresHabilitacion));
        }

        return TurnoOperativoEstacionamiento::query()->create([
            'empresa_id' => $empresaId,
            'jornada_estacionamiento_id' => (int) $jornada->id,
            'turno_estacionamiento_id' => (int) $turno->id,
            'configuracion_puntoventa_estacionamiento_id' => (int) $cfg->id,
            'identificador_pc' => $identificadorPc,
            'estado' => TurnoOperativoEstacionamiento::ESTADO_HABILITADO,
            'usuario_habilitacion_id' => (int) Auth::id(),
            'usuario_habilitado_id' => $usuarioHabilitadoId,
            'monto_habilitacion' => round($montoHabilitacion, 2),
            'observacion_habilitacion' => $this->limpiarObservacion($observacion),
            'habilitacion_en' => now(),
        ]);
    }

    public function actualizarMontoHabilitacion(
        int $turnoOperativoId,
        string $identificadorPc,
        float $nuevoMonto,
        ?string $motivo = null,
    ): TurnoOperativoEstacionamiento {
        if (! self::requiereHabilitacionTurno()) {
            throw new InvalidArgumentException(
                'El modo de operación es caja directo; la habilitación de turno no está activa.'
            );
        }

        if (Auth::id() === null) {
            throw new InvalidArgumentException('No hay usuario autenticado.');
        }

        $turno = TurnoOperativoEstacionamiento::query()->findOrFail($turnoOperativoId);
        $this->exigirTurnoActivoEnPc($turno, $identificadorPc);

        $jornada = $this->jornadaService->jornadaAbierta((int) $turno->empresa_id);
        if ($jornada === null) {
            throw new InvalidArgumentException('No hay jornada abierta para esta empresa.');
        }
        if ((int) $turno->jornada_estacionamiento_id !== (int) $jornada->id) {
            throw new InvalidArgumentException(
                'El turno pertenece a una jornada que ya no está abierta.'
            );
        }

        if ($nuevoMonto < 0) {
            throw new InvalidArgumentException('El monto de habilitación no puede ser negativo.');
        }

        $nuevoMonto = round($nuevoMonto, 2);
        $montoAnterior = round((float) $turno->monto_habilitacion, 2);
        if ($nuevoMonto === $montoAnterior) {
            throw new InvalidArgumentException('El monto indicado es igual al actual.');
        }

        $usuario = Auth::user();
        $usuarioId = (int) ($usuario?->id ?? 0);
        $usuarioNombre = (string) ($usuario?->nombre ?? 'usuario');

        $nota = GastronomiaTurnoObservacionHabilitacionSupport::notaModificacionMonto(
            $usuarioId,
            $usuarioNombre,
            $identificadorPc,
            $montoAnterior,
            $nuevoMonto,
            $this->limpiarObservacion($motivo),
        );

        return DB::transaction(function () use ($turno, $nota, $nuevoMonto, $identificadorPc, $usuarioId, $usuarioNombre) {
            $obsHab = trim((string) $turno->observacion_habilitacion);
            $obsHab = $obsHab === '' ? $nota : $obsHab."\n".$nota;

            $turno->update([
                'monto_habilitacion' => $nuevoMonto,
                'observacion_habilitacion' => mb_substr($obsHab, 0, 2000),
            ]);

            Log::info('estacionamiento.turno.modificar_monto_habilitacion', [
                'turno_operativo_id' => (int) $turno->id,
                'empresa_id' => (int) $turno->empresa_id,
                'jornada_estacionamiento_id' => (int) $turno->jornada_estacionamiento_id,
                'identificador_pc' => $identificadorPc,
                'usuario_id' => $usuarioId,
                'usuario_nombre' => $usuarioNombre,
                'monto_anterior' => round((float) $turno->getOriginal('monto_habilitacion'), 2),
                'monto_nuevo' => $nuevoMonto,
            ]);

            return $turno->fresh(['turno', 'jornada', 'usuarioHabilitado', 'cierresParciales']);
        });
    }

    public function registrarCierreParcial(
        TurnoOperativoEstacionamiento $turno,
        string $identificadorPc,
    ): CierreParcialTurnoEstacionamiento {
        $this->exigirTurnoActivoEnPc($turno, $identificadorPc);

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');

        $totales = EstacionamientoTurnoOperativoTotalesSupport::calcular(
            $identificadorPc,
            (int) $turno->empresa_id,
            $fechaJornada,
            $turno->habilitacion_en,
        );

        $numero = (int) CierreParcialTurnoEstacionamiento::query()
            ->where('turno_operativo_estacionamiento_id', $turno->id)
            ->max('numero_parcial') + 1;

        return CierreParcialTurnoEstacionamiento::query()->create([
            'turno_operativo_estacionamiento_id' => (int) $turno->id,
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
     * @param  array{
     *   cierre_remoto?:bool,
     *   pc_operador?:string,
     *   omitir_validacion_jornada_posterior?:bool
     * }  $opciones
     */
    public function cerrar(
        TurnoOperativoEstacionamiento $turno,
        string $identificadorPc,
        array $datosCierre = [],
        array $opciones = [],
    ): TurnoOperativoEstacionamiento {
        $cierreRemoto = ! empty($opciones['cierre_remoto']);
        $pcTerminal = (string) $turno->identificador_pc;

        if ($cierreRemoto) {
            $identificadorPc = $pcTerminal;
        } else {
            $this->exigirTurnoActivoEnPc($turno, $identificadorPc);
        }

        $errores = $this->erroresAntesDeCerrar($turno, $opciones);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        $vaciasAutoDescartadas = $this->autoDescartarCuentasAbiertasVaciasEnTerminal($turno);

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');

        $cierreEn = now();

        $totalesTurno = EstacionamientoTurnoOperativoTotalesSupport::calcular(
            $identificadorPc,
            (int) $turno->empresa_id,
            $fechaJornada,
            $turno->habilitacion_en,
            $cierreEn,
        );

        $totalesDia = EstacionamientoTurnoOperativoTotalesSupport::calcular(
            $identificadorPc,
            (int) $turno->empresa_id,
            $fechaJornada,
            null,
        );

        $sobranteFaltanteAutoRemoto = false;
        if ($cierreRemoto) {
            $ajustes = EstacionamientoTurnoOperativoTotalesSupport::resolverAjustesCierreConSobranteFaltanteResidual(
                $totalesTurno,
                isset($datosCierre['redondeo_invitaciones'])
                    ? round((float) $datosCierre['redondeo_invitaciones'], 2)
                    : null,
                isset($datosCierre['redondeo_turno'])
                    ? round((float) $datosCierre['redondeo_turno'], 2)
                    : null,
            );
            $redondeoInvitaciones = $ajustes['redondeo_invitaciones'];
            $redondeoTurno = $ajustes['redondeo_turno'];
            $sobranteFaltante = $ajustes['sobrante_faltante'];
            $sobranteFaltanteAutoRemoto = $ajustes['sobrante_faltante_auto'];
        } else {
            $redondeoInvitaciones = isset($datosCierre['redondeo_invitaciones'])
                ? round((float) $datosCierre['redondeo_invitaciones'], 2)
                : (float) $totalesTurno['redondeo_invitaciones_sugerido'];
            $redondeoTurno = round((float) ($datosCierre['redondeo_turno'] ?? 0), 2);
            $sobranteFaltante = round((float) ($datosCierre['sobrante_faltante'] ?? 0), 2);
        }

        $mediosContadoCierre = GastronomiaTurnoMediosContadoCierreSupport::normalizarParaGuardar(
            $datosCierre['medios_contado'] ?? null,
            $totalesTurno,
            (int) $turno->empresa_id,
        );

        if (! EstacionamientoTurnoOperativoTotalesSupport::cierreCuadraConAjustesManuales(
            $totalesTurno,
            $redondeoInvitaciones,
            $redondeoTurno,
            $sobranteFaltante,
        )) {
            $diff = round((float) ($totalesTurno['diferencia_cobranza'] ?? 0), 2);
            throw new InvalidArgumentException(
                'No puede cerrar el turno con diferencia de conciliación ($ '.number_format(abs($diff), 2, ',', '.').'). '
                .'Revise comprobantes (incl. notas de crédito de otra terminal), cargue el redondeo invitaciones sugerido '
                .'o registre sobrante/faltante hasta cuadrar.'
            );
        }

        $pcOperadorRemoto = null;
        if ($cierreRemoto) {
            $pcOperador = trim((string) ($opciones['pc_operador'] ?? ''));
            if ($pcOperador !== '') {
                $pcOperadorRemoto = $pcOperador;
            }
        }

        DB::transaction(function () use (
            $turno,
            $cierreEn,
            $totalesTurno,
            $totalesDia,
            $redondeoInvitaciones,
            $redondeoTurno,
            $sobranteFaltante,
            $datosCierre,
            $vaciasAutoDescartadas,
            $pcOperadorRemoto,
            $sobranteFaltanteAutoRemoto,
            $mediosContadoCierre,
        ) {
            $max = (int) TurnoOperativoEstacionamiento::query()
                ->where('empresa_id', (int) $turno->empresa_id)
                ->where('estado', TurnoOperativoEstacionamiento::ESTADO_CERRADO)
                ->whereNotNull('numero_cierre')
                ->lockForUpdate()
                ->max('numero_cierre');

            $turno->update([
                'estado' => TurnoOperativoEstacionamiento::ESTADO_CERRADO,
                'usuario_cierre_id' => Auth::id(),
                'cierre_en' => $cierreEn,
                'numero_cierre' => $max + 1,
                'monto_facturacion_turno' => $totalesTurno['total_general'],
                'monto_facturacion_dia' => $totalesDia['total_general'],
                'redondeo_invitaciones' => $redondeoInvitaciones,
                'redondeo_turno' => $redondeoTurno,
                'sobrante_faltante' => $sobranteFaltante,
                'medios_contado_cierre_json' => $mediosContadoCierre,
                'observacion_cierre' => $this->componerObservacionCierreTurno(
                    $datosCierre['observacion_cierre'] ?? null,
                    $vaciasAutoDescartadas,
                    $pcOperadorRemoto,
                    $sobranteFaltanteAutoRemoto,
                    $sobranteFaltante,
                ),
            ]);
        });

        return $turno->fresh([
            'turno',
            'jornada',
            'usuarioHabilitado',
            'usuarioCierre',
            'cierresParciales',
        ]);
    }

    /**
     * @param  array{omitir_validacion_jornada_posterior?:bool}  $opciones
     * @return list<string>
     */
    public function erroresAntesDeCerrar(TurnoOperativoEstacionamiento $turno, array $opciones = []): array
    {
        $errores = [];
        $pc = (string) $turno->identificador_pc;

        if (empty($opciones['omitir_validacion_jornada_posterior'])) {
            $this->validarCierreNoPosteriorAJornadaSiguiente($turno, $errores);
        }

        if ($this->esUltimoTurnoDelDia($turno)) {
            $ticketsPendientes = $this->contarTicketsPendientesIngresoEnTerminal($turno);

            if ($ticketsPendientes > 0) {
                $errores[] = 'Hay '.$ticketsPendientes.' ticket(s) con ingreso pendiente de facturar o anular en esta terminal ('.$pc.'). '
                    .'Al cerrar el último turno del día deben quedar facturados o anulados.';
            }
        }

        return $errores;
    }

    public function esUltimoTurnoDelDia(TurnoOperativoEstacionamiento $turno): bool
    {
        $turno->loadMissing('turno');
        $ordenActual = (int) ($turno->turno?->orden ?? 0);
        $maxOrden = (int) TurnoEstacionamiento::query()
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
        JornadaEstacionamiento $jornada,
    ): array {
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');

        return EstacionamientoTurnoOperativoTotalesSupport::facturasHuerfanasDelDia(
            $identificadorPc,
            $empresaId,
            $fechaJornada,
            (int) $jornada->id,
        );
    }

    /**
     * @return list<int>
     */
    public function idsTurnosMaestroCerradosEnJornada(int $jornadaEstacionamientoId, string $identificadorPc): array
    {
        if ($identificadorPc === '') {
            return [];
        }

        return TurnoOperativoEstacionamiento::query()
            ->where('jornada_estacionamiento_id', $jornadaEstacionamientoId)
            ->where('identificador_pc', $identificadorPc)
            ->where('estado', TurnoOperativoEstacionamiento::ESTADO_CERRADO)
            ->pluck('turno_estacionamiento_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public const PREFIJO_CONFIRMACION_ANULAR_CIERRE = 'ANULAR-';

    public function ultimoTurnoCerradoEnJornadaTerminal(
        int $jornadaEstacionamientoId,
        string $identificadorPc,
        int $empresaId,
    ): ?TurnoOperativoEstacionamiento {
        if ($identificadorPc === '' || $jornadaEstacionamientoId <= 0) {
            return null;
        }

        return TurnoOperativoEstacionamiento::query()
            ->with(['turno', 'jornada', 'usuarioCierre', 'usuarioHabilitado'])
            ->where('jornada_estacionamiento_id', $jornadaEstacionamientoId)
            ->where('empresa_id', $empresaId)
            ->where('identificador_pc', $identificadorPc)
            ->where('estado', TurnoOperativoEstacionamiento::ESTADO_CERRADO)
            ->whereNotNull('cierre_en')
            ->orderByDesc('cierre_en')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function describirCierreAnulable(int $empresaId, string $identificadorPc): ?array
    {
        $jornada = $this->jornadaService->jornadaAbierta($empresaId);
        if ($jornada === null || $identificadorPc === '') {
            return null;
        }

        if ($this->turnoHabilitadoEnPc($identificadorPc) !== null) {
            return [
                'puede_anular' => false,
                'bloqueo_mensaje' => 'Hay un turno habilitado en esta terminal. Ciérrelo o continúe operando antes de anular un cierre anterior.',
            ];
        }

        $turno = $this->ultimoTurnoCerradoEnJornadaTerminal(
            (int) $jornada->id,
            $identificadorPc,
            $empresaId,
        );

        if ($turno === null) {
            return null;
        }

        $eval = $this->evaluarAnulacionCierre($turno, $identificadorPc);

        return array_merge($eval, [
            'turno_operativo_id' => (int) $turno->id,
            'turno_nombre' => (string) ($turno->turno?->nombre ?? ''),
            'numero_cierre' => $turno->numero_cierre,
            'cierre_en_fmt' => $turno->cierre_en?->format('d/m/Y H:i'),
            'usuario_cierre' => $turno->usuarioCierre?->nombre,
            'identificador_pc' => (string) $turno->identificador_pc,
            'texto_confirmacion' => self::textoConfirmacionAnularCierre((int) $turno->id),
        ]);
    }

    public static function textoConfirmacionAnularCierre(int $turnoOperativoId): string
    {
        return self::PREFIJO_CONFIRMACION_ANULAR_CIERRE.$turnoOperativoId;
    }

    /**
     * @return array{puede_anular: bool, bloqueo_mensaje: string|null}
     */
    public function evaluarAnulacionCierre(TurnoOperativoEstacionamiento $turno, string $identificadorPc): array
    {
        $errores = $this->erroresAntesDeAnularCierre($turno, $identificadorPc);

        return [
            'puede_anular' => $errores === [],
            'bloqueo_mensaje' => $errores !== [] ? implode(' ', $errores) : null,
        ];
    }

    /**
     * @return array{turno: TurnoOperativoEstacionamiento, mensaje: string}
     */
    public function anularCierreDefinitivo(
        int $turnoOperativoId,
        string $identificadorPc,
        string $confirmacion,
        ?string $motivo = null,
    ): array {
        $turno = TurnoOperativoEstacionamiento::query()
            ->with(['turno', 'jornada'])
            ->findOrFail($turnoOperativoId);

        $errores = $this->erroresAntesDeAnularCierre($turno, $identificadorPc);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        $textoEsperado = self::textoConfirmacionAnularCierre((int) $turno->id);
        if (trim($confirmacion) !== $textoEsperado) {
            throw new InvalidArgumentException(
                'Confirmación incorrecta. Escriba exactamente: '.$textoEsperado
            );
        }

        $usuario = Auth::user();
        $usuarioId = (int) ($usuario?->id ?? 0);
        $usuarioNombre = (string) ($usuario?->nombre ?? 'usuario');

        $snapshotCierre = [
            'estado' => $turno->estado,
            'cierre_en' => $turno->cierre_en?->format('Y-m-d H:i:s'),
            'numero_cierre' => $turno->numero_cierre,
            'usuario_cierre_id' => $turno->usuario_cierre_id,
            'monto_facturacion_turno' => $turno->monto_facturacion_turno,
            'monto_facturacion_dia' => $turno->monto_facturacion_dia,
            'redondeo_invitaciones' => $turno->redondeo_invitaciones,
            'redondeo_turno' => $turno->redondeo_turno,
            'sobrante_faltante' => $turno->sobrante_faltante,
            'medios_contado_cierre_json' => $turno->medios_contado_cierre_json,
            'observacion_cierre' => $turno->observacion_cierre,
        ];

        $nota = GastronomiaTurnoObservacionHabilitacionSupport::notaAnulacionCierre(
            $usuarioId,
            $usuarioNombre,
            $identificadorPc,
            $turno->numero_cierre !== null ? (int) $turno->numero_cierre : null,
            $turno->cierre_en,
            $this->limpiarObservacion($motivo),
        );

        return DB::transaction(function () use ($turno, $nota, $identificadorPc, $usuarioId, $usuarioNombre, $snapshotCierre) {
            $obsHab = trim((string) $turno->observacion_habilitacion);
            $obsHab = $obsHab === '' ? $nota : $obsHab."\n".$nota;

            $turno->update([
                'estado' => TurnoOperativoEstacionamiento::ESTADO_HABILITADO,
                'usuario_cierre_id' => null,
                'cierre_en' => null,
                'numero_cierre' => null,
                'monto_facturacion_turno' => null,
                'monto_facturacion_dia' => null,
                'redondeo_invitaciones' => null,
                'redondeo_turno' => null,
                'sobrante_faltante' => null,
                'medios_contado_cierre_json' => null,
                'observacion_cierre' => null,
                'observacion_habilitacion' => mb_substr($obsHab, 0, 2000),
            ]);

            $turno = $turno->fresh(['turno', 'jornada', 'usuarioHabilitado', 'cierresParciales']);

            Log::info('estacionamiento.turno.anular_cierre', [
                'turno_operativo_id' => (int) $turno->id,
                'empresa_id' => (int) $turno->empresa_id,
                'jornada_estacionamiento_id' => (int) $turno->jornada_estacionamiento_id,
                'identificador_pc' => $identificadorPc,
                'usuario_id' => $usuarioId,
                'usuario_nombre' => $usuarioNombre,
                'cierre_anulado' => $snapshotCierre,
            ]);

            return [
                'turno' => $turno,
                'mensaje' => 'Cierre del turno «'.($turno->turno?->nombre ?? '').'» anulado. El turno volvió a estado habilitado en '
                    .$identificadorPc.'.',
            ];
        });
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeAnularCierre(TurnoOperativoEstacionamiento $turno, string $identificadorPc): array
    {
        $errores = [];
        $pcTurno = (string) $turno->identificador_pc;
        $pcReq = trim($identificadorPc);

        if ($pcReq === '') {
            $errores[] = 'Debe indicar la terminal (identificador PC).';

            return $errores;
        }

        if ($pcTurno !== $pcReq) {
            $errores[] = 'El cierre pertenece a la terminal '.$pcTurno
                .'; no coincide con la PC de trabajo ('.$pcReq.').';
        }

        if ($turno->estado !== TurnoOperativoEstacionamiento::ESTADO_CERRADO) {
            $errores[] = 'El turno operativo no está cerrado.';

            return $errores;
        }

        if ($turno->cierre_en === null) {
            $errores[] = 'El turno no tiene fecha de cierre registrada.';
        }

        $jornadaAbierta = $this->jornadaService->jornadaAbierta((int) $turno->empresa_id);
        if ($jornadaAbierta === null) {
            $errores[] = 'No hay jornada abierta para esta empresa. Solo puede anular cierres en la jornada activa.';

            return $errores;
        }

        if ((int) $turno->jornada_estacionamiento_id !== (int) $jornadaAbierta->id) {
            $errores[] = 'El cierre pertenece a otra jornada. Solo puede anular dentro de la jornada abierta actual.';
        }

        if (RendicionEstacionamientoCaja::query()
            ->where('turno_operativo_estacionamiento_id', (int) $turno->id)
            ->exists()) {
            $errores[] = 'El turno tiene una rendición de estacionamiento en caja asociada. Elimine o corrija la rendición antes de anular el cierre.';
        }

        if (RendicionEstacionamientoCaja::query()
            ->where('tipo', RendicionEstacionamientoCaja::TIPO_JORNADA)
            ->where('jornada_estacionamiento_id', (int) $turno->jornada_estacionamiento_id)
            ->exists()) {
            $errores[] = 'La jornada de este turno ya fue presentada en caja (rendición de jornada). Elimine esa rendición antes de anular el cierre del turno.';
        }

        $habilitado = $this->turnoHabilitadoEnPc($pcReq);
        if ($habilitado !== null && (int) $habilitado->id !== (int) $turno->id) {
            $errores[] = 'Hay otro turno habilitado en esta terminal. No puede anular un cierre anterior mientras exista un turno activo.';
        }

        $ultimo = $this->ultimoTurnoCerradoEnJornadaTerminal(
            (int) $turno->jornada_estacionamiento_id,
            $pcReq,
            (int) $turno->empresa_id,
        );
        if ($ultimo !== null && (int) $ultimo->id !== (int) $turno->id) {
            $errores[] = 'Solo puede anular el último cierre definitivo de esta terminal en la jornada activa '
                .'(cierre más reciente: #'.$ultimo->id.').';
        }

        $turno->loadMissing('turno');
        $ordenActual = (int) ($turno->turno?->orden ?? 0);

        $haySiguienteTurno = TurnoOperativoEstacionamiento::query()
            ->where('jornada_estacionamiento_id', (int) $turno->jornada_estacionamiento_id)
            ->where('identificador_pc', $pcReq)
            ->where('id', '!=', (int) $turno->id)
            ->whereHas('turno', fn ($q) => $q->where('orden', '>', $ordenActual))
            ->exists();

        if ($haySiguienteTurno) {
            $errores[] = 'Ya existe un turno posterior del día en esta terminal. No puede anular este cierre.';
        }

        if (Auth::id() === null) {
            $errores[] = 'No hay usuario autenticado.';
        }

        return $errores;
    }

    public function turnoMaestroYaCerradoEnJornada(
        int $jornadaEstacionamientoId,
        string $identificadorPc,
        int $turnoEstacionamientoId,
    ): bool {
        if ($identificadorPc === '' || $turnoEstacionamientoId <= 0) {
            return false;
        }

        return TurnoOperativoEstacionamiento::query()
            ->where('jornada_estacionamiento_id', $jornadaEstacionamientoId)
            ->where('identificador_pc', $identificadorPc)
            ->where('turno_estacionamiento_id', $turnoEstacionamientoId)
            ->where('estado', TurnoOperativoEstacionamiento::ESTADO_CERRADO)
            ->exists();
    }

    public function hayTurnoMaestroPendienteDeHabilitar(
        int $empresaId,
        int $jornadaEstacionamientoId,
        string $identificadorPc,
    ): bool {
        return $this->idsTurnosMaestroHabilitablesEnJornada(
            $empresaId,
            $jornadaEstacionamientoId,
            $identificadorPc,
        ) !== [];
    }

    public function maxOrdenTurnoUsadoEnJornadaTerminal(
        int $jornadaEstacionamientoId,
        string $identificadorPc,
    ): int {
        if ($identificadorPc === '' || $jornadaEstacionamientoId <= 0) {
            return 0;
        }

        $maxOrden = TurnoOperativoEstacionamiento::query()
            ->where('jornada_estacionamiento_id', $jornadaEstacionamientoId)
            ->where('identificador_pc', $identificadorPc)
            ->whereIn('estado', [
                TurnoOperativoEstacionamiento::ESTADO_HABILITADO,
                TurnoOperativoEstacionamiento::ESTADO_CERRADO,
            ])
            ->join(
                'turno_estacionamiento',
                'turno_estacionamiento.id',
                '=',
                'turno_operativo_estacionamiento.turno_estacionamiento_id',
            )
            ->max('turno_estacionamiento.orden');

        return max(0, (int) $maxOrden);
    }

    /**
     * @return list<int>
     */
    public function idsTurnosMaestroHabilitablesEnJornada(
        int $empresaId,
        int $jornadaEstacionamientoId,
        string $identificadorPc,
    ): array {
        if ($identificadorPc === '' || $empresaId <= 0 || $jornadaEstacionamientoId <= 0) {
            return [];
        }

        $cerrados = $this->idsTurnosMaestroCerradosEnJornada($jornadaEstacionamientoId, $identificadorPc);
        $maxOrdenUsado = $this->maxOrdenTurnoUsadoEnJornadaTerminal($jornadaEstacionamientoId, $identificadorPc);

        return TurnoEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->filter(function (TurnoEstacionamiento $turno) use ($cerrados, $maxOrdenUsado) {
                if (in_array((int) $turno->id, $cerrados, true)) {
                    return false;
                }

                $orden = (int) $turno->orden;

                return $maxOrdenUsado === 0 || $orden > $maxOrdenUsado;
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeHabilitar(
        ConfiguracionPuntoventaEstacionamiento $cfg,
        string $identificadorPc,
        JornadaEstacionamiento $jornada,
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

    public function terminalTuvoActividadEnJornada(TurnoOperativoEstacionamiento $turno): bool
    {
        $pc = trim((string) $turno->identificador_pc);
        $empresaId = (int) $turno->empresa_id;
        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d');

        if ($pc === '' || $empresaId <= 0 || $fechaJornada === null || $fechaJornada === '') {
            return true;
        }

        return VentaEstacionamientoEmision::query()
            ->where('identificador_pc', $pc)
            ->whereHas('venta', function ($v) use ($empresaId, $fechaJornada) {
                $v->where(function ($fecha) use ($fechaJornada) {
                    $fecha->whereDate('fechajornada', $fechaJornada)
                        ->orWhere(function ($legacy) use ($fechaJornada) {
                            $legacy->whereNull('fechajornada')
                                ->whereDate('fecha', $fechaJornada);
                        });
                })->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarTurnosHabilitadosParaCierreRemoto(int $empresaId, ?JornadaEstacionamiento $jornada = null): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        $jornada ??= $this->jornadaService->jornadaAbierta($empresaId);
        if ($jornada === null) {
            return [];
        }

        return TurnoOperativoEstacionamiento::query()
            ->with('turno')
            ->where('empresa_id', $empresaId)
            ->where('jornada_estacionamiento_id', (int) $jornada->id)
            ->where('estado', TurnoOperativoEstacionamiento::ESTADO_HABILITADO)
            ->orderBy('identificador_pc')
            ->orderBy('habilitacion_en')
            ->get()
            ->map(fn (TurnoOperativoEstacionamiento $t) => [
                'turno_operativo_id' => (int) $t->id,
                'identificador_pc' => (string) $t->identificador_pc,
                'turno_nombre' => (string) ($t->turno?->nombre ?? ''),
                'habilitacion_en' => $t->habilitacion_en?->format('d/m/Y H:i') ?? '',
                'con_actividad' => $this->terminalTuvoActividadEnJornada($t),
                'es_ultimo_turno_dia' => $this->esUltimoTurnoDelDia($t),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $errores
     */
    private function validarCierreNoPosteriorAJornadaSiguiente(
        TurnoOperativoEstacionamiento $turno,
        array &$errores,
    ): void {
        $empresaId = (int) $turno->empresa_id;
        $jornadaAbierta = $this->jornadaService->jornadaAbierta($empresaId);

        if ($jornadaAbierta !== null
            && (int) $jornadaAbierta->id !== (int) $turno->jornada_estacionamiento_id) {
            $errores[] = 'Ya se abrió una jornada nueva ('
                .($jornadaAbierta->fecha_jornada?->format('d/m/Y') ?? '')
                .'). Cierre el turno habilitado antes de abrir la jornada del día siguiente.';

            return;
        }

        $jornadaPosterior = JornadaEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('id', '>', (int) $turno->jornada_estacionamiento_id)
            ->where('apertura_en', '>', $turno->habilitacion_en)
            ->exists();

        if ($jornadaPosterior && $jornadaAbierta === null) {
            $errores[] = 'No puede cerrar este turno: ya existe una jornada posterior a la del turno habilitado.';
        }
    }

    private function exigirTurnoActivoEnPc(TurnoOperativoEstacionamiento $turno, string $identificadorPc): void
    {
        if ($turno->estado !== TurnoOperativoEstacionamiento::ESTADO_HABILITADO) {
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

    /**
     * @return \Illuminate\Database\Eloquent\Builder<TicketEstacionamiento>
     */
    private function queryTicketsPendientesIngresoParaPuntoventa(int $empresaId, int $jornadaId, int $cfgId, string $pc)
    {
        $query = TicketEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('jornada_estacionamiento_id', $jornadaId)
            ->where('estado', TicketEstacionamiento::ESTADO_INGRESO);

        if ($cfgId <= 0 && $pc === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where(function ($q) use ($cfgId, $pc) {
                if ($cfgId > 0) {
                    $q->where('configuracion_puntoventa_estacionamiento_id', $cfgId);
                }
                if ($pc !== '') {
                    if ($cfgId > 0) {
                        $q->orWhere('identificador_pc', $pc);
                    } else {
                        $q->where('identificador_pc', $pc);
                    }
                }
            })
            ->orderBy('numero_ticket');
    }

    /**
     * @param  list<string>  $estados
     * @return \Illuminate\Database\Eloquent\Builder<CuentaEstacionamiento>
     */
    private function queryCuentasNoFacturadasParaPuntoventa(int $empresaId, int $cfgId, string $pc, array $estados)
    {
        $query = CuentaEstacionamiento::query()
            ->with(['lineas', 'cliente', 'categoriaAutomovil', 'ticket'])
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', $estados);

        if ($cfgId <= 0 && $pc === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where(function ($q) use ($cfgId, $pc) {
                if ($cfgId > 0) {
                    $q->where('configuracion_puntoventa_estacionamiento_id', $cfgId);
                }
                if ($pc !== '') {
                    if ($cfgId > 0) {
                        $q->orWhere('identificador_pc', $pc);
                    } else {
                        $q->where('identificador_pc', $pc);
                    }
                }
            })
            ->orderBy('id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<TicketEstacionamiento>
     */
    private function queryTicketsPendientesIngresoTerminal(TurnoOperativoEstacionamiento $turno)
    {
        return TicketEstacionamiento::query()
            ->where('empresa_id', (int) $turno->empresa_id)
            ->where('jornada_estacionamiento_id', (int) $turno->jornada_estacionamiento_id)
            ->where('estado', TicketEstacionamiento::ESTADO_INGRESO)
            ->where(function ($q) use ($turno) {
                $q->where('identificador_pc', (string) $turno->identificador_pc);
                if ((int) $turno->configuracion_puntoventa_estacionamiento_id > 0) {
                    $q->orWhere(
                        'configuracion_puntoventa_estacionamiento_id',
                        (int) $turno->configuracion_puntoventa_estacionamiento_id,
                    );
                }
            })
            ->orderBy('numero_ticket');
    }

    private function componerObservacionCierreTurno(
        ?string $observacion,
        int $vaciasAutoDescartadas,
        ?string $pcOperadorRemoto = null,
        bool $sobranteFaltanteAutoRemoto = false,
        float $sobranteFaltante = 0.0,
    ): ?string {
        $partes = [];
        $base = $this->limpiarObservacion($observacion);
        if ($base !== null) {
            $partes[] = $base;
        }

        if ($pcOperadorRemoto !== null && trim($pcOperadorRemoto) !== '') {
            $usuario = Auth::user()?->nombre ?? 'usuario';
            $partes[] = '[Cierre remoto desde '.trim($pcOperadorRemoto).' por '.$usuario
                .' el '.now()->format('Y-m-d H:i').']';
        }

        if ($sobranteFaltanteAutoRemoto) {
            $tipo = $sobranteFaltante >= 0 ? 'sobrante' : 'faltante';
            $partes[] = '[Auto cierre remoto '.now()->format('Y-m-d H:i').'] Diferencia de conciliación imputada a '
                .$tipo.' ($ '.number_format(abs($sobranteFaltante), 2, ',', '.').'). '
                .'Rectificar con anulación de cierre cuando la terminal vuelva a operar.';
        }

        if ($vaciasAutoDescartadas > 0) {
            $partes[] = '[Auto '.now()->format('Y-m-d H:i').'] '.$vaciasAutoDescartadas
                .' cuenta(s) abierta(s) sin ítems descartada(s) automáticamente al cerrar el turno.';
        }

        if ($partes === []) {
            return null;
        }

        return mb_substr(implode("\n", $partes), 0, 2000);
    }
}
