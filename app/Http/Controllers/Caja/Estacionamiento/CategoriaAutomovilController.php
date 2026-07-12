<?php

namespace App\Http\Controllers\Caja\Estacionamiento;

use App\Exports\Caja\Estacionamiento\CategoriaAutomovilListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionEstacionamientoCategoriaAutomovil;
use App\Models\Caja\Estacionamiento\CategoriaAutomovil;
use App\Repositories\Caja\Estacionamiento\CategoriaAutomovilRepositoryInterface;
use App\Support\Caja\Estacionamiento\CategoriaAutomovilListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;

class CategoriaAutomovilController extends Controller
{
    public function __construct(
        private readonly CategoriaAutomovilRepositoryInterface $repository,
    ) {}

    public function index(Request $request)
    {
        can('listar-estacionamiento-categoria-automovil');

        $filtros = CategoriaAutomovilListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeCategoriaAutomovil($filtros, true);

        return view('caja.estacionamiento.categoria_automovil.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => CategoriaAutomovilListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => CategoriaAutomovilListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-estacionamiento-categoria-automovil');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = CategoriaAutomovilListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeCategoriaAutomovil($filtros, false);
                $view = \View::make('caja.estacionamiento.categoria_automovil.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_estacionamiento_categoria_automovil';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new CategoriaAutomovilListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('estacionamiento_categorias_automovil.xlsx');

            case 'CSV':
                return (new CategoriaAutomovilListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('estacionamiento_categorias_automovil.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('estacionamiento_categoria_automovil', CategoriaAutomovilListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-estacionamiento-categoria-automovil');
        $data = new CategoriaAutomovil();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, CategoriaAutomovilListadoFiltros::class);

        return view('caja.estacionamiento.categoria_automovil.crear', compact('data', 'filtrosQuery'));
    }

    public function guardar(ValidacionEstacionamientoCategoriaAutomovil $request)
    {
        can('crear-estacionamiento-categoria-automovil');
        $this->repository->create($request->all());

        return redirect()->route('estacionamiento_categoria_automovil', QueryRetornoListado::desdeRequest($request, CategoriaAutomovilListadoFiltros::class))
            ->with('mensaje', 'Categoría de automóvil creada con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-estacionamiento-categoria-automovil');
        $data = $this->repository->findOrFail($id);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, CategoriaAutomovilListadoFiltros::class);

        return view('caja.estacionamiento.categoria_automovil.editar', compact('data', 'filtrosQuery'));
    }

    public function actualizar(ValidacionEstacionamientoCategoriaAutomovil $request, $id)
    {
        can('actualizar-estacionamiento-categoria-automovil');
        $this->repository->update($request->all(), $id);

        return redirect()->route('estacionamiento_categoria_automovil', QueryRetornoListado::desdeRequest($request, CategoriaAutomovilListadoFiltros::class))
            ->with('mensaje', 'Categoría de automóvil actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-estacionamiento-categoria-automovil');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
