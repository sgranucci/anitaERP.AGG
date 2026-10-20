<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\ControlInventarioItemsReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\Categoria;
use App\Models\Stock\Depmae;
use App\Models\Stock\Subcategoria;
use App\Models\Stock\Tipoarticulo;
use App\Models\Stock\Usoarticulo;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Stock\ControlInventarioItemsReporteService;
use App\Support\Stock\ControlInventarioItemsListadoFiltros;
use Illuminate\Http\Request;

class ControlInventarioItemsReporteController extends Controller
{
    public function __construct(
        private ControlInventarioItemsReporteService $service,
        private EmpresaRepositoryInterface $empresaRepository,
        private ArticuloQueryInterface $articuloQuery,
    ) {}

    public function index(Request $request)
    {
        can('listar-reporte-control-inventario-items');

        $filtros = ControlInventarioItemsListadoFiltros::resolverDesdeRequest($request);
        $filtrosQuery = ControlInventarioItemsListadoFiltros::paraQueryString($filtros);
        $consultado = $request->boolean('consultar');

        $filas = null;
        $totales = null;
        $avisos = [];

        if ($consultado) {
            ini_set('memory_limit', '512M');
            $resultado = $this->service->consultar($filtros, true, 25);
            $filas = $resultado['filas'];
            $totales = $resultado['totales'];
            $avisos = $resultado['avisos'];
        }

        return view('stock.control_inventario_items_reporte.index', [
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'consultado' => $consultado,
            'filas' => $filas,
            'totales' => $totales,
            'avisos' => $avisos,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'articulo_query' => $this->opcionesArticulo(),
            'categoria_query' => $this->opcionesCategoria(),
            'subcategoria_query' => $this->opcionesSubcategoria(),
            'usoarticulo_query' => $this->opcionesUsoarticulo(),
            'tipoarticulo_query' => $this->opcionesTipoarticulo(),
            'descripcion_desde_deposito' => $this->descripcionDeposito((string) ($filtros['desde_deposito'] ?? '')),
            'descripcion_hasta_deposito' => $this->descripcionDeposito((string) ($filtros['hasta_deposito'] ?? '')),
            'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
        ]);
    }

    public function exportar(Request $request, ?string $formato = null)
    {
        can('listar-reporte-control-inventario-items');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ControlInventarioItemsListadoFiltros::resolverDesdeRequest($request);
        $resultado = $this->service->consultar($filtros, false);
        $titulo = 'Control de inventario de items';
        $subtitulo = $this->service->subtituloFiltros($filtros);

        switch (strtoupper((string) $formato)) {
            case 'PDF':
                $view = \View::make('stock.control_inventario_items_reporte.listado', [
                    'filas' => $resultado['filas'],
                    'totales' => $resultado['totales'],
                    'avisos' => $resultado['avisos'],
                    'titulo' => $titulo,
                    'subtitulo' => $subtitulo,
                    'puede_ver_articulo' => false,
                ])->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombrePdf = 'control_inventario_items_'.date('Ymd_His');
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ControlInventarioItemsReporteExport(
                    $resultado['filas'],
                    $titulo,
                    $subtitulo,
                    $resultado['totales'],
                    $resultado['avisos'],
                ))->download('control_inventario_items.xlsx');

            case 'CSV':
                return (new ControlInventarioItemsReporteExport(
                    $resultado['filas'],
                    $titulo,
                    $subtitulo,
                    $resultado['totales'],
                    $resultado['avisos'],
                ))->download('control_inventario_items.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('reporte_control_inventario_items', ControlInventarioItemsListadoFiltros::paraQueryString($filtros));
    }

    private function opcionesArticulo()
    {
        $query = $this->articuloQuery->allQuery(['id', 'sku', 'descripcion'], 'sku');
        $query->prepend((object) ['id' => ControlInventarioItemsListadoFiltros::ID_PRIMERO, 'sku' => '', 'descripcion' => 'Primero']);
        $query->push((object) ['id' => ControlInventarioItemsListadoFiltros::ID_ULTIMO, 'sku' => '', 'descripcion' => 'Último']);

        return $query;
    }

    private function opcionesCategoria()
    {
        $query = Categoria::query()->orderBy('nombre')->get(['id', 'nombre']);
        $query->prepend((object) ['id' => ControlInventarioItemsListadoFiltros::ID_PRIMERO, 'nombre' => 'Primero']);
        $query->push((object) ['id' => ControlInventarioItemsListadoFiltros::ID_ULTIMO, 'nombre' => 'Último']);

        return $query;
    }

    private function opcionesSubcategoria()
    {
        $query = Subcategoria::query()->orderBy('nombre')->get(['id', 'nombre']);
        $query->prepend((object) ['id' => ControlInventarioItemsListadoFiltros::ID_PRIMERO, 'nombre' => 'Primero']);
        $query->push((object) ['id' => ControlInventarioItemsListadoFiltros::ID_ULTIMO, 'nombre' => 'Último']);

        return $query;
    }

    private function opcionesUsoarticulo()
    {
        $query = Usoarticulo::query()->orderBy('nombre')->get(['id', 'nombre']);
        $query->prepend((object) ['id' => ControlInventarioItemsListadoFiltros::ID_PRIMERO, 'nombre' => 'Primero']);
        $query->push((object) ['id' => ControlInventarioItemsListadoFiltros::ID_ULTIMO, 'nombre' => 'Último']);

        return $query;
    }

    private function opcionesTipoarticulo()
    {
        $query = Tipoarticulo::query()->orderBy('nombre')->get(['id', 'nombre']);
        $query->prepend((object) ['id' => ControlInventarioItemsListadoFiltros::ID_PRIMERO, 'nombre' => 'Primero']);
        $query->push((object) ['id' => ControlInventarioItemsListadoFiltros::ID_ULTIMO, 'nombre' => 'Último']);

        return $query;
    }

    private function descripcionDeposito(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return '';
        }
        $nombre = Depmae::query()->where('codigo', $codigo)->value('nombre');
        if ($nombre === null && ctype_digit($codigo)) {
            $nombre = Depmae::query()->where('codigo', ltrim($codigo, '0') ?: '0')->value('nombre');
        }

        return trim((string) $nombre);
    }
}
