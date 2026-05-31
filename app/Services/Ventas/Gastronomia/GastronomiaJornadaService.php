<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\CierreTotemJornadaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Repositories\Ventas\JornadaGastronomiaRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Apertura/cierre de jornada operativa por empresa (solo gastronomía).
 * fecha factura = día calendario real; fecha jornada = día de turno abierto.
 */
final class GastronomiaJornadaService
{
    public function __construct(
        private readonly JornadaGastronomiaRepositoryInterface $jornadaRepository,
        private readonly GastronomiaCierreTotemJornadaService $cierreTotemJornadaService,
    ) {
    }

    public function jornadaAbierta(int $empresaId): ?JornadaGastronomia
    {
        if ($empresaId <= 0) {
            return null;
        }

        return $this->jornadaRepository->jornadaAbiertaPorEmpresa($empresaId);
    }

    public function exigirJornadaAbierta(int $empresaId): JornadaGastronomia
    {
        $jornada = $this->jornadaAbierta($empresaId);
        if ($jornada === null) {
            throw new InvalidArgumentException(
                'No hay jornada abierta para esta empresa. Abra la jornada en Ventas → Gastronomía → Jornada.'
            );
        }

        return $jornada;
    }

    /**
     * @return array{fechafactura:string,fechajornada:string,jornada_id:int}
     */
    public function fechasParaEmision(int $empresaId): array
    {
        $jornada = $this->exigirJornadaAbierta($empresaId);

        return [
            'fechafactura' => Carbon::today()->format('Y-m-d'),
            'fechajornada' => $jornada->fecha_jornada->format('Y-m-d'),
            'jornada_id' => (int) $jornada->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function aplicarFechasAlPayload(array $payload, int $empresaId): array
    {
        $fechas = $this->fechasParaEmision($empresaId);
        $payload['fechafactura'] = $fechas['fechafactura'];
        $payload['fechajornada'] = $fechas['fechajornada'];
        $payload['jornada_gastronomia_id'] = $fechas['jornada_id'];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function estadoParaEmpresa(int $empresaId): array
    {
        $abierta = $this->jornadaAbierta($empresaId);
        $hoy = Carbon::today()->format('Y-m-d');

        $cuentasAbiertasConItems = $abierta !== null
            ? $this->contarCuentasAbiertasConItemsPorEmpresa($empresaId)
            : 0;
        $cuentasAbiertasVacias = $abierta !== null
            ? $this->contarCuentasAbiertasVaciasPorEmpresa($empresaId)
            : 0;

        $ultimaJornada = $this->jornadaRepository->ultimaJornadaPorEmpresa($empresaId);
        $fechasApertura = $this->fechasSugeridasApertura($ultimaJornada);

        return [
            'empresa_id' => $empresaId,
            'jornada_abierta' => $abierta !== null,
            'jornada_id' => $abierta?->id,
            'fecha_jornada' => $abierta?->fecha_jornada?->format('Y-m-d'),
            'fecha_jornada_fmt' => $abierta?->fecha_jornada
                ? $this->formatearFechaEncabezado($abierta->fecha_jornada->format('Y-m-d'))
                : null,
            'fecha_factura_hoy' => $hoy,
            'fecha_factura_hoy_fmt' => $this->formatearFechaEncabezado($hoy),
            'apertura_en' => $abierta?->apertura_en?->format('d/m/Y H:i'),
            'fecha_jornada_minima_abrir' => $fechasApertura['minima'],
            'fecha_jornada_sugerida_abrir' => $fechasApertura['sugerida'],
            'usuario_apertura' => $abierta?->usuarioApertura?->nombre,
            'observacion_apertura' => $abierta?->observacion_apertura,
            'puede_abrir' => $abierta === null,
            'puede_cerrar' => $abierta !== null,
            'motivo_no_puede_abrir' => $abierta !== null
                ? $this->describirJornadaAbierta($abierta)
                : null,
            'errores_cierre' => $abierta !== null
                ? $this->erroresAntesDeCerrar($empresaId)
                : [],
            'turnos_habilitados' => $abierta !== null
                ? $this->detalleTurnosHabilitadosJornada($empresaId, $abierta)
                : [],
            'nota_politica_turnos' => config('gastronomia.requiere_habilitacion_turno', true)
                ? 'Cada turno que se habilitó en la jornada debe cerrarse antes de cerrar la jornada '
                    .'(en su terminal o con cierre remoto en Saneamiento). '
                    .'No es obligatorio que todas las PCs de la red habiliten turno noche: '
                    .'las terminales que operan solo uno o dos turnos del día no bloquean por no haber abierto noche.'
                : null,
            'cuentas_abiertas_con_items' => $cuentasAbiertasConItems,
            'cuentas_abiertas_vacias' => $cuentasAbiertasVacias,
        ];
    }

    /**
     * Valida reglas de negocio antes de abrir; lanza InvalidArgumentException con detalle.
     */
    public function validarApertura(int $empresaId, string $fechaJornada): string
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException(
                'Debe seleccionar una empresa antes de abrir la jornada.'
            );
        }

        if (trim($fechaJornada) === '') {
            throw new InvalidArgumentException(
                'Debe indicar la fecha de jornada (día de turno).'
            );
        }

        $fecha = $this->normalizarFecha($fechaJornada, 'fecha de jornada');
        $fechaFmt = $this->formatearFechaHumana($fecha);

        $abierta = $this->jornadaAbierta($empresaId);
        if ($abierta !== null) {
            throw new InvalidArgumentException(
                'No puede abrir la jornada del '.$fechaFmt.': '.$this->describirJornadaAbierta($abierta)
                .' Cierre esa jornada antes de abrir otra (aunque sea de otra fecha).'
            );
        }

        if (config('gastronomia.requiere_habilitacion_turno', true)) {
            $turnosHabilitados = TurnoOperativoGastronomia::query()
                ->with('jornada')
                ->where('empresa_id', $empresaId)
                ->where('estado', TurnoOperativoGastronomia::ESTADO_HABILITADO)
                ->get();

            if ($turnosHabilitados->isNotEmpty()) {
                $detalle = $turnosHabilitados
                    ->map(function (TurnoOperativoGastronomia $t) {
                        $fj = $t->jornada?->fecha_jornada?->format('d/m/Y') ?? '?';

                        return $t->identificador_pc.' (jornada '.$fj.')';
                    })
                    ->implode(', ');

                throw new InvalidArgumentException(
                    'No puede abrir una jornada nueva: hay turno(s) habilitado(s) sin cerrar en: '.$detalle
                    .'. Cierre cada turno que se habilitó antes de abrir la jornada del día siguiente.'
                );
            }
        }

        $ultima = $this->jornadaRepository->ultimaJornadaPorEmpresa($empresaId);
        if ($ultima !== null) {
            $ultimaFecha = $ultima->fecha_jornada->format('Y-m-d');
            if ($fecha <= $ultimaFecha) {
                $ultimaFmt = $this->formatearFechaHumana($ultimaFecha);
                $estadoUltima = $ultima->estado === JornadaGastronomia::ESTADO_ABIERTA
                    ? 'abierta'
                    : 'cerrada';

                throw new InvalidArgumentException(
                    'No puede abrir la jornada del '.$fechaFmt.': la última jornada registrada es del '
                    .$ultimaFmt.' ('.$estadoUltima.'). Solo puede abrir jornadas de fechas posteriores.'
                );
            }
        }

        return $fecha;
    }

    public function abrir(int $empresaId, string $fechaJornada, ?string $observacion = null): JornadaGastronomia
    {
        $fecha = $this->validarApertura($empresaId, $fechaJornada);

        if (Auth::id() === null) {
            throw new InvalidArgumentException(
                'No hay usuario autenticado. Vuelva a iniciar sesión e intente abrir la jornada.'
            );
        }

        try {
            return DB::transaction(function () use ($empresaId, $fecha, $observacion) {
                $existente = JornadaGastronomia::query()
                    ->with(['usuarioApertura', 'empresa'])
                    ->where('empresa_id', $empresaId)
                    ->where('estado', JornadaGastronomia::ESTADO_ABIERTA)
                    ->lockForUpdate()
                    ->first();

                if ($existente !== null) {
                    throw new InvalidArgumentException(
                        'No puede abrir la jornada del '.$this->formatearFechaHumana($fecha).': '
                        .$this->describirJornadaAbierta($existente)
                        .' (otro usuario pudo haberla abierto en este momento).'
                    );
                }

                return $this->jornadaRepository->create([
                    'empresa_id' => $empresaId,
                    'fecha_jornada' => $fecha,
                    'estado' => JornadaGastronomia::ESTADO_ABIERTA,
                    'usuario_apertura_id' => Auth::id(),
                    'apertura_en' => now(),
                    'observacion_apertura' => $this->limpiarObservacion($observacion),
                ]);
            });
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidArgumentException(self::mensajeDesdeExcepcion($e), 0, $e);
        }
    }

    public function cerrar(int $empresaId, ?string $observacion = null): JornadaGastronomia
    {
        $jornada = $this->exigirJornadaAbierta($empresaId);

        $errores = $this->erroresAntesDeCerrar($empresaId);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        $vaciasAutoDescartadas = $this->autoDescartarCuentasAbiertasVaciasPorEmpresa($empresaId);

        $observacionFinal = $this->componerObservacionCierreJornada($observacion, $vaciasAutoDescartadas);

        $this->jornadaRepository->update([
            'estado' => JornadaGastronomia::ESTADO_CERRADA,
            'usuario_cierre_id' => Auth::id(),
            'cierre_en' => now(),
            'observacion_cierre' => $observacionFinal,
        ], (int) $jornada->id);

        $jornada = $this->jornadaRepository->findOrFail((int) $jornada->id);

        if ($this->cierreTotemJornadaService->habilitado()) {
            $this->cierreTotemJornadaService->registrarAlCerrarJornada($jornada);
        }

        return $jornada;
    }

    /**
     * Bloqueantes para cerrar la jornada.
     *
     * Política (actualizada 2026-05):
     *   - Cuentas ABIERTA con ítems  → bloquean: hay que facturarlas o cerrarlas sin facturar desde Saneamiento.
     *   - Cuentas ABIERTA sin ítems  → NO bloquean: se auto-descartan al cerrar la jornada (se mueven a CERRADA).
     *   - Cuentas CERRADA (sin facturar) → estado terminal, NO bloquean.
     *   - Turno operativo HABILITADO en la jornada → bloquea (cada habilitación debe cerrarse y rendirse).
     *     No exige que todas las PCs habiliten turno noche: solo las que abrieron un turno deben cerrarlo.
     *
     * @return list<string>
     */
    public function erroresAntesDeCerrar(int $empresaId): array
    {
        $errores = [];

        $cuentasAbiertasConItems = $this->contarCuentasAbiertasConItemsPorEmpresa($empresaId);

        if ($cuentasAbiertasConItems > 0) {
            $errores[] = 'Hay '.$cuentasAbiertasConItems.' cuenta(s) de mesa ABIERTA(S) con consumos sin facturar. '
                .'Factúrelas o ciérrelas sin facturar desde Ventas → Gastronomía → '
                .'Saneamiento de turnos antes de cerrar la jornada. '
                .'Las cuentas abiertas sin ítems se descartan automáticamente al cerrar la jornada.';
        }

        if (config('gastronomia.requiere_habilitacion_turno', true)) {
            $habilitados = $this->detalleTurnosHabilitadosJornada($empresaId);
            if ($habilitados !== []) {
                $detalle = collect($habilitados)
                    ->map(fn (array $t) => $t['identificador_pc'].' ('.$t['turno_nombre'].')')
                    ->implode(', ');
                $errores[] = 'Hay '.count($habilitados).' turno(s) habilitado(s) sin cerrar: '.$detalle.'. '
                    .'Cada turno que se abrió debe cerrarse (en su terminal o cierre remoto en Saneamiento). '
                    .'Las PCs que no habilitaron turno en esta jornada no bloquean el cierre.';
            }
        }

        return $errores;
    }

    /**
     * Turnos operativos aún habilitados en la jornada abierta (todos bloquean el cierre de jornada).
     *
     * @return list<array{
     *   turno_operativo_id:int,
     *   identificador_pc:string,
     *   turno_nombre:string,
     *   habilitacion_en:string,
     *   con_actividad:bool
     * }>
     */
    public function detalleTurnosHabilitadosJornada(int $empresaId, ?JornadaGastronomia $jornada = null): array
    {
        $jornada ??= $this->jornadaAbierta($empresaId);
        if ($jornada === null) {
            return [];
        }

        $turnoService = app(GastronomiaTurnoOperativoService::class);

        return TurnoOperativoGastronomia::query()
            ->with('turno')
            ->where('empresa_id', $empresaId)
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->where('estado', TurnoOperativoGastronomia::ESTADO_HABILITADO)
            ->orderBy('identificador_pc')
            ->get()
            ->map(fn (TurnoOperativoGastronomia $t) => [
                'turno_operativo_id' => (int) $t->id,
                'identificador_pc' => (string) $t->identificador_pc,
                'turno_nombre' => (string) ($t->turno?->nombre ?? ''),
                'habilitacion_en' => $t->habilitacion_en?->format('d/m/Y H:i') ?? '',
                'con_actividad' => $turnoService->terminalTuvoActividadEnJornada($t),
            ])
            ->values()
            ->all();
    }

    /**
     * Cuenta cuentas ABIERTA con al menos una línea de consumo (bloqueantes reales).
     */
    public function contarCuentasAbiertasConItemsPorEmpresa(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        return CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->whereHas('lineas')
            ->count();
    }

    /**
     * Cuenta cuentas ABIERTA sin líneas (vacías; candidatas a auto-descarte al cerrar).
     */
    public function contarCuentasAbiertasVaciasPorEmpresa(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        return CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->whereDoesntHave('lineas')
            ->count();
    }

    /**
     * Cierra sin facturar todas las cuentas ABIERTA sin ítems de la empresa.
     * Devuelve cuántas se descartaron.
     */
    public function autoDescartarCuentasAbiertasVaciasPorEmpresa(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        return CuentaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', CuentaGastronomia::ESTADO_ABIERTA)
            ->whereDoesntHave('lineas')
            ->update(['estado' => CuentaGastronomia::ESTADO_CERRADA]);
    }

    private function componerObservacionCierreJornada(?string $observacion, int $vaciasAutoDescartadas): ?string
    {
        $base = $this->limpiarObservacion($observacion);

        if ($vaciasAutoDescartadas <= 0) {
            return $base;
        }

        $nota = '[Auto '.now()->format('Y-m-d H:i').'] '.$vaciasAutoDescartadas
            .' cuenta(s) abierta(s) sin ítems descartada(s) automáticamente al cerrar la jornada.';
        $obs = $base === null ? $nota : $base."\n".$nota;

        return mb_substr($obs, 0, 2000);
    }

    /**
     * @return array{
     *   puede_eliminar: bool,
     *   motivo_no_eliminar: ?string,
     *   turnos_operativos: int,
     *   ventas_gastronomia: int
     * }
     */
    public function resumenEliminacion(JornadaGastronomia $jornada): array
    {
        $turnos = $this->contarTurnosOperativosJornada($jornada);
        $ventas = $this->contarVentasGastronomiaJornada($jornada);

        if ($turnos > 0 || $ventas > 0) {
            $partes = [];
            if ($turnos > 0) {
                $partes[] = $turnos.' turno(s) operativo(s)';
            }
            if ($ventas > 0) {
                $partes[] = $ventas.' comprobante(s) gastronómicos';
            }

            return [
                'puede_eliminar' => false,
                'motivo_no_eliminar' => 'No se puede eliminar: la jornada tiene '.implode(' y ', $partes).'.',
                'turnos_operativos' => $turnos,
                'ventas_gastronomia' => $ventas,
            ];
        }

        return [
            'puede_eliminar' => true,
            'motivo_no_eliminar' => null,
            'turnos_operativos' => 0,
            'ventas_gastronomia' => 0,
        ];
    }

    public function eliminar(int $jornadaId): void
    {
        $jornada = $this->jornadaRepository->findOrFail($jornadaId);
        $resumen = $this->resumenEliminacion($jornada);

        if (! $resumen['puede_eliminar']) {
            throw new InvalidArgumentException(
                $resumen['motivo_no_eliminar'] ?? 'No se puede eliminar la jornada porque tiene movimientos.'
            );
        }

        try {
            DB::transaction(function () use ($jornada) {
                CierreTotemJornadaGastronomia::query()
                    ->where('jornada_gastronomia_id', (int) $jornada->id)
                    ->delete();

                $this->jornadaRepository->delete((int) $jornada->id);
            });
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidArgumentException(self::mensajeDesdeExcepcion($e), 0, $e);
        }
    }

    public function exigirJornadaSiConfigurada(int $empresaId): void
    {
        if (! config('gastronomia.jornada_obligatoria', true)) {
            return;
        }

        $this->exigirJornadaAbierta($empresaId);
    }

    public static function mensajeDesdeExcepcion(Throwable $e): string
    {
        if ($e instanceof InvalidArgumentException) {
            return $e->getMessage();
        }

        $prev = $e->getPrevious();
        if ($prev instanceof QueryException) {
            $sqlMsg = $prev->getMessage();

            if (str_contains($sqlMsg, 'foreign key constraint') || str_contains($sqlMsg, 'FOREIGN KEY')) {
                if (str_contains($sqlMsg, 'empresa_id')) {
                    return 'La empresa seleccionada no existe en el sistema o no puede usarse para jornadas.';
                }
                if (str_contains($sqlMsg, 'usuario_apertura_id')) {
                    return 'No se pudo asociar su usuario a la apertura. Cierre sesión, vuelva a entrar e intente de nuevo.';
                }

                return 'No se pudo grabar la jornada por un dato referenciado inválido (restricción de base de datos).';
            }

            if (str_contains($sqlMsg, 'Duplicate entry') || str_contains($sqlMsg, 'duplicate key')) {
                return 'Ya existe un registro de jornada con esos datos. Consulte el historial o cierre la jornada anterior.';
            }
        }

        $detalle = trim($e->getMessage());
        if ($detalle === '') {
            return 'Error inesperado al abrir la jornada. Revise el log del servidor o contacte soporte.';
        }

        return 'Error al abrir la jornada: '.$detalle;
    }

    private function fechasSugeridasApertura(?JornadaGastronomia $ultimaJornada): array
    {
        $hoy = Carbon::today()->format('Y-m-d');
        $minima = null;

        if ($ultimaJornada?->fecha_jornada !== null) {
            $minima = $ultimaJornada->fecha_jornada->copy()->addDay()->format('Y-m-d');
        }

        $sugerida = $minima !== null && $minima > $hoy ? $minima : $hoy;

        return [
            'minima' => $minima,
            'sugerida' => $sugerida,
        ];
    }

    private function contarTurnosOperativosJornada(JornadaGastronomia $jornada): int
    {
        return (int) TurnoOperativoGastronomia::query()
            ->where('jornada_gastronomia_id', (int) $jornada->id)
            ->count();
    }

    private function contarVentasGastronomiaJornada(JornadaGastronomia $jornada): int
    {
        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d');

        if ($empresaId <= 0 || $fechaJornada === null || $fechaJornada === '') {
            return 0;
        }

        return (int) VentaGastronomiaEmision::query()
            ->whereHas('venta', function ($v) use ($empresaId, $fechaJornada) {
                $v->where(function ($fecha) use ($fechaJornada) {
                    $fecha->whereDate('fechajornada', $fechaJornada)
                        ->orWhere(function ($legacy) use ($fechaJornada) {
                            $legacy->whereNull('fechajornada')
                                ->whereDate('fecha', $fechaJornada);
                        });
                })->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->count();
    }

    private function describirJornadaAbierta(JornadaGastronomia $jornada): string
    {
        $jornada->loadMissing(['usuarioApertura', 'empresa']);

        $fecha = $jornada->fecha_jornada
            ? $this->formatearFechaHumana($jornada->fecha_jornada->format('Y-m-d'))
            : '(sin fecha)';
        $empresa = $jornada->empresa?->nombre ?? ('empresa #'.$jornada->empresa_id);
        $usuario = $jornada->usuarioApertura?->nombre ?? 'usuario desconocido';
        $cuando = $jornada->apertura_en?->format('d/m/Y H:i') ?? 'fecha no registrada';

        return 'ya hay una jornada abierta del '.$fecha.' ('.$empresa.', id '.$jornada->id
            .', abierta por '.$usuario.' el '.$cuando.').';
    }

    private function formatearFechaHumana(string $fechaYmd): string
    {
        try {
            return Carbon::parse($fechaYmd)->format('d/m/Y');
        } catch (Throwable) {
            return $fechaYmd;
        }
    }

    private function formatearFechaEncabezado(string $fechaYmd): string
    {
        try {
            return Carbon::parse($fechaYmd)->format('d-m-Y');
        } catch (Throwable) {
            return $fechaYmd;
        }
    }

    private function normalizarFecha(string $fecha, string $etiqueta): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            throw new InvalidArgumentException('La '.$etiqueta.' está vacía.');
        }

        try {
            $parsed = Carbon::parse($fecha);
        } catch (Throwable) {
            throw new InvalidArgumentException(
                'La '.$etiqueta.' "'.$fecha.'" no es válida. Use el selector de fecha (formato AAAA-MM-DD).'
            );
        }

        return $parsed->format('Y-m-d');
    }

    private function limpiarObservacion(?string $observacion): ?string
    {
        $txt = trim((string) $observacion);

        return $txt === '' ? null : mb_substr($txt, 0, 2000);
    }
}
