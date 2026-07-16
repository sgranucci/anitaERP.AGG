<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Exports\Contable\CierreTurnoGastronomiaContableConciliacionExport;
use App\Exports\Contable\CierreTurnoGastronomiaContableListadoExport;
use App\Exports\Contable\GastronomiaDiarioPuntoventaExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\CierreParcialTurnoGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Contable\CierreTurnoGastronomiaContableService;
use App\Support\Contable\CierreTurnoGastronomiaContableListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Ventas\GastronomiaCierreTurnoReporteSupport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class CierreTurnoGastronomiaContableController extends Controller
{
    public function __construct(
        private readonly CierreTurnoGastronomiaContableService $service,
        private readonly GastronomiaCierreTurnoReporteSupport $reporteSupport,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-cierres-turno-gastronomia-contable');

        $filtros = CierreTurnoGastronomiaContableListadoFiltros::resolverDesdeRequest($request);
        $coleccion = $this->service->listar($filtros, true);

        return view('contable.cierre_turno_gastronomia.index', [
            'coleccion' => $coleccion,
            'filtros' => $filtros,
            'filtrosQuery' => CierreTurnoGastronomiaContableListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => CierreTurnoGastronomiaContableListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, ?string $formato = null, ?string $busqueda = null)
    {
        can('exportar-cierres-turno-gastronomia-contable');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = CierreTurnoGastronomiaContableListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $filas = $this->service->listar($filtros, false);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('contable.cierre_turno_gastronomia.listado', [
                    'filas' => $filas,
                    'esExcel' => false,
                    'subtituloFiltros' => CierreTurnoGastronomiaContableListadoFiltros::textoCabeceraExport($filtros),
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'listado_cierres_turno_gastronomia_contable';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
            case 'CSV':
                $mime = $formato === 'CSV' ? Excel::CSV : Excel::XLSX;
                $ext = $formato === 'CSV' ? 'csv' : 'xlsx';

                return \Maatwebsite\Excel\Facades\Excel::download(
                    new CierreTurnoGastronomiaContableListadoExport($filas, $filtros),
                    'cierres_turno_gastronomia_contable.'.$ext,
                    $mime,
                );
        }

        return redirect()->route(
            'cierres_turno_gastronomia_contable',
            CierreTurnoGastronomiaContableListadoFiltros::paraQueryString($filtros),
        );
    }

    public function conciliacion(Request $request)
    {
        can('listar-cierres-turno-gastronomia-contable');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        $empresaId = (int) $request->input('empresa_id', 0);

        if ($empresaId <= 0 && count($asignadas) === 1) {
            $primera = $empresaQuery->first();
            if ($primera !== null) {
                $empresaId = (int) $primera->id;
            }
        } elseif ($empresaId > 0 && count($asignadas) >= 1 && ! in_array($empresaId, $asignadas, true)) {
            $primera = $empresaQuery->first();
            $empresaId = $primera !== null ? (int) $primera->id : 0;
        }

        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));

        if ($fechaDesde === '' && $fechaHasta === '') {
            $defaults = $this->service->resolverRangoConciliacionDefault($empresaId);
            $fechaDesde = $defaults['desde'];
            $fechaHasta = $defaults['hasta'];
        } elseif ($fechaHasta === '' && $fechaDesde !== '') {
            $fechaHasta = now()->toDateString();
        }

        $consultar = $request->boolean('consultar');
        $resultado = null;
        $errorConciliacion = null;

        if ($consultar && $empresaId > 0 && $fechaDesde !== '' && $fechaHasta !== '') {
            try {
                $resultado = $this->service->conciliarFlash($empresaId, $fechaDesde, $fechaHasta);
            } catch (\Throwable $e) {
                $errorConciliacion = $e->getMessage();
            }
        }

        $filtrosQueryConciliacion = array_filter([
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'fecha_desde' => $fechaDesde !== '' ? $fechaDesde : null,
            'fecha_hasta' => $fechaHasta !== '' ? $fechaHasta : null,
            'consultar' => $consultar ? 1 : null,
        ], static fn ($v) => $v !== null && $v !== '');

        return view('contable.cierre_turno_gastronomia.conciliacion', [
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'consultar' => $consultar,
            'resultado' => $resultado,
            'error_conciliacion' => $errorConciliacion,
            'filtrosQueryConciliacion' => $filtrosQueryConciliacion,
            'retornoListadoQuery' => $this->resolverRetornoListadoQuery($request),
        ]);
    }

    public function listarConciliacion(Request $request, ?string $formato = null)
    {
        can('exportar-cierres-turno-gastronomia-contable');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));

        $redirectQuery = array_filter([
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'fecha_desde' => $fechaDesde !== '' ? $fechaDesde : null,
            'fecha_hasta' => $fechaHasta !== '' ? $fechaHasta : null,
            'consultar' => 1,
        ], static fn ($v) => $v !== null && $v !== '');

        if ($empresaId <= 0 || $fechaDesde === '' || $fechaHasta === '') {
            return redirect()
                ->route('cierres_turno_gastronomia_contable_conciliacion', $redirectQuery)
                ->with('mensaje_error', 'Indique empresa y rango de jornadas para exportar.');
        }

        try {
            $resultado = $this->service->conciliarFlash($empresaId, $fechaDesde, $fechaHasta);
        } catch (\Throwable $e) {
            return redirect()
                ->route('cierres_turno_gastronomia_contable_conciliacion', $redirectQuery)
                ->with('mensaje_error', $e->getMessage());
        }

        switch ($formato) {
            case 'PDF':
                $view = \View::make('contable.cierre_turno_gastronomia.conciliacion_listado', [
                    'resultado' => $resultado,
                    'esExcel' => false,
                    'filas' => CierreTurnoGastronomiaContableConciliacionExport::aplanarFilas($resultado),
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'conciliacion_cierres_turno_gastronomia_contable';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
            case 'CSV':
                $mime = $formato === 'CSV' ? Excel::CSV : Excel::XLSX;
                $ext = $formato === 'CSV' ? 'csv' : 'xlsx';

                return \Maatwebsite\Excel\Facades\Excel::download(
                    new CierreTurnoGastronomiaContableConciliacionExport($resultado),
                    'conciliacion_cierres_turno_gastronomia_contable.'.$ext,
                    $mime,
                );
        }

        return redirect()->route('cierres_turno_gastronomia_contable_conciliacion', $redirectQuery);
    }

    public function diarioPuntoventa(Request $request)
    {
        can('listar-cierres-turno-gastronomia-contable');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        $empresaId = (int) $request->input('empresa_id', 0);

        if ($empresaId <= 0 && count($asignadas) === 1) {
            $primera = $empresaQuery->first();
            if ($primera !== null) {
                $empresaId = (int) $primera->id;
            }
        } elseif ($empresaId > 0 && count($asignadas) >= 1 && ! in_array($empresaId, $asignadas, true)) {
            $primera = $empresaQuery->first();
            $empresaId = $primera !== null ? (int) $primera->id : 0;
        }

        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));

        if ($fechaDesde === '' && $fechaHasta === '') {
            $defaults = $this->service->resolverRangoConciliacionDefault($empresaId);
            $fechaDesde = $defaults['desde'];
            $fechaHasta = $defaults['hasta'];
        } elseif ($fechaHasta === '' && $fechaDesde !== '') {
            $fechaHasta = now()->toDateString();
        }

        $consultar = $request->boolean('consultar');
        $resultado = null;
        $errorReporte = null;

        if ($consultar && $empresaId > 0 && $fechaDesde !== '' && $fechaHasta !== '') {
            try {
                $resultado = $this->service->reporteDiarioPuntoventa($empresaId, $fechaDesde, $fechaHasta);
            } catch (\Throwable $e) {
                $errorReporte = $e->getMessage();
            }
        }

        $filtrosQuery = array_filter([
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'fecha_desde' => $fechaDesde !== '' ? $fechaDesde : null,
            'fecha_hasta' => $fechaHasta !== '' ? $fechaHasta : null,
            'consultar' => $consultar ? 1 : null,
        ], static fn ($v) => $v !== null && $v !== '');

        return view('contable.cierre_turno_gastronomia.diario_puntoventa', [
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'consultar' => $consultar,
            'resultado' => $resultado,
            'error_reporte' => $errorReporte,
            'filtrosQuery' => $filtrosQuery,
            'retornoListadoQuery' => $this->resolverRetornoListadoQuery($request),
        ]);
    }

    public function listarDiarioPuntoventa(Request $request, ?string $formato = null)
    {
        can('exportar-cierres-turno-gastronomia-contable');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));

        $redirectQuery = array_filter([
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'fecha_desde' => $fechaDesde !== '' ? $fechaDesde : null,
            'fecha_hasta' => $fechaHasta !== '' ? $fechaHasta : null,
            'consultar' => 1,
        ], static fn ($v) => $v !== null && $v !== '');

        if ($empresaId <= 0 || $fechaDesde === '' || $fechaHasta === '') {
            return redirect()
                ->route('cierres_turno_gastronomia_contable_diario_puntoventa', $redirectQuery)
                ->with('mensaje_error', 'Indique empresa y rango de jornadas para exportar.');
        }

        try {
            $resultado = $this->service->reporteDiarioPuntoventa($empresaId, $fechaDesde, $fechaHasta);
        } catch (\Throwable $e) {
            return redirect()
                ->route('cierres_turno_gastronomia_contable_diario_puntoventa', $redirectQuery)
                ->with('mensaje_error', $e->getMessage());
        }

        switch ($formato) {
            case 'PDF':
                $view = \View::make('contable.cierre_turno_gastronomia.diario_puntoventa_listado', [
                    'resultado' => $resultado,
                    'esExcel' => false,
                    'matriz' => GastronomiaDiarioPuntoventaExport::matrizAncha($resultado),
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'gastronomia_diario_puntoventa_contable';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
            case 'CSV':
                $mime = $formato === 'CSV' ? Excel::CSV : Excel::XLSX;
                $ext = $formato === 'CSV' ? 'csv' : 'xlsx';

                return \Maatwebsite\Excel\Facades\Excel::download(
                    new GastronomiaDiarioPuntoventaExport($resultado),
                    'gastronomia_diario_puntoventa_contable.'.$ext,
                    $mime,
                );
        }

        return redirect()->route('cierres_turno_gastronomia_contable_diario_puntoventa', $redirectQuery);
    }

    public function comprobanteCierre(Request $request, int $id)
    {
        can('listar-cierres-turno-gastronomia-contable');

        $turno = TurnoOperativoGastronomia::query()
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->findOrFail($id);

        $this->assertEmpresaPermitida((int) $turno->empresa_id);

        $datos = $this->reporteSupport->datosComprobanteCierreDefinitivo($turno);
        $nombre = 'cierre_turno_gastronomia_'.$turno->id.'.pdf';

        return $this->pdfComprobante($datos, $nombre, $request->boolean('inline'));
    }

    public function comprobanteParcial(Request $request, int $id)
    {
        can('listar-cierres-turno-gastronomia-contable');

        $parcial = CierreParcialTurnoGastronomia::query()->findOrFail($id);
        $turno = $parcial->turnoOperativo;
        if ($turno === null) {
            abort(404);
        }
        $this->assertEmpresaPermitida((int) $turno->empresa_id);

        $datos = $this->reporteSupport->datosComprobanteParcial($parcial);
        $nombre = 'cierre_parcial_turno_gastronomia_'.$parcial->id.'.pdf';

        return $this->pdfComprobante($datos, $nombre, $request->boolean('inline'));
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'Empresa no autorizada.');
        }
    }

    /**
     * @return array<string, string|int>
     */
    private function resolverRetornoListadoQuery(Request $request): array
    {
        $retorno = $request->input('retorno');
        if (is_array($retorno) && $retorno !== []) {
            $query = [];
            foreach ($retorno as $key => $value) {
                if (! is_string($key) || $key === '' || ! is_scalar($value)) {
                    continue;
                }
                $trimmed = is_string($value) ? trim($value) : $value;
                if ($trimmed === '' || $trimmed === null) {
                    continue;
                }
                $query[$key] = $trimmed;
            }

            return $query;
        }

        return QueryRetornoListado::desdeRequest($request, CierreTurnoGastronomiaContableListadoFiltros::class);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function pdfComprobante(array $datos, string $nombre, bool $inline)
    {
        $html = view('ventas.gastronomia.cierres_turno.comprobante', compact('datos'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html, 'UTF-8');

        if ($inline) {
            return $pdf->stream($nombre);
        }

        return $pdf->download($nombre);
    }
}
