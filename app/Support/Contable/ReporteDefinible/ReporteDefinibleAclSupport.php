<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\UsuarioReporteContable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * ACL por informe: sin filas en usuario_reporte_contable = sin restricción;
 * con filas = solo usuarios listados.
 */
class ReporteDefinibleAclSupport
{
    public function usuarioPuede(int $usuarioId, int $reporteId): bool
    {
        if ($reporteId <= 0) {
            return true;
        }
        if ($usuarioId <= 0) {
            return false;
        }

        $tieneRestriccion = UsuarioReporteContable::query()
            ->where('reporte_contable_id', $reporteId)
            ->exists();
        if (! $tieneRestriccion) {
            return true;
        }

        return UsuarioReporteContable::query()
            ->where('reporte_contable_id', $reporteId)
            ->where('usuario_id', $usuarioId)
            ->exists();
    }

    /**
     * @param  Builder|\Illuminate\Database\Query\Builder  $query
     * @return Builder|\Illuminate\Database\Query\Builder
     */
    public function filtrarQuery($query, int $usuarioId)
    {
        if ($usuarioId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($usuarioId) {
            $q->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('usuario_reporte_contable')
                    ->whereColumn('usuario_reporte_contable.reporte_contable_id', 'reporte_contable.id');
            })->orWhereExists(function ($sub) use ($usuarioId) {
                $sub->selectRaw('1')
                    ->from('usuario_reporte_contable')
                    ->whereColumn('usuario_reporte_contable.reporte_contable_id', 'reporte_contable.id')
                    ->where('usuario_reporte_contable.usuario_id', $usuarioId);
            });
        });
    }

    /**
     * @param  list<int|string>  $usuarioIds
     */
    public function syncUsuarios(int $reporteId, array $usuarioIds): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $usuarioIds),
            fn (int $id) => $id > 0
        )));

        DB::transaction(function () use ($reporteId, $ids) {
            UsuarioReporteContable::query()
                ->where('reporte_contable_id', $reporteId)
                ->delete();

            foreach ($ids as $usuarioId) {
                UsuarioReporteContable::query()->create([
                    'reporte_contable_id' => $reporteId,
                    'usuario_id' => $usuarioId,
                ]);
            }
        });
    }

    /**
     * @return list<array{id: int, usuario_id: int}>
     */
    public function payloadUi(int $reporteId): array
    {
        $out = [];
        $rows = UsuarioReporteContable::query()
            ->where('reporte_contable_id', $reporteId)
            ->orderBy('usuario_id')
            ->get(['id', 'usuario_id']);
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row->id,
                'usuario_id' => (int) $row->usuario_id,
            ];
        }

        return $out;
    }
}
