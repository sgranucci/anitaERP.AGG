<?php

namespace App\Support\Sueldos;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Arma la consulta agregada de saldos de vacaciones leyendo el ledger
 * (empleado_cuota_movimiento_sueldos) por empleado.
 *
 * saldo = SUM(dias)  ·  devengado = SUM(dias>0)  ·  consumido = SUM(-dias<0)
 */
class SaldoVacacionesReporteConsulta
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Empleado_Sueldos>
     */
    public static function query(array $filtros, EmpresaRepositoryInterface $empresaRepository): Builder
    {
        $anio = ! empty($filtros['anio']) ? (int) $filtros['anio'] : null;

        $sub = DB::table('empleado_cuota_movimiento_sueldos')
            ->select('empleado_id')
            ->selectRaw('SUM(CASE WHEN dias > 0 THEN dias ELSE 0 END) as devengado')
            ->selectRaw('SUM(CASE WHEN dias < 0 THEN -dias ELSE 0 END) as consumido')
            ->selectRaw('SUM(dias) as saldo')
            ->when($anio !== null, fn ($q) => $q->where('anio_periodo', $anio))
            ->groupBy('empleado_id');

        $query = Empleado_Sueldos::query()
            ->leftJoinSub($sub, 'cuota', fn ($join) => $join->on('cuota.empleado_id', '=', 'empleado_sueldos.id'))
            ->leftJoin('empresa', 'empresa.id', '=', 'empleado_sueldos.empresa_id')
            ->select('empleado_sueldos.*')
            ->selectRaw('COALESCE(empresa.nombre, "") as empresa_nombre')
            ->selectRaw('COALESCE(cuota.devengado, 0) as devengado')
            ->selectRaw('COALESCE(cuota.consumido, 0) as consumido')
            ->selectRaw('COALESCE(cuota.saldo, 0) as saldo');

        $empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empleado_sueldos.empresa_id');

        if (($filtros['empresa_scope'] ?? 'una') !== 'todas' && ! empty($filtros['empresa_id'])) {
            $query->where('empleado_sueldos.empresa_id', (int) $filtros['empresa_id']);
        }

        $estado = $filtros['estado'] ?? EmpleadoEstados::ACTIVO;
        if ($estado !== 'TODOS') {
            $query->where('empleado_sueldos.estado', $estado);
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '') {
            $id = filter_var($valor, FILTER_VALIDATE_INT);
            $like = '%'.addcslashes($valor, '%_\\').'%';
            $query->where(function ($q) use ($valor, $like, $id) {
                if ($id !== false) {
                    $q->orWhere('empleado_sueldos.legajo', (int) $id);
                }
                $q->orWhere('empleado_sueldos.nombre', 'like', $like);
                CoincidenciaFlexibleTexto::aplicar(
                    $q,
                    'empleado_sueldos.nombre',
                    $valor,
                    true,
                    CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                );
            });
        }

        if (! empty($filtros['solo_con_saldo'])) {
            $query->having('saldo', '>', 0);
        }

        $query->orderBy('empleado_sueldos.empresa_id')
            ->orderBy('empleado_sueldos.legajo');

        return $query;
    }

    /**
     * Totales del filtro completo (no solo la página visible).
     *
     * @param  Builder<Empleado_Sueldos>  $query
     * @return array{empleados: int, devengado: float, consumido: float, saldo: float}
     */
    public static function totales(Builder $query): array
    {
        $base = $query->clone()->reorder();
        $filas = $base->get(['empleado_sueldos.id', 'devengado', 'consumido', 'saldo']);

        return [
            'empleados' => $filas->count(),
            'devengado' => round((float) $filas->sum('devengado'), 2),
            'consumido' => round((float) $filas->sum('consumido'), 2),
            'saldo' => round((float) $filas->sum('saldo'), 2),
        ];
    }

    /**
     * Antigüedad total (años completos) al día de hoy, sumando antigüedad anterior.
     */
    public static function aniosAntiguedad(Empleado_Sueldos $empleado): int
    {
        if (empty($empleado->fecha_ingreso)) {
            return 0;
        }
        $previos = VacacionEscalaAntiguedad::aniosDesdeAntiguedadAnterior($empleado->antiguedad_anterior);
        $ingreso = Carbon::parse($empleado->fecha_ingreso)->startOfDay();
        $hasta = ! empty($empleado->fecha_egreso) ? Carbon::parse($empleado->fecha_egreso)->startOfDay() : Carbon::now()->startOfDay();
        if ($ingreso->greaterThan($hasta)) {
            return $previos;
        }

        return $previos + (int) $ingreso->diffInYears($hasta);
    }
}
