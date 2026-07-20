<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\LugartrabajoSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionLugartrabajo_Sueldos;
use App\Repositories\Sueldos\Lugartrabajo_SueldosRepositoryInterface;
use App\Support\Sueldos\LugartrabajoSueldosListadoFiltros;
use Illuminate\Http\Request;

class Lugartrabajo_SueldosController extends Controller
{
    private Lugartrabajo_SueldosRepositoryInterface $repository;

    public function __construct(Lugartrabajo_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-lugartrabajo-sueldos');

        $filtros = LugartrabajoSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeLugartrabajo($filtros, true);

        return view('sueldos.lugartrabajo.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => LugartrabajoSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => LugartrabajoSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-lugartrabajo-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = LugartrabajoSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeLugartrabajo($filtros, false);

                $view = \View::make('sueldos.lugartrabajo.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_lugartrabajo_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(LugartrabajoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('lugartrabajo_sueldos.xlsx');

            case 'CSV':
                return app(LugartrabajoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('lugartrabajo_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_lugartrabajo_sueldos', LugartrabajoSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-lugartrabajo-sueldos');

        return view('sueldos.lugartrabajo.crear');
    }

    public function guardar(ValidacionLugartrabajo_Sueldos $request)
    {
        can('crear-lugartrabajo-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/lugartrabajo')
            ->with('mensaje', 'Lugar de trabajo creado con éxito');
    }

    public function editar($id)
    {
        can('editar-lugartrabajo-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.lugartrabajo.editar', compact('data'));
    }

    public function actualizar(ValidacionLugartrabajo_Sueldos $request, $id)
    {
        can('actualizar-lugartrabajo-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/lugartrabajo')
            ->with('mensaje', 'Lugar de trabajo actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-lugartrabajo-sueldos');

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
        can('actualizar-lugartrabajo-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $resultado = $this->repository->sincronizarConAnita();

        if (! empty($resultado['errores'])) {
            return redirect('sueldos/lugartrabajo')
                ->with('error', 'No se pudo sincronizar con Anita: '.implode(' | ', $resultado['errores']));
        }

        return redirect('sueldos/lugartrabajo')->with(
            'mensaje',
            'Sincronización con Anita: '.$resultado['importados'].' importados, '
                .$resultado['omitidos'].' ya existentes (de '.$resultado['en_anita'].' en Anita).'
        );
    }
}
