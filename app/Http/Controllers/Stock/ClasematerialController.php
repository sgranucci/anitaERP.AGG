<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\ClasematerialListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionClasematerial;
use App\Repositories\Stock\ClasematerialRepositoryInterface;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Stock\ClasematerialListadoFiltros;
use App\Support\Stock\InterformingSifabSupport;
use Illuminate\Http\Request;

class ClasematerialController extends Controller
{
    public function __construct(
        private ClasematerialRepositoryInterface $repository,
    ) {
    }

    public function index(Request $request)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('listar-clases-material');

        $filtros = ClasematerialListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeClasematerial($filtros, true);

        return view('stock.clasematerial.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ClasematerialListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ClasematerialListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('listar-clases-material');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ClasematerialListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeClasematerial($filtros, false);
                $view = \View::make('stock.clasematerial.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_clasematerial';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new ClasematerialListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('clasematerial.xlsx');

            case 'CSV':
                return (new ClasematerialListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('clasematerial.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('clasematerial', ClasematerialListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-clases-material');
        $data = new \App\Models\Stock\Clasematerial();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ClasematerialListadoFiltros::class);

        return view('stock.clasematerial.crear', compact('data', 'filtrosQuery'));
    }

    public function guardar(ValidacionClasematerial $request)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-clases-material');
        $this->repository->create($this->payload($request));

        return redirect()->route('clasematerial')->with('mensaje', 'Clase de material creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('editar-clases-material');
        $data = $this->repository->findOrFail($id);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ClasematerialListadoFiltros::class);

        return view('stock.clasematerial.editar', compact('data', 'filtrosQuery'));
    }

    public function actualizar(ValidacionClasematerial $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('actualizar-clases-material');
        $this->repository->update($this->payload($request), $id);

        return redirect()->route('clasematerial')->with('mensaje', 'Clase de material actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('borrar-clases-material');
        $this->repository->delete($id);
        if ($request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('clasematerial')->with('mensaje', 'Clase de material eliminado con éxito');
    }

    private function payload(ValidacionClasematerial $request): array
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
