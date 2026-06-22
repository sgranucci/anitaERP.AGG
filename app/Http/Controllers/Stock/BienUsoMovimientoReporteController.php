<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\BienUsoMovimientoReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Contable\BienUso;
use App\Services\Stock\BienUsoMovimientoReporteService;
use App\Support\Stock\BienUsoMovimientoListadoFiltros;
use Illuminate\Http\Request;

class BienUsoMovimientoReporteController extends Controller
{
    public function __construct(
        private BienUsoMovimientoReporteService $service,
    ) {}

    public function index(Request $request)
    {
        can('listar-reporte-movimientos-bien-uso');

        $filtros = BienUsoMovimientoListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = BienUsoMovimientoListadoFiltros::paraQueryString($filtros);
        $consultado = $request->boolean('consultar');

        $filas = null;
        $totales = null;

        if ($consultado) {
            ini_set('memory_limit', '512M');
            $filas = $this->service->consultar($filtros, true, 25);
            $totales = $this->service->totales($filtros);
        }

        $bienesUso = BienUso::query()
            ->where('estado', 'A')
            ->orderBy('hostname')
            ->get(['id', 'codigo_inventario', 'hostname', 'modelo']);

        return view('stock.bien_uso_movimiento_reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'filas' => $filas,
            'totales' => $totales,
            'bienesUso' => $bienesUso,
            'efectos' => BienUsoMovimientoListadoFiltros::EFECTOS,
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
            'puede_ver_bien_uso' => can('editar-bien-uso', false) || can('listar-bien-uso', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-movimientos-bien-uso');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = BienUsoMovimientoListadoFiltros::resolverDesdeRequest($request);
        $filas = $this->service->consultar($filtros, false);
        $totales = $this->service->totales($filtros);
        $titulo = 'Movimientos por bien de uso';
        $subtitulo = $this->service->subtituloFiltros($filtros);

        switch ($formato) {
            case 'PDF':
                $view = \View::make('stock.bien_uso_movimiento_reporte.listado', compact(
                    'filas',
                    'totales',
                    'titulo',
                    'subtitulo',
                ))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'movimientos_bien_uso_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new BienUsoMovimientoReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('movimientos_bien_uso.xlsx');

            case 'CSV':
                return (new BienUsoMovimientoReporteExport($filas, $titulo, $subtitulo, $totales))
                    ->download('movimientos_bien_uso.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('reporte_movimientos_bien_uso', BienUsoMovimientoListadoFiltros::paraQueryString($filtros));
    }
}
