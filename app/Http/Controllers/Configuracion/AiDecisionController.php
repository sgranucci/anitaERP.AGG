<?php

namespace App\Http\Controllers\Configuracion;

use App\Exports\Configuracion\AiDecisionListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Ai\AiDecision;
use App\Services\Ai\AiDecisionLogger;
use App\Support\Configuracion\AiDecisionKpisSupport;
use App\Support\Configuracion\AiDecisionListadoFiltros;
use App\Support\Configuracion\AiOperacionSaludSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Excel;

/**
 * Panel de gobernanza IA: KPIs y detalle de ai_decision.
 */
class AiDecisionController extends Controller
{
    public function __construct(
        private AiDecisionLogger $aiDecisionLogger,
    ) {}

    public function index(Request $request)
    {
        can('listar-ai-decisiones');

        $filtros = AiDecisionListadoFiltros::resolverDesdeRequest($request);
        $consultado = (bool) ($filtros['consultar'] ?? false);
        $filtrosQuery = AiDecisionListadoFiltros::paraQueryString($filtros);

        $kpis = null;
        $coleccion = null;
        if ($consultado) {
            $kpis = AiDecisionKpisSupport::calcular($filtros);
            $coleccion = AiDecisionKpisSupport::listar($filtros, true);
            $coleccion->appends($filtrosQuery);
        }

        return view('configuracion.ai_decision.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'kpis' => $kpis,
            'coleccion' => $coleccion,
            'acciones' => AiDecisionListadoFiltros::accionesEtiquetas(),
            'skills' => AiDecisionListadoFiltros::skillsEtiquetas(),
            'salud' => AiOperacionSaludSupport::snapshot(),
            'eventosPendientes' => AiOperacionSaludSupport::eventosPendientes(12),
        ]);
    }

    public function listar(Request $request, ?string $formato = null)
    {
        can('listar-ai-decisiones');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = AiDecisionListadoFiltros::resolverDesdeRequest($request);
        $filtros['consultar'] = true;

        if (! AiDecisionListadoFiltros::tieneCriteriosAplicados($filtros)
            && trim((string) ($filtros['fecha_desde'] ?? '')) === '') {
            return redirect()->route('ai_decision', AiDecisionListadoFiltros::paraQueryString($filtros));
        }

        $filas = AiDecisionKpisSupport::listar($filtros, false);
        $kpis = AiDecisionKpisSupport::calcular($filtros);
        $titulo = 'Gobernanza IA — decisiones';
        $subtitulo = $this->subtituloFiltros($filtros);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('configuracion.ai_decision.listado', [
                    'filas' => $filas,
                    'kpis' => $kpis,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'totalFilas' => $filas->count(),
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'listado_ai_decision_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new AiDecisionListadoExport($filas, $kpis, $titulo, $subtitulo))
                    ->download('listado_ai_decision_'.date('Ymd_His').'.xlsx');

            case 'CSV':
                return (new AiDecisionListadoExport($filas, $kpis, $titulo, $subtitulo))
                    ->download('listado_ai_decision_'.date('Ymd_His').'.csv', Excel::CSV);

            default:
                return redirect()->route('ai_decision', AiDecisionListadoFiltros::paraQueryString($filtros));
        }
    }

    /**
     * El usuario cerró el modal de preview sin confirmar: marca la sugerencia como descartada.
     */
    public function descartar(Request $request)
    {
        if (
            ! can('crear-precarga-proveedores', false)
            && ! can('crear-ingresos-egresos-caja', false)
            && ! can('editar-ingresos-egresos-caja', false)
            && ! can('ocr-recepcion-proveedor', false)
            && ! can('listar-ai-decisiones', false)
        ) {
            abort(403);
        }

        $request->validate([
            'decision_id' => 'required|integer|min:1',
        ]);

        $decision = AiDecision::find((int) $request->input('decision_id'));
        if (! $decision) {
            return response()->json(['ok' => false, 'message' => 'Decisión inexistente.'], 404);
        }

        if ($decision->accion !== AiDecision::ACCION_SUGERIDA) {
            return response()->json(['ok' => true, 'message' => 'Ya estaba resuelta.']);
        }

        $this->aiDecisionLogger->resolver(
            (int) $decision->id,
            AiDecision::ACCION_DESCARTADA,
            Auth::id() ? (int) Auth::id() : null,
        );

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function subtituloFiltros(array $filtros): string
    {
        $partes = [];
        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== '') {
            $partes[] = 'Desde '.$filtros['fecha_desde'];
        }
        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            $partes[] = 'Hasta '.$filtros['fecha_hasta'];
        }
        if (trim((string) ($filtros['skill'] ?? '')) !== '') {
            $partes[] = 'Skill: '.AiDecisionListadoFiltros::etiquetaSkill($filtros['skill']);
        }
        if (trim((string) ($filtros['accion'] ?? '')) !== '') {
            $partes[] = 'Acción: '.AiDecisionListadoFiltros::etiquetaAccion($filtros['accion']);
        }

        return $partes !== [] ? implode(' · ', $partes) : 'Sin filtros';
    }
}
