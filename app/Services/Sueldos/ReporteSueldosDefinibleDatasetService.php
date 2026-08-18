<?php

namespace App\Services\Sueldos;

use App\Models\Sueldos\ReporteSueldosDefinible;
use App\Models\Sueldos\ReporteSueldosDefinibleDataset;
use App\Models\Sueldos\ReporteSueldosDefinibleDatasetFila;
use App\Models\Sueldos\ReporteSueldosDefinibleDatasetPublicacion;
use App\Models\Sueldos\ReporteSueldosDefinibleEjecucion;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleParidadPublicacionSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Materializa filas de dataset y gobierna publicación separada de la definición.
 */
class ReporteSueldosDefinibleDatasetService
{
    /**
     * @param  array{columnas?:list,filas?:list,totales?:array,meta?:array}  $resultado
     */
    public function materializar(
        ReporteSueldosDefinibleEjecucion $ejecucion,
        array $resultado
    ): ReporteSueldosDefinibleDataset {
        return DB::transaction(function () use ($ejecucion, $resultado) {
            $filas = array_values((array) ($resultado['filas'] ?? []));
            $dataset = ReporteSueldosDefinibleDataset::query()->create([
                'uuid' => (string) Str::uuid(),
                'reporte_sueldos_definible_id' => (int) $ejecucion->reporte_sueldos_definible_id,
                'ejecucion_id' => (int) $ejecucion->id,
                'version_id' => $ejecucion->version_id,
                'estado' => ReporteSueldosDefinibleDataset::ESTADO_BORRADOR,
                'cantidad_filas' => count($filas),
                'columnas' => array_values((array) ($resultado['columnas'] ?? [])),
                'totales' => (array) ($resultado['totales'] ?? []),
                'meta' => (array) ($resultado['meta'] ?? []),
            ]);

            $buffer = [];
            $now = now();
            foreach ($filas as $i => $fila) {
                $buffer[] = [
                    'dataset_id' => $dataset->id,
                    'orden' => $i,
                    'legajo' => isset($fila['legajo']) ? (int) $fila['legajo'] : null,
                    'empleado_id' => isset($fila['empleado_id']) ? (int) $fila['empleado_id'] : null,
                    'datos' => json_encode($fila, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($buffer) >= 200) {
                    ReporteSueldosDefinibleDatasetFila::query()->insert($buffer);
                    $buffer = [];
                }
            }
            if ($buffer !== []) {
                ReporteSueldosDefinibleDatasetFila::query()->insert($buffer);
            }

            $ejecucion->update(['dataset_id' => (int) $dataset->id]);

            return $dataset;
        });
    }

    public function publicar(
        ReporteSueldosDefinible $reporte,
        ReporteSueldosDefinibleDataset $dataset,
        ?string $comentario = null
    ): ReporteSueldosDefinibleDataset {
        abort_unless(
            (int) $dataset->reporte_sueldos_definible_id === (int) $reporte->id,
            422,
            'Dataset ajeno al informe.'
        );
        ReporteSueldosDefinibleParidadPublicacionSupport::assertPublicable(
            $reporte,
            (int) $dataset->ejecucion_id,
            true
        );

        return DB::transaction(function () use ($reporte, $dataset, $comentario) {
            ReporteSueldosDefinibleDataset::query()
                ->where('reporte_sueldos_definible_id', $reporte->id)
                ->where('estado', ReporteSueldosDefinibleDataset::ESTADO_PUBLICADO)
                ->where('id', '!=', $dataset->id)
                ->update(['estado' => ReporteSueldosDefinibleDataset::ESTADO_ARCHIVADO]);

            $dataset->update([
                'estado' => ReporteSueldosDefinibleDataset::ESTADO_PUBLICADO,
                'publicado_por' => Auth::id(),
                'publicado_at' => now(),
            ]);

            $reporte->update([
                'publicado_dataset_id' => (int) $dataset->id,
                'publicado_ejecucion_id' => (int) $dataset->ejecucion_id,
                'estado_publicacion' => 'publicado',
            ]);

            ReporteSueldosDefinibleDatasetPublicacion::query()->create([
                'reporte_sueldos_definible_id' => (int) $reporte->id,
                'dataset_id' => (int) $dataset->id,
                'usuario_id' => Auth::id(),
                'accion' => 'publicar',
                'comentario' => $comentario,
            ]);

            return $dataset->fresh();
        });
    }

    public function rollback(ReporteSueldosDefinible $reporte, int $datasetId, ?string $comentario = null): ?ReporteSueldosDefinibleDataset
    {
        $dataset = ReporteSueldosDefinibleDataset::query()
            ->where('reporte_sueldos_definible_id', $reporte->id)
            ->whereKey($datasetId)
            ->firstOrFail();

        return $this->publicar($reporte, $dataset, $comentario ?? 'Rollback de publicación');
    }

    /**
     * @return array{columnas:list,filas:list,totales:array,meta:array}
     */
    public function cargarResultado(ReporteSueldosDefinibleDataset $dataset): array
    {
        $filas = $dataset->filas()
            ->orderBy('orden')
            ->get()
            ->map(fn (ReporteSueldosDefinibleDatasetFila $f) => (array) $f->datos)
            ->all();

        return [
            'columnas' => array_values((array) ($dataset->columnas ?? [])),
            'filas' => $filas,
            'totales' => (array) ($dataset->totales ?? []),
            'meta' => array_merge((array) ($dataset->meta ?? []), [
                'dataset_uuid' => $dataset->uuid,
                'dataset_id' => (int) $dataset->id,
            ]),
        ];
    }

    /**
     * @return array{
     *   data:array{columnas:list,filas:list,totales:array,meta:array},
     *   pagination:array{page:int,per_page:int,total:int}
     * }
     */
    public function cargarResultadoPaginado(
        ReporteSueldosDefinibleDataset $dataset,
        int $page = 1,
        int $perPage = 100
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(1000, $perPage));
        $total = (int) ($dataset->cantidad_filas ?: $dataset->filas()->count());
        $filas = $dataset->filas()
            ->orderBy('orden')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (ReporteSueldosDefinibleDatasetFila $f) => (array) $f->datos)
            ->all();

        return [
            'data' => [
                'columnas' => array_values((array) ($dataset->columnas ?? [])),
                'filas' => $filas,
                'totales' => (array) ($dataset->totales ?? []),
                'meta' => array_merge((array) ($dataset->meta ?? []), [
                    'dataset_uuid' => $dataset->uuid,
                    'dataset_id' => (int) $dataset->id,
                    'paginado' => true,
                ]),
            ],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }
}
