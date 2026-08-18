<?php

namespace App\Services\Sueldos;

use App\Jobs\Sueldos\DispararReporteSueldosDefinibleWebhookJob;
use App\Models\Sueldos\ReporteSueldosDefinible;
use App\Models\Sueldos\ReporteSueldosDefinibleEjecucion;
use App\Models\Sueldos\ReporteSueldosDefinibleParidad;
use App\Models\Sueldos\ReporteSueldosDefinibleVersion;
use App\Models\Sueldos\ReporteSueldosDefinibleWebhook;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleAlertaSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleProcesador;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleParidadAnitaSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleSnapshotSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runtime auditable. Cada corrida queda congelada con definición, filtros, resultado y métricas.
 */
class ReporteSueldosDefinibleEjecucionService
{
    public function __construct(
        private ReporteSueldosDefinibleProcesador $procesador,
        private ReporteSueldosDefinibleAlertaSupport $alertas,
        private ReporteSueldosDefinibleSnapshotSupport $snapshots,
        private ReporteSueldosDefinibleDatasetService $datasets,
        private ReporteSueldosDefinibleParidadAnitaSupport $paridadAnita,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $contexto
     */
    public function crearPendiente(
        ReporteSueldosDefinible $reporte,
        array $filtros,
        array $contexto = []
    ): ReporteSueldosDefinibleEjecucion {
        return ReporteSueldosDefinibleEjecucion::query()->create([
            'uuid' => (string) Str::uuid(),
            'reporte_sueldos_definible_id' => (int) $reporte->id,
            'version_id' => $reporte->versiones()->orderByDesc('version')->value('id'),
            'suscripcion_id' => $contexto['suscripcion_id'] ?? null,
            'usuario_id' => $contexto['usuario_id'] ?? null,
            'origen' => (string) ($contexto['origen'] ?? 'cola'),
            'estado' => ReporteSueldosDefinibleEjecucion::ESTADO_PENDIENTE,
            'filtros' => $this->normalizar($filtros),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $contexto
     * @return array{ejecucion:ReporteSueldosDefinibleEjecucion,resultado:array<string,mixed>}
     */
    public function ejecutar(
        ReporteSueldosDefinible $reporte,
        array $filtros,
        array $contexto = [],
    ): array {
        $inicio = hrtime(true);
        $memoriaAntes = memory_get_peak_usage(true);
        $version = ReporteSueldosDefinibleVersion::query()
            ->where('reporte_sueldos_definible_id', (int) $reporte->id)
            ->orderByDesc('version')
            ->first();
        $versionId = $version?->id;
        $reporteEjecutar = $this->snapshots->desdeVersion($reporte, $version);

        $ejecucionId = (int) ($contexto['ejecucion_id'] ?? 0);
        if ($ejecucionId > 0) {
            $ejecucion = ReporteSueldosDefinibleEjecucion::query()
                ->where('id', $ejecucionId)
                ->where('reporte_sueldos_definible_id', (int) $reporte->id)
                ->firstOrFail();
            if ($ejecucion->estado !== ReporteSueldosDefinibleEjecucion::ESTADO_PENDIENTE) {
                throw new \RuntimeException('La ejecución en cola ya fue tomada o finalizada.');
            }
            $ejecucion->update([
                'estado' => ReporteSueldosDefinibleEjecucion::ESTADO_PROCESANDO,
                'version_id' => $versionId !== null ? (int) $versionId : $ejecucion->version_id,
                'iniciada_at' => now(),
            ]);
        } else {
            $ejecucion = ReporteSueldosDefinibleEjecucion::query()->create([
                'uuid' => (string) Str::uuid(),
                'reporte_sueldos_definible_id' => (int) $reporte->id,
                'version_id' => $versionId !== null ? (int) $versionId : null,
                'suscripcion_id' => $contexto['suscripcion_id'] ?? null,
                'ejecucion_padre_id' => $contexto['ejecucion_padre_id'] ?? null,
                'usuario_id' => $contexto['usuario_id'] ?? null,
                'origen' => (string) ($contexto['origen'] ?? 'manual'),
                'estado' => ReporteSueldosDefinibleEjecucion::ESTADO_PROCESANDO,
                'filtros' => $this->normalizar($filtros),
                'dimensiones' => $contexto['dimensiones'] ?? null,
                'burst_clave' => $contexto['burst_clave'] ?? null,
                'burst_etiqueta' => $contexto['burst_etiqueta'] ?? null,
                'iniciada_at' => now(),
            ]);
        }

        try {
            $resultado = $this->procesador->ejecutar($reporteEjecutar, $filtros);
            $resultado['meta']['version_id'] = $versionId;
            $resultado['meta']['desde_snapshot'] = $version !== null;
            $this->agregarMetaParidad($reporteEjecutar, $filtros, $resultado, $ejecucion);
            $evaluacion = $this->alertas->evaluar((int) $reporte->id, $resultado);
            $advertencias = array_values(array_unique(array_merge(
                (array) ($resultado['meta']['advertencias'] ?? []),
                $evaluacion['mensajes'],
            )));
            $resultado['meta']['advertencias'] = $advertencias;
            $resultado['meta']['controles_bloqueantes'] = $evaluacion['bloqueantes'];

            $congelado = $this->congelar($resultado);
            $json = json_encode($congelado, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            if ($json === false) {
                throw new \RuntimeException('No se pudo serializar el resultado del informe.');
            }

            $duracionMs = (int) round((hrtime(true) - $inicio) / 1_000_000);
            $estado = $advertencias === []
                ? ReporteSueldosDefinibleEjecucion::ESTADO_OK
                : ReporteSueldosDefinibleEjecucion::ESTADO_ADVERTENCIA;

            $ejecucion->update([
                'estado' => $estado,
                'resultado_hash' => hash('sha256', $json),
                'resultado_formato' => 'gzip-base64-json-v1',
                'resultado' => base64_encode(gzencode($json, 6)),
                'cantidad_filas' => count((array) ($resultado['filas'] ?? [])),
                'cantidad_columnas' => count((array) ($resultado['columnas'] ?? [])),
                'duracion_ms' => $duracionMs,
                'memoria_pico_bytes' => max($memoriaAntes, memory_get_peak_usage(true)),
                'advertencias_count' => count($advertencias),
                'advertencias' => $advertencias,
                'finalizada_at' => now(),
            ]);

            $dataset = $this->datasets->materializar($ejecucion->fresh(), $resultado);
            $resultado['meta']['dataset_uuid'] = $dataset->uuid;
            $resultado['meta']['dataset_id'] = (int) $dataset->id;

            if (! empty($contexto['publicar']) && $evaluacion['bloqueantes'] === []) {
                try {
                    $this->datasets->publicar($reporte, $dataset, 'Publicación automática de ejecución');
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $resultado['meta']['advertencias'] = array_values(array_unique(array_merge(
                        (array) ($resultado['meta']['advertencias'] ?? []),
                        \Illuminate\Support\Arr::flatten($e->errors())
                    )));
                }
            }

            $this->dispararWebhooks($ejecucion->fresh(), ReporteSueldosDefinibleWebhook::EVENTO_OK);

            return ['ejecucion' => $ejecucion->fresh(), 'resultado' => $resultado, 'dataset' => $dataset];
        } catch (Throwable $e) {
            $ejecucion->update([
                'estado' => ReporteSueldosDefinibleEjecucion::ESTADO_ERROR,
                'duracion_ms' => (int) round((hrtime(true) - $inicio) / 1_000_000),
                'memoria_pico_bytes' => max($memoriaAntes, memory_get_peak_usage(true)),
                'error' => mb_substr($e->getMessage(), 0, 65535),
                'finalizada_at' => now(),
            ]);

            $this->dispararWebhooks($ejecucion->fresh(), ReporteSueldosDefinibleWebhook::EVENTO_ERROR);

            throw $e;
        }
    }

    private function dispararWebhooks(ReporteSueldosDefinibleEjecucion $ejecucion, string $evento): void
    {
        DispararReporteSueldosDefinibleWebhookJob::dispatch((int) $ejecucion->id, $evento);
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public function congelar(array $resultado): array
    {
        return [
            'columnas' => array_values((array) ($resultado['columnas'] ?? [])),
            'filas' => array_values((array) ($resultado['filas'] ?? [])),
            'totales' => (array) ($resultado['totales'] ?? []),
            'meta' => (array) ($resultado['meta'] ?? []),
        ];
    }

    /**
     * Registra una salida segmentada sin recalcular la liquidación completa.
     *
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $contexto
     */
    public function registrarDerivada(
        ReporteSueldosDefinibleEjecucion $padre,
        array $resultado,
        array $contexto
    ): ReporteSueldosDefinibleEjecucion {
        $congelado = $this->congelar($resultado);
        $json = json_encode($congelado, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            throw new \RuntimeException('No se pudo serializar la salida segmentada.');
        }
        $advertencias = array_values((array) ($resultado['meta']['advertencias'] ?? []));

        return ReporteSueldosDefinibleEjecucion::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'reporte_sueldos_definible_id' => (int) $padre->reporte_sueldos_definible_id,
            'version_id' => $padre->version_id,
            'dataset_id' => $padre->dataset_id,
            'suscripcion_id' => $padre->suscripcion_id,
            'ejecucion_padre_id' => (int) $padre->id,
            'usuario_id' => $padre->usuario_id,
            'origen' => 'burst',
            'estado' => $advertencias === []
                ? ReporteSueldosDefinibleEjecucion::ESTADO_OK
                : ReporteSueldosDefinibleEjecucion::ESTADO_ADVERTENCIA,
            'filtros' => $padre->filtros,
            'dimensiones' => $contexto['dimensiones'] ?? null,
            'burst_clave' => $contexto['burst_clave'] ?? null,
            'burst_etiqueta' => $contexto['burst_etiqueta'] ?? null,
            'resultado_hash' => hash('sha256', $json),
            'resultado_formato' => 'gzip-base64-json-v1',
            'resultado' => base64_encode(gzencode($json, 6)),
            'cantidad_filas' => count((array) ($resultado['filas'] ?? [])),
            'cantidad_columnas' => count((array) ($resultado['columnas'] ?? [])),
            'advertencias_count' => count($advertencias),
            'advertencias' => $advertencias,
            'iniciada_at' => now(),
            'finalizada_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos): array
    {
        ksort($datos);
        foreach ($datos as $key => $valor) {
            if (is_array($valor)) {
                $datos[$key] = $this->normalizar($valor);
            }
        }

        return $datos;
    }

    /**
     * @param array<string, mixed> $filtros
     * @param array<string, mixed> $resultado
     */
    private function agregarMetaParidad(
        ReporteSueldosDefinible $reporte,
        array $filtros,
        array &$resultado,
        ?ReporteSueldosDefinibleEjecucion $ejecucion = null
    ): void {
        $liquidacionId = (int) ($filtros['liquidacion_id'] ?? 0);
        if ((string) $reporte->origen !== 'anita' || $liquidacionId <= 0) {
            return;
        }
        $liquidacion = \App\Models\Sueldos\Liquidacion_Sueldos::query()->find($liquidacionId);
        if (! $liquidacion) {
            return;
        }
        $empresaAnita = (int) ($filtros['empresa_anita'] ?? $liquidacion->empresa_id ?? 1);
        $liquidacionAnita = (int) ($filtros['liquidacion_anita'] ?? $liquidacion->numero);
        $totalesAnita = $this->paridadAnita->totales(
            $reporte,
            $empresaAnita,
            $liquidacionAnita,
            ! empty($filtros['incluir_confidencial']) ? 'ambos' : 'normal'
        );
        $tolerancia = abs((float) ($filtros['tolerancia_paridad'] ?? 0.01));
        $maxima = 0.0;
        $conDiferencia = 0;
        $filas = [];
        foreach ((array) ($resultado['columnas'] ?? []) as $columna) {
            if (empty($columna['numerica'])) {
                continue;
            }
            $nro = (int) ($columna['nro'] ?? 0);
            $erp = (float) ($resultado['totales'][$nro] ?? 0);
            $anita = (float) ($totalesAnita[$nro] ?? 0);
            $diferencia = round($erp - $anita, 4);
            $maxima = max($maxima, abs($diferencia));
            $coincide = abs($diferencia) <= $tolerancia;
            if (! $coincide) {
                $conDiferencia++;
            }
            $filas[] = [
                'ejecucion_id' => $ejecucion?->id,
                'liquidacion_anita' => $liquidacionAnita,
                'empresa_anita' => $empresaAnita,
                'columna_nro' => $nro,
                'columna_descripcion' => (string) ($columna['descripcion'] ?? ''),
                'total_erp' => $erp,
                'total_anita' => $anita,
                'diferencia' => $diferencia,
                'tolerancia' => $tolerancia,
                'coincide' => $coincide,
            ];
        }
        $resultado['meta']['paridad_diferencia_maxima'] = round($maxima, 4);
        $resultado['meta']['paridad_columnas_con_diferencia'] = $conDiferencia;
        if ($ejecucion instanceof ReporteSueldosDefinibleEjecucion) {
            $ejecucion->paridades()->delete();
            foreach ($filas as $fila) {
                $fila['ejecucion_id'] = (int) $ejecucion->id;
                ReporteSueldosDefinibleParidad::query()->create($fila);
            }
        }
    }
}
