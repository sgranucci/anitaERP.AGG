<?php

namespace App\Support\Sueldos\ReporteDefinible;

use App\Models\Sueldos\ReporteSueldosDefinibleAclUsuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * ACL por informe: sin filas = sin restricción.
 */
final class ReporteSueldosDefinibleAclSupport
{
    public function filtrarQuery(Builder $query, int $usuarioId): void
    {
        if ($usuarioId <= 0) {
            return;
        }

        $tieneAlguna = DB::table('usuario_reporte_sueldos_definible')
            ->where('usuario_id', $usuarioId)
            ->exists();
        if (! $tieneAlguna) {
            // Sin asignaciones propias: solo ocultar informes que SÍ tienen ACL de otros.
            $query->where(function (Builder $q) {
                $q->whereNotExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('usuario_reporte_sueldos_definible as ursd')
                        ->whereColumn('ursd.reporte_sueldos_definible_id', 'reporte_sueldos_definible.id');
                });
            });

            return;
        }

        $query->where(function (Builder $q) use ($usuarioId) {
            $q->whereExists(function ($sub) use ($usuarioId) {
                $sub->selectRaw('1')
                    ->from('usuario_reporte_sueldos_definible as ursd')
                    ->whereColumn('ursd.reporte_sueldos_definible_id', 'reporte_sueldos_definible.id')
                    ->where('ursd.usuario_id', $usuarioId);
            })->orWhereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('usuario_reporte_sueldos_definible as ursd')
                    ->whereColumn('ursd.reporte_sueldos_definible_id', 'reporte_sueldos_definible.id');
            });
        });
    }

    public function puedeAcceder(int $reporteId, int $usuarioId): bool
    {
        $tieneAcl = DB::table('usuario_reporte_sueldos_definible')
            ->where('reporte_sueldos_definible_id', $reporteId)
            ->exists();
        if (! $tieneAcl) {
            return true;
        }

        return DB::table('usuario_reporte_sueldos_definible')
            ->where('reporte_sueldos_definible_id', $reporteId)
            ->where('usuario_id', $usuarioId)
            ->exists();
    }

    /**
     * @param  list<int>  $usuarioIds
     */
    public function sincronizar(int $reporteId, array $usuarioIds): void
    {
        ReporteSueldosDefinibleAclUsuario::query()
            ->where('reporte_sueldos_definible_id', $reporteId)
            ->delete();
        foreach (array_unique(array_filter(array_map('intval', $usuarioIds))) as $uid) {
            if ($uid <= 0) {
                continue;
            }
            ReporteSueldosDefinibleAclUsuario::query()->create([
                'usuario_id' => $uid,
                'reporte_sueldos_definible_id' => $reporteId,
            ]);
        }
    }

    /**
     * @return list<int>
     */
    public function usuarioIds(int $reporteId): array
    {
        return DB::table('usuario_reporte_sueldos_definible')
            ->where('reporte_sueldos_definible_id', $reporteId)
            ->pluck('usuario_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
