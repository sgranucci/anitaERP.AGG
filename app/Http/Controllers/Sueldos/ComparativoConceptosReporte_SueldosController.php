<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\ComparativoConceptosReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Sueldos\ComparativoConceptosReporteService;
use App\Support\Sueldos\ComparativoConceptosListadoFiltros;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ComparativoConceptosReporte_SueldosController extends Controller
{
    public function __construct(
        private readonly ComparativoConceptosReporteService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-comparativo-conceptos-sueldos');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaDefault = optional($empresaQuery->first())->id;
        $filtros = ComparativoConceptosListadoFiltros::resolverDesdeRequest(
            $request,
            $empresaDefault ? (int) $empresaDefault : null
        );

        $consultado = $request->boolean('consultar');
        $resultado = null;
        $filasPaginadas = null;

        $tieneEmpresa = (int) ($filtros['empresa_id'] ?? 0) > 0
            || collect($filtros['empresa_ids'] ?? [])->filter(fn ($id) => (int) $id > 0)->isNotEmpty();
        if ($consultado && $tieneEmpresa) {
            $resultado = $this->service->generar($filtros);
            $page = max(1, (int) $request->input('page', 1));
            $perPage = 50;
            $slice = array_slice($resultado['filas'], ($page - 1) * $perPage, $perPage);
            $filasPaginadas = new LengthAwarePaginator(
                $slice,
                count($resultado['filas']),
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => ComparativoConceptosListadoFiltros::paraQueryString($filtros, true),
                ]
            );
        }

        $catalogos = $this->service->catalogos();

        return view('sueldos.comparativo_conceptos.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => ComparativoConceptosListadoFiltros::paraQueryString($filtros, $consultado),
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filasPaginadas' => $filasPaginadas,
            'centrocostos' => $catalogos['centrocostos'],
            'categorias' => $catalogos['categorias'],
            'sindicatos' => $catalogos['sindicatos'],
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-comparativo-conceptos-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = ComparativoConceptosListadoFiltros::resolverDesdeRequest(
            $request,
            $empresaDefault ? (int) $empresaDefault : null
        );

        $tieneEmpresaExport = (int) ($filtros['empresa_id'] ?? 0) > 0
            || collect($filtros['empresa_ids'] ?? [])->filter(fn ($id) => (int) $id > 0)->isNotEmpty();
        if (! $tieneEmpresaExport) {
            return redirect()->route(
                'comparativo_conceptos_sueldos',
                ComparativoConceptosListadoFiltros::paraQueryString($filtros)
            )->with('error', 'Seleccione una empresa.');
        }

        $resultado = $this->service->generar($filtros);
        $titulo = 'Comparativo de conceptos liquidados';
        $subtitulo = $resultado['subtitulo'];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('sueldos.comparativo_conceptos.listado', [
                    'filas' => $resultado['filas'],
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'resultado' => $resultado,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre = 'comparativo_conceptos_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre.'.pdf');

                return response()->download($path.'/'.$nombre.'.pdf');

            case 'EXCEL':
                return (new ComparativoConceptosReporteExport($resultado, $titulo, $subtitulo))
                    ->download('comparativo_conceptos.xlsx');

            case 'CSV':
                return (new ComparativoConceptosReporteExport($resultado, $titulo, $subtitulo))
                    ->download('comparativo_conceptos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(
            'comparativo_conceptos_sueldos',
            ComparativoConceptosListadoFiltros::paraQueryString($filtros, true)
        );
    }
}
