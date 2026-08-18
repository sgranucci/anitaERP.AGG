<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Jobs\Sueldos\PivotReporteSueldosDefinibleJob;
use App\Models\Sueldos\ReporteSueldosDefinible;
use App\Models\Sueldos\ReporteSueldosDefinibleDashboard;
use App\Models\Sueldos\ReporteSueldosDefinibleDashboardWidget;
use App\Models\Sueldos\ReporteSueldosDefinibleDataset;
use App\Models\Sueldos\ReporteSueldosDefinibleEjecucion;
use App\Services\Sueldos\ReporteSueldosDefinibleDatasetService;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleAclSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefiniblePivotSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReporteSueldosDefinibleDashboardController extends Controller
{
    public function __construct(
        private ReporteSueldosDefinibleAclSupport $acl,
        private ReporteSueldosDefinibleDatasetService $datasets,
        private ReporteSueldosDefiniblePivotSupport $pivots,
    ) {}

    public function show($id)
    {
        can('listar-reporte-sueldos-definible');
        $reporte = ReporteSueldosDefinible::query()->findOrFail((int) $id);
        abort_unless($this->acl->puedeAcceder((int) $id, (int) Auth::id()), 403);

        $dashboards = ReporteSueldosDefinibleDashboard::query()
            ->where('reporte_sueldos_definible_id', $reporte->id)
            ->where(fn ($q) => $q->where('usuario_id', Auth::id())->orWhere('compartida', true))
            ->with('widgets')
            ->orderBy('nombre')
            ->get();

        $dataset = null;
        if ($reporte->publicado_dataset_id) {
            $dataset = ReporteSueldosDefinibleDataset::query()->find($reporte->publicado_dataset_id);
        }
        $resultado = $dataset
            ? $this->datasets->cargarResultado($dataset)
            : (($reporte->ejecucionPublicada?->resultadoDecodificado()) ?? ['filas' => [], 'columnas' => [], 'totales' => [], 'meta' => []]);

        return view('sueldos.reporte_definible.dashboard', [
            'data' => $reporte,
            'dashboards' => $dashboards,
            'resultado' => $resultado,
            'dataset' => $dataset,
        ]);
    }

    public function guardar(Request $request, $id)
    {
        can('actualizar-reporte-sueldos-definible');
        abort_unless($this->acl->puedeAcceder((int) $id, (int) Auth::id()), 403);
        $data = $request->validate([
            'nombre' => 'required|string|max:80',
            'compartida' => 'nullable|boolean',
            'widgets' => 'nullable|array',
            'widgets.*.titulo' => 'required|string|max:100',
            'widgets.*.tipo' => 'required|in:tabla,barra,linea,pie,kpi',
            'widgets.*.pivot_spec' => 'nullable|array',
            'widgets.*.orden' => 'nullable|integer',
            'widgets.*.ancho' => 'nullable|integer|min:3|max:12',
        ]);

        $dashboard = DB::transaction(function () use ($id, $data) {
            $dashboard = ReporteSueldosDefinibleDashboard::query()->updateOrCreate(
                [
                    'reporte_sueldos_definible_id' => (int) $id,
                    'usuario_id' => (int) Auth::id(),
                    'nombre' => mb_substr(trim($data['nombre']), 0, 80),
                ],
                ['compartida' => (bool) ($data['compartida'] ?? false)]
            );
            $dashboard->widgets()->delete();
            foreach ((array) ($data['widgets'] ?? []) as $i => $w) {
                ReporteSueldosDefinibleDashboardWidget::query()->create([
                    'dashboard_id' => $dashboard->id,
                    'titulo' => $w['titulo'],
                    'tipo' => $w['tipo'],
                    'pivot_spec' => $w['pivot_spec'] ?? null,
                    'orden' => (int) ($w['orden'] ?? $i),
                    'ancho' => (int) ($w['ancho'] ?? 12),
                ]);
            }

            return $dashboard;
        });

        return redirect()
            ->route('dashboard_reporte_sueldos_definible', ['id' => $id])
            ->with('mensaje', 'Dashboard «'.$dashboard->nombre.'» guardado.');
    }

    public function pivot(Request $request, $id)
    {
        can('listar-reporte-sueldos-definible');
        abort_unless($this->acl->puedeAcceder((int) $id, (int) Auth::id()), 403);
        $data = $request->validate([
            'dataset_id' => 'nullable|integer',
            'ejecucion_id' => 'nullable|integer',
            'pivot_spec' => 'required|array',
        ]);

        $filas = [];
        if (! empty($data['dataset_id'])) {
            $dataset = ReporteSueldosDefinibleDataset::query()
                ->where('reporte_sueldos_definible_id', (int) $id)
                ->whereKey((int) $data['dataset_id'])
                ->firstOrFail();
            $filas = $this->datasets->cargarResultado($dataset)['filas'];
        } elseif (! empty($data['ejecucion_id'])) {
            $ejecucion = ReporteSueldosDefinibleEjecucion::query()
                ->where('reporte_sueldos_definible_id', (int) $id)
                ->whereKey((int) $data['ejecucion_id'])
                ->firstOrFail();
            $filas = (array) ($ejecucion->resultadoDecodificado()['filas'] ?? []);
        } else {
            $reporte = ReporteSueldosDefinible::query()->findOrFail((int) $id);
            if ($reporte->publicado_dataset_id) {
                $dataset = ReporteSueldosDefinibleDataset::query()->findOrFail((int) $reporte->publicado_dataset_id);
                $filas = $this->datasets->cargarResultado($dataset)['filas'];
            }
        }

        if (count($filas) > ReporteSueldosDefiniblePivotSupport::UMBRAL_SYNC) {
            $uuid = (string) Str::uuid();
            Cache::put('pivot:'.$uuid, ['estado' => 'procesando'], now()->addMinutes(30));
            PivotReporteSueldosDefinibleJob::dispatch($uuid, $filas, (array) $data['pivot_spec']);

            return response()->json(['async' => true, 'job_uuid' => $uuid], 202);
        }

        return response()->json(['data' => $this->pivots->pivotar($filas, (array) $data['pivot_spec'])]);
    }

    public function pivotEstado($id, $uuid)
    {
        can('listar-reporte-sueldos-definible');
        abort_unless($this->acl->puedeAcceder((int) $id, (int) Auth::id()), 403);
        abort_unless(Str::isUuid((string) $uuid), 404);
        $estado = Cache::get('pivot:'.$uuid);
        abort_if($estado === null, 404, 'El resultado del pivot venció o no existe.');

        return response()->json($estado, ($estado['estado'] ?? null) === 'procesando' ? 202 : 200);
    }
}
