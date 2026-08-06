<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\AgrupamientoSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionAgrupamiento_Sueldos;
use App\Repositories\Sueldos\Agrupamiento_SueldosRepositoryInterface;
use App\Support\Sueldos\AgrupamientoSueldosListadoFiltros;
use App\Support\Sueldos\FallocajaResumen;
use App\Support\Sueldos\FalloCajaTipo;
use Illuminate\Http\Request;

class Agrupamiento_SueldosController extends Controller
{
    private Agrupamiento_SueldosRepositoryInterface $repository;

    public function __construct(Agrupamiento_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-agrupamiento-sueldos');

        $filtros = AgrupamientoSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeAgrupamiento($filtros, true);

        return view('sueldos.agrupamiento.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => AgrupamientoSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => AgrupamientoSueldosListadoFiltros::CAMPOS,
            'fallosPorTipo' => FallocajaResumen::porTipo(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-agrupamiento-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = AgrupamientoSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeAgrupamiento($filtros, false);
                $fallosPorTipo = FallocajaResumen::porTipo();

                $view = \View::make('sueldos.agrupamiento.listado', compact('datas', 'fallosPorTipo'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_agrupamiento_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(AgrupamientoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('agrupamiento_sueldos.xlsx');

            case 'CSV':
                return app(AgrupamientoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('agrupamiento_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_agrupamiento_sueldos', AgrupamientoSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-agrupamiento-sueldos');

        return view('sueldos.agrupamiento.crear', [
            'tipos' => FalloCajaTipo::OPCIONES,
            'fallosPorTipo' => FallocajaResumen::porTipo(),
        ]);
    }

    public function guardar(ValidacionAgrupamiento_Sueldos $request)
    {
        can('crear-agrupamiento-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/agrupamiento')
            ->with('mensaje', 'Agrupamiento creado con éxito');
    }

    public function editar($id)
    {
        can('editar-agrupamiento-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.agrupamiento.editar', [
            'data' => $data,
            'tipos' => FalloCajaTipo::OPCIONES,
            'fallosPorTipo' => FallocajaResumen::porTipo(),
        ]);
    }

    public function actualizar(ValidacionAgrupamiento_Sueldos $request, $id)
    {
        can('actualizar-agrupamiento-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/agrupamiento')
            ->with('mensaje', 'Agrupamiento actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-agrupamiento-sueldos');

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
        can('actualizar-agrupamiento-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $resultado = $this->repository->sincronizarConAnita();

        if (! empty($resultado['errores'])) {
            return redirect('sueldos/agrupamiento')
                ->with('error', 'No se pudo sincronizar con Anita: '.implode(' | ', $resultado['errores']));
        }

        return redirect('sueldos/agrupamiento')->with(
            'mensaje',
            'Sincronización con Anita: '.$resultado['importados'].' importados, '
                .($resultado['actualizados'] ?? 0).' actualizados, '
                .$resultado['omitidos'].' omitidos (de '.$resultado['en_anita'].' en Anita).'
        );
    }
}
