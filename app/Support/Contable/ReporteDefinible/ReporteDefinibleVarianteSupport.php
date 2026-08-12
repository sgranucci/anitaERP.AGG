<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\ReporteContableVariante;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Variantes nombradas de filtros de ejecución por usuario + informe.
 */
class ReporteDefinibleVarianteSupport
{
    /**
     * @return Collection<int, ReporteContableVariante>
     */
    public function listar(int $reporteId, int $usuarioId): Collection
    {
        return ReporteContableVariante::query()
            ->where('reporte_contable_id', $reporteId)
            ->where('usuario_id', $usuarioId)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payloadUi(int $reporteId, int $usuarioId): array
    {
        $out = [];
        foreach ($this->listar($reporteId, $usuarioId) as $v) {
            $out[] = [
                'id' => (int) $v->id,
                'nombre' => (string) $v->nombre,
                'filtros' => $v->filtros ?? [],
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function guardar(int $reporteId, int $usuarioId, string $nombre, array $filtros): ReporteContableVariante
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            throw ValidationException::withMessages(['nombre' => 'Nombre de variante obligatorio.']);
        }
        if (mb_strlen($nombre) > 80) {
            throw ValidationException::withMessages(['nombre' => 'Máximo 80 caracteres.']);
        }

        return ReporteContableVariante::query()->updateOrCreate(
            [
                'usuario_id' => $usuarioId,
                'reporte_contable_id' => $reporteId,
                'nombre' => $nombre,
            ],
            [
                'filtros' => $filtros,
            ]
        );
    }

    public function eliminar(int $reporteId, int $usuarioId, int $varianteId): void
    {
        ReporteContableVariante::query()
            ->where('reporte_contable_id', $reporteId)
            ->where('usuario_id', $usuarioId)
            ->whereKey($varianteId)
            ->delete();
    }
}
