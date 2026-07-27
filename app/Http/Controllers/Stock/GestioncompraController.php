<?php

namespace App\Http\Controllers\Stock;

use App\Exports\Stock\GestioncompraListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionGestioncompra;
use App\Repositories\Stock\GestioncompraRepositoryInterface;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Stock\GestioncompraListadoFiltros;
use App\Support\Stock\InterformingSifabSupport;
use Illuminate\Http\Request;

class GestioncompraController extends Controller
{
    public function __construct(
        private GestioncompraRepositoryInterface $repository,
    ) {
    }

    public function index(Request $request)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('listar-gestiones-compra');

        $filtros = GestioncompraListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeGestioncompra($filtros, true);

        return view('stock.gestioncompra.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => GestioncompraListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => GestioncompraListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('listar-gestiones-compra');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = GestioncompraListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeGestioncompra($filtros, false);
                $view = \View::make('stock.gestioncompra.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_gestioncompra';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return (new GestioncompraListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('gestioncompra.xlsx');

            case 'CSV':
                return (new GestioncompraListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('gestioncompra.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('gestioncompra', GestioncompraListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-gestiones-compra');
        $data = new \App\Models\Stock\Gestioncompra();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, GestioncompraListadoFiltros::class);

        return view('stock.gestioncompra.crear', compact('data', 'filtrosQuery'));
    }

    public function guardar(ValidacionGestioncompra $request)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('crear-gestiones-compra');
        $this->repository->create($this->payload($request));

        return redirect()->route('gestioncompra')->with('mensaje', 'Gestión de compra creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('editar-gestiones-compra');
        $data = $this->repository->findOrFail($id);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, GestioncompraListadoFiltros::class);

        return view('stock.gestioncompra.editar', compact('data', 'filtrosQuery'));
    }

    public function actualizar(ValidacionGestioncompra $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('actualizar-gestiones-compra');
        $this->repository->update($this->payload($request), $id);

        return redirect()->route('gestioncompra')->with('mensaje', 'Gestión de compra actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        InterformingSifabSupport::abortSiNoInterforming();
        can('borrar-gestiones-compra');
        $this->repository->delete($id);
        if ($request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('gestioncompra')->with('mensaje', 'Gestión de compra eliminado con éxito');
    }

    private function payload(ValidacionGestioncompra $request): array
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
