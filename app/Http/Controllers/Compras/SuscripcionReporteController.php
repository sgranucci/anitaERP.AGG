<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\SuscripcionListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\SuscripcionReporteService;
use App\Services\Compras\SuscripcionService;
use App\Support\Compras\SuscripcionSupport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Base completa de suscripciones con los indicadores de gestión del módulo.
 */
class SuscripcionReporteController extends Controller
{
    public function __construct(
        private SuscripcionReporteService $reporteService,
        private SuscripcionService $suscripcionService,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('reportar-suscripcion');

        $filtros = $this->filtrosDesdeRequest($request);
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $ccId = (int) ($filtros['centrocosto_id'] ?? 0);
        $cuentaId = (int) ($filtros['cuentacontable_id'] ?? 0);

        $cc = $ccId > 0 ? Centrocosto::query()->find($ccId, ['id', 'codigo', 'nombre']) : null;
        $cuenta = $cuentaId > 0 ? Cuentacontable::query()->find($cuentaId, ['id', 'codigo', 'nombre']) : null;

        return view('compras.suscripcion.reporte', [
            'filtros' => $filtros,
            'filas' => $this->reporteService->base($filtros),
            'indicadores' => $this->reporteService->indicadores($filtros),
            'por_area' => $this->reporteService->porArea($filtros),
            'compromiso' => $this->reporteService->compromisoContraPresupuesto($filtros),
            'proximas' => $this->reporteService->proximasARenovar($empresaId ?: null),
            'sin_orden' => $this->reporteService->gastoSinOrden($empresaId ?: null),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'centrocosto_filtro' => $cc,
            'cuenta_filtro' => $cuenta,
            'estados' => SuscripcionSupport::estadosNegocio(),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('reportar-suscripcion');

        $filtros = $this->filtrosDesdeRequest($request);
        $filas = $this->reporteService->base($filtros);
        $kpis = $this->reporteService->indicadores($filtros);

        return match (strtoupper($formato)) {
            'PDF' => $this->descargarPdf($filas, $filtros, $kpis),
            'CSV' => (new SuscripcionListadoExport)
                ->parametros($filas, $filtros, $kpis)
                ->download('suscripciones_reporte.csv', Excel::CSV),
            default => (new SuscripcionListadoExport)
                ->parametros($filas, $filtros, $kpis)
                ->download('suscripciones_reporte.xlsx'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosDesdeRequest(Request $request): array
    {
        return [
            'empresa_id' => $request->input('empresa_id'),
            'centrocosto_id' => $request->input('centrocosto_id'),
            'cuentacontable_id' => $request->input('cuentacontable_id'),
            'area' => $request->input('area'),
            'estado' => $request->input('estado'),
            'q' => $request->input('q'),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Compras\Ordencompra>  $filas
     * @param  array<string, mixed>  $filtros
     * @param  array<string, float|int|string>  $kpis
     */
    private function descargarPdf($filas, array $filtros, array $kpis): BinaryFileResponse
    {
        $html = view('exports.compras.suscripcion_listado_pdf', compact('filas', 'filtros', 'kpis'))->render();

        $directorio = storage_path('pdf/listados');
        if (! is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }
        $archivo = $directorio.'/suscripciones_reporte_'.now()->format('Ymd_His').'.pdf';

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html)->save($archivo);

        return response()->download($archivo)->deleteFileAfterSend(true);
    }
}
