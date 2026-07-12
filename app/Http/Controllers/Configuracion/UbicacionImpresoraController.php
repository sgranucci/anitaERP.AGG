<?php

namespace App\Http\Controllers\Configuracion;

use App\Exports\Configuracion\UbicacionImpresoraListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionUbicacionImpresora;
use App\Models\Configuracion\UbicacionImpresora;
use App\Repositories\Configuracion\UbicacionImpresoraRepositoryInterface;
use App\Support\Configuracion\UbicacionImpresoraListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;

class UbicacionImpresoraController extends Controller
{
    public function __construct(
        private readonly UbicacionImpresoraRepositoryInterface $repository,
    ) {}

    public function index(Request $request)
    {
        can('listar-ubicacion-impresora');

        $filtros = UbicacionImpresoraListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeUbicacionImpresora($filtros, true);

        return view('configuracion.ubicacion_impresora.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => UbicacionImpresoraListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => UbicacionImpresoraListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-ubicacion-impresora');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = UbicacionImpresoraListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeUbicacionImpresora($filtros, false);
                $view = \View::make('configuracion.ubicacion_impresora.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_ubicacion_impresora';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new UbicacionImpresoraListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('ubicacion_impresora.xlsx');

            case 'CSV':
                return (new UbicacionImpresoraListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('ubicacion_impresora.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('ubicacion_impresora', UbicacionImpresoraListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-ubicacion-impresora');
        $data = new UbicacionImpresora();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, UbicacionImpresoraListadoFiltros::class);

        return view('configuracion.ubicacion_impresora.crear', compact('data', 'filtrosQuery'));
    }

    public function guardar(ValidacionUbicacionImpresora $request)
    {
        can('crear-ubicacion-impresora');
        $this->repository->create($request->all());

        return redirect()->route('ubicacion_impresora', QueryRetornoListado::desdeRequest($request, UbicacionImpresoraListadoFiltros::class))
            ->with('mensaje', 'Ubicación de impresora creada con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-ubicacion-impresora');
        $data = $this->repository->findOrFail($id);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, UbicacionImpresoraListadoFiltros::class);

        return view('configuracion.ubicacion_impresora.editar', compact('data', 'filtrosQuery'));
    }

    public function actualizar(ValidacionUbicacionImpresora $request, $id)
    {
        can('actualizar-ubicacion-impresora');
        $this->repository->update($request->all(), $id);

        return redirect()->route('ubicacion_impresora', QueryRetornoListado::desdeRequest($request, UbicacionImpresoraListadoFiltros::class))
            ->with('mensaje', 'Ubicación de impresora actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-ubicacion-impresora');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
