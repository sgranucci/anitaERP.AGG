<?php

namespace App\Http\Controllers\Compras;

use App\Exports\Compras\ComprobanteProveedorImputacionApReporteExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ComprobanteProveedorImputacionApReporteService;
use App\Support\Compras\ComprobanteProveedorImputacionApReporteFiltros;
use App\Support\Compras\OrdencompraReporteCriteriosSupport;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class ComprobanteProveedorImputacionApReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'comprobante_proveedor_imputacion_ap_reporte';

    public function __construct(
        private ComprobanteProveedorImputacionApReporteService $service,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-reporte-imputacion-ap-proveedor');

        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ComprobanteProveedorImputacionApReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        $consultado = $request->boolean('consultar')
            && ComprobanteProveedorImputacionApReporteFiltros::tieneCriteriosAplicados($filtros);

        $resultado = null;
        $filas = null;
        $filasVista = [];

        if ($consultado) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_ids' => $filtros['empresa_ids'],
                'consolidar_empresas' => (bool) ($filtros['consolidar_empresas'] ?? true),
            ]);
            $resultado = $this->service->generar($filtros);
            $perPage = max(25, min(500, (int) $request->input('per_page', 100)));
            $filas = $this->service->paginarFilas(
                $resultado['filas'],
                $perPage,
                max(1, (int) $request->input('page', 1)),
            );
            $filasVista = $filas->items();
        }

        $filtrosQuery = ComprobanteProveedorImputacionApReporteFiltros::paraQueryString($filtros);
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = (int) $request->input('per_page');
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('compras.comprobante_proveedor_imputacion_ap_reporte.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'periodo_texto' => ComprobanteProveedorImputacionApReporteFiltros::formatearPeriodoTexto($filtros),
            'subtitulo' => $this->service->subtituloFiltros($filtros, $empresaQuery),
            'meta_proveedores' => OrdencompraReporteCriteriosSupport::metaTextoProveedores(
                (string) ($filtros['proveedores'] ?? ''),
            ),
            'puede_ver_comprobante' => can('editar-comprobante-proveedor', false) || can('listar-comprobante-proveedor', false),
            'puede_ver_proveedor' => can('editar-proveedor', false) || can('listar-proveedor', false),
            'puede_ver_asiento' => can('editar-asiento', false) || can('listar-asiento', false),
            'puede_ver_pagoproveedor' => can('editar-pagoproveedor', false) || can('listar-pagoproveedor', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-imputacion-ap-proveedor');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = ComprobanteProveedorImputacionApReporteFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasYDefaults($request, $filtros, $empresaQuery);

        if (! ComprobanteProveedorImputacionApReporteFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('reporte_imputacion_ap_proveedor');
        }

        $resultado = $this->service->generar($filtros);
        $titulo = 'Comprobantes vs imputación AP';
        $subtitulo = $this->service->subtituloFiltros($filtros, $empresaQuery);
        $filas = $resultado['filas'];
        $totales = $resultado['totales'];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('compras.comprobante_proveedor_imputacion_ap_reporte.listado', compact(
                    'filas',
                    'totales',
                    'titulo',
                    'subtitulo',
                ))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'imputacion_ap_proveedor_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ComprobanteProveedorImputacionApReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('imputacion_ap_proveedor.xlsx');

            case 'CSV':
                return (new ComprobanteProveedorImputacionApReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('imputacion_ap_proveedor.csv', Excel::CSV);
        }

        return redirect()->route(
            'reporte_imputacion_ap_proveedor',
            ComprobanteProveedorImputacionApReporteFiltros::paraQueryString($filtros)
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasYDefaults(Request $request, array $filtros, $empresaQuery): array
    {
        $defaults = ComprobanteProveedorImputacionApReporteFiltros::defaults();
        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (empty($filtros['fecha_desde'])) {
            $filtros['fecha_desde'] = $defaults['fecha_desde'];
        }

        if (empty($filtros['fecha_hasta']) && ! $request->has('fecha_hasta')) {
            $filtros['fecha_hasta'] = $defaults['fecha_hasta'];
        }

        if (($filtros['empresa_ids'] ?? []) === []) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_CLAVE);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            }
        }

        if (($filtros['empresa_ids'] ?? []) === [] && $empresaQuery->count() >= 1) {
            $filtros['empresa_ids'] = $empresaQuery->count() === 1
                ? [(int) $empresaQuery->first()->id]
                : $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $filtros;
    }
}
