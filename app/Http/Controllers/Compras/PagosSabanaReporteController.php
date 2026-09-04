<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\PagosSabanaReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\PagosSabanaReporteService;
use App\Support\Compras\PagosSabanaColumnasSupport;
use App\Support\Compras\PagosSabanaReporteFiltros;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Jurosh\PDFMerge\PDFMerger;
use Maatwebsite\Excel\Excel;

class PagosSabanaReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'pagos_sabana_compras';

    private const PERMISO = 'listar-reporte-pagos-sabana';

    public function __construct(
        private PagosSabanaReporteService $service,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can(self::PERMISO);

        ini_set('memory_limit', '768M');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = PagosSabanaReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        $consultado = $request->boolean('consultar')
            && PagosSabanaReporteFiltros::tieneCriteriosAplicados($filtros);

        $resultado = null;
        $filas = null;
        $filasVista = [];

        if ($consultado) {
            $this->persistirPreferencias($filtros);
            $resultado = $this->service->generar($filtros);
            $perPage = max(25, min(500, (int) $request->input('per_page', 100)));
            $filas = $this->service->paginarFilas(
                $resultado['filas'],
                $perPage,
                max(1, (int) $request->input('page', 1)),
            );
            $filasVista = $filas->items();
        } else {
            $resultado = [
                'filas' => [],
                'columnas' => PagosSabanaColumnasSupport::resolverVisibles([]),
                'totales' => ['cantidad' => 0, 'importes' => [], 'total_pago' => 0.0],
                'secciones' => [],
            ];
        }

        $filtrosQuery = PagosSabanaReporteFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filas instanceof LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('compras.pagos_sabana_reporte.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'columnas' => $resultado['columnas'],
            'filas' => $filas,
            'filasVista' => $filasVista,
            'subtitulo' => $this->service->subtituloFiltros($filtros, $empresaQuery),
            'puede_ver_proveedor' => can('editar-proveedor', false) || can('listar-proveedor', false),
            'puede_ver_pagoproveedor' => can('editar-pagoproveedor', false) || can('listar-pagoproveedor', false),
            'puede_ver_ingresoegreso' => can('editar-ingresos-egresos-caja', false) || can('listar-ingresos-egresos-caja', false),
            'puede_ver_comprobante' => can('editar-comprobante-proveedor', false)
                || can('listar-comprobante-proveedor', false),
            'puede_ver_ordencompra' => can('editar-ordencompra', false) || can('listar-ordencompra', false),
            'puede_ver_solicitudpago' => can('editar-solicitud-pago', false) || can('listar-solicitud-pago', false),
            'anita_habilitada' => PagosSabanaReporteService::anitaHabilitadaEnConfig(),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can(self::PERMISO);

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = PagosSabanaReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        if (! PagosSabanaReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('reporte_pagos_sabana');
        }

        $resultado = $this->service->generar($filtros);
        $titulo = 'Pagos x Fecha de Movimiento';
        $subtitulo = $this->service->subtituloFiltros($filtros, $empresaQuery);
        $filas = $resultado['filas'];
        $totales = $resultado['totales'];
        $columnas = $resultado['columnas'];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                if (count($filtros['empresa_ids'] ?? []) > 1 && empty($filtros['consolidar_empresas'])) {
                    return $this->descargarPdfPorEmpresa($filtros, $resultado, $empresaQuery, $titulo);
                }

                return $this->descargarPdf(
                    $this->renderizarPdf($filas, $totales, $columnas, $titulo, $subtitulo),
                    'pagos_sabana_'.date('Ymd_His'),
                );

            case 'EXCEL':
                return (new PagosSabanaReporteExport($filas, $columnas, $titulo, $subtitulo, $totales))
                    ->download('pagos_sabana.xlsx');

            case 'CSV':
                return (new PagosSabanaReporteExport($filas, $columnas, $titulo, $subtitulo, $totales))
                    ->download('pagos_sabana.csv', Excel::CSV);
        }

        return redirect()->route('reporte_pagos_sabana', PagosSabanaReporteFiltros::paraQueryString($filtros));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $totales
     * @param  list<array<string, mixed>>  $columnas
     */
    private function renderizarPdf(array $filas, array $totales, array $columnas, string $titulo, string $subtitulo): string
    {
        return \View::make('compras.pagos_sabana_reporte.listado', [
            'filas' => $filas,
            'totales' => $totales,
            'columnas' => $columnas,
            'titulo' => $titulo,
            'subtitulo' => $subtitulo,
            'puede_ver_proveedor' => false,
            'puede_ver_pagoproveedor' => false,
            'puede_ver_ingresoegreso' => false,
            'puede_ver_comprobante' => false,
            'puede_ver_ordencompra' => false,
            'puede_ver_solicitudpago' => false,
            'para_export' => true,
        ])->render();
    }

    private function descargarPdf(string $html, string $nombre)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html, 'UTF-8')->save($path.'/'.$nombre.'.pdf');

        return response()->download($path.'/'.$nombre.'.pdf')->deleteFileAfterSend(true);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  array<string, mixed>  $resultadoCompleto
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     */
    private function descargarPdfPorEmpresa(array $filtros, array $resultadoCompleto, $empresaQuery, string $titulo)
    {
        $dir = storage_path('pdf/listados');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $temporales = [];

        try {
            foreach ($resultadoCompleto['secciones'] ?? [] as $seccion) {
                $empresaId = (int) ($seccion['empresa_id'] ?? 0);
                $filtrosEmpresa = array_merge($filtros, [
                    'empresa_ids' => [$empresaId],
                    'consolidar_empresas' => true,
                ]);
                $subtitulo = ((string) ($seccion['empresa_nombre'] ?? ''))
                    .' · '.$this->service->subtituloFiltros($filtrosEmpresa, $empresaQuery);

                $html = $this->renderizarPdf(
                    $seccion['filas'] ?? [],
                    $seccion['totales'] ?? [],
                    $resultadoCompleto['columnas'] ?? [],
                    $titulo,
                    $subtitulo,
                );

                $temp = $dir.'/pagos_sabana_tmp_'.uniqid('', true).'.pdf';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($html, 'UTF-8')->save($temp);
                $temporales[] = $temp;
            }

            $nombreBase = 'pagos_sabana_'.date('Ymd_His');
            $destino = $dir.'/'.$nombreBase.'.pdf';

            if (count($temporales) === 1) {
                rename($temporales[0], $destino);
                $temporales = [];
            } else {
                $merger = new PDFMerger;
                foreach ($temporales as $ruta) {
                    $merger->addPDF($ruta, 'all', 'horizontal');
                }
                $merger->merge('file', $destino);
            }

            return response()->download($destino, $nombreBase.'.pdf')->deleteFileAfterSend(true);
        } finally {
            foreach ($temporales as $ruta) {
                if (is_file($ruta)) {
                    @unlink($ruta);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function persistirPreferencias(array $filtros): void
    {
        ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
            'empresa_ids' => $filtros['empresa_ids'],
            'consolidar_empresas' => (bool) ($filtros['consolidar_empresas'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasYDefaults(Request $request, array $filtros, $empresaQuery): array
    {
        $defaults = PagosSabanaReporteFiltros::defaults();
        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (($filtros['empresa_ids'] ?? []) === []) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            }
        }

        if (($filtros['empresa_ids'] ?? []) === [] && $empresaQuery->count() >= 1) {
            $filtros['empresa_ids'] = $empresaQuery->count() === 1
                ? [(int) $empresaQuery->first()->id]
                : $permitidos;
        }

        if (! $request->has('consolidar_empresas')) {
            $filtros['consolidar_empresas'] = ReportePreferenciasUsuario::leerBool(
                self::PREFERENCIAS_CLAVE,
                'consolidar_empresas',
                true,
            );
        }

        if (empty($filtros['fecha_desde'])) {
            $filtros['fecha_desde'] = $defaults['fecha_desde'];
        }
        if (empty($filtros['fecha_hasta'])) {
            $filtros['fecha_hasta'] = $defaults['fecha_hasta'];
        }

        return $filtros;
    }
}
