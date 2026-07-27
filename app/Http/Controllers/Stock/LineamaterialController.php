<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\LineamaterialListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionLineamaterial;
use App\Repositories\Stock\LineamaterialRepositoryInterface;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Stock\LineamaterialListadoFiltros;
use App\Support\Stock\InterformingSifabSupport;
use Illuminate\Http\Request;

class LineamaterialController extends Controller
{
    public function __construct(
        private LineamaterialRepositoryInterface $repository,
    ) {
    }

    public function index(Request $request)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('listar-lineas-material');

        $filtros = LineamaterialListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeLineamaterial($filtros, true);

        return view('stock.lineamaterial.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => LineamaterialListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => LineamaterialListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('listar-lineas-material');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = LineamaterialListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeLineamaterial($filtros, false);
                $view = \View::make('stock.lineamaterial.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_lineamaterial';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new LineamaterialListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('lineamaterial.xlsx');

            case 'CSV':
                return (new LineamaterialListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('lineamaterial.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('lineamaterial', LineamaterialListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-lineas-material');
        $data = new \App\Models\Stock\Lineamaterial();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, LineamaterialListadoFiltros::class);

        return view('stock.lineamaterial.crear', compact('data', 'filtrosQuery'));
    }

    public function guardar(ValidacionLineamaterial $request)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-lineas-material');
        $this->repository->create($this->payload($request));

        return redirect()->route('lineamaterial')->with('mensaje', 'Línea de material creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('editar-lineas-material');
        $data = $this->repository->findOrFail($id);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, LineamaterialListadoFiltros::class);

        return view('stock.lineamaterial.editar', compact('data', 'filtrosQuery'));
    }

    public function actualizar(ValidacionLineamaterial $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('actualizar-lineas-material');
        $this->repository->update($this->payload($request), $id);

        return redirect()->route('lineamaterial')->with('mensaje', 'Línea de material actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('borrar-lineas-material');
        $this->repository->delete($id);
        if ($request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('lineamaterial')->with('mensaje', 'Línea de material eliminado con éxito');
    }

    private function payload(ValidacionLineamaterial $request): array
    {
        return [
            'codigo_interno_sifab' => $request->input('codigo_interno_sifab') !== null && $request->input('codigo_interno_sifab') !== ''
                ? (int) $request->input('codigo_interno_sifab') : null,
            'codigo' => $request->input('codigo'),
            'nombre' => $request->input('nombre'),
            'habilitado' => $request->boolean('habilitado', true),
        ];
    }
}
