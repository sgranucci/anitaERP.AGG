<?php

namespace App\Services\Contable;

use App\Models\Contable\PeriodoCierreProgramado;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\PeriodoContableCierreSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class PeriodoCierreProgramadoService
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly PeriodoCierreContableService $cierreService,
    ) {
    }

    public static function anioMesDesdePartes(int $anio, int $mes): int
    {
        return ($anio * 100) + $mes;
    }

    public static function fechaHastaDefaultParaAgenda(int $anioMes): Carbon
    {
        $anio = intdiv($anioMes, 100);
        $mes = $anioMes % 100;

        return Carbon::create($anio, $mes, 1)->startOfMonth()->subDay()->startOfDay();
    }

    /**
     * Filas planas de agenda (módulos + submódulos) indexadas para lookups.
     *
     * @return Collection<string, array{
     *   alcance: string,
     *   etiqueta: string,
     *   es_modulo: bool,
     *   programado: ?PeriodoCierreProgramado,
     *   fecha_ejecucion: ?string,
     *   hora_ejecucion: string,
     *   fecha_hasta: string,
     *   estado: string,
     *   observacion: ?string,
     *   error_mensaje: ?string,
     *   puede_ejecutar_ahora: bool
     * }>
     */
    public function filasAgendaPorAlcance(int $empresaId, int $anioMes): Collection
    {
        $existentes = PeriodoCierreProgramado::query()
            ->where('empresa_id', $empresaId)
            ->where('anio_mes', $anioMes)
            ->get()
            ->keyBy('alcance');

        $fechaHastaDefault = self::fechaHastaDefaultParaAgenda($anioMes)->format('Y-m-d');

        return collect(PeriodoContableCierreSupport::alcancesAgenda())
            ->map(function (string $etiqueta, string $alcance) use ($existentes, $fechaHastaDefault) {
                /** @var PeriodoCierreProgramado|null $prog */
                $prog = $existentes->get($alcance);

                return [
                    'alcance' => $alcance,
                    'etiqueta' => $etiqueta,
                    'es_modulo' => PeriodoContableCierreSupport::esModuloPadre($alcance),
                    'programado' => $prog,
                    'fecha_ejecucion' => $prog?->fecha_ejecucion?->format('Y-m-d'),
                    'hora_ejecucion' => $prog?->horaEjecucionNormalizada() ?? PeriodoCierreProgramado::HORA_FIN_DIA,
                    'fecha_hasta' => $prog?->fecha_hasta?->format('Y-m-d') ?? $fechaHastaDefault,
                    'estado' => $prog?->estado ?? '',
                    'observacion' => $prog?->observacion,
                    'error_mensaje' => $prog?->error_mensaje,
                    'puede_ejecutar_ahora' => $prog?->puedeEjecutarAhora() ?? false,
                ];
            });
    }

    /**
     * Agenda jerárquica: módulo padre + submódulos hijos.
     *
     * @return Collection<int, array{
     *   codigo: string,
     *   etiqueta: string,
     *   es_modulo: bool,
     *   fila: array,
     *   hijos: list<array>
     * }>
     */
    public function filasAgenda(int $empresaId, int $anioMes): Collection
    {
        $porAlcance = $this->filasAgendaPorAlcance($empresaId, $anioMes);

        return collect(PeriodoContableCierreSupport::jerarquiaAgenda())
            ->map(function (array $modulo) use ($porAlcance) {
                $filaModulo = $porAlcance->get($modulo['codigo']);
                $hijos = [];
                foreach ($modulo['hijos'] as $hijo) {
                    $filaHijo = $porAlcance->get($hijo['codigo']);
                    if ($filaHijo !== null) {
                        $hijos[] = $filaHijo;
                    }
                }

                return [
                    'codigo' => $modulo['codigo'],
                    'etiqueta' => $modulo['etiqueta'],
                    'es_modulo' => true,
                    'fila' => $filaModulo,
                    'hijos' => $hijos,
                ];
            })
            ->values();
    }

    /**
     * Guarda o actualiza una fila de agenda (queda pendiente).
     *
     * @param  array{empresa_id: int, anio_mes: int, alcance: string, fecha_ejecucion: string, fecha_hasta: string, hora_ejecucion?: string|null, observacion?: string|null}  $datos
     */
    public function guardarFila(array $datos, int $usuarioId): PeriodoCierreProgramado
    {
        $empresaId = (int) ($datos['empresa_id'] ?? 0);
        $anioMes = (int) ($datos['anio_mes'] ?? 0);
        $alcance = (string) ($datos['alcance'] ?? '');

        $this->assertEmpresaPermitida($empresaId);
        $this->assertAnioMes($anioMes);

        if (! array_key_exists($alcance, PeriodoContableCierreSupport::alcancesAgenda())
            && $alcance !== PeriodoContableCierreSupport::ALCANCE_GENERAL) {
            throw new InvalidArgumentException('Alcance de agenda inválido.');
        }

        $fechaEjecucion = Carbon::parse($datos['fecha_ejecucion'])->startOfDay();
        $fechaHasta = Carbon::parse($datos['fecha_hasta'])->startOfDay();
        $horaEjecucion = PeriodoCierreProgramado::normalizarHoraEjecucion($datos['hora_ejecucion'] ?? null);

        $this->validarFechasAgenda($anioMes, $fechaEjecucion, $fechaHasta);

        $existente = PeriodoCierreProgramado::query()
            ->where('empresa_id', $empresaId)
            ->where('anio_mes', $anioMes)
            ->where('alcance', $alcance)
            ->first();

        if ($existente !== null && $existente->estado === PeriodoCierreProgramado::ESTADO_EJECUTADO) {
            throw new InvalidArgumentException(
                'El cierre de '.PeriodoContableCierreSupport::etiquetaAlcance($alcance)
                .' ya fue ejecutado para este mes. No se puede reprogramar.'
            );
        }

        $payload = [
            'fecha_ejecucion' => $fechaEjecucion->format('Y-m-d'),
            'hora_ejecucion' => $horaEjecucion,
            'fecha_hasta' => $fechaHasta->format('Y-m-d'),
            'estado' => PeriodoCierreProgramado::ESTADO_PENDIENTE,
            'observacion' => trim((string) ($datos['observacion'] ?? '')) ?: null,
            'usuario_id' => $usuarioId,
            'ejecutado_en' => null,
            'periodo_cierre_id' => null,
            'error_mensaje' => null,
        ];

        if ($existente !== null) {
            $existente->update($payload);

            return $existente->fresh();
        }

        return PeriodoCierreProgramado::query()->create(array_merge($payload, [
            'empresa_id' => $empresaId,
            'anio_mes' => $anioMes,
            'alcance' => $alcance,
        ]));
    }

    /**
     * Programa todos los módulos del mes con las mismas fechas (sin ejecutar).
     *
     * @return array{guardados: int, errores: array<int, string>}
     */
    public function guardarTodosLosModulos(
        int $empresaId,
        int $anioMes,
        string $fechaEjecucion,
        string $fechaHasta,
        ?string $observacion,
        int $usuarioId,
        ?string $horaEjecucion = null
    ): array {
        $guardados = 0;
        $errores = [];

        foreach (array_keys(PeriodoContableCierreSupport::alcancesAgenda()) as $alcance) {
            try {
                $this->guardarFila([
                    'empresa_id' => $empresaId,
                    'anio_mes' => $anioMes,
                    'alcance' => $alcance,
                    'fecha_ejecucion' => $fechaEjecucion,
                    'hora_ejecucion' => $horaEjecucion,
                    'fecha_hasta' => $fechaHasta,
                    'observacion' => $observacion,
                ], $usuarioId);
                $guardados++;
            } catch (Throwable $e) {
                $errores[] = PeriodoContableCierreSupport::etiquetaAlcance($alcance).': '.$e->getMessage();
            }
        }

        return compact('guardados', 'errores');
    }

    public function cancelar(int $id): PeriodoCierreProgramado
    {
        $prog = $this->findConAcceso($id);

        if ($prog->estado !== PeriodoCierreProgramado::ESTADO_PENDIENTE
            && $prog->estado !== PeriodoCierreProgramado::ESTADO_ERROR) {
            throw new InvalidArgumentException('Solo se pueden cancelar programaciones pendientes o con error.');
        }

        $prog->update([
            'estado' => PeriodoCierreProgramado::ESTADO_CANCELADO,
            'error_mensaje' => null,
        ]);

        return $prog->fresh();
    }

    public function ejecutarAhora(int $id, int $usuarioId): PeriodoCierreProgramado
    {
        $prog = $this->findConAcceso($id);

        if (! $prog->estaPendiente() && $prog->estado !== PeriodoCierreProgramado::ESTADO_ERROR) {
            throw new InvalidArgumentException('Solo se pueden ejecutar programaciones pendientes o con error.');
        }

        if ($prog->fecha_ejecucion !== null && $prog->fecha_ejecucion->gt(now()->startOfDay())) {
            throw new InvalidArgumentException(
                'La fecha de ejecución es futura ('.$prog->fecha_ejecucion->format('d/m/Y')
                .'). Espere o modifique la fecha para aplicar ahora.'
            );
        }

        return $this->ejecutarProgramado($prog, $usuarioId);
    }

    /**
     * Ejecuta todos los pendientes del mes con fecha_ejecucion <= hoy.
     *
     * @return array{ejecutados: int, errores: array<int, string>}
     */
    public function ejecutarTodosPendientesDelMes(int $empresaId, int $anioMes, int $usuarioId): array
    {
        $this->assertEmpresaPermitida($empresaId);

        $pendientes = PeriodoCierreProgramado::query()
            ->where('empresa_id', $empresaId)
            ->where('anio_mes', $anioMes)
            ->whereIn('estado', [
                PeriodoCierreProgramado::ESTADO_PENDIENTE,
                PeriodoCierreProgramado::ESTADO_ERROR,
            ])
            ->whereDate('fecha_ejecucion', '<=', now()->toDateString())
            ->orderBy('alcance')
            ->get();

        $ejecutados = 0;
        $errores = [];

        foreach ($pendientes as $prog) {
            try {
                $this->ejecutarProgramado($prog, $usuarioId);
                $ejecutados++;
            } catch (Throwable $e) {
                $errores[] = $prog->etiquetaAlcance().': '.$e->getMessage();
            }
        }

        return compact('ejecutados', 'errores');
    }

    /**
     * Cierre inmediato "todos los módulos" = registrar cierre general.
     */
    public function cerrarTodosLosModulosAhora(
        int $empresaId,
        string $fechaHasta,
        ?string $observacion,
        int $usuarioId
    ): \App\Models\Contable\PeriodoCierreContable {
        return $this->cierreService->registrarCierre(
            $empresaId,
            $fechaHasta,
            $observacion,
            $usuarioId,
            PeriodoContableCierreSupport::ALCANCE_GENERAL
        );
    }

    /**
     * Job: pendientes cuyo momento de ejecución (fecha + hora) ya venció.
     * Hora 24:00 = fin del día.
     *
     * @return array{ejecutados: int, errores: int}
     */
    public function procesarPendientesVencidos(?Carbon $ahora = null): array
    {
        $ahora = $ahora ?? now();

        $pendientes = PeriodoCierreProgramado::query()
            ->where('estado', PeriodoCierreProgramado::ESTADO_PENDIENTE)
            ->whereDate('fecha_ejecucion', '<=', $ahora->toDateString())
            ->orderBy('fecha_ejecucion')
            ->orderBy('id')
            ->get();

        $ejecutados = 0;
        $errores = 0;

        foreach ($pendientes as $prog) {
            if (! $prog->momentoEjecucionVencido($ahora)) {
                continue;
            }

            try {
                $this->ejecutarProgramado($prog, (int) $prog->usuario_id);
                $ejecutados++;
            } catch (Throwable $e) {
                $errores++;
                Log::warning('contable_periodo_cierre_programado: error al ejecutar', [
                    'id' => $prog->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return compact('ejecutados', 'errores');
    }

    public function findConAcceso(int $id): PeriodoCierreProgramado
    {
        $prog = PeriodoCierreProgramado::query()->find($id);
        if ($prog === null) {
            throw new InvalidArgumentException('Programación de cierre no encontrada.');
        }

        $this->assertEmpresaPermitida((int) $prog->empresa_id);

        return $prog;
    }

    private function ejecutarProgramado(PeriodoCierreProgramado $prog, int $usuarioId): PeriodoCierreProgramado
    {
        try {
            $cierre = DB::transaction(function () use ($prog, $usuarioId) {
                $cierre = $this->cierreService->registrarCierre(
                    (int) $prog->empresa_id,
                    $prog->fecha_hasta->format('Y-m-d'),
                    $prog->observacion,
                    $usuarioId,
                    (string) $prog->alcance
                );

                $prog->update([
                    'estado' => PeriodoCierreProgramado::ESTADO_EJECUTADO,
                    'ejecutado_en' => now(),
                    'periodo_cierre_id' => $cierre->id,
                    'error_mensaje' => null,
                ]);

                return $cierre;
            });

            Log::info('contable_periodo_cierre_programado: ejecutado', [
                'programado_id' => $prog->id,
                'periodo_cierre_id' => $cierre->id,
                'empresa_id' => $prog->empresa_id,
                'alcance' => $prog->alcance,
            ]);

            return $prog->fresh();
        } catch (Throwable $e) {
            $prog->update([
                'estado' => PeriodoCierreProgramado::ESTADO_ERROR,
                'error_mensaje' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            throw $e instanceof InvalidArgumentException
                ? $e
                : new InvalidArgumentException($e->getMessage(), 0, $e);
        }
    }

    private function validarFechasAgenda(int $anioMes, Carbon $fechaEjecucion, Carbon $fechaHasta): void
    {
        if ($fechaHasta->isFuture()) {
            throw new InvalidArgumentException('La fecha de cierre contable no puede ser futura.');
        }

        $anio = intdiv($anioMes, 100);
        $mes = $anioMes % 100;
        $inicioMes = Carbon::create($anio, $mes, 1)->startOfDay();
        $finMes = $inicioMes->copy()->endOfMonth()->startOfDay();

        // Permitir ejecución desde el 1er día del mes de agenda (o hoy si estamos en ese mes).
        if ($fechaEjecucion->lt($inicioMes->copy()->subDays(0))) {
            throw new InvalidArgumentException(
                'La fecha de ejecución no puede ser anterior al mes de la agenda ('
                .$inicioMes->format('m/Y').').'
            );
        }

        // No programar más allá del mes siguiente (margenencia razonable).
        if ($fechaEjecucion->gt($finMes->copy()->addMonth()->endOfMonth())) {
            throw new InvalidArgumentException('La fecha de ejecución está demasiado lejos del mes de agenda.');
        }
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new InvalidArgumentException('No tiene acceso a la empresa seleccionada.');
        }
    }

    private function assertAnioMes(int $anioMes): void
    {
        $anio = intdiv($anioMes, 100);
        $mes = $anioMes % 100;
        if ($anio < 2000 || $anio > 2100 || $mes < 1 || $mes > 12) {
            throw new InvalidArgumentException('Mes/año de agenda inválido.');
        }
    }
}
