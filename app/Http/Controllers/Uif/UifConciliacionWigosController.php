<?php

namespace App\Http\Controllers\Uif;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Empresa;
use App\Models\Uif\UifConciliacionWigosPeriodo;
use App\Models\Uif\UifConciliacionWigosUnificado;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Uif\UifConciliacionWigosLibroExcelService;
use App\Services\Uif\UifWigosConciliacionService;
use App\Support\Uif\UifWigosConciliacionEmpresaSupport;
use App\Support\Uif\UifWigosConciliacionFiltros;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class UifConciliacionWigosController extends Controller
{
    public function __construct(
        private readonly UifWigosConciliacionService $service,
        private readonly UifConciliacionWigosLibroExcelService $libroExcelService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-conciliacion-wigos-uif');

        $filtros = UifWigosConciliacionFiltros::resolverDesdeRequest($request);
        $filtrosQuery = UifWigosConciliacionFiltros::paraQueryString($filtros);
        $empresaQuery = $this->empresaRepository->allFiltrado()
            ->filter(fn ($e) => in_array((int) $e->id, UifWigosConciliacionEmpresaSupport::empresaIdsOrdenados(), true))
            ->values();

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $this->assertEmpresaPermitida($empresaId);
        }

        $resumenEmpresas = $this->service->resumenEmpresasPeriodo($filtros['anio'], $filtros['mes']);
        $periodo = null;
        $filas = null;
        $totales = null;
        $error = null;

        if ($filtros['consultar'] && $empresaId > 0) {
            $periodo = UifConciliacionWigosPeriodo::query()
                ->where('empresa_id', $empresaId)
                ->where('anio', $filtros['anio'])
                ->where('mes', $filtros['mes'])
                ->first();

            if ($periodo !== null && $periodo->unificado()->count() === 0 && ($periodo->titos()->count() > 0 || $periodo->premiosMaquina()->count() > 0)) {
                try {
                    $this->service->conciliar($periodo);
                    $periodo->refresh();
                } catch (\Throwable $e) {
                    $error = $e->getMessage();
                }
            }

            if ($periodo !== null) {
                $query = UifConciliacionWigosUnificado::query()
                    ->where('periodo_id', $periodo->id)
                    ->orderBy('orden');

                $coleccion = $query->get();
                $totales = [
                    'registros' => $coleccion->count(),
                    'monto' => (float) $coleccion->sum(fn ($r) => (float) ($r->monto ?? 0)),
                    'con_ticket' => $coleccion->whereNotNull('numero')->count(),
                    'sin_ticket' => $coleccion->whereNull('numero')->count(),
                ];

                $perPage = 50;
                $currentPage = LengthAwarePaginator::resolveCurrentPage();
                $items = $coleccion->slice(($currentPage - 1) * $perPage, $perPage)->values();
                $filas = new LengthAwarePaginator(
                    $items,
                    $coleccion->count(),
                    $perPage,
                    $currentPage,
                    ['path' => LengthAwarePaginator::resolveCurrentPath()],
                );
            }
        }

        $empresa = $empresaId > 0 ? $empresaQuery->firstWhere('id', $empresaId) : null;

        return view('uif.conciliacion_wigos.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'empresa_id' => $empresaId,
            'empresa' => $empresa,
            'periodo' => $periodo,
            'filas' => $filas,
            'totales' => $totales,
            'resumen_empresas' => $resumenEmpresas,
            'periodo_texto' => UifWigosConciliacionFiltros::periodoTexto($filtros),
            'codigo_empresa' => $empresaId > 0 ? UifWigosConciliacionEmpresaSupport::codigoDesdeEmpresaId($empresaId) : '',
            'error' => $error,
        ]);
    }

    public function cargar(Request $request)
    {
        can('cargar-conciliacion-wigos-uif');

        $request->validate([
            'periodo' => 'required|regex:/^\d{4}-\d{1,2}$/',
            'empresa_id' => 'required|integer',
            'archivo_titos' => 'required|file|mimes:xls,xlsx|mimetypes:application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-office',
            'archivo_pm' => 'required|file|mimes:xls,xlsx|mimetypes:application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-office',
        ]);

        $filtros = UifWigosConciliacionFiltros::resolverDesdeRequest($request);
        $usuarioId = (int) Auth::id();
        $empresaId = (int) $request->input('empresa_id');
        $this->assertEmpresaPermitida($empresaId);

        try {
            set_time_limit(0);

            $periodo = null;
            $mensajes = [];

            $fileTitos = $request->file('archivo_titos');
            $resultadoTitos = $this->service->cargarArchivoTitos(
                $fileTitos->getRealPath() ?: $fileTitos->path(),
                $empresaId,
                $filtros['anio'],
                $filtros['mes'],
                $usuarioId,
                $fileTitos->getClientOriginalName(),
            );
            $periodo = $resultadoTitos['periodo'];
            if ($resultadoTitos['titos'] === 0) {
                return back()->withInput()->with('errores', [
                    'El archivo Titos no tiene filas legibles. Debe tener encabezados Número, Monto, Fecha Pago (como el export Wigos / solapa BSA Tito Wigos).',
                ]);
            }
            $mensajes[] = sprintf('Titos Wigos: %d registros.', $resultadoTitos['titos']);

            $filePm = $request->file('archivo_pm');
            $resultadoPm = $this->service->cargarArchivoPm(
                $filePm->getRealPath() ?: $filePm->path(),
                $empresaId,
                $filtros['anio'],
                $filtros['mes'],
                $usuarioId,
                $filePm->getClientOriginalName(),
            );
            $periodo = $resultadoPm['periodo'];
            if ($resultadoPm['pm'] === 0) {
                return back()->withInput()->with('errores', [
                    'El archivo PM no tiene filas legibles. Debe tener encabezados Fecha, Proveedor, Monto Pagado (como el export Wigos / solapa BSA PM Wigos).',
                ]);
            }
            $mensajes[] = sprintf('PM Wigos: %d registros.', $resultadoPm['pm']);

            $conc = $this->service->conciliar($periodo);
            $codigo = UifWigosConciliacionEmpresaSupport::codigoDesdeEmpresaId($empresaId) ?? '';
            $mensajes[] = sprintf(
                '%s UNIFICADO: %d filas (%d Titos + %d PM del período).',
                $codigo,
                $conc['unificado'],
                $conc['titos_periodo'],
                $conc['pm_periodo'],
            );

            $redirectFiltros = UifWigosConciliacionFiltros::paraQueryString(array_merge($filtros, [
                'empresa_id' => $empresaId,
                'consultar' => true,
            ]));

            return redirect()
                ->route('conciliacion_wigos_uif', $redirectFiltros)
                ->with('mensaje', implode(' ', $mensajes))
                ->with('descargar_excel_conciliacion', route('listar_conciliacion_wigos_uif', array_merge(
                    ['formato' => 'EXCEL'],
                    UifWigosConciliacionFiltros::paraQueryStringExport(array_merge($filtros, [
                        'empresa_id' => $empresaId,
                    ])),
                )));
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('errores', [$e->getMessage()]);
        }
    }

    public function conciliar(Request $request)
    {
        can('conciliar-conciliacion-wigos-uif');

        $filtros = UifWigosConciliacionFiltros::resolverDesdeRequest($request);
        $empresaId = (int) $request->input('empresa_id', 0);

        if ($empresaId <= 0) {
            return back()->with('errores', ['Debe seleccionar una empresa.']);
        }

        $this->assertEmpresaPermitida($empresaId);

        $periodo = UifConciliacionWigosPeriodo::query()
            ->where('empresa_id', $empresaId)
            ->where('anio', $filtros['anio'])
            ->where('mes', $filtros['mes'])
            ->first();

        if ($periodo === null) {
            return back()->with('errores', ['No hay planillas cargadas para el período indicado.']);
        }

        try {
            $conc = $this->service->conciliar($periodo);

            return redirect()
                ->route('conciliacion_wigos_uif', UifWigosConciliacionFiltros::paraQueryString(array_merge($filtros, [
                    'empresa_id' => $empresaId,
                    'consultar' => true,
                ])))
                ->with('mensaje', sprintf(
                    'Conciliación generada: %d registros unificados (%d Titos + %d PM en período).',
                    $conc['unificado'],
                    $conc['titos_periodo'],
                    $conc['pm_periodo'],
                ))
                ->with('descargar_excel_conciliacion', route('listar_conciliacion_wigos_uif', array_merge(
                    ['formato' => 'EXCEL'],
                    UifWigosConciliacionFiltros::paraQueryStringExport(array_merge($filtros, [
                        'empresa_id' => $empresaId,
                    ])),
                )));
        } catch (\Throwable $e) {
            return back()->with('errores', [$e->getMessage()]);
        }
    }

    public function exportar(Request $request, string $formato)
    {
        if (! can('exportar-conciliacion-wigos-uif', false) && ! can('listar-conciliacion-wigos-uif', false)) {
            abort(403);
        }

        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $filtros = UifWigosConciliacionFiltros::resolverDesdeRequest($request);
        $formatoUpper = strtoupper($formato);

        if ($formatoUpper === 'GLOBAL' || $formatoUpper === 'EXCEL-GLOBAL') {
            return $this->exportarLibroGlobal($filtros);
        }

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);

        if ($empresaId <= 0) {
            return redirect()
                ->route('conciliacion_wigos_uif', UifWigosConciliacionFiltros::paraQueryString(array_merge($filtros, [
                    'consultar' => true,
                ])))
                ->with('errores', ['Indique la empresa para exportar (use Excel empresa en la grilla o el preview).']);
        }

        $this->assertEmpresaPermitida($empresaId);

        $periodo = UifConciliacionWigosPeriodo::query()
            ->where('empresa_id', $empresaId)
            ->where('anio', $filtros['anio'])
            ->where('mes', $filtros['mes'])
            ->first();

        if ($periodo === null || $periodo->unificado()->count() === 0) {
            return redirect()
                ->route('conciliacion_wigos_uif', UifWigosConciliacionFiltros::paraQueryString(array_merge($filtros, [
                    'empresa_id' => $empresaId,
                    'consultar' => true,
                ])))
                ->with('errores', ['No hay datos unificados para exportar.']);
        }

        $empresa = Empresa::query()->find($empresaId);
        $filas = $periodo->unificado()->orderBy('orden')->get();
        $codigo = UifWigosConciliacionEmpresaSupport::codigoDesdeEmpresaId($empresaId) ?? 'UIF';
        $titulo = sprintf('Conciliación Wigos %s — %s', $codigo, UifWigosConciliacionFiltros::periodoTexto($filtros));
        $subtitulo = $empresa?->nombre ?? '';

        switch ($formatoUpper) {
            case 'PDF':
                $view = view('uif.conciliacion_wigos.listado', [
                    'filas' => $filas,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'empresa' => $empresa,
                    'periodo_texto' => UifWigosConciliacionFiltros::periodoTexto($filtros),
                ])->render();

                return $this->descargarPdf($view, 'conciliacion_wigos_'.$codigo, 'legal', 'landscape');

            case 'EXCEL':
                $path = $this->libroExcelService->guardarEnTemporal($periodo);
                $nombre = UifWigosConciliacionEmpresaSupport::nombreArchivoLibro(
                    $empresaId,
                    $filtros['anio'],
                    $filtros['mes'],
                );

                return response()->download($path, $nombre)->deleteFileAfterSend(true);

            case 'CSV':
                return redirect()
                    ->route('conciliacion_wigos_uif', UifWigosConciliacionFiltros::paraQueryString($filtros))
                    ->with('errores', ['Use Excel para descargar el libro con Titos, PM y UNIFICADO.']);
        }

        return redirect()->route('conciliacion_wigos_uif', UifWigosConciliacionFiltros::paraQueryString($filtros));
    }

    /**
     * @param  array{anio: int, mes: int, empresa_id?: int, consultar?: bool}  $filtros
     */
    private function exportarLibroGlobal(array $filtros)
    {
        $empresaIds = array_values(array_filter(
            UifWigosConciliacionEmpresaSupport::empresaIdsOrdenados(),
            fn (int $id) => $this->empresaRepository->empresaIdPermitida($id),
        ));

        if ($empresaIds === []) {
            abort(403, 'No tiene acceso a empresas habilitadas para conciliación Wigos.');
        }

        $periodos = UifConciliacionWigosPeriodo::query()
            ->whereIn('empresa_id', $empresaIds)
            ->where('anio', $filtros['anio'])
            ->where('mes', $filtros['mes'])
            ->get();

        $totalUnificado = $periodos->sum(fn (UifConciliacionWigosPeriodo $p) => $p->unificado()->count());

        if ($totalUnificado === 0) {
            return redirect()
                ->route('conciliacion_wigos_uif', UifWigosConciliacionFiltros::paraQueryString(array_merge($filtros, [
                    'consultar' => true,
                ])))
                ->with('errores', [
                    sprintf(
                        'No hay datos unificados para el libro global en %s. Cargue Titos y PM de al menos una empresa.',
                        UifWigosConciliacionFiltros::periodoTexto($filtros),
                    ),
                ]);
        }

        $path = $this->libroExcelService->guardarEnTemporalGlobal(
            $filtros['anio'],
            $filtros['mes'],
            $empresaIds,
        );
        $nombre = UifWigosConciliacionEmpresaSupport::nombreArchivoLibroGlobal(
            $filtros['anio'],
            $filtros['mes'],
        );

        return response()->download($path, $nombre)->deleteFileAfterSend(true);
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if (! in_array($empresaId, UifWigosConciliacionEmpresaSupport::empresaIdsOrdenados(), true)) {
            abort(403, 'Empresa no habilitada para conciliación Wigos.');
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'No tiene acceso a la empresa seleccionada.');
        }
    }

    private function descargarPdf(string $view, string $nombreBase, string $paper, string $orientation)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $nombrePdf = $nombreBase.'_'.date('Ymd_His');
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper($paper, $orientation);
        $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf');
    }
}
