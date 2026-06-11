<?php

namespace App\Services\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\CuentaEstacionamiento;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Services\Caja\RendicionEstacionamientoAnitaSyncService;
use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Models\Caja\Estacionamiento\TurnoOperativoEstacionamiento;
use App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision;
use App\Repositories\Caja\Estacionamiento\JornadaEstacionamientoRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Apertura/cierre de jornada operativa por empresa (estacionamiento).
 * fecha factura = día calendario real; fecha jornada = día de turno abierto.
 */
final class JornadaEstacionamientoService
{
    public function __construct(
        private readonly JornadaEstacionamientoRepositoryInterface $jornadaRepository,
    ) {
    }

    public function jornadaAbierta(int $empresaId): ?JornadaEstacionamiento
    {
        if ($empresaId <= 0) {
            return null;
        }

        return $this->jornadaRepository->jornadaAbiertaPorEmpresa($empresaId);
    }

    public function exigirJornadaAbierta(int $empresaId): JornadaEstacionamiento
    {
        $jornada = $this->jornadaAbierta($empresaId);
        if ($jornada === null) {
            throw new InvalidArgumentException(
                'No hay jornada abierta para esta empresa. Abra la jornada en Caja → Estacionamiento → Jornada.'
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
        $payload['jornada_estacionamiento_id'] = $fechas['jornada_id'];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function estadoParaEmpresa(int $empresaId): array
    {
        $abierta = $this->jornadaAbierta($empresaId);
        $hoy = Carbon::today()->format('Y-m-d');

        $ultimaJornada = $this->jornadaRepository->ultimaJornadaPorEmpresa($empresaId);
        $fechasApertura = $this->fechasSugeridasApertura($ultimaJornada);
        $fechaJornadaMinimaAbrir = $abierta !== null ? null : $fechasApertura['minima'];
        $fechaJornadaSugeridaAbrir = $abierta !== null && $abierta->fecha_jornada !== null
            ? $abierta->fecha_jornada->format('Y-m-d')
            : $fechasApertura['sugerida'];
        $cuentasAbiertasConItems = $abierta !== null
            ? $this->contarCuentasAbiertasConItemsPorEmpresa($empresaId)
            : 0;
        $cuentasAbiertasVacias = $abierta !== null
            ? $this->contarCuentasAbiertasVaciasPorEmpresa($empresaId)
            : 0;
        $ticketsPendientesIngreso = $abierta !== null
            ? app(EstacionamientoTurnoOperativoService::class)->contarTicketsPendientesIngresoJornada($empresaId, $abierta)
            : 0;

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
            'fecha_jornada_minima_abrir' => $fechaJornadaMinimaAbrir,
            'fecha_jornada_maxima_abrir' => $hoy,
            'fecha_jornada_sugerida_abrir' => $fechaJornadaSugeridaAbrir,
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
            'nota_politica_turnos' => EstacionamientoTurnoOperativoService::requiereHabilitacionTurno()
                ? 'Cada turno que se habilitó en la jornada debe cerrarse antes de cerrar la jornada '
                    .'(en su terminal o con cierre remoto en Saneamiento). '
                    .'No es obligatorio que todas las PCs habiliten el último turno del día: '
                    .'las terminales que operan solo algunos turnos no bloquean por no haber abierto los restantes.'
                : null,
            'cuentas_abiertas_con_items' => $cuentasAbiertasConItems,
            'cuentas_abiertas_vacias' => $cuentasAbiertasVacias,
            'tickets_pendientes_ingreso' => $ticketsPendientesIngreso,
        ];
    }

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
        $hoy = Carbon::today()->format('Y-m-d');

        if ($fecha > $hoy) {
            throw new InvalidArgumentException(
                'No puede abrir la jornada del '.$fechaFmt.': la fecha no puede ser posterior a hoy ('
                .$this->formatearFechaHumana($hoy).').'
            );
        }

        $abierta = $this->jornadaAbierta($empresaId);
        if ($abierta !== null) {
            throw new InvalidArgumentException(
                'No puede abrir la jornada del '.$fechaFmt.': '.$this->describirJornadaAbierta($abierta)
                .' Cierre esa jornada antes de abrir otra (aunque sea de otra fecha).'
            );
        }

        $ultima = $this->jornadaRepository->ultimaJornadaPorEmpresa($empresaId);
        if ($ultima !== null) {
            $ultimaFecha = $ultima->fecha_jornada->format('Y-m-d');
            if ($fecha <= $ultimaFecha) {
                $ultimaFmt = $this->formatearFechaHumana($ultimaFecha);
                $estadoUltima = $ultima->estado === JornadaEstacionamiento::ESTADO_ABIERTA
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

    public function abrir(int $empresaId, string $fechaJornada, ?string $observacion = null): JornadaEstacionamiento
    {
        $fecha = $this->validarApertura($empresaId, $fechaJornada);

        if (Auth::id() === null) {
            throw new InvalidArgumentException(
                'No hay usuario autenticado. Vuelva a iniciar sesión e intente abrir la jornada.'
            );
        }

        try {
            return DB::transaction(function () use ($empresaId, $fecha, $observacion) {
                $existente = JornadaEstacionamiento::query()
                    ->with(['usuarioApertura', 'empresa'])
                    ->where('empresa_id', $empresaId)
                    ->where('estado', JornadaEstacionamiento::ESTADO_ABIERTA)
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
                    'estado' => JornadaEstacionamiento::ESTADO_ABIERTA,
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

    public function cerrar(int $empresaId, ?string $observacion = null): JornadaEstacionamiento
    {
        $jornada = $this->exigirJornadaAbierta($empresaId);

        $errores = $this->erroresAntesDeCerrar($empresaId);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        $vaciasAutoDescartadas = $this->autoDescartarCuentasAbiertasVaciasPorEmpresa($empresaId);

        $this->jornadaRepository->update([
            'estado' => JornadaEstacionamiento::ESTADO_CERRADA,
            'usuario_cierre_id' => Auth::id(),
            'cierre_en' => now(),
            'observacion_cierre' => $this->componerObservacionCierreJornada($observacion, $vaciasAutoDescartadas),
        ], (int) $jornada->id);

        return $this->jornadaRepository->findOrFail((int) $jornada->id);
    }

    /**
     * Bloqueantes para cerrar la jornada (misma política que gastronomía).
     *
     * @return list<string>
     */
    public function erroresAntesDeCerrar(int $empresaId): array
    {
        $errores = [];
        $turnoService = app(EstacionamientoTurnoOperativoService::class);

        $cuentasAbiertasConItems = $this->contarCuentasAbiertasConItemsPorEmpresa($empresaId);
        if ($cuentasAbiertasConItems > 0) {
            $errores[] = 'Hay '.$cuentasAbiertasConItems.' cuenta(s) ABIERTA(S) con ítems sin facturar. '
                .'Factúrelas o ciérrelas sin facturar desde Caja → Estacionamiento → '
                .'Saneamiento de turnos antes de cerrar la jornada. '
                .'Las cuentas abiertas sin ítems se descartan automáticamente al cerrar la jornada.';
        }

        $ticketsPendientes = $turnoService->contarTicketsPendientesIngresoJornada($empresaId);
        if (EstacionamientoTurnoOperativoService::validarTicketsIngresoAlCerrar() && $ticketsPendientes > 0) {
            $errores[] = 'Hay '.$ticketsPendientes.' ticket(s) con ingreso pendiente de facturar o anular. '
                .'Resuélvalos desde el facturador o desde Caja → Estacionamiento → Saneamiento de turnos '
                .'antes de cerrar la jornada.';
        }

        if (EstacionamientoTurnoOperativoService::requiereHabilitacionTurno()) {
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
     * @return list<array{
     *   turno_operativo_id:int,
     *   identificador_pc:string,
     *   turno_nombre:string,
     *   habilitacion_en:string,
     *   con_actividad:bool
     * }>
     */
    public function detalleTurnosHabilitadosJornada(int $empresaId, ?JornadaEstacionamiento $jornada = null): array
    {
        $jornada ??= $this->jornadaAbierta($empresaId);
        if ($jornada === null) {
            return [];
        }

        $turnoService = app(EstacionamientoTurnoOperativoService::class);

        return TurnoOperativoEstacionamiento::query()
            ->with('turno')
            ->where('empresa_id', $empresaId)
            ->where('jornada_estacionamiento_id', (int) $jornada->id)
            ->where('estado', TurnoOperativoEstacionamiento::ESTADO_HABILITADO)
            ->orderBy('identificador_pc')
            ->get()
            ->map(fn (TurnoOperativoEstacionamiento $t) => [
                'turno_operativo_id' => (int) $t->id,
                'identificador_pc' => (string) $t->identificador_pc,
                'turno_nombre' => (string) ($t->turno?->nombre ?? ''),
                'habilitacion_en' => $t->habilitacion_en?->format('d/m/Y H:i') ?? '',
                'con_actividad' => $turnoService->terminalTuvoActividadEnJornada($t),
            ])
            ->values()
            ->all();
    }

    public function contarCuentasAbiertasConItemsPorEmpresa(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        return CuentaEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', CuentaEstacionamiento::ESTADO_ABIERTA)
            ->whereHas('lineas')
            ->count();
    }

    public function contarCuentasAbiertasVaciasPorEmpresa(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        return CuentaEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', CuentaEstacionamiento::ESTADO_ABIERTA)
            ->whereDoesntHave('lineas')
            ->count();
    }

    public function autoDescartarCuentasAbiertasVaciasPorEmpresa(int $empresaId): int
    {
        if ($empresaId <= 0) {
            return 0;
        }

        return CuentaEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', CuentaEstacionamiento::ESTADO_ABIERTA)
            ->whereDoesntHave('lineas')
            ->update(['estado' => CuentaEstacionamiento::ESTADO_CERRADA]);
    }

    private function componerObservacionCierreJornada(?string $observacion, int $vaciasAutoDescartadas): ?string
    {
        $partes = [];
        $base = $this->limpiarObservacion($observacion);
        if ($base !== null) {
            $partes[] = $base;
        }

        if ($vaciasAutoDescartadas > 0) {
            $partes[] = '[Auto '.now()->format('Y-m-d H:i').'] '.$vaciasAutoDescartadas
                .' cuenta(s) abierta(s) sin ítems descartada(s) automáticamente al cerrar la jornada.';
        }

        if ($partes === []) {
            return null;
        }

        return mb_substr(implode("\n", $partes), 0, 2000);
    }

    /**
     * @return array{
     *   puede_eliminar: bool,
     *   motivo_no_eliminar: ?string,
     *   ventas_estacionamiento: int
     * }
     */
    public function resumenEliminacion(JornadaEstacionamiento $jornada): array
    {
        $ventas = $this->contarVentasEstacionamientoJornada($jornada);
        $rendicionCaja = RendicionEstacionamientoCaja::query()
            ->where('tipo', RendicionEstacionamientoCaja::TIPO_JORNADA)
            ->where('jornada_estacionamiento_id', (int) $jornada->id)
            ->exists();

        if ($rendicionCaja) {
            return [
                'puede_eliminar' => false,
                'motivo_no_eliminar' => 'No se puede eliminar: la jornada fue presentada en caja (rendición de tesorería). Elimine primero la rendición en Caja → Rendiciones estacionamiento.',
                'ventas_estacionamiento' => $ventas,
            ];
        }

        if ($this->existeJornadaPosterior((int) $jornada->empresa_id, $jornada)) {
            return [
                'puede_eliminar' => false,
                'motivo_no_eliminar' => 'No se puede eliminar: ya existe una jornada posterior para esta empresa.',
                'ventas_estacionamiento' => $ventas,
            ];
        }

        if ($ventas > 0) {
            return [
                'puede_eliminar' => false,
                'motivo_no_eliminar' => 'No se puede eliminar: la jornada tiene '.$ventas.' comprobante(s) de estacionamiento.',
                'ventas_estacionamiento' => $ventas,
            ];
        }

        return [
            'puede_eliminar' => true,
            'motivo_no_eliminar' => null,
            'ventas_estacionamiento' => 0,
        ];
    }

    /**
     * @return array{
     *   puede_anular: bool,
     *   motivo_no_anular: ?string,
     *   jornada_id: int,
     *   texto_confirmacion: string,
     *   fecha_jornada_fmt: string,
     *   cierre_en_fmt: string,
     *   usuario_cierre: string
     * }
     */
    public function resumenAnulacionCierre(JornadaEstacionamiento $jornada): array
    {
        $jornada->loadMissing(['usuarioCierre']);
        $errores = $this->erroresAntesDeAnularCierre($jornada);
        $id = (int) $jornada->id;

        return [
            'puede_anular' => $errores === [],
            'motivo_no_anular' => $errores !== [] ? implode(' ', $errores) : null,
            'jornada_id' => $id,
            'texto_confirmacion' => 'ANULAR-JORNADA-'.$id,
            'fecha_jornada_fmt' => $jornada->fecha_jornada?->format('d/m/Y') ?? '',
            'cierre_en_fmt' => $jornada->cierre_en?->format('d/m/Y H:i') ?? '',
            'usuario_cierre' => (string) ($jornada->usuarioCierre?->nombre ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function cierreAnulableParaEmpresa(int $empresaId): ?array
    {
        if ($empresaId <= 0 || $this->jornadaAbierta($empresaId) !== null) {
            return null;
        }

        $ultima = $this->jornadaRepository->ultimaJornadaPorEmpresa($empresaId);
        if ($ultima === null || $ultima->estado !== JornadaEstacionamiento::ESTADO_CERRADA) {
            return null;
        }

        $resumen = $this->resumenAnulacionCierre($ultima);

        return $resumen['puede_anular'] ? $resumen : null;
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeAnularCierre(JornadaEstacionamiento $jornada): array
    {
        $errores = [];

        if ($jornada->estado !== JornadaEstacionamiento::ESTADO_CERRADA || $jornada->cierre_en === null) {
            $errores[] = 'La jornada no está cerrada.';

            return $errores;
        }

        if ($this->jornadaAbierta((int) $jornada->empresa_id) !== null) {
            $errores[] = 'Hay una jornada abierta para esta empresa. No puede anular el cierre mientras exista otra jornada activa.';
        }

        if (RendicionEstacionamientoCaja::query()
            ->where('tipo', RendicionEstacionamientoCaja::TIPO_JORNADA)
            ->where('jornada_estacionamiento_id', (int) $jornada->id)
            ->exists()) {
            $errores[] = 'La jornada tiene una rendición de tesorería en caja. Elimine la rendición antes de anular el cierre.';
        }

        if ($this->existeJornadaPosterior((int) $jornada->empresa_id, $jornada)) {
            $errores[] = 'Ya existe una jornada posterior para esta empresa. No puede anular este cierre.';
        }

        $ultima = $this->jornadaRepository->ultimaJornadaPorEmpresa((int) $jornada->empresa_id);
        if ($ultima !== null && (int) $ultima->id !== (int) $jornada->id) {
            $errores[] = 'Solo puede anular el cierre de la última jornada cerrada de la empresa (más reciente: #'.$ultima->id.').';
        }

        return $errores;
    }

    public function anularCierre(int $jornadaId, string $motivo): JornadaEstacionamiento
    {
        $jornada = $this->jornadaRepository->findOrFail($jornadaId);
        $errores = $this->erroresAntesDeAnularCierre($jornada);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        if (Auth::id() === null) {
            throw new InvalidArgumentException('No hay usuario autenticado.');
        }

        $nota = '[Anulación cierre jornada '.now()->format('Y-m-d H:i')
            .' user #'.Auth::id().'] Motivo: '.($this->limpiarObservacion($motivo) ?? '(sin detalle)');

        return DB::transaction(function () use ($jornada, $nota) {
            $obsApertura = trim((string) $jornada->observacion_apertura);
            $obsApertura = $obsApertura === '' ? $nota : $obsApertura."\n".$nota;

            $this->jornadaRepository->update([
                'estado' => JornadaEstacionamiento::ESTADO_ABIERTA,
                'usuario_cierre_id' => null,
                'cierre_en' => null,
                'observacion_cierre' => null,
                'observacion_apertura' => mb_substr($obsApertura, 0, 2000),
            ], (int) $jornada->id);

            app(RendicionEstacionamientoAnitaSyncService::class)->resetTotalZPorPcEnJornada((int) $jornada->id);

            return $this->jornadaRepository->findOrFail((int) $jornada->id);
        });
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
        if (! config('estacionamiento.jornada_obligatoria', true)) {
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
            return 'Error inesperado al procesar la jornada. Revise el log del servidor o contacte soporte.';
        }

        return 'Error al procesar la jornada: '.$detalle;
    }

    private function contarVentasEstacionamientoJornada(JornadaEstacionamiento $jornada): int
    {
        return (int) VentaEstacionamientoEmision::query()
            ->where('jornada_estacionamiento_id', (int) $jornada->id)
            ->whereHas('venta', fn ($v) => $v->whereHas(
                'puntoventas',
                fn ($pv) => $pv->where('empresa_id', (int) $jornada->empresa_id),
            ))
            ->count();
    }

    private function existeJornadaPosterior(int $empresaId, JornadaEstacionamiento $jornada): bool
    {
        if ($empresaId <= 0 || $jornada->fecha_jornada === null) {
            return false;
        }

        return JornadaEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('id', '!=', (int) $jornada->id)
            ->where(function ($q) use ($jornada) {
                $q->whereDate('fecha_jornada', '>', $jornada->fecha_jornada->format('Y-m-d'))
                    ->orWhere(function ($w) use ($jornada) {
                        $w->whereDate('fecha_jornada', $jornada->fecha_jornada->format('Y-m-d'))
                            ->where('id', '>', (int) $jornada->id);
                    });
            })
            ->exists();
    }

    private function fechasSugeridasApertura(?JornadaEstacionamiento $ultimaJornada): array
    {
        $hoy = Carbon::today()->format('Y-m-d');
        $minima = null;

        if ($ultimaJornada?->fecha_jornada !== null) {
            $minima = $ultimaJornada->fecha_jornada->copy()->addDay()->format('Y-m-d');
        }

        return [
            'minima' => $minima,
            'sugerida' => $hoy,
        ];
    }

    private function describirJornadaAbierta(JornadaEstacionamiento $jornada): string
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
