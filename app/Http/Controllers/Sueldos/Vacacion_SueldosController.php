<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\VacacionSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionVacacion_Sueldos;
use App\Repositories\Sueldos\Vacacion_SueldosRepositoryInterface;
use App\Support\Sueldos\VacacionSueldosListadoFiltros;
use Illuminate\Http\Request;

class Vacacion_SueldosController extends Controller
{
    private Vacacion_SueldosRepositoryInterface $repository;

    public function __construct(Vacacion_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-vacacion-sueldos');

        $filtros = VacacionSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeVacacion($filtros, true);

        return view('sueldos.vacacion.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => VacacionSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => VacacionSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-vacacion-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = VacacionSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeVacacion($filtros, false);

                $view = \View::make('sueldos.vacacion.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_vacacion_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(VacacionSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('vacacion_sueldos.xlsx');

            case 'CSV':
                return app(VacacionSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('vacacion_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_vacacion_sueldos', VacacionSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-vacacion-sueldos');

        return view('sueldos.vacacion.crear');
    }

    public function guardar(ValidacionVacacion_Sueldos $request)
    {
        can('crear-vacacion-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/vacacion')
            ->with('mensaje', 'Vacación creada con éxito');
    }

    public function editar($id)
    {
        can('editar-vacacion-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.vacacion.editar', compact('data'));
    }

    public function actualizar(ValidacionVacacion_Sueldos $request, $id)
    {
        can('actualizar-vacacion-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/vacacion')
            ->with('mensaje', 'Vacación actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-vacacion-sueldos');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function sincronizarAnita(Request $request)
    {
        can('actualizar-vacacion-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $resultado = $this->repository->sincronizarConAnita();

        if (! empty($resultado['errores'])) {
            return redirect('sueldos/vacacion')
                ->with('error', 'No se pudo sincronizar con Anita: '.implode(' | ', $resultado['errores']));
        }

        return redirect('sueldos/vacacion')->with(
            'mensaje',
            'Sincronización con Anita: '.$resultado['importados'].' importados, '
                .$resultado['omitidos'].' ya existentes (de '.$resultado['en_anita'].' en Anita), '
                .$resultado['periodos_importados'].' períodos.'
        );
    }
}
