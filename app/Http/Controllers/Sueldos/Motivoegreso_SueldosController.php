<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\MotivoegresoSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionMotivoegreso_Sueldos;
use App\Repositories\Sueldos\Motivoegreso_SueldosRepositoryInterface;
use App\Support\Sueldos\MotivoegresoSueldosListadoFiltros;
use Illuminate\Http\Request;

class Motivoegreso_SueldosController extends Controller
{
    private Motivoegreso_SueldosRepositoryInterface $repository;

    public function __construct(Motivoegreso_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-motivoegreso-sueldos');

        $filtros = MotivoegresoSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeMotivoegreso($filtros, true);

        return view('sueldos.motivoegreso.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => MotivoegresoSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => MotivoegresoSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-motivoegreso-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = MotivoegresoSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeMotivoegreso($filtros, false);

                $view = \View::make('sueldos.motivoegreso.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_motivoegreso_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(MotivoegresoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('motivoegreso_sueldos.xlsx');

            case 'CSV':
                return app(MotivoegresoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('motivoegreso_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_motivoegreso_sueldos', MotivoegresoSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-motivoegreso-sueldos');

        return view('sueldos.motivoegreso.crear');
    }

    public function guardar(ValidacionMotivoegreso_Sueldos $request)
    {
        can('crear-motivoegreso-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/motivoegreso')
            ->with('mensaje', 'Motivo de egreso creado con éxito');
    }

    public function editar($id)
    {
        can('editar-motivoegreso-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.motivoegreso.editar', compact('data'));
    }

    public function actualizar(ValidacionMotivoegreso_Sueldos $request, $id)
    {
        can('actualizar-motivoegreso-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/motivoegreso')
            ->with('mensaje', 'Motivo de egreso actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-motivoegreso-sueldos');

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
        can('actualizar-motivoegreso-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $resultado = $this->repository->sincronizarConAnita();

        if (! empty($resultado['errores'])) {
            return redirect('sueldos/motivoegreso')
                ->with('error', 'No se pudo sincronizar con Anita: '.implode(' | ', $resultado['errores']));
        }

        return redirect('sueldos/motivoegreso')->with(
            'mensaje',
            'Sincronización con Anita: '.$resultado['importados'].' importados, '
                .$resultado['omitidos'].' ya existentes (de '.$resultado['en_anita'].' en Anita).'
        );
    }
}
