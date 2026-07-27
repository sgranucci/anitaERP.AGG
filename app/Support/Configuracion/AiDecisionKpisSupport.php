<?php

namespace App\Support\Configuracion;

use App\Models\Ai\AiDecision;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KPIs de gobernanza sobre ai_decision (tasa de aceptación, pendientes, latencia).
 */
final class AiDecisionKpisSupport
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   total: int,
     *   por_accion: array<string, int>,
     *   tasa_aceptacion: ?float,
     *   tasa_edicion: ?float,
     *   tasa_descarte: ?float,
     *   pendientes: int,
     *   errores: int,
     *   score_promedio: ?float,
     *   latencia_promedio_ms: ?float,
     *   por_skill: list<array{skill: string, total: int, confirmadas: int, editadas: int, descartadas: int}>
     * }
     */
    public static function calcular(array $filtros): array
    {
        $base = AiDecision::query();
        AiDecisionListadoFiltros::aplicar($base, $filtros);

        $total = (clone $base)->count();

        $porAccionRaw = (clone $base)
            ->select('accion', DB::raw('COUNT(*) as c'))
            ->groupBy('accion')
            ->pluck('c', 'accion');

        $porAccion = [];
        foreach (array_keys(AiDecisionListadoFiltros::accionesEtiquetas()) as $accion) {
            $porAccion[$accion] = (int) ($porAccionRaw[$accion] ?? 0);
        }

        $confirmadas = $porAccion[AiDecision::ACCION_CONFIRMADA] ?? 0;
        $editadas = $porAccion[AiDecision::ACCION_EDITADA] ?? 0;
        $descartadas = $porAccion[AiDecision::ACCION_DESCARTADA] ?? 0;
        $auto = $porAccion[AiDecision::ACCION_AUTO_APLICADA] ?? 0;
        $pendientes = $porAccion[AiDecision::ACCION_SUGERIDA] ?? 0;
        $errores = $porAccion[AiDecision::ACCION_ERROR] ?? 0;

        $resueltas = $confirmadas + $editadas + $descartadas + $auto;
        $aceptadas = $confirmadas + $editadas + $auto;

        $agregados = (clone $base)
            ->selectRaw('AVG(score) as score_avg, AVG(latencia_ms) as latencia_avg')
            ->first();

        $porSkill = (clone $base)
            ->select(
                'skill',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN accion = '".AiDecision::ACCION_CONFIRMADA."' THEN 1 ELSE 0 END) as confirmadas"),
                DB::raw("SUM(CASE WHEN accion = '".AiDecision::ACCION_EDITADA."' THEN 1 ELSE 0 END) as editadas"),
                DB::raw("SUM(CASE WHEN accion = '".AiDecision::ACCION_DESCARTADA."' THEN 1 ELSE 0 END) as descartadas"),
            )
            ->groupBy('skill')
            ->orderBy('skill')
            ->get()
            ->map(static fn ($row): array => [
                'skill' => (string) $row->skill,
                'total' => (int) $row->total,
                'confirmadas' => (int) $row->confirmadas,
                'editadas' => (int) $row->editadas,
                'descartadas' => (int) $row->descartadas,
            ])
            ->all();

        return [
            'total' => $total,
            'por_accion' => $porAccion,
            'tasa_aceptacion' => $resueltas > 0 ? round($aceptadas / $resueltas, 4) : null,
            'tasa_edicion' => $aceptadas > 0 ? round($editadas / $aceptadas, 4) : null,
            'tasa_descarte' => $resueltas > 0 ? round($descartadas / $resueltas, 4) : null,
            'pendientes' => $pendientes,
            'errores' => $errores,
            'score_promedio' => $agregados && $agregados->score_avg !== null
                ? round((float) $agregados->score_avg, 4)
                : null,
            'latencia_promedio_ms' => $agregados && $agregados->latencia_avg !== null
                ? round((float) $agregados->latencia_avg, 0)
                : null,
            'por_skill' => $porSkill,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, AiDecision>
     */
    public static function listar(array $filtros, bool $paginar = true)
    {
        $query = AiDecision::query()
            ->with(['usuario:id,nombre', 'resolutor:id,nombre'])
            ->orderByDesc('id');
        AiDecisionListadoFiltros::aplicar($query, $filtros);

        if ($paginar) {
            return $query->paginate(25);
        }

        return $query->limit(5000)->get();
    }
}
