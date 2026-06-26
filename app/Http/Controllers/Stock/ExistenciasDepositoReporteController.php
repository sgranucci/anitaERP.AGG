<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\ExistenciasDepositoReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Stock\Categoria;
use App\Models\Stock\Tipoarticulo;
use App\Models\Stock\Usoarticulo;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Stock\ExistenciasDepositoReporteService;
use App\Support\Stock\ExistenciasDepositoListadoFiltros;
use Illuminate\Http\Request;

class ExistenciasDepositoReporteController extends Controller
{
  public function __construct(
    private ExistenciasDepositoReporteService $service,
    private EmpresaRepositoryInterface $empresaRepository,
    private ArticuloQueryInterface $articuloQuery,
  ) {}

  public function index(Request $request)
  {
    can('listar-reporte-existencias-deposito');

    $filtros = ExistenciasDepositoListadoFiltros::resolverDesdeRequest($request);
    $filtrosQuery = ExistenciasDepositoListadoFiltros::paraQueryString($filtros);
    $consultado = $request->boolean('consultar');

    $depositos = null;
    $filas = null;
    $totales = null;

    if ($consultado) {
      ini_set('memory_limit', '512M');
      $resultado = $this->service->consultar($filtros, true, 25);
      $depositos = $resultado['depositos'];
      $filas = $resultado['filas'];
      $totales = $resultado['totales'];
    }

    return view('stock.existencias_deposito_reporte.index', [
      'filtros' => $filtros,
      'filtrosQuery' => $filtrosQuery,
      'consultado' => $consultado,
      'depositos' => $depositos,
      'filas' => $filas,
      'totales' => $totales,
      'empresa_query' => $this->empresaRepository->allFiltrado(),
      'articulo_query' => $this->opcionesArticulo(),
      'categoria_query' => $this->opcionesCategoria(),
      'usoarticulo_query' => $this->opcionesUsoarticulo(),
      'tipoarticulo_query' => $this->opcionesTipoarticulo(),
      'puede_ver_articulo' => can('editar-articulos', false) || can('listar-articulos', false),
    ]);
  }

  public function exportar(Request $request, ?string $formato = null)
  {
    can('listar-reporte-existencias-deposito');

    ini_set('memory_limit', '-1');
    ini_set('max_execution_time', '0');

    $filtros = ExistenciasDepositoListadoFiltros::resolverDesdeRequest($request);
    $resultado = $this->service->consultar($filtros, false);
    $titulo = 'Existencias por depósito';
    $subtitulo = $this->service->subtituloFiltros($filtros);

    switch ($formato) {
      case 'PDF':
        $view = \View::make('stock.existencias_deposito_reporte.listado', [
          'depositos' => $resultado['depositos'],
          'filas' => $resultado['filas'],
          'totales' => $resultado['totales'],
          'titulo' => $titulo,
          'subtitulo' => $subtitulo,
          'puede_ver_articulo' => false,
        ])->render();
        $path = storage_path('pdf/listados');
        $nombrePdf = 'existencias_deposito_'.date('Ymd_His');
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf');

      case 'EXCEL':
        return (new ExistenciasDepositoReporteExport(
          $resultado['depositos'],
          $resultado['filas'],
          $titulo,
          $subtitulo,
          $resultado['totales'],
        ))->download('existencias_deposito.xlsx');

      case 'CSV':
        return (new ExistenciasDepositoReporteExport(
          $resultado['depositos'],
          $resultado['filas'],
          $titulo,
          $subtitulo,
          $resultado['totales'],
        ))->download('existencias_deposito.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    return redirect()->route('reporte_existencias_deposito', ExistenciasDepositoListadoFiltros::paraQueryString($filtros));
  }

  private function opcionesArticulo()
  {
    $query = $this->articuloQuery->allQuery(['id', 'sku', 'descripcion'], 'sku');
    $query->prepend((object) ['id' => ExistenciasDepositoListadoFiltros::ID_PRIMERO, 'sku' => '', 'descripcion' => 'Primero']);
    $query->push((object) ['id' => ExistenciasDepositoListadoFiltros::ID_ULTIMO, 'sku' => '', 'descripcion' => 'Último']);

    return $query;
  }

  private function opcionesCategoria()
  {
    $query = Categoria::query()->orderBy('nombre')->get(['id', 'nombre']);
    $query->prepend((object) ['id' => ExistenciasDepositoListadoFiltros::ID_PRIMERO, 'nombre' => 'Primero']);
    $query->push((object) ['id' => ExistenciasDepositoListadoFiltros::ID_ULTIMO, 'nombre' => 'Último']);

    return $query;
  }

  private function opcionesUsoarticulo()
  {
    $query = Usoarticulo::query()->orderBy('nombre')->get(['id', 'nombre']);
    $query->prepend((object) ['id' => ExistenciasDepositoListadoFiltros::ID_PRIMERO, 'nombre' => 'Primero']);
    $query->push((object) ['id' => ExistenciasDepositoListadoFiltros::ID_ULTIMO, 'nombre' => 'Último']);

    return $query;
  }

  private function opcionesTipoarticulo()
  {
    $query = Tipoarticulo::query()->orderBy('nombre')->get(['id', 'nombre']);
    $query->prepend((object) ['id' => ExistenciasDepositoListadoFiltros::ID_PRIMERO, 'nombre' => 'Primero']);
    $query->push((object) ['id' => ExistenciasDepositoListadoFiltros::ID_ULTIMO, 'nombre' => 'Último']);

    return $query;
  }
}
