<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\AcumuladorSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionAcumulador_Sueldos;
use App\Repositories\Sueldos\Acumulador_SueldosRepositoryInterface;
use App\Support\Sueldos\AcumuladorSueldosListadoFiltros;
use Illuminate\Http\Request;

class Acumulador_SueldosController extends Controller
{
    private Acumulador_SueldosRepositoryInterface $repository;

    public function __construct(Acumulador_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-acumulador-sueldos');

        $filtros = AcumuladorSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeAcumulador($filtros, true);

        return view('sueldos.acumulador.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => AcumuladorSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => AcumuladorSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-acumulador-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = AcumuladorSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeAcumulador($filtros, false);

                $view = \View::make('sueldos.acumulador.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_acumulador_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(AcumuladorSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('acumulador_sueldos.xlsx');

            case 'CSV':
                return app(AcumuladorSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('acumulador_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_acumulador_sueldos', AcumuladorSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-acumulador-sueldos');

        return view('sueldos.acumulador.crear');
    }

    public function guardar(ValidacionAcumulador_Sueldos $request)
    {
        can('crear-acumulador-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/acumulador')
            ->with('mensaje', 'Acumulador creado con éxito');
    }

    public function editar($id)
    {
        can('editar-acumulador-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.acumulador.editar', compact('data'));
    }

    public function actualizar(ValidacionAcumulador_Sueldos $request, $id)
    {
        can('actualizar-acumulador-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/acumulador')
            ->with('mensaje', 'Acumulador actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-acumulador-sueldos');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
