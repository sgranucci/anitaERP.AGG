<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\ConceptoPerdidaListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConceptoPerdida;
use App\Models\Caja\ConceptoPerdida;
use App\Repositories\Caja\ConceptoPerdidaRepositoryInterface;
use App\Support\Caja\ConceptoPerdidaListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;

class ConceptoPerdidaController extends Controller
{
    public function __construct(
        private readonly ConceptoPerdidaRepositoryInterface $repository,
    ) {}

    public function index(Request $request)
    {
        can('listar-concepto-perdida');

        $filtros = ConceptoPerdidaListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeConceptoPerdida($filtros, true);

        return view('caja.concepto_perdida.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ConceptoPerdidaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ConceptoPerdidaListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-concepto-perdida');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ConceptoPerdidaListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeConceptoPerdida($filtros, false);
                $view = \View::make('caja.concepto_perdida.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_concepto_perdida';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ConceptoPerdidaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('concepto_perdida.xlsx');

            case 'CSV':
                return (new ConceptoPerdidaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('concepto_perdida.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('concepto_perdida', ConceptoPerdidaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-concepto-perdida');
        $data = new ConceptoPerdida();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ConceptoPerdidaListadoFiltros::class);

        return view('caja.concepto_perdida.crear', compact('data', 'filtrosQuery'));
    }

    public function guardar(ValidacionConceptoPerdida $request)
    {
        can('crear-concepto-perdida');
        $this->repository->create($request->validated());

        return redirect()->route('concepto_perdida', QueryRetornoListado::desdeRequest($request, ConceptoPerdidaListadoFiltros::class))
            ->with('mensaje', 'Concepto de pérdida creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-concepto-perdida');
        $data = $this->repository->findOrFail($id);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ConceptoPerdidaListadoFiltros::class);

        return view('caja.concepto_perdida.editar', compact('data', 'filtrosQuery'));
    }

    public function actualizar(ValidacionConceptoPerdida $request, $id)
    {
        can('actualizar-concepto-perdida');
        $this->repository->update($request->validated(), $id);

        return redirect()->route('concepto_perdida', QueryRetornoListado::desdeRequest($request, ConceptoPerdidaListadoFiltros::class))
            ->with('mensaje', 'Concepto de pérdida actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-concepto-perdida');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
