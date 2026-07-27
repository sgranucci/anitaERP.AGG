<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\GastronomiaControlContableCigarrillosExport;
use App\Exports\Ventas\GastronomiaInsumosTipoarticuloReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\Tipoarticulo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\GastronomiaControlContableCigarrillosService;
use App\Services\Ventas\GastronomiaInsumosTipoarticuloReporteService;
use App\Support\Ventas\GastronomiaInsumosTipoarticuloReporteFiltros;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class GastronomiaInsumosTipoarticuloReporteController extends Controller
{
    public function __construct(
        private readonly GastronomiaInsumosTipoarticuloReporteService $reporteService,
        private readonly GastronomiaControlContableCigarrillosService $controlContableService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-insumos-tipoarticulo-gastronomia');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaInsumosTipoarticuloReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        $consultado = $request->boolean('consultar')
            && GastronomiaInsumosTipoarticuloReporteFiltros::tieneCriteriosAplicados($filtros);

        $resultado = null;
        $filas = null;
        $filasVista = [];
        $controlContable = null;
        $tipoSeleccionado = $this->tipoarticuloSeleccionado((int) ($filtros['tipoarticulo_id'] ?? 0));
        $usaControlContable = (bool) ($tipoSeleccionado?->usa_control_contable_cigarrillos);

        if ($consultado) {
            $resultado = $this->reporteService->generar($filtros);
            $perPage = max(10, min(200, (int) $request->input('per_page', 50)));
            $filas = $this->reporteService->paginarFilas(
                $resultado['filas'],
                $perPage,
                max(1, (int) $request->input('page', 1)),
            );
            $filasVista = $filas->items();

            if ($usaControlContable) {
                $controlContable = $this->controlContableService->generar($filtros);
            }
        }

        $filtrosQuery = GastronomiaInsumosTipoarticuloReporteFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('ventas.gastronomia.insumos_tipoarticulo_reporte.index', [
            'empresa_query' => $empresaQuery,
            'tipoarticulo_query' => Tipoarticulo::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'usa_control_contable_cigarrillos']),
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'periodo_texto' => GastronomiaInsumosTipoarticuloReporteFiltros::formatearPeriodoTexto($filtros),
            'tipoarticulo_etiqueta' => $tipoSeleccionado
                ? trim((string) $tipoSeleccionado->nombre)
                : $this->etiquetaTipoarticulo((int) ($filtros['tipoarticulo_id'] ?? 0)),
            'empresa_texto' => $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'usa_control_contable_cigarrillos' => $usaControlContable,
            'control_contable' => $controlContable,
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-insumos-tipoarticulo-gastronomia');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaInsumosTipoarticuloReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if (! GastronomiaInsumosTipoarticuloReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('gastronomia_insumos_tipoarticulo_reporte');
        }

        $resultado = $this->reporteService->generar($filtros);
        $titulo = 'Ventas insumos gastronomía por día';
        $empresaTexto = $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery);
        $subtitulo = 'Tipo: '.$this->etiquetaTipoarticulo((int) ($filtros['tipoarticulo_id'] ?? 0))
            .' · Empresa: '.$empresaTexto
            .' · Período: '.GastronomiaInsumosTipoarticuloReporteFiltros::formatearPeriodoTexto($filtros);

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.gastronomia.insumos_tipoarticulo_reporte.listado', [
                    'resultado' => $resultado,
                    'filtros' => $filtros,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'empresa_nombre' => $empresaTexto,
                ])->render();

                return $this->descargarPdf($view, 'insumos_tipoarticulo_gastronomia', 'legal', 'landscape');

            case 'EXCEL':
                return (new GastronomiaInsumosTipoarticuloReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo, $empresaTexto)
                    ->download('insumos_tipoarticulo_gastronomia.xlsx');

            case 'CSV':
                return (new GastronomiaInsumosTipoarticuloReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo, $empresaTexto)
                    ->download('insumos_tipoarticulo_gastronomia.csv', Excel::CSV);
        }

        return redirect()->route(
            'gastronomia_insumos_tipoarticulo_reporte',
            GastronomiaInsumosTipoarticuloReporteFiltros::paraQueryString($filtros),
        );
    }

    public function exportarControlContable(Request $request, string $formato)
    {
        can('listar-insumos-tipoarticulo-gastronomia');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = GastronomiaInsumosTipoarticuloReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsFiltros($filtros, $empresaQuery);
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        $tipo = $this->tipoarticuloSeleccionado((int) ($filtros['tipoarticulo_id'] ?? 0));
        if ($tipo === null || ! $tipo->usa_control_contable_cigarrillos) {
            return redirect()
                ->route('gastronomia_insumos_tipoarticulo_reporte', GastronomiaInsumosTipoarticuloReporteFiltros::paraQueryString($filtros))
                ->with('error', 'El tipo de artículo seleccionado no tiene habilitado el control contable de cigarrillos.');
        }

        if (! GastronomiaInsumosTipoarticuloReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('gastronomia_insumos_tipoarticulo_reporte');
        }

        $titulo = 'Control contable cigarrillos';
        $empresaTexto = $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery);
        $subtitulo = 'Tipo: '.trim((string) $tipo->nombre)
            .' · Empresa: '.$empresaTexto
            .' · Período: '.GastronomiaInsumosTipoarticuloReporteFiltros::formatearPeriodoTexto($filtros);

        switch (strtoupper($formato)) {
            case 'EXCEL':
                return (new GastronomiaControlContableCigarrillosExport($this->controlContableService))
                    ->parametros($filtros, $titulo, $subtitulo, $empresaTexto, false)
                    ->download('control_contable_cigarrillos.xlsx');

            case 'CSV':
                return (new GastronomiaControlContableCigarrillosExport($this->controlContableService))
                    ->parametros($filtros, $titulo, $subtitulo, $empresaTexto, true)
                    ->download('control_contable_cigarrillos.csv', Excel::CSV);
        }

        return redirect()->route(
            'gastronomia_insumos_tipoarticulo_reporte',
            GastronomiaInsumosTipoarticuloReporteFiltros::paraQueryString($filtros),
        );
    }

    private function tipoarticuloSeleccionado(int $tipoarticuloId): ?Tipoarticulo
    {
        if ($tipoarticuloId <= 0) {
            return null;
        }

        return Tipoarticulo::query()->find($tipoarticuloId);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarDefaultsFiltros(array $filtros, $empresaQuery): array
    {
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0 && $empresaQuery->count() === 1) {
            $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
        }

        if (($filtros['tipoarticulo_id'] ?? 0) <= 0) {
            $filtros['tipoarticulo_id'] = (int) (GastronomiaInsumosTipoarticuloReporteFiltros::tipoarticuloDefaultId() ?? 0);
        }

        if (($filtros['fecha_desde'] ?? '') === '' && ($filtros['fecha_hasta'] ?? '') === '') {
            $filtros['fecha_desde'] = Carbon::today()->startOfMonth()->format('Y-m-d');
            $filtros['fecha_hasta'] = Carbon::today()->format('Y-m-d');
        }

        [$desde, $hasta] = GastronomiaInsumosTipoarticuloReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        $filtros['fecha_desde'] = $desde;
        $filtros['fecha_hasta'] = $hasta;

        return $filtros;
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'No tiene acceso a la empresa seleccionada.');
        }
    }

    private function etiquetaEmpresa(int $empresaId, $empresaQuery): string
    {
        if ($empresaId <= 0) {
            return '—';
        }

        $nombre = $empresaQuery->firstWhere('id', $empresaId)?->nombre;

        return $nombre !== null && trim((string) $nombre) !== ''
            ? trim((string) $nombre)
            : (string) $empresaId;
    }

    private function etiquetaTipoarticulo(int $tipoarticuloId): string
    {
        if ($tipoarticuloId <= 0) {
            return '—';
        }

        $row = Tipoarticulo::query()->find($tipoarticuloId);

        return $row ? trim((string) $row->nombre) : (string) $tipoarticuloId;
    }

    private function descargarPdf(string $view, string $nombreBase, string $paper, string $orientation)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            abort(500, 'No se pudo crear el directorio para el PDF.');
        }

        $nombrePdf = $nombreBase.'_'.date('Ymd_His').'_'.uniqid('', true);
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper($paper, $orientation);
        $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf');
    }
}
