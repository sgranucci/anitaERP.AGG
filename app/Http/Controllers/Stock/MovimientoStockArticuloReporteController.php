<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\MovimientoStockArticuloReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Reportes\DompdfListadoSupport;
use App\Support\Stock\MovimientoStockArticuloReporteFiltros;
use App\Support\Stock\MovimientoStockArticuloReporteSupport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MovimientoStockArticuloReporteController extends Controller
{
    public function index(Request $request)
    {
        $this->assertFerli();
        can('listar-reporte-movimientos-stock-articulo');

        $filtros = MovimientoStockArticuloReporteFiltros::resolverDesdeRequest($request);
        if (! $request->boolean('consultar')) {
            $hoy = date('Y-m-d');
            $filtros['fecha_desde'] = $filtros['fecha_desde'] !== '' ? $filtros['fecha_desde'] : $hoy;
            $filtros['fecha_hasta'] = $filtros['fecha_hasta'] !== '' ? $filtros['fecha_hasta'] : $hoy;
        }

        $filtrosQuery = MovimientoStockArticuloReporteFiltros::paraQueryString($filtros);
        $consultado = $request->boolean('consultar');
        $filas = null;
        $totales = null;
        $error = null;

        if ($consultado) {
            try {
                ini_set('memory_limit', '512M');
                $resultado = MovimientoStockArticuloReporteSupport::consultar($filtros);
                $totales = $resultado['totales'];
                $page = max(1, (int) $request->query('page', 1));
                $porPagina = 40;
                $slice = array_slice($resultado['filas'], ($page - 1) * $porPagina, $porPagina);
                $filas = new LengthAwarePaginator(
                    $slice,
                    count($resultado['filas']),
                    $porPagina,
                    $page,
                    ['path' => $request->url(), 'query' => $filtrosQuery]
                );
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
                $consultado = false;
            }
        }

        return view('stock.movimiento_stock_articulo_reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'filas' => $filas,
            'totales' => $totales,
            'error' => $error,
            'subtitulo' => MovimientoStockArticuloReporteFiltros::subtitulo($filtros),
            'descripcion_desde_sku' => $this->descripcionSku((string) ($filtros['desde_sku'] ?? '')),
            'descripcion_hasta_sku' => $this->descripcionSku((string) ($filtros['hasta_sku'] ?? '')),
            'descripcion_desde_deposito' => $this->descripcionDeposito((string) ($filtros['desde_deposito'] ?? '')),
            'descripcion_hasta_deposito' => $this->descripcionDeposito((string) ($filtros['hasta_deposito'] ?? '')),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        $this->assertFerli();
        can('listar-reporte-movimientos-stock-articulo');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = MovimientoStockArticuloReporteFiltros::resolverDesdeRequest($request);
        try {
            $resultado = MovimientoStockArticuloReporteSupport::consultar($filtros);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('reporte_movimientos_stock_articulo', MovimientoStockArticuloReporteFiltros::paraQueryString($filtros))
                ->with('mensaje', $e->getMessage());
        }

        $titulo = 'Movimientos de stock';
        $subtitulo = MovimientoStockArticuloReporteFiltros::subtitulo($filtros);
        $formato = strtoupper((string) $formato);

        if ($formato === 'PDF') {
            $html = view('stock.movimiento_stock_articulo_reporte.listado', [
                'filas' => $resultado['filas'],
                'totales' => $resultado['totales'],
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'puede_ver_articulo' => false,
            ])->render();
            $ruta = storage_path('pdf/listados/movimientos_stock_articulo.pdf');
            if (! is_dir(dirname($ruta))) {
                mkdir(dirname($ruta), 0775, true);
            }
            DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                'titulo_corto' => 'Movimientos de stock',
            ]);

            return response()->download($ruta);
        }

        if ($formato === 'EXCEL' || $formato === 'CSV') {
            $export = new MovimientoStockArticuloReporteExport(
                $resultado['filas'],
                $titulo,
                $subtitulo,
                $resultado['totales'],
            );
            if ($formato === 'CSV') {
                return $export->download('movimientos_stock_articulo.csv', \Maatwebsite\Excel\Excel::CSV);
            }

            return $export->download('movimientos_stock_articulo.xlsx');
        }

        return redirect()->route(
            'reporte_movimientos_stock_articulo',
            MovimientoStockArticuloReporteFiltros::paraQueryString($filtros)
        );
    }

    private function assertFerli(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            abort(404);
        }
    }

    private function descripcionSku(string $sku): string
    {
        $sku = ltrim(trim($sku), '0');
        if ($sku === '') {
            return '';
        }
        $desc = Articulo::query()->where('sku', $sku)->value('descripcion');

        return trim((string) $desc);
    }

    private function descripcionDeposito(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }
        $nombre = Depmae::query()->where('codigo', $codigo)->value('nombre');
        if ($nombre === null && ctype_digit($codigo)) {
            $nombre = Depmae::query()->where('codigo', ltrim($codigo, '0'))->value('nombre');
        }

        return trim((string) $nombre);
    }
}
