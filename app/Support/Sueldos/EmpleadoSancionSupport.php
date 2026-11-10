<?php

namespace App\Support\Sueldos;

use App\Models\Sueldos\Empleado_Sancion_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Tipo_Sancion_Sueldos;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Reglas de expediente disciplinario (estados, días, inmediatez, reincidencia).
 */
class EmpleadoSancionSupport
{
    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_NOTIFICADA = 'notificada';

    public const ESTADO_CON_DESCARGO = 'con_descargo';

    public const ESTADO_FIRME = 'firme';

    public const ESTADO_IMPUGNADA = 'impugnada';

    public const ESTADO_ANULADA = 'anulada';

    /** @var array<string, string> */
    public const ESTADOS = [
        self::ESTADO_BORRADOR => 'Borrador',
        self::ESTADO_NOTIFICADA => 'Notificada',
        self::ESTADO_CON_DESCARGO => 'Con descargo',
        self::ESTADO_FIRME => 'Firme',
        self::ESTADO_IMPUGNADA => 'Impugnada',
        self::ESTADO_ANULADA => 'Anulada',
    ];

    /** @var list<string> */
    public const ESTADOS_GENERAN_NOVEDAD = [
        self::ESTADO_NOTIFICADA,
        self::ESTADO_CON_DESCARGO,
        self::ESTADO_FIRME,
    ];

    public const DIAS_INMEDIATEZ_ALERTA = 30;

    public const MESES_REINCIDENCIA = 12;

    public static function etiquetaEstado(?string $estado): string
    {
        return self::ESTADOS[$estado] ?? (string) $estado;
    }

    /**
     * @return list<string>
     */
    public static function estadosPermitidos(): array
    {
        return array_keys(self::ESTADOS);
    }

    public static function generaNovedad(?string $estado): bool
    {
        return in_array((string) $estado, self::ESTADOS_GENERAN_NOVEDAD, true);
    }

    public static function contarDias(?CarbonInterface $desde, ?CarbonInterface $hasta, string $tipoDias = 'corridos'): int
    {
        if ($desde === null || $hasta === null) {
            return 0;
        }
        if ($hasta->lt($desde)) {
            return 0;
        }

        if ($tipoDias === 'habiles') {
            $dias = 0;
            $cursor = $desde->copy()->startOfDay();
            $fin = $hasta->copy()->startOfDay();
            while ($cursor->lte($fin)) {
                if (! $cursor->isWeekend()) {
                    $dias++;
                }
                $cursor->addDay();
            }

            return $dias;
        }

        return (int) $desde->copy()->startOfDay()->diffInDays($hasta->copy()->startOfDay()) + 1;
    }

    /**
     * @return array{alerta: bool, dias: int}
     */
    public static function inmediatez(?string $fechaHecho, ?string $fechaNotificacion): array
    {
        if ($fechaHecho === null || $fechaHecho === '' || $fechaNotificacion === null || $fechaNotificacion === '') {
            return ['alerta' => false, 'dias' => 0];
        }

        $hecho = Carbon::parse($fechaHecho)->startOfDay();
        $notif = Carbon::parse($fechaNotificacion)->startOfDay();
        $dias = (int) $hecho->diffInDays($notif, false);

        return [
            'alerta' => $dias > self::DIAS_INMEDIATEZ_ALERTA,
            'dias' => max(0, $dias),
        ];
    }

    public static function contarReincidencias(int $empleadoId, int $motivoId, ?int $exceptoId = null): int
    {
        if ($empleadoId <= 0 || $motivoId <= 0) {
            return 0;
        }

        $empleado = Empleado_Sueldos::query()->find($empleadoId);
        $ids = $empleado ? self::idsEmpleadoMismoLegajo($empleado) : [$empleadoId];
        $desde = Carbon::now()->subMonths(self::MESES_REINCIDENCIA)->toDateString();

        $query = Empleado_Sancion_Sueldos::query()
            ->whereIn('empleado_id', $ids)
            ->where('motivo_sancion_id', $motivoId)
            ->where('fecha_hecho', '>=', $desde)
            ->whereNotIn('estado', [self::ESTADO_ANULADA, self::ESTADO_BORRADOR]);

        if ($exceptoId !== null && $exceptoId > 0) {
            $query->where('id', '!=', $exceptoId);
        }

        return (int) $query->count();
    }

    /**
     * Anita guarda sanciones con empresa 99 (todas). En el ERP el mismo legajo
     * puede vivir en más de una empresa; el expediente se arma por legajo.
     *
     * @return list<int>
     */
    public static function idsEmpleadoMismoLegajo(Empleado_Sueldos $empleado): array
    {
        $legajo = (int) ($empleado->legajo ?? 0);
        if ($legajo <= 0) {
            return [(int) $empleado->id];
        }

        return Empleado_Sueldos::query()
            ->where('legajo', $legajo)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Empleado_Sancion_Sueldos>
     */
    public static function sancionesDelLegajo(Empleado_Sueldos $empleado): Collection
    {
        $ids = self::idsEmpleadoMismoLegajo($empleado);
        if ($ids === []) {
            return collect();
        }

        return Empleado_Sancion_Sueldos::query()
            ->with(['tipo', 'motivo', 'archivos', 'novedad', 'empleado.empresa'])
            ->whereIn('empleado_id', $ids)
            ->orderByDesc('fecha_hecho')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array{total: int, dias_suspension_anio: int, ultima: ?Empleado_Sancion_Sueldos}
     */
    public static function resumenEmpleado(int $empleadoId): array
    {
        $empleado = Empleado_Sueldos::query()->find($empleadoId);
        $ids = $empleado ? self::idsEmpleadoMismoLegajo($empleado) : [$empleadoId];
        $anio = (int) date('Y');
        $query = Empleado_Sancion_Sueldos::query()
            ->whereIn('empleado_id', $ids)
            ->whereNotIn('estado', [self::ESTADO_ANULADA]);

        $ultima = (clone $query)->orderByDesc('fecha_hecho')->orderByDesc('id')->first();

        $diasSuspension = (int) Empleado_Sancion_Sueldos::query()
            ->whereIn('empleado_sancion_sueldos.empleado_id', $ids)
            ->whereNotIn('empleado_sancion_sueldos.estado', [self::ESTADO_ANULADA, self::ESTADO_BORRADOR])
            ->whereYear('empleado_sancion_sueldos.fecha_hecho', $anio)
            ->whereHas('tipo', fn ($q) => $q->where('clase', Tipo_Sancion_Sueldos::CLASE_SUSPENSION))
            ->sum('cant_dias');

        return [
            'total' => (int) $query->count(),
            'dias_suspension_anio' => $diasSuspension,
            'ultima' => $ultima,
        ];
    }
}
