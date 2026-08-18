<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Exports\Sueldos\ReporteSueldosDefinibleExport;
use App\Jobs\Sueldos\EjecutarReporteSueldosDefinibleJob;
use App\Jobs\Sueldos\PivotReporteSueldosDefinibleJob;
use App\Models\Sueldos\ReporteSueldosDefinible;
use App\Models\Sueldos\ReporteSueldosDefinibleCertificacion;
use App\Models\Sueldos\ReporteSueldosDefinibleDataset;
use App\Models\Sueldos\ReporteSueldosDefinibleEjecucion;
use App\Models\Sueldos\ReporteSueldosDefinibleSuscripcion;
use App\Models\Sueldos\ReporteSueldosDefinibleWebhook;
use App\Services\Sueldos\ReporteSueldosDefinibleDatasetService;
use App\Services\Sueldos\ReporteSueldosDefinibleDistribucionService;
use App\Services\Sueldos\ReporteSueldosDefinibleEjecucionService;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleAclSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefiniblePivotSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleSeguridadSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleVarianteSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReporteSueldosDefinibleApiController extends Controller
{
    public function __construct(
        private readonly ReporteSueldosDefinibleAclSupport $acl,
        private readonly ReporteSueldosDefinibleEjecucionService $ejecuciones,
        private readonly ReporteSueldosDefinibleSeguridadSupport $seguridad,
        private readonly ReporteSueldosDefinibleDatasetService $datasets,
        private readonly ReporteSueldosDefiniblePivotSupport $pivots,
        private readonly ReporteSueldosDefinibleDistribucionService $distribucion,
        private readonly ReporteSueldosDefinibleVarianteSupport $variantes,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertAbility(ReporteSueldosDefinibleSeguridadSupport::ABILITY_REPORTS_READ);
        can('listar-reporte-sueldos-definible');
        $query = ReporteSueldosDefinible::query()
            ->where('activo', true)
            ->orderBy('codigo');
        $this->acl->filtrarQuery($query, (int) Auth::id());

        return response()->json([
            'data' => $query->get(['id', 'codigo', 'titulo', 'tipo', 'origen', 'version_actual', 'estado_publicacion']),
            'api_version' => 'v1',
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $this->assertAbility(ReporteSueldosDefinibleSeguridadSupport::ABILITY_REPORTS_READ);
        can('listar-reporte-sueldos-definible');
        $reporte = $this->reporteAutorizado($id);
        $reporte->load('columnas.conceptos');

        return response()->json(['data' => $reporte, 'api_version' => 'v1']);
    }

    public function encolar(Request $request, int $id): JsonResponse
    {
        $this->assertAbility(ReporteSueldosDefinibleSeguridadSupport::ABILITY_EXECUTIONS_CREATE);
        can('ejecutar-reporte-sueldos-definible', false) || can('listar-reporte-sueldos-definible');
        $reporte = $this->reporteAutorizado($id);
        $filtros = $request->validate([
            'origen' => 'required|in:liquidacion,abm',
            'liquidacion_id' => 'nullable|integer|exists:liquidacion_sueldos,id',
            'liquidacion_id_comparar' => 'nullable|integer|exists:liquidacion_sueldos,id',
            'empresa_id' => 'nullable|integer|exists:empresa,id',
            'filtro_estado' => 'nullable|in:activo,baja,todos',
            'agrupacion' => 'nullable|in:empleado,centrocosto,lugartrabajo,agrupamiento',
            'agrupaciones' => 'nullable|array|max:3',
            'agrupaciones.*' => 'in:centrocosto,lugartrabajo,agrupamiento',
            'resumido' => 'nullable|boolean',
            'lugartrabajo_ids' => 'nullable|array',
            'lugartrabajo_ids.*' => 'integer',
            'centrocosto_ids' => 'nullable|array',
            'centrocosto_ids.*' => 'integer',
            'agrupamiento_ids' => 'nullable|array',
            'agrupamiento_ids.*' => 'integer',
            'orden_columna' => 'nullable|integer|min:0',
            'orden_direccion' => 'nullable|in:asc,desc',
            'top_n' => 'nullable|integer|min:0|max:10000',
            'incluir_confidencial' => 'nullable|boolean',
        ]);
        $filtros = $this->seguridad->normalizarFiltrosAutorizados($filtros);
        if (($filtros['origen'] ?? null) === ReporteSueldosDefinibleSupport::ORIGEN_LIQUIDACION
            && empty($filtros['liquidacion_id'])) {
            return response()->json(['message' => 'liquidacion_id es obligatorio para ese origen.'], 422);
        }

        $ejecucion = $this->ejecuciones->crearPendiente($reporte, $filtros, [
            'usuario_id' => Auth::id(),
            'origen' => 'api',
        ]);
        EjecutarReporteSueldosDefinibleJob::dispatch((int) $ejecucion->id)
            ->onQueue((string) config('sueldos.reporte_definible.cola', 'reports'));

        return response()->json([
            'data' => $this->estadoEjecucion($ejecucion),
            'api_version' => 'v1',
        ], 202);
    }

    public function ejecucion(int $id): JsonResponse
    {
        $this->assertAbility(ReporteSueldosDefinibleSeguridadSupport::ABILITY_REPORTS_READ);
        can('listar-reporte-sueldos-definible');
        $ejecucion = ReporteSueldosDefinibleEjecucion::query()->findOrFail($id);
        $this->assertAcl((int) $ejecucion->reporte_sueldos_definible_id);

        return response()->json([
            'data' => $this->estadoEjecucion($ejecucion),
            'api_version' => 'v1',
        ]);
    }

    public function resultado(int $id): JsonResponse
    {
        $this->assertAbility(ReporteSueldosDefinibleSeguridadSupport::ABILITY_DATASETS_READ);
        can('listar-reporte-sueldos-definible');
        $ejecucion = ReporteSueldosDefinibleEjecucion::query()->findOrFail($id);
        $reporte = $this->reporteAutorizado((int) $ejecucion->reporte_sueldos_definible_id);
        abort_unless(in_array($ejecucion->estado, [
            ReporteSueldosDefinibleEjecucion::ESTADO_OK,
            ReporteSueldosDefinibleEjecucion::ESTADO_ADVERTENCIA,
        ], true), 409, 'La ejecución todavía no tiene un resultado disponible.');

        $resultado = $ejecucion->resultadoDecodificado();
        if ($ejecucion->dataset_id) {
            $dataset = ReporteSueldosDefinibleDataset::query()->find($ejecucion->dataset_id);
            if ($dataset) {
                $resultado = $this->datasets->cargarResultado($dataset);
            }
        }
        $resultado = $this->seguridad->proyectarResultado($resultado, $reporte);

        return response()->json([
            'data' => $resultado,
            'execution' => $this->estadoEjecucion($ejecucion),
            'api_version' => 'v1',
        ]);
    }

    public function publicado(int $id): JsonResponse
    {
        $this->assertAbility(ReporteSueldosDefinibleSeguridadSupport::ABILITY_DATASETS_READ);
        can('listar-reporte-sueldos-definible');
        $reporte = $this->reporteAutorizado($id);
        if ($reporte->publicado_dataset_id) {
            $dataset = ReporteSueldosDefinibleDataset::query()->findOrFail((int) $reporte->publicado_dataset_id);
            $resultado = $this->seguridad->proyectarResultado(
                $this->datasets->cargarResultado($dataset),
                $reporte
            );

            return response()->json([
                'data' => $resultado,
                'dataset' => [
                    'id' => (int) $dataset->id,
                    'uuid' => $dataset->uuid,
                    'estado' => $dataset->estado,
                    'publicado_at' => $dataset->publicado_at?->toIso8601String(),
                ],
                'api_version' => 'v1',
            ]);
        }
        $ejecucion = $reporte->ejecucionPublicada;
        abort_if(! $ejecucion, 404, 'El informe no tiene un dataset publicado.');

        return response()->json([
            'data' => $this->seguridad->proyectarResultado($ejecucion->resultadoDecodificado(), $reporte),
            'execution' => $this->estadoEjecucion($ejecucion),
            'api_version' => 'v1',
        ]);
    }

    public function pivot(Request $request, int $id): JsonResponse
    {
        $this->assertAbility(ReporteSueldosDefinibleSeguridadSupport::ABILITY_PIVOTS_RUN);
        can('listar-reporte-sueldos-definible');
        $reporte = $this->reporteAutorizado($id);
        $data = $request->validate([
            'dataset_id' => 'nullable|integer',
            'ejecucion_id' => 'nullable|integer',
            'pivot_spec' => 'required|array',
        ]);

        $filas = [];
        if (! empty($data['dataset_id'])) {
            $dataset = ReporteSueldosDefinibleDataset::query()
                ->where('reporte_sueldos_definible_id', $reporte->id)
                ->whereKey((int) $data['dataset_id'])
                ->firstOrFail();
            $filas = $this->datasets->cargarResultado($dataset)['filas'];
        } elseif (! empty($data['ejecucion_id'])) {
            $ejecucion = ReporteSueldosDefinibleEjecucion::query()
                ->where('reporte_sueldos_definible_id', $reporte->id)
                ->whereKey((int) $data['ejecucion_id'])
                ->firstOrFail();
            $filas = (array) ($ejecucion->resultadoDecodificado()['filas'] ?? []);
        } else {
            abort(422, 'Indique dataset_id o ejecucion_id.');
        }

        if (count($filas) > ReporteSueldosDefiniblePivotSupport::UMBRAL_SYNC) {
            $uuid = (string) Str::uuid();
            Cache::put('pivot:'.$uuid, ['estado' => 'procesando'], now()->addMinutes(30));
            PivotReporteSueldosDefinibleJob::dispatch($uuid, $filas, (array) $data['pivot_spec']);

            return response()->json([
                'async' => true,
                'job_uuid' => $uuid,
                'api_version' => 'v1',
            ], 202);
        }

        return response()->json(['data' => $this->pivots->pivotar($filas, (array) $data['pivot_spec']), 'api_version' => 'v1']);
    }

    public function exportarApi(int $id, string $formato)
    {
        $this->assertAbility(ReporteSueldosDefinibleSeguridadSupport::ABILITY_DATASETS_READ);
        can('listar-reporte-sueldos-definible');
        $reporte = $this->reporteAutorizado($id);
        $dataset = ReporteSueldosDefinibleDataset::query()
            ->where('reporte_sueldos_definible_id', $id)
            ->whereKey((int) $reporte->publicado_dataset_id)
            ->firstOrFail();
        $resultado = $this->seguridad->proyectarResultado($this->datasets->cargarResultado($dataset), $reporte);
        $formato = strtoupper($formato);
        if ($formato === 'PDF') {
            return Pdf::loadView('sueldos.reporte_definible.listado', [
                'resultado' => $resultado,
                'titulo' => $reporte->titulo,
                'subtitulo' => 'Dataset publicado '.$dataset->uuid,
                'logos' => [],
            ])->setPaper('legal', 'landscape')->download('reporte_sueldos_'.$id.'.pdf');
        }
        abort_unless(in_array($formato, ['EXCEL', 'CSV'], true), 422, 'Formato no soportado.');
        $export = (new ReporteSueldosDefinibleExport)->parametros(
            $resultado,
            $reporte->titulo,
            'Dataset publicado '.$dataset->uuid
        );

        return $export->download(
            'reporte_sueldos_'.$id.($formato === 'CSV' ? '.csv' : '.xlsx'),
            $formato === 'CSV' ? Excel::CSV : Excel::XLSX
        );
    }

    public function datasetFilasApi(Request $request, int $id, int $datasetId): JsonResponse
    {
        $this->assertAbility(ReporteSueldosDefinibleSeguridadSupport::ABILITY_DATASETS_READ);
        can('listar-reporte-sueldos-definible');
        $reporte = $this->reporteAutorizado($id);
        $dataset = ReporteSueldosDefinibleDataset::query()
            ->where('reporte_sueldos_definible_id', $id)
            ->findOrFail($datasetId);

        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(1000, (int) $request->input('per_page', 100)));
        $paginado = $this->datasets->cargarResultadoPaginado($dataset, $page, $perPage);
        $paginado['data'] = $this->seguridad->proyectarResultado($paginado['data'], $reporte);

        return response()->json([
            'data' => $paginado['data'],
            'pagination' => $paginado['pagination'],
            'api_version' => 'v1',
        ]);
    }

    public function listarWebhooksApi(int $id): JsonResponse
    {
        $this->assertAbility(ReporteSueldosDefinibleSeguridadSupport::ABILITY_REPORTS_READ);
        can('listar-reporte-sueldos-definible');
        $this->reporteAutorizado($id);
        $webhooks = ReporteSueldosDefinibleWebhook::query()
            ->where('reporte_sueldos_definible_id', $id)
            ->orderByDesc('id')
            ->get(['id', 'url', 'eventos', 'activo', 'created_at', 'updated_at']);

        return response()->json(['data' => $webhooks, 'api_version' => 'v1']);
    }

    public function crearWebhookApi(Request $request, int $id): JsonResponse
    {
        $this->assertAbility(ReporteSueldosDefinibleSeguridadSupport::ABILITY_WEBHOOKS_MANAGE);
        can('actualizar-reporte-sueldos-definible', false) || can('ejecutar-reporte-sueldos-definible');
        $this->reporteAutorizado($id);
        $data = $request->validate([
            'url' => 'required|url|max:500',
            'secret' => 'required|string|min:16|max:120',
            'eventos' => 'nullable|array',
            'eventos.*' => 'in:ejecucion.ok,ejecucion.error',
            'activo' => 'nullable|boolean',
        ]);
        $webhook = ReporteSueldosDefinibleWebhook::query()->create([
            'reporte_sueldos_definible_id' => $id,
            'url' => $data['url'],
            'secret' => $data['secret'],
            'eventos' => array_values((array) ($data['eventos'] ?? ReporteSueldosDefinibleWebhook::eventosCatalogo())),
            'activo' => (bool) ($data['activo'] ?? true),
        ]);

        return response()->json([
            'data' => $webhook->only(['id', 'url', 'eventos', 'activo', 'created_at']),
            'api_version' => 'v1',
        ], 201);
    }

    public function borrarWebhookApi(int $id, int $wid): JsonResponse
    {
        $this->assertAbility(ReporteSueldosDefinibleSeguridadSupport::ABILITY_WEBHOOKS_MANAGE);
        can('actualizar-reporte-sueldos-definible', false) || can('ejecutar-reporte-sueldos-definible');
        $this->reporteAutorizado($id);
        $webhook = ReporteSueldosDefinibleWebhook::query()
            ->where('reporte_sueldos_definible_id', $id)
            ->whereKey($wid)
            ->firstOrFail();
        $webhook->delete();

        return response()->json(['data' => ['deleted' => true], 'api_version' => 'v1']);
    }

    public function openapi(): BinaryFileResponse|JsonResponse
    {
        $path = base_path('docs/api/sueldos-reportes-definibles-v1.openapi.yaml');
        if (! is_file($path)) {
            return response()->json(['error' => 'OpenAPI no disponible'], 404);
        }

        return response()->file($path, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="sueldos-reportes-definibles-v1.openapi.yaml"',
        ]);
    }

    public function forceRunSuscripcionApi(Request $request, int $id, int $sid): JsonResponse
    {
        $this->assertAbility(ReporteSueldosDefinibleSeguridadSupport::ABILITY_EXECUTIONS_CREATE);
        can('ejecutar-reporte-sueldos-definible', false) || can('listar-reporte-sueldos-definible');
        $this->reporteAutorizado($id);
        $suscripcion = ReporteSueldosDefinibleSuscripcion::query()
            ->with('reporte.columnas.conceptos')
            ->where('reporte_sueldos_definible_id', $id)
            ->findOrFail($sid);

        return response()->json([
            'data' => $this->distribucion->enviar($suscripcion, $request->boolean('dry_run')),
            'api_version' => 'v1',
        ]);
    }

    public function guardarVarianteApi(Request $request, int $id): JsonResponse
    {
        $this->assertAbility(ReporteSueldosDefinibleSeguridadSupport::ABILITY_REPORTS_READ);
        can('listar-reporte-sueldos-definible');
        $this->reporteAutorizado($id);
        $data = $request->validate([
            'nombre' => 'required|string|max:80',
            'filtros' => 'nullable|array',
            'columnas_visibles' => 'nullable|array',
            'ordenamiento' => 'nullable|array',
            'agrupaciones' => 'nullable|array',
            'pivot_spec' => 'nullable|array',
            'visualizacion' => 'nullable|array',
            'compartida' => 'nullable|boolean',
            'predeterminada' => 'nullable|boolean',
        ]);
        $variante = $this->variantes->guardar($id, (int) Auth::id(), $data);

        return response()->json(['data' => $variante, 'api_version' => 'v1'], 201);
    }

    private function reporteAutorizado(int $id): ReporteSueldosDefinible
    {
        $this->assertAcl($id);
        $reporte = ReporteSueldosDefinible::query()->findOrFail($id);
        if ($reporte->empresa_id) {
            $this->seguridad->assertEmpresaAutorizada((int) $reporte->empresa_id);
        }

        return $reporte;
    }

    private function assertAcl(int $reporteId): void
    {
        abort_unless($this->acl->puedeAcceder($reporteId, (int) Auth::id()), 403);
    }

    private function assertAbility(string $ability): void
    {
        $user = Auth::user();
        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            abort_unless($user->tokenCan($ability) || $user->tokenCan('*'), 403, 'Token sin ability '.$ability);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function estadoEjecucion(ReporteSueldosDefinibleEjecucion $ejecucion): array
    {
        $meta = [];
        try {
            $meta = (array) (($ejecucion->resultadoDecodificado()['meta'] ?? []));
        } catch (\Throwable) {
            $meta = [];
        }
        $cert = ReporteSueldosDefinibleCertificacion::query()
            ->where('ejecucion_id', (int) $ejecucion->id)
            ->where('estado', ReporteSueldosDefinibleCertificacion::ESTADO_CERTIFICADA)
            ->orderByDesc('id')
            ->first();

        return [
            'id' => (int) $ejecucion->id,
            'uuid' => $ejecucion->uuid,
            'reporte_id' => (int) $ejecucion->reporte_sueldos_definible_id,
            'dataset_id' => $ejecucion->dataset_id ? (int) $ejecucion->dataset_id : null,
            'version_id' => $ejecucion->version_id ? (int) $ejecucion->version_id : null,
            'estado' => $ejecucion->estado,
            'origen' => $ejecucion->origen,
            'cantidad_filas' => (int) $ejecucion->cantidad_filas,
            'cantidad_columnas' => (int) $ejecucion->cantidad_columnas,
            'duracion_ms' => (int) $ejecucion->duracion_ms,
            'resultado_hash' => $ejecucion->resultado_hash,
            'advertencias' => $ejecucion->advertencias,
            'error' => $ejecucion->error,
            'certificacion_id' => $cert?->id,
            'paridad_diferencia_maxima' => array_key_exists('paridad_diferencia_maxima', $meta)
                ? round((float) $meta['paridad_diferencia_maxima'], 4)
                : null,
            'created_at' => $ejecucion->created_at?->toIso8601String(),
            'iniciada_at' => $ejecucion->iniciada_at?->toIso8601String(),
            'finalizada_at' => $ejecucion->finalizada_at?->toIso8601String(),
        ];
    }
}
