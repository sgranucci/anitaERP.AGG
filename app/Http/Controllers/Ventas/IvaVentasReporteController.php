<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\IvaVentasListadoExport;
use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Ventas\IvaVentasReporteService;
use App\Support\Reportes\ReportePreferenciasUsuario;
use App\Support\Ventas\IvaVentasListadoFiltros;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class IvaVentasReporteController extends Controller
{
    private const PREFERENCIAS_CLAVE = 'iva_ventas';

    public function __construct(
        private readonly IvaVentasReporteService $reporteService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly MonedaRepositoryInterface $monedaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        can('listar-iva-ventas');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $monedaQuery = $this->monedaRepository->all();
        $filtros = IvaVentasListadoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarPreferenciasEmpresa($request, $filtros, $empresaQuery);

        if ($request->boolean('consultar')) {
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_CLAVE, [
                'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            ]);
        }

        $consultado = false;
        $resultado = null;
        $filas = null;
        $filasVista = [];

        if ($request->boolean('consultar') && IvaVentasListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ini_set('memory_limit', '-1');
            ini_set('max_execution_time', '0');

            $resultado = $this->reporteService->generarDesdeFiltros($filtros);
            $perPage = max(10, min(500, (int) $request->input('per_page', 50)));
            $filas = $this->reporteService->paginarFilas(
                $resultado['filas'],
                $perPage,
                max(1, (int) $request->input('page', 1)),
            );
            $filasVista = $filas->items();
            $consultado = true;
        }

        $filtrosQuery = IvaVentasListadoFiltros::paraQueryString($filtros);
        if ($consultado) {
            $filtrosQuery['consultar'] = 1;
        }
        if ($request->has('per_page')) {
            $filtrosQuery['per_page'] = max(10, min(500, (int) $request->input('per_page', 50)));
        }
        if ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            $filas->appends($filtrosQuery);
        }

        return view('ventas.iva_ventas.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'empresa_query' => $empresaQuery,
            'moneda_query' => $monedaQuery,
            'orden_enum' => IvaVentasListadoFiltros::ORDENES,
            'subdiario_enum' => IvaVentasListadoFiltros::SUBDIARIOS,
            'consultado' => $consultado,
            'resultado' => $resultado,
            'filas' => $filas,
            'filasVista' => $filasVista,
            'periodo_texto' => IvaVentasListadoFiltros::formatearPeriodoTexto($filtros),
            'orden_texto' => IvaVentasListadoFiltros::formatearOrdenTexto($filtros),
            'subdiario_texto' => IvaVentasListadoFiltros::formatearSubdiarioTexto($filtros),
            'puede_ver_venta' => can('editar-factura', false) || can('listar-factura', false),
            'puede_ver_cliente' => can('editar-clientes', false) || can('listar-clientes', false),
            'puede_ver_puntoventa' => can('editar-puntos-de-venta', false) || can('listar-puntos-de-venta', false),
            'puede_ver_tipotransaccion' => can('editar-tipos-transacciones', false) || can('listar-tipos-transacciones', false),
            'puede_ver_cuenta' => can('listar-cuentas-contables', false) || can('editar-cuentas-contables', false),
        ]);
    }

    public function exportar(Request $request, string $formato)
    {
        can('listar-iva-ventas');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = IvaVentasListadoFiltros::resolverDesdeRequest($request);

        if (! IvaVentasListadoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('iva_ventas');
        }

        $resultado = $this->reporteService->generarDesdeFiltros($filtros);
        $filas = $resultado['filas'];

        switch (strtoupper($formato)) {
            case 'PDF':
                $view = \View::make('ventas.iva_ventas.listado', [
                    'resultado' => $resultado,
                    'filas' => $filas,
                    'filtros' => $filtros,
                    'para_pdf' => true,
                    'puede_ver_venta' => false,
                ])->render();

                return $this->descargarPdfListado($view, 'iva_ventas', 'legal', 'landscape');

            case 'EXCEL':
                return (new IvaVentasListadoExport($this->reporteService))
                    ->parametros($filtros, $resultado)
                    ->download('iva_ventas.xlsx');

            case 'CSV':
                return (new IvaVentasListadoExport($this->reporteService))
                    ->parametros($filtros, $resultado)
                    ->download('iva_ventas.csv', Excel::CSV);
        }

        return redirect()->route('iva_ventas', IvaVentasListadoFiltros::paraQueryString($filtros));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $empresaQuery
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function aplicarPreferenciasEmpresa(Request $request, array $filtros, $empresaQuery): array
    {
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
            $preferida = ReportePreferenciasUsuario::leerEmpresaId(self::PREFERENCIAS_CLAVE);
            if ($preferida !== null && $this->empresaRepository->empresaIdPermitida($preferida)) {
                $filtros['empresa_id'] = $preferida;
            } elseif ($empresaQuery->count() === 1) {
                $filtros['empresa_id'] = (int) $empresaQuery->first()->id;
            }
        }

        return $filtros;
    }

    private function descargarPdfListado(string $view, string $nombreBase, string $paper, string $orientation)
    {
        $path = storage_path('pdf/listados');
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            abort(500, 'No se pudo crear el directorio para el PDF.');
        }

        $nombrePdf = $nombreBase.'_'.date('Ymd_His').'_'.uniqid('', true);
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper($paper, $orientation);
        $pdf->loadHTML($view, 'UTF-8')->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf')->deleteFileAfterSend(true);
    }
}
