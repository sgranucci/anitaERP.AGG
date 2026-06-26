<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\TurnoGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Models\Ventas\CierreParcialTurnoGastronomia;
use App\Support\Ventas\GastronomiaCierreTurnoReporteSupport;
use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\GastronomiaTurnoMediosContadoCierreSupport;
use App\Support\Ventas\GastronomiaTurnoNumeracionComprobanteSupport;
use App\Support\Ventas\GastronomiaTurnoObservacionHabilitacionSupport;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        $turnosHabilitablesIds = ($activo === null && $jornada !== null)
            ? $this->idsTurnosMaestroHabilitablesEnJornada($empresaId, (int) $jornada->id, $identificadorPc)
            : [];

        $totalesTurno = null;
        $totalesDia = null;
        $numeracionFiscal = ['filas' => []];
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
            $numeracionFiscal = GastronomiaTurnoNumeracionComprobanteSupport::paraTurno($activo);
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
            'jornada_id' => $activo?->jornada_gastronomia_id ?? $jornada?->id,
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
            'numeracion_fiscal' => $numeracionFiscal,
            'puede_habilitar' => $activo === null && $jornada !== null && $erroresHabilitacion === []
                && $turnosHabilitablesIds !== [],
            'errores_habilitacion' => $erroresHabilitacion,
            'turnos_gastronomia_habilitables_ids' => $turnosHabilitablesIds,
            'sin_turnos_por_abrir' => $activo === null
                && $jornada !== null
                && $erroresHabilitacion === []
                && $turnosHabilitablesIds === [],
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
            'url_facturas_dia' => route('gastronomia_facturas_dia', ['empresa_id' => $empresaId]),
            'url_saneamiento_turno' => route('gastronomia_saneamiento_turno', ['empresa_id' => $empresaId]),
            'cuentacaja_efectivo_id' => (int) (GastronomiaCuentacajaEfectivo::idParaEmpresa($empresaId) ?? 0),
        ];
    }

    /**
     * Estado de cierre para un turno habilitado concreto (cierre centralizado desde otra PC).
     *
     * @return array<string, mixed>
     */
    public function estadoParaTurnoOperativo(TurnoOperativoGastronomia $turno): array
    {
        if ($turno->estado !== TurnoOperativoGastronomia::ESTADO_HABILITADO) {
            throw new InvalidArgumentException('El turno no está habilitado.');
        }

        $turno->loadMissing([
            'turno',
            'jornada',
            'usuarioHabilitado',
            'configuracionPuntoventa.puntoventaCae',
            'configuracionPuntoventa.puntoventaCaea',
        ]);

        $cfg = $turno->configuracionPuntoventa;
        if ($cfg === null) {
            throw new InvalidArgumentException('El turno no tiene configuración de punto de venta.');
        }

        $pc = (string) $turno->identificador_pc;
        $empresaId = (int) $turno->empresa_id;
        $estado = $this->estadoParaTerminal($cfg, $pc);

        $estado['modo_cierre_central'] = true;
        $estado['identificador_pc_turno'] = $pc;
        $estado['puntoventa_etiqueta'] = GastronomiaCierreTurnoReporteSupport::etiquetaPuntoventaDesdeConfiguracion($cfg);
        $estado['configuracion_descripcion'] = (string) ($cfg->descripcion ?? '');

        return $estado;
    }

    /**
     * Cuentas no facturadas asociadas a la terminal del turno.
     * Incluye:
     *   - 'abierta'  → bloquea el cierre del último turno; requiere acción (facturar o cerrar sin facturar).
     *   - 'cerrada'  → cerrada sin facturar (estado terminal por saneamiento). Visible para auditoría;
     *                  NO bloquea el cierre. Si necesitás contar solo bloqueantes usá
     *                  {@see listarCuentasAbiertasEnTerminal} / {@see contarCuentasAbiertasEnTerminal}.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CuentaGastronomia>
     */
    public function listarCuentasSinFacturarEnTerminal(TurnoOperativoGastronomia $turno)
    {
        return $this->listarCuentasSinFacturarParaPuntoventa(
            (int) $turno->empresa_id,
            (int) $turno->configuracion_puntoventa_gastronomia_id,
            (string) $turno->identificador_pc,
        );
    }

    public function contarCuentasSinFacturarEnTerminal(TurnoOperativoGastronomia $turno): int
    {
        return $this->listarCuentasSinFacturarEnTerminal($turno)->count();
    }

    /**
     * Solo las cuentas en estado 'abierta' en la terminal.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CuentaGastronomia>
     */
    public function listarCuentasAbiertasEnTerminal(TurnoOperativoGastronomia $turno)
    {
        return $this->listarCuentasAbiertasParaPuntoventa(
            (int) $turno->empresa_id,
            (int) $turno->configuracion_puntoventa_gastronomia_id,
            (string) $turno->identificador_pc,
        );
    }

    public function contarCuentasAbiertasEnTerminal(TurnoOperativoGastronomia $turno): int
    {
        return $this->queryCuentasNoFacturadasParaPuntoventa(
            (int) $turno->empresa_id,
            (int) $turno->configuracion_puntoventa_gastronomia_id,
            (string) $turno->identificador_pc,
            [CuentaGastronomia::ESTADO_ABIERTA],
        )->count();
    }

    /**
     * Cuentas ABIERTA con al menos una línea (bloqueantes reales del cierre del último turno).
     */
    public function contarCuentasAbiertasConItemsEnTerminal(TurnoOperativoGastronomia $turno): int
    {
        return $this->queryCuentasNoFacturadasParaPuntoventa(
            (int) $turno->empresa_id,
            (int) $turno->configuracion_puntoventa_gastronomia_id,
            (string) $turno->identificador_pc,
            [CuentaGastronomia::ESTADO_ABIERTA],
        )->whereHas('lineas')->count();
    }

    /**
     * Cuentas ABIERTA sin líneas en la terminal (vacías; candidatas a auto-descarte).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CuentaGastronomia>
     */
    public function listarCuentasAbiertasVaciasEnTerminal(TurnoOperativoGastronomia $turno)
    {
        return $this->queryCuentasNoFacturadasParaPuntoventa(
            (int) $turno->empresa_id,
            (int) $turno->configuracion_puntoventa_gastronomia_id,
            (string) $turno->identificador_pc,
            [CuentaGastronomia::ESTADO_ABIERTA],
        )->whereDoesntHave('lineas')->get();
    }

    public function contarCuentasAbiertasVaciasEnTerminal(TurnoOperativoGastronomia $turno): int
    {
        return $this->queryCuentasNoFacturadasParaPuntoventa(
            (int) $turno->empresa_id,
            (int) $turno->configuracion_puntoventa_gastronomia_id,
            (string) $turno->identificador_pc,
            [CuentaGastronomia::ESTADO_ABIERTA],
        )->whereDoesntHave('lineas')->count();
    }

    /**
     * Cierra sin facturar las cuentas ABIERTA sin ítems de la terminal del turno.
     * Devuelve cuántas se descartaron.
     */
    public function autoDescartarCuentasAbiertasVaciasEnTerminal(TurnoOperativoGastronomia $turno): int
    {
        return $this->queryCuentasNoFacturadasParaPuntoventa(
            (int) $turno->empresa_id,
            (int) $turno->configuracion_puntoventa_gastronomia_id,
            (string) $turno->identificador_pc,
            [CuentaGastronomia::ESTADO_ABIERTA],
        )->whereDoesntHave('lineas')->update(['estado' => CuentaGastronomia::ESTADO_CERRADA]);
    }

    public function contarCuentasCerradasSinFacturarEnTerminal(TurnoOperativoGastronomia $turno): int
    {
        return $this->queryCuentasNoFacturadasParaPuntoventa(
            (int) $turno->empresa_id,
            (int) $turno->configuracion_puntoventa_gastronomia_id,
            (string) $turno->identificador_pc,
            [CuentaGastronomia::ESTADO_CERRADA],
        )->count();
    }

    /**
     * Variantes "para puntoventa" — no requieren un TurnoOperativoGastronomia activo.
     * Útiles para saneamiento cuando no hay turno habilitado (saneamiento.diagnosticoTerminal,
     * cierre administrativo de cuentas pendientes sin turno activo, etc.).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CuentaGastronomia>
     */
    public function listarCuentasSinFacturarParaPuntoventa(int $empresaId, int $cfgId, string $pc)
    {
        return $this->queryCuentasNoFacturadasParaPuntoventa(
            $empresaId,
            $cfgId,
            $pc,
            [CuentaGastronomia::ESTADO_ABIERTA, CuentaGastronomia::ESTADO_CERRADA],
        )->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CuentaGastronomia>
     */
    public function listarCuentasAbiertasParaPuntoventa(int $empresaId, int $cfgId, string $pc)
    {
        return $this->queryCuentasNoFacturadasParaPuntoventa(
            $empresaId,
            $cfgId,
            $pc,
            [CuentaGastronomia::ESTADO_ABIERTA],
        )->get();
    }

    /**
     * Cuentas abiertas/cerradas sin facturar de la empresa que NO quedaron cubiertas
     * por las terminales configuradas (loop por PV) del diagnóstico de saneamiento.
     * Las usa el bucket huérfano para garantizar que el contador de jornada
     * (`contarCuentasAbiertasConItemsPorEmpresa`) coincida con lo listado en saneamiento.
     *
     * Casos típicos que caen acá (todos invisibles antes):
     *   - Cuenta con `configuracion_puntoventa_gastronomia_id` apuntando a una PV
     *     borrada/deshabilitada (cuenta de mesa abierta vía `abrirMesa`: queda con
     *     `identificador_pc = NULL`).
     *   - Cuenta con `cfgId` y `identificador_pc` que no coinciden con ninguna PV
     *     configurada de la empresa.
     *   - Cuenta con `cfgId` apuntando a PV de otra empresa.
     *
     * @param  list<int>  $idsCubiertas  ids de cuentas ya visibles en alguna terminal configurada
     * @return \Illuminate\Database\Eloquent\Collection<int, CuentaGastronomia>
     */
    public function listarCuentasNoFacturadasNoCubiertas(int $empresaId, array $idsCubiertas)
    {
        $estados = [CuentaGastronomia::ESTADO_ABIERTA, CuentaGastronomia::ESTADO_CERRADA];

        return CuentaGastronomia::query()
            ->with(['mesa', 'mozo', 'lineas'])
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', $estados)
            ->when($idsCubiertas !== [], fn ($q) => $q->whereNotIn('id', $idsCubiertas))
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<string>  $estados
     */
    private function queryCuentasNoFacturadasParaPuntoventa(int $empresaId, int $cfgId, string $pc, array $estados)
    {
        $query = CuentaGastronomia::query()
            ->with(['mesa', 'mozo', 'lineas'])
            ->where('empresa_id', $empresaId)
            ->whereIn('estado', $estados);

        if ($cfgId <= 0 && $pc === '') {
            // No hay forma de identificar la terminal → no retornamos cuentas para evitar
            // mezclar todas las cuentas de la empresa entre terminales en saneamiento.
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where(function ($q) use ($cfgId, $pc) {
                if ($cfgId > 0) {
                    $q->where('configuracion_puntoventa_gastronomia_id', $cfgId);
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

    /**
     * Corrige el monto de habilitación de un turno aún abierto (misma PC, jornada activa).
     */
    public function actualizarMontoHabilitacion(
        int $turnoOperativoId,
        string $identificadorPc,
        float $nuevoMonto,
        ?string $motivo = null,
    ): TurnoOperativoGastronomia {
        if (! self::requiereHabilitacionTurno()) {
            throw new InvalidArgumentException(
                'El modo de operación es caja directo; la habilitación de turno no está activa.'
            );
        }

        if (Auth::id() === null) {
            throw new InvalidArgumentException('No hay usuario autenticado.');
        }

        $turno = TurnoOperativoGastronomia::query()->findOrFail($turnoOperativoId);
        $this->exigirTurnoActivoEnPc($turno, $identificadorPc);

        $jornada = $this->jornadaService->jornadaAbierta((int) $turno->empresa_id);
        if ($jornada === null) {
            throw new InvalidArgumentException('No hay jornada abierta para esta empresa.');
        }
        if ((int) $turno->jornada_gastronomia_id !== (int) $jornada->id) {
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

        return DB::transaction(function () use ($turno, $nota, $nuevoMonto, $montoAnterior, $identificadorPc, $usuarioId, $usuarioNombre) {
            $obsHab = trim((string) $turno->observacion_habilitacion);
            $obsHab = $obsHab === '' ? $nota : $obsHab."\n".$nota;

            $turno->update([
                'monto_habilitacion' => $nuevoMonto,
                'observacion_habilitacion' => mb_substr($obsHab, 0, 2000),
            ]);

            Log::info('gastronomia.turno.modificar_monto_habilitacion', [
                'turno_operativo_id' => (int) $turno->id,
                'empresa_id' => (int) $turno->empresa_id,
                'jornada_gastronomia_id' => (int) $turno->jornada_gastronomia_id,
                'identificador_pc' => $identificadorPc,
                'usuario_id' => $usuarioId,
                'usuario_nombre' => $usuarioNombre,
                'monto_anterior' => $montoAnterior,
                'monto_nuevo' => $nuevoMonto,
            ]);

            return $turno->fresh(['turno', 'jornada', 'usuarioHabilitado', 'cierresParciales']);
        });
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
     * @param  array{
     *   cierre_remoto?:bool,
     *   pc_operador?:string,
     *   omitir_validacion_jornada_posterior?:bool
     * }  $opciones
     */
    public function cerrar(
        TurnoOperativoGastronomia $turno,
        string $identificadorPc,
        array $datosCierre = [],
        array $opciones = [],
    ): TurnoOperativoGastronomia {
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

        $vaciasAutoDescartadas = $this->esUltimoTurnoDelDia($turno)
            ? $this->autoDescartarCuentasAbiertasVaciasEnTerminal($turno)
            : 0;

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

        $sobranteFaltanteAutoRemoto = false;
        if ($cierreRemoto) {
            $ajustes = GastronomiaTurnoOperativoTotalesSupport::resolverAjustesCierreConSobranteFaltanteResidual(
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

        $mediosContadoCierre = null;
        try {
            $mediosContadoCierre = GastronomiaTurnoMediosContadoCierreSupport::normalizarParaGuardar(
                $datosCierre['medios_contado'] ?? null,
                $totalesTurno,
                (int) $turno->empresa_id,
            );
        } catch (InvalidArgumentException $e) {
            throw $e;
        }

        if (! GastronomiaTurnoOperativoTotalesSupport::cierreCuadraConAjustesManuales(
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
            $max = (int) TurnoOperativoGastronomia::query()
                ->where('empresa_id', (int) $turno->empresa_id)
                ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
                ->whereNotNull('numero_cierre')
                ->lockForUpdate()
                ->max('numero_cierre');

            $turno->update([
                'estado' => TurnoOperativoGastronomia::ESTADO_CERRADO,
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
     * Bloqueantes para cerrar el turno. Solo aplica el conteo de cuentas en el último
     * turno del día.
     *
     * Política (actualizada 2026-05):
     *   - Cuentas ABIERTA con ítems  → bloquean: hay que facturarlas o cerrarlas sin facturar desde Saneamiento.
     *   - Cuentas ABIERTA sin ítems  → NO bloquean: se auto-descartan al cerrar el turno (se mueven a CERRADA).
     *   - Cuentas CERRADA (sin facturar) → estado terminal, NO bloquean.
     *
     * @param  array{omitir_validacion_jornada_posterior?:bool}  $opciones
     * @return list<string>
     */
    public function erroresAntesDeCerrar(TurnoOperativoGastronomia $turno, array $opciones = []): array
    {
        $errores = [];
        $pc = (string) $turno->identificador_pc;

        if (empty($opciones['omitir_validacion_jornada_posterior'])) {
            $this->validarCierreNoPosteriorAJornadaSiguiente($turno, $errores);
        }

        if ($this->esUltimoTurnoDelDia($turno)) {
            $abiertasConItems = $this->contarCuentasAbiertasConItemsEnTerminal($turno);

            if ($abiertasConItems > 0) {
                $errores[] = 'Hay '.$abiertasConItems.' cuenta(s) o mesa(s) ABIERTA(S) con consumos sin facturar en esta terminal ('.$pc.'). '
                    .'Al cerrar el último turno del día deben quedar facturadas o cerradas sin facturar. '
                    .'Vaya a Ventas → Gastronomía → Saneamiento de turnos para resolverlas. '
                    .'Las cuentas abiertas sin ítems se descartan automáticamente al cerrar el turno.';
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

    public const PREFIJO_CONFIRMACION_ANULAR_CIERRE = 'ANULAR-';

    /**
     * Último cierre definitivo de la terminal en la jornada indicada (para evaluar anulación).
     */
    public function ultimoTurnoCerradoEnJornadaTerminal(
        int $jornadaGastronomiaId,
        string $identificadorPc,
        int $empresaId,
    ): ?TurnoOperativoGastronomia {
        if ($identificadorPc === '' || $jornadaGastronomiaId <= 0) {
            return null;
        }

        return TurnoOperativoGastronomia::query()
            ->with(['turno', 'jornada', 'usuarioCierre', 'usuarioHabilitado'])
            ->where('jornada_gastronomia_id', $jornadaGastronomiaId)
            ->where('empresa_id', $empresaId)
            ->where('identificador_pc', $identificadorPc)
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
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
    public function evaluarAnulacionCierre(TurnoOperativoGastronomia $turno, string $identificadorPc): array
    {
        $errores = $this->erroresAntesDeAnularCierre($turno, $identificadorPc);

        return [
            'puede_anular' => $errores === [],
            'bloqueo_mensaje' => $errores !== [] ? implode(' ', $errores) : null,
        ];
    }

    /**
     * Revierte un cierre definitivo a turno habilitado (misma jornada activa, misma PC).
     *
     * @return array{turno: TurnoOperativoGastronomia, mensaje: string}
     */
    public function anularCierreDefinitivo(
        int $turnoOperativoId,
        string $identificadorPc,
        string $confirmacion,
        ?string $motivo = null,
    ): array {
        $turno = TurnoOperativoGastronomia::query()
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
                'estado' => TurnoOperativoGastronomia::ESTADO_HABILITADO,
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

            Log::info('gastronomia.turno.anular_cierre', [
                'turno_operativo_id' => (int) $turno->id,
                'empresa_id' => (int) $turno->empresa_id,
                'jornada_gastronomia_id' => (int) $turno->jornada_gastronomia_id,
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
    public function erroresAntesDeAnularCierre(TurnoOperativoGastronomia $turno, string $identificadorPc): array
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

        if ($turno->estado !== TurnoOperativoGastronomia::ESTADO_CERRADO) {
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

        if ((int) $turno->jornada_gastronomia_id !== (int) $jornadaAbierta->id) {
            $errores[] = 'El cierre pertenece a otra jornada. Solo puede anular dentro de la jornada abierta actual.';
        }

        if (RendicionGastronomiaCaja::query()
            ->where('turno_operativo_gastronomia_id', (int) $turno->id)
            ->exists()) {
            $errores[] = 'El turno tiene una rendición de gastronomía en caja asociada. Elimine o corrija la rendición antes de anular el cierre.';
        }

        if (RendicionGastronomiaCaja::query()
            ->where('tipo', RendicionGastronomiaCaja::TIPO_JORNADA)
            ->where('jornada_gastronomia_id', (int) $turno->jornada_gastronomia_id)
            ->exists()) {
            $errores[] = 'La jornada de este turno ya fue presentada en caja (rendición de jornada). Elimine esa rendición antes de anular el cierre del turno.';
        }

        $habilitado = $this->turnoHabilitadoEnPc($pcReq);
        if ($habilitado !== null && (int) $habilitado->id !== (int) $turno->id) {
            $errores[] = 'Hay otro turno habilitado en esta terminal. No puede anular un cierre anterior mientras exista un turno activo.';
        }

        $ultimo = $this->ultimoTurnoCerradoEnJornadaTerminal(
            (int) $turno->jornada_gastronomia_id,
            $pcReq,
            (int) $turno->empresa_id,
        );
        if ($ultimo !== null && (int) $ultimo->id !== (int) $turno->id) {
            $errores[] = 'Solo puede anular el último cierre definitivo de esta terminal en la jornada activa '
                .'(cierre más reciente: #'.$ultimo->id.').';
        }

        $turno->loadMissing('turno');
        $ordenActual = (int) ($turno->turno?->orden ?? 0);

        $haySiguienteTurno = TurnoOperativoGastronomia::query()
            ->where('jornada_gastronomia_id', (int) $turno->jornada_gastronomia_id)
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

    /**
     * @return array{puede_corregir: bool, bloqueo_mensaje: string|null}
     */
    public function evaluarCorreccionArqueoCierre(TurnoOperativoGastronomia $turno): array
    {
        $errores = $this->erroresAntesDeCorregirArqueoCierre($turno);

        return [
            'puede_corregir' => $errores === [],
            'bloqueo_mensaje' => $errores !== [] ? implode(' ', $errores) : null,
        ];
    }

    /**
     * Marca cada fila del listado de cierres con flags de corrección de arqueo
     * (evita mostrar «Corregir» cuando ya hay rendición en caja).
     *
     * @param  Collection<int, object>  $filas
     * @param  array<string, mixed>|null  $jornadaEstado
     * @return Collection<int, object>
     */
    public function enriquecerFilasListadoCorreccionArqueo(
        Collection $filas,
        int $empresaIdJornada,
        ?array $jornadaEstado,
    ): Collection {
        $jornadaAbierta = ! empty($jornadaEstado['jornada_abierta']);
        $jornadaId = (int) ($jornadaEstado['jornada_id'] ?? 0);

        $cierreIds = $filas
            ->filter(fn ($f) => ($f->tipo ?? '') === 'cierre')
            ->filter(fn ($f) => (int) ($f->empresa_id ?? 0) === $empresaIdJornada
                && (int) ($f->jornada_gastronomia_id ?? 0) === $jornadaId)
            ->pluck('id');

        $rendTurnoIds = $cierreIds->isEmpty()
            ? collect()
            : RendicionGastronomiaCaja::query()
                ->whereIn('turno_operativo_gastronomia_id', $cierreIds->all())
                ->pluck('turno_operativo_gastronomia_id')
                ->flip();

        $jornadaIds = $filas
            ->filter(fn ($f) => ($f->tipo ?? '') === 'cierre')
            ->pluck('jornada_gastronomia_id')
            ->unique()
            ->filter(fn ($id) => (int) $id > 0);

        $rendJornadaIds = $jornadaIds->isEmpty()
            ? collect()
            : RendicionGastronomiaCaja::query()
                ->where('tipo', RendicionGastronomiaCaja::TIPO_JORNADA)
                ->whereIn('jornada_gastronomia_id', $jornadaIds->all())
                ->pluck('jornada_gastronomia_id')
                ->flip();

        return $filas->map(function ($f) use (
            $empresaIdJornada,
            $jornadaId,
            $jornadaAbierta,
            $rendTurnoIds,
            $rendJornadaIds,
        ) {
            $f->puede_corregir_arqueo_fila = false;
            $f->bloqueo_corregir_arqueo_fila = null;
            $f->mostrar_arqueo_cierre_fila = false;

            if (($f->tipo ?? '') !== 'cierre') {
                return $f;
            }

            if (! $jornadaAbierta) {
                return $f;
            }

            if ((int) ($f->empresa_id ?? 0) !== $empresaIdJornada) {
                return $f;
            }

            if ((int) ($f->jornada_gastronomia_id ?? 0) !== $jornadaId) {
                $f->bloqueo_corregir_arqueo_fila = 'El cierre pertenece a otra jornada.';

                return $f;
            }

            $f->mostrar_arqueo_cierre_fila = true;
            $errores = [];

            if ($rendTurnoIds->has((int) ($f->id ?? 0))) {
                $errores[] = 'El turno ya fue presentado en caja (rendición de turno). '
                    .'Elimine o ajuste esa rendición antes de modificar el arqueo.';
            }

            if ($rendJornadaIds->has((int) ($f->jornada_gastronomia_id ?? 0))) {
                $errores[] = 'La jornada ya fue presentada en caja (rendición de jornada). '
                    .'Elimine esa rendición antes de modificar el arqueo.';
            }

            if ($errores === []) {
                $f->puede_corregir_arqueo_fila = true;
            } else {
                $f->bloqueo_corregir_arqueo_fila = implode(' ', $errores);
            }

            return $f;
        });
    }

    /**
     * @return array{
     *   turno: TurnoOperativoGastronomia,
     *   totales_turno: array<string, mixed>,
     *   mensaje: string
     * }
     */
    public function corregirArqueoCierreDefinitivo(
        TurnoOperativoGastronomia $turno,
        array $datosCorreccion,
    ): array {
        $errores = $this->erroresAntesDeCorregirArqueoCierre($turno);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        $motivo = $this->limpiarObservacion($datosCorreccion['motivo'] ?? null);
        if ($motivo === '') {
            throw new InvalidArgumentException('Debe indicar el motivo de la corrección.');
        }

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? ($turno->cierre_en?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d'));

        $totalesTurno = GastronomiaTurnoOperativoTotalesSupport::calcular(
            (string) $turno->identificador_pc,
            (int) $turno->empresa_id,
            $fechaJornada,
            $turno->habilitacion_en,
            $turno->cierre_en,
        );

        $redondeoInvitaciones = round((float) ($datosCorreccion['redondeo_invitaciones'] ?? $turno->redondeo_invitaciones ?? 0), 2);
        $redondeoTurno = round((float) ($datosCorreccion['redondeo_turno'] ?? $turno->redondeo_turno ?? 0), 2);
        $sobranteFaltante = round((float) ($datosCorreccion['sobrante_faltante'] ?? $turno->sobrante_faltante ?? 0), 2);

        $mediosContadoCierre = GastronomiaTurnoMediosContadoCierreSupport::normalizarParaGuardar(
            $datosCorreccion['medios_contado'] ?? null,
            $totalesTurno,
            (int) $turno->empresa_id,
        );

        if (! GastronomiaTurnoOperativoTotalesSupport::cierreCuadraConAjustesManuales(
            $totalesTurno,
            $redondeoInvitaciones,
            $redondeoTurno,
            $sobranteFaltante,
        )) {
            $diff = round((float) ($totalesTurno['diferencia_cobranza'] ?? 0), 2);
            throw new InvalidArgumentException(
                'Los ajustes no absorben la diferencia de conciliación del turno ($ '
                .number_format(abs($diff), 2, ',', '.').'). '
                .'Revise redondeo invitaciones, redondeo turno y sobrante/faltante.'
            );
        }

        $usuario = Auth::user();
        $usuarioId = (int) ($usuario?->id ?? 0);
        $usuarioNombre = (string) ($usuario?->nombre ?? 'usuario');

        $snapshotAnterior = [
            'redondeo_invitaciones' => $turno->redondeo_invitaciones,
            'redondeo_turno' => $turno->redondeo_turno,
            'sobrante_faltante' => $turno->sobrante_faltante,
            'medios_contado_cierre_json' => $turno->medios_contado_cierre_json,
        ];

        $nota = GastronomiaTurnoObservacionHabilitacionSupport::notaCorreccionArqueoCierre(
            $usuarioId,
            $usuarioNombre,
            $turno->numero_cierre !== null ? (int) $turno->numero_cierre : null,
            $turno->cierre_en,
            $motivo,
        );

        return DB::transaction(function () use (
            $turno,
            $redondeoInvitaciones,
            $redondeoTurno,
            $sobranteFaltante,
            $mediosContadoCierre,
            $nota,
            $usuarioId,
            $usuarioNombre,
            $snapshotAnterior,
            $totalesTurno,
        ) {
            $obsCierre = trim((string) $turno->observacion_cierre);
            $obsCierre = $obsCierre === '' ? $nota : $obsCierre."\n".$nota;

            $turno->update([
                'redondeo_invitaciones' => $redondeoInvitaciones,
                'redondeo_turno' => $redondeoTurno,
                'sobrante_faltante' => $sobranteFaltante,
                'medios_contado_cierre_json' => $mediosContadoCierre,
                'observacion_cierre' => $obsCierre,
            ]);

            Log::info('gastronomia.turno.corregir_arqueo_cierre', [
                'turno_operativo_id' => (int) $turno->id,
                'usuario_id' => $usuarioId,
                'usuario_nombre' => $usuarioNombre,
                'anterior' => $snapshotAnterior,
                'nuevo' => [
                    'redondeo_invitaciones' => $redondeoInvitaciones,
                    'redondeo_turno' => $redondeoTurno,
                    'sobrante_faltante' => $sobranteFaltante,
                    'medios_contado_cierre_json' => $mediosContadoCierre,
                ],
            ]);

            $totalesEnriquecidos = GastronomiaTurnoMediosContadoCierreSupport::enriquecerTotalesConContado(
                $totalesTurno,
                $mediosContadoCierre,
            );

            return [
                'turno' => $turno->fresh(['turno', 'jornada', 'usuarioCierre']),
                'totales_turno' => $totalesEnriquecidos,
                'mensaje' => 'Arqueo y ajustes del cierre actualizados correctamente.',
            ];
        });
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeCorregirArqueoCierre(TurnoOperativoGastronomia $turno): array
    {
        $errores = [];

        if ($turno->estado !== TurnoOperativoGastronomia::ESTADO_CERRADO) {
            $errores[] = 'Solo puede corregir el arqueo de un cierre definitivo ya registrado.';

            return $errores;
        }

        if ($turno->cierre_en === null) {
            $errores[] = 'El turno no tiene fecha de cierre registrada.';
        }

        $jornadaAbierta = $this->jornadaService->jornadaAbierta((int) $turno->empresa_id);
        if ($jornadaAbierta === null) {
            $errores[] = 'No hay jornada abierta. Solo puede corregir arqueos de cierres del día operativo en curso.';
        } elseif ((int) $turno->jornada_gastronomia_id !== (int) $jornadaAbierta->id) {
            $errores[] = 'El cierre pertenece a otra jornada. Solo puede corregir cierres del día operativo actual.';
        }

        $errores = array_merge($errores, $this->erroresSiTurnoPresentadoEnCaja($turno));

        if (Auth::id() === null) {
            $errores[] = 'No hay usuario autenticado.';
        }

        return $errores;
    }

    /**
     * Bloquea corrección/anulación si el turno o su jornada ya tienen rendición en caja.
     *
     * @return list<string>
     */
    public function erroresSiTurnoPresentadoEnCaja(TurnoOperativoGastronomia $turno): array
    {
        $errores = [];

        if (RendicionGastronomiaCaja::query()
            ->where('turno_operativo_gastronomia_id', (int) $turno->id)
            ->exists()) {
            $errores[] = 'El turno ya fue presentado en caja (rendición de turno). '
                .'Elimine o ajuste esa rendición antes de modificar el arqueo.';
        }

        if (RendicionGastronomiaCaja::query()
            ->where('tipo', RendicionGastronomiaCaja::TIPO_JORNADA)
            ->where('jornada_gastronomia_id', (int) $turno->jornada_gastronomia_id)
            ->exists()) {
            $errores[] = 'La jornada ya fue presentada en caja (rendición de jornada). '
                .'Elimine esa rendición antes de modificar el arqueo del cierre.';
        }

        return $errores;
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
        return $this->idsTurnosMaestroHabilitablesEnJornada(
            $empresaId,
            $jornadaGastronomiaId,
            $identificadorPc,
        ) !== [];
    }

    /**
     * Mayor `orden` entre turnos ya habilitados o cerrados en la jornada y terminal.
     * Si aún no se abrió ninguno, devuelve 0.
     */
    public function maxOrdenTurnoUsadoEnJornadaTerminal(
        int $jornadaGastronomiaId,
        string $identificadorPc,
    ): int {
        if ($identificadorPc === '' || $jornadaGastronomiaId <= 0) {
            return 0;
        }

        $maxOrden = TurnoOperativoGastronomia::query()
            ->where('jornada_gastronomia_id', $jornadaGastronomiaId)
            ->where('identificador_pc', $identificadorPc)
            ->whereIn('estado', [
                TurnoOperativoGastronomia::ESTADO_HABILITADO,
                TurnoOperativoGastronomia::ESTADO_CERRADO,
            ])
            ->join(
                'turno_gastronomia',
                'turno_gastronomia.id',
                '=',
                'turno_operativo_gastronomia.turno_gastronomia_id',
            )
            ->max('turno_gastronomia.orden');

        return max(0, (int) $maxOrden);
    }

    /**
     * Turnos maestros que pueden habilitarse respetando orden y cierres previos en la terminal.
     *
     * Regla: solo turnos no cerrados cuyo `orden` sea mayor al máximo ya utilizado en la jornada.
     * Permite saltar turnos intermedios hacia adelante, pero no volver atrás tras uno posterior.
     *
     * @return list<int>
     */
    public function idsTurnosMaestroHabilitablesEnJornada(
        int $empresaId,
        int $jornadaGastronomiaId,
        string $identificadorPc,
    ): array {
        if ($identificadorPc === '' || $empresaId <= 0 || $jornadaGastronomiaId <= 0) {
            return [];
        }

        $cerrados = $this->idsTurnosMaestroCerradosEnJornada($jornadaGastronomiaId, $identificadorPc);
        $maxOrdenUsado = $this->maxOrdenTurnoUsadoEnJornadaTerminal($jornadaGastronomiaId, $identificadorPc);

        return TurnoGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->filter(function (TurnoGastronomia $turno) use ($cerrados, $maxOrdenUsado) {
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
                .'). Cierre el turno habilitado antes de abrir la jornada del día siguiente.';

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

    /**
     * Terminal con al menos un comprobante gastronómico en la jornada del turno.
     */
    public function terminalTuvoActividadEnJornada(TurnoOperativoGastronomia $turno): bool
    {
        $pc = trim((string) $turno->identificador_pc);
        $empresaId = (int) $turno->empresa_id;
        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d');

        if ($pc === '' || $empresaId <= 0 || $fechaJornada === null || $fechaJornada === '') {
            return true;
        }

        return \App\Models\Ventas\VentaGastronomiaEmision::query()
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
     * @return list<array{
     *   turno_operativo_id:int,
     *   identificador_pc:string,
     *   turno_nombre:string,
     *   habilitacion_en:string,
     *   con_actividad:bool,
     *   es_ultimo_turno_dia:bool
     * }>
     */
    public function listarTurnosHabilitadosParaCierreRemoto(int $empresaId, ?JornadaGastronomia $jornada = null): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        $jornada ??= $this->jornadaService->jornadaAbierta($empresaId);
        if ($jornada === null) {
            return [];
        }

        return TurnoOperativoGastronomia::query()
            ->with('turno')
            ->where('empresa_id', $empresaId)
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->where('estado', TurnoOperativoGastronomia::ESTADO_HABILITADO)
            ->orderBy('identificador_pc')
            ->orderBy('habilitacion_en')
            ->get()
            ->map(fn (TurnoOperativoGastronomia $t) => [
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
     * Turnos habilitados en la jornada con totales para la grilla de cierre centralizado.
     *
     * @return list<array{
     *   turno_operativo_id:int,
     *   identificador_pc:string,
     *   turno_nombre:string,
     *   habilitacion_en:string,
     *   con_actividad:bool,
     *   es_ultimo_turno_dia:bool,
     *   puntoventa_etiqueta:string,
     *   configuracion_descripcion:string,
     *   total_ventas:float,
     *   cantidad_comprobantes:int,
     *   conciliacion_ok:bool,
     *   usuario_habilitado:?string
     * }>
     */
    public function listarTurnosParaCierreCentral(int $empresaId, ?JornadaGastronomia $jornada = null): array
    {
        $base = $this->listarTurnosHabilitadosParaCierreRemoto($empresaId, $jornada);
        if ($base === []) {
            return [];
        }

        $turnoIds = array_column($base, 'turno_operativo_id');
        $turnos = TurnoOperativoGastronomia::query()
            ->with([
                'turno',
                'jornada',
                'usuarioHabilitado',
                'configuracionPuntoventa.puntoventaCae',
                'configuracionPuntoventa.puntoventaCaea',
            ])
            ->whereIn('id', $turnoIds)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($base as $row) {
            $turno = $turnos->get((int) $row['turno_operativo_id']);
            if ($turno === null) {
                continue;
            }

            $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
                ?? Carbon::today()->format('Y-m-d');
            $totales = GastronomiaTurnoOperativoTotalesSupport::calcular(
                (string) $turno->identificador_pc,
                $empresaId,
                $fechaJornada,
                $turno->habilitacion_en,
            );

            $cfg = $turno->configuracionPuntoventa;
            $out[] = array_merge($row, [
                'puntoventa_etiqueta' => GastronomiaCierreTurnoReporteSupport::etiquetaPuntoventaDesdeConfiguracion($cfg),
                'configuracion_descripcion' => (string) ($cfg?->descripcion ?? ''),
                'total_ventas' => round((float) ($totales['total_ventas'] ?? 0), 2),
                'cantidad_comprobantes' => (int) ($totales['cantidad_comprobantes'] ?? 0),
                'conciliacion_ok' => ! empty($totales['conciliacion_ok']),
                'usuario_habilitado' => $turno->usuarioHabilitado?->nombre,
            ]);
        }

        return $out;
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
