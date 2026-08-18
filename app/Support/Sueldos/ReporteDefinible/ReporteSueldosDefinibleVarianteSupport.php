<?php

namespace App\Support\Sueldos\ReporteDefinible;

use App\Models\Sueldos\ReporteSueldosDefinibleVariante;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReporteSueldosDefinibleVarianteSupport
{
    /**
     * @return Collection<int, ReporteSueldosDefinibleVariante>
     */
    public function listar(int $reporteId, int $usuarioId): Collection
    {
        return ReporteSueldosDefinibleVariante::query()
            ->where('reporte_sueldos_definible_id', $reporteId)
            ->where(fn ($q) => $q->where('usuario_id', $usuarioId)->orWhere('compartida', true))
            ->with('usuario:id,nombre')
            ->orderByDesc('predeterminada')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function guardar(int $reporteId, int $usuarioId, array $datos): ReporteSueldosDefinibleVariante
    {
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'El nombre de la variante es obligatorio.']);
        }

        return DB::transaction(function () use ($reporteId, $usuarioId, $datos, $nombre) {
            $predeterminada = (bool) ($datos['predeterminada'] ?? false);
            if ($predeterminada) {
                ReporteSueldosDefinibleVariante::query()
                    ->where('reporte_sueldos_definible_id', $reporteId)
                    ->where('usuario_id', $usuarioId)
                    ->update(['predeterminada' => false]);
            }

            return ReporteSueldosDefinibleVariante::query()->updateOrCreate(
                [
                    'reporte_sueldos_definible_id' => $reporteId,
                    'usuario_id' => $usuarioId,
                    'nombre' => mb_substr($nombre, 0, 80),
                ],
                [
                    'filtros' => (array) ($datos['filtros'] ?? []),
                    'columnas_visibles' => $datos['columnas_visibles'] ?? null,
                    'ordenamiento' => $datos['ordenamiento'] ?? null,
                    'agrupaciones' => $datos['agrupaciones'] ?? null,
                    'pivot_spec' => $datos['pivot_spec'] ?? null,
                    'visualizacion' => $datos['visualizacion'] ?? null,
                    'compartida' => (bool) ($datos['compartida'] ?? false),
                    'predeterminada' => $predeterminada,
                ]
            );
        });
    }

    public function eliminar(int $reporteId, int $usuarioId, int $varianteId): void
    {
        ReporteSueldosDefinibleVariante::query()
            ->where('reporte_sueldos_definible_id', $reporteId)
            ->where('usuario_id', $usuarioId)
            ->whereKey($varianteId)
            ->delete();
    }
}
