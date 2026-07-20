<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\FallocajaSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionFallocaja_Sueldos;
use App\Repositories\Sueldos\Fallocaja_SueldosRepositoryInterface;
use App\Support\Sueldos\FalloCajaTipo;
use App\Support\Sueldos\FallocajaSueldosListadoFiltros;
use Illuminate\Http\Request;

class Fallocaja_SueldosController extends Controller
{
    private Fallocaja_SueldosRepositoryInterface $repository;

    public function __construct(Fallocaja_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-fallocaja-sueldos');

        $filtros = FallocajaSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeFallocaja($filtros, true);

        return view('sueldos.fallocaja.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => FallocajaSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => FallocajaSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-fallocaja-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = FallocajaSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeFallocaja($filtros, false);

                $view = \View::make('sueldos.fallocaja.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_fallocaja_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(FallocajaSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('fallocaja_sueldos.xlsx');

            case 'CSV':
                return app(FallocajaSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('fallocaja_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_fallocaja_sueldos', FallocajaSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-fallocaja-sueldos');

        return view('sueldos.fallocaja.crear', ['tipos' => FalloCajaTipo::OPCIONES]);
    }

    public function guardar(ValidacionFallocaja_Sueldos $request)
    {
        can('crear-fallocaja-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/fallocaja')
            ->with('mensaje', 'Fallo de caja creado con éxito');
    }

    public function editar($id)
    {
        can('editar-fallocaja-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.fallocaja.editar', ['data' => $data, 'tipos' => FalloCajaTipo::OPCIONES]);
    }

    public function actualizar(ValidacionFallocaja_Sueldos $request, $id)
    {
        can('actualizar-fallocaja-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/fallocaja')
            ->with('mensaje', 'Fallo de caja actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-fallocaja-sueldos');

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
        can('actualizar-fallocaja-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $resultado = $this->repository->sincronizarConAnita();

        if (! empty($resultado['errores'])) {
            return redirect('sueldos/fallocaja')
                ->with('error', 'No se pudo sincronizar con Anita: '.implode(' | ', $resultado['errores']));
        }

        return redirect('sueldos/fallocaja')->with(
            'mensaje',
            'Sincronización con Anita: '.$resultado['importados'].' importados, '
                .$resultado['omitidos'].' ya existentes (de '.$resultado['en_anita'].' en Anita).'
        );
    }
}
