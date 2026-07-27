<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\GastronomiaVentaHoraReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\GastronomiaVentaHoraReporteService;
use App\Support\Ventas\GastronomiaVentaHoraReporteFiltros;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Excel;

final class GastronomiaVentaHoraReporteController extends Controller
{
    private const MENU_URL = 'ventas/gastronomia/venta-hora-reporte';

    private const PER_PAGE = 15;

    public function __construct(
        private readonly GastronomiaVentaHoraReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->assertAccesoMenu();

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->aplicarDefaults(
            GastronomiaVentaHoraReporteFiltros::resolverDesdeRequest($request),
            $empresaQuery,
        );
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        $consultado = $request->boolean('consultar')
            && GastronomiaVentaHoraReporteFiltros::tieneCriteriosAplicados($filtros);
        $resultado = null;
        $filasPag = null;
        $filasVista = [];

        if ($consultado) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            $resultado = $this->reporteService->generar($filtros);
            $perPage = max(10, min(100, (int) $request->input('per_page', self::PER_PAGE)));
            $filasPag = $this->reporteService->paginarFilas(
                $resultado['filas'],
                $perPage,
                max(1, (int) $request->input('page', 1)),
            );
            $filasVista = $filasPag->items();
        }

        $filtrosQuery = GastronomiaVentaHoraReporteFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filasPag !== null) {
            $filasPag->appends($filtrosQuery);
        }

        return view('ventas.gastronomia.venta_hora_reporte.index', [
            'empresa_query' => $empresaQuery,
            'empresa_texto' => $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery),
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas_pag' => $filasPag,
            'filas_vista' => $filasVista,
            'periodo_texto' => GastronomiaVentaHoraReporteFiltros::formatearPeriodoTexto($filtros),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        $this->assertAccesoMenu();

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = $this->aplicarDefaults(
            GastronomiaVentaHoraReporteFiltros::resolverDesdeRequest($request),
            $empresaQuery,
        );
        $this->assertAccesoEmpresa((int) ($filtros['empresa_id'] ?? 0));

        if (! $request->boolean('consultar')
            || ! GastronomiaVentaHoraReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()
                ->route('gastronomia_venta_hora_reporte', GastronomiaVentaHoraReporteFiltros::paraQueryString($filtros))
                ->with('errores', ['Consulte el reporte antes de exportar.']);
        }

        $resultado = $this->reporteService->generar($filtros);
        $empresaTexto = $this->etiquetaEmpresa((int) ($filtros['empresa_id'] ?? 0), $empresaQuery);
        $titulo = 'Venta hora por hora';
        $subtitulo = 'Empresa: '.$empresaTexto.' · '.$resultado['periodo_texto'];

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.gastronomia.venta_hora_reporte.listado', [
                    'resultado' => $resultado,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'empresa_nombre' => $empresaTexto,
                ])->render();

                return $this->descargarPdf($view);

            case 'EXCEL':
                return (new GastronomiaVentaHoraReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo, $empresaTexto)
                    ->download('venta_hora_por_hora_gastronomia.xlsx');

            case 'CSV':
                return (new GastronomiaVentaHoraReporteExport($this->reporteService))
                    ->parametros($filtros, $titulo, $subtitulo, $empresaTexto, true)
                    ->download('venta_hora_por_hora_gastronomia.csv', Excel::CSV);
        }

        return redirect()->route(
            'gastronomia_venta_hora_reporte',
            GastronomiaVentaHoraReporteFiltros::paraQueryString($filtros),
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarDefaults(array $filtros, $empresaQuery): array
    {
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0 && $empresaQuery->count() === 1) {
            $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
        }

        if (($filtros['fecha_desde'] ?? '') === '' && ($filtros['fecha_hasta'] ?? '') === '') {
            $filtros['fecha_desde'] = Carbon::today()->startOfMonth()->toDateString();
            $filtros['fecha_hasta'] = Carbon::today()->toDateString();
        }

        [$filtros['fecha_desde'], $filtros['fecha_hasta']] =
            GastronomiaVentaHoraReporteFiltros::normalizarRangoFechas(
                (string) ($filtros['fecha_desde'] ?? ''),
                (string) ($filtros['fecha_hasta'] ?? ''),
            );

        [$filtros['hora_desde'], $filtros['hora_hasta']] =
            GastronomiaVentaHoraReporteFiltros::normalizarRangoHoras(
                $filtros['hora_desde'] ?? GastronomiaVentaHoraReporteFiltros::HORA_DESDE_DEFAULT,
                $filtros['hora_hasta'] ?? GastronomiaVentaHoraReporteFiltros::HORA_HASTA_DEFAULT,
            );

        return $filtros;
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId > 0 && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'No tiene acceso a la empresa seleccionada.');
        }
    }

    private function assertAccesoMenu(): void
    {
        if (session()->get('rol_nombre') === 'administrador') {
            return;
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $rolId = (int) session()->get('rol_id');

        if ($menuId <= 0
            || ! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            abort(403, 'No tiene acceso a este reporte.');
        }
    }

    private function etiquetaEmpresa(int $empresaId, $empresaQuery): string
    {
        $nombre = $empresaQuery->firstWhere('id', $empresaId)?->nombre;

        return $nombre !== null && trim((string) $nombre) !== ''
            ? trim((string) $nombre)
            : (string) $empresaId;
    }

    private function descargarPdf(string $view)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            abort(500, 'No se pudo crear el directorio para el PDF.');
        }

        $archivo = 'venta_hora_gastronomia_'.date('Ymd_His').'_'.uniqid('', true).'.pdf';
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$archivo);

        return response()->download($path.'/'.$archivo);
    }
}
