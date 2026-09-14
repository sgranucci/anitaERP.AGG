<?php

namespace App\Http\Controllers\Ventas\FacturacionLocal;

use App\Exports\Ventas\StockLocalInformeExport;
use App\Http\Controllers\Controller;
use App\Models\Ventas\LocalVenta;
use App\Services\Ventas\FacturacionLocal\StockLocalInformeService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\StockLocalInformeListadoFiltros;
use Illuminate\Http\Request;

/**
 * Informe de stock de locales (l-stocklocal.c).
 */
class StockLocalInformeController extends Controller
{
    public function __construct(
        private readonly StockLocalInformeService $service,
    ) {
    }

    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-informe-stock-local');

        $filtros = StockLocalInformeListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = StockLocalInformeListadoFiltros::paraQueryString($filtros);
        $consultado = $request->boolean('consultar');
        $locales = LocalVenta::query()->where('activo', true)->orderBy('codigo')->get();

        if (empty($filtros['local_venta_id']) && $locales->isNotEmpty()) {
            $filtros['local_venta_id'] = (int) $locales->first()->id;
        }

        $medidas = [];
        $filas = null;
        $totales = null;
        $subtitulo = '';
        $error = null;
        $depositoAnita = null;

        if ($consultado) {
            ini_set('memory_limit', '512M');
            set_time_limit(300);
            $resultado = $this->service->consultar($filtros, true, 40);
            if (! ($resultado['ok'] ?? false)) {
                $error = (string) ($resultado['error'] ?? 'No se pudo consultar el informe.');
            }
            $medidas = $resultado['medidas'] ?? [];
            $filas = $resultado['filas'] ?? null;
            $totales = $resultado['totales'] ?? null;
            $subtitulo = (string) ($resultado['subtitulo'] ?? '');
            $depositoAnita = $resultado['deposito_anita'] ?? null;
            if ($filas instanceof \Illuminate\Pagination\LengthAwarePaginator) {
                $filas->appends($filtrosQuery);
            }
        }

        return view('ventas.facturacion_local.stock_local_informe.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'locales' => $locales,
            'medidas' => $medidas,
            'filas' => $filas,
            'totales' => $totales,
            'subtitulo' => $subtitulo,
            'error' => $error,
            'depositoAnita' => $depositoAnita,
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        $this->assertFerli();
        can('listar-informe-stock-local');

        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $filtros = StockLocalInformeListadoFiltros::resolverDesdeRequest($request);
        $resultado = $this->service->consultar($filtros, false);
        if (! ($resultado['ok'] ?? false)) {
            return redirect()
                ->route('facturacion_local_informe_stock', StockLocalInformeListadoFiltros::paraQueryString($filtros))
                ->with('mensaje_error', $resultado['error'] ?? 'No se pudo exportar.');
        }

        $titulo = 'Stock del local';
        $subtitulo = (string) ($resultado['subtitulo'] ?? '');
        $filas = $resultado['filas'];
        $medidas = $resultado['medidas'] ?? [];
        $totales = $resultado['totales'] ?? [];

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('ventas.facturacion_local.stock_local_informe.listado', [
                    'filas' => $filas,
                    'medidas' => $medidas,
                    'totales' => $totales,
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'puede_ver_articulo' => false,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                $nombrePdf = 'stock_local_informe_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new StockLocalInformeExport(
                    $medidas,
                    $filas,
                    $titulo,
                    $subtitulo,
                    $totales,
                ))->download('stock_local_informe.xlsx');

            case 'CSV':
                return (new StockLocalInformeExport(
                    $medidas,
                    $filas,
                    $titulo,
                    $subtitulo,
                    $totales,
                ))->download('stock_local_informe.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route(
            'facturacion_local_informe_stock',
            StockLocalInformeListadoFiltros::paraQueryString($filtros)
        );
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }
}
