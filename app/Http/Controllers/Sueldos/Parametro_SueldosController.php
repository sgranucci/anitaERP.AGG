<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\ParametroSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionParametro_Sueldos;
use App\Repositories\Sueldos\Parametro_SueldosRepositoryInterface;
use App\Support\Sueldos\ParametroSueldosListadoFiltros;
use Illuminate\Http\Request;

class Parametro_SueldosController extends Controller
{
    private Parametro_SueldosRepositoryInterface $repository;

    public function __construct(Parametro_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-parametro-sueldos');

        $filtros = ParametroSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeParametro($filtros, true);

        return view('sueldos.parametro.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ParametroSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ParametroSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-parametro-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ParametroSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeParametro($filtros, false);

                $view = \View::make('sueldos.parametro.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_parametro_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(ParametroSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('parametro_sueldos.xlsx');

            case 'CSV':
                return app(ParametroSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('parametro_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_parametro_sueldos', ParametroSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-parametro-sueldos');

        return view('sueldos.parametro.crear');
    }

    public function guardar(ValidacionParametro_Sueldos $request)
    {
        can('crear-parametro-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/parametro')
            ->with('mensaje', 'Parámetro creado con éxito');
    }

    public function editar($id)
    {
        can('editar-parametro-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.parametro.editar', compact('data'));
    }

    public function actualizar(ValidacionParametro_Sueldos $request, $id)
    {
        can('actualizar-parametro-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/parametro')
            ->with('mensaje', 'Parámetro actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-parametro-sueldos');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
