<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\ParteUnicaBajaReporteExport;
use App\Http\Controllers\Controller;
use App\Services\Stock\ParteUnicaBajaReporteService;
use App\Support\Stock\ParteUnicaBajaReporteFiltros;
use Illuminate\Http\Request;

class ParteUnicaBajaReporteController extends Controller
{
    public function __construct(
        private ParteUnicaBajaReporteService $service,
    ) {}

    public function index(Request $request)
    {
        can('listar-reporte-baja-npu');

        $filtros = ParteUnicaBajaReporteFiltros::resolverDesdeRequest($request);
        $filtrosQuery = ParteUnicaBajaReporteFiltros::paraQueryString($filtros);
        $consultado = $request->boolean('consultar');

        $filas = null;
        $totales = null;

        if ($consultado) {
            ini_set('memory_limit', '512M');
            $filas = $this->service->consultar($filtros, true, 25);
            $totales = $this->service->totales($filtros);
        }

        return view('stock.parte_unica_baja_reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'filas' => $filas,
            'totales' => $totales,
            'estados' => ParteUnicaBajaReporteFiltros::ESTADOS,
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_movimiento' => can('listar-movimientos-de-stock', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-baja-npu');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ParteUnicaBajaReporteFiltros::resolverDesdeRequest($request);
        $filas = $this->service->consultar($filtros, false);
        $totales = $this->service->totales($filtros);
        $titulo = 'Números de parte única — consulta de bajas';
        $subtitulo = $this->service->subtituloFiltros($filtros);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('stock.parte_unica_baja_reporte.listado', compact(
                    'filas',
                    'totales',
                    'titulo',
                    'subtitulo',
                ))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'partes_unicas_baja_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ParteUnicaBajaReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('partes_unicas_baja.xlsx');

            case 'CSV':
                return (new ParteUnicaBajaReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('partes_unicas_baja.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('reporte_baja_npu', ParteUnicaBajaReporteFiltros::paraQueryString($filtros));
    }
}
