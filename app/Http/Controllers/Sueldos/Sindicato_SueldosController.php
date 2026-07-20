<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\SindicatoSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionSindicato_Sueldos;
use App\Repositories\Sueldos\Sindicato_SueldosRepositoryInterface;
use App\Support\Sueldos\SindicatoSueldosListadoFiltros;
use Illuminate\Http\Request;

class Sindicato_SueldosController extends Controller
{
    private Sindicato_SueldosRepositoryInterface $repository;

    public function __construct(Sindicato_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-sindicato-sueldos');

        $filtros = SindicatoSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeSindicato($filtros, true);

        return view('sueldos.sindicato.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => SindicatoSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => SindicatoSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-sindicato-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = SindicatoSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeSindicato($filtros, false);

                $view = \View::make('sueldos.sindicato.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_sindicato_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(SindicatoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('sindicato_sueldos.xlsx');

            case 'CSV':
                return app(SindicatoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('sindicato_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_sindicato_sueldos', SindicatoSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-sindicato-sueldos');

        return view('sueldos.sindicato.crear');
    }

    public function guardar(ValidacionSindicato_Sueldos $request)
    {
        can('crear-sindicato-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/sindicato')->with('mensaje', 'Sindicato creado con éxito');
    }

    public function editar($id)
    {
        can('editar-sindicato-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.sindicato.editar', compact('data'));
    }

    public function actualizar(ValidacionSindicato_Sueldos $request, $id)
    {
        can('actualizar-sindicato-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/sindicato')->with('mensaje', 'Sindicato actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-sindicato-sueldos');

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
        can('actualizar-sindicato-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $r = $this->repository->sincronizarConAnita();

        if (! empty($r['errores'])) {
            return redirect('sueldos/sindicato')
                ->with('error', 'No se pudo sincronizar con Anita: '.implode(' | ', $r['errores']));
        }

        return redirect('sueldos/sindicato')->with(
            'mensaje',
            'Sincronización con Anita: '.$r['importados'].' importados, '.$r['omitidos'].' ya existentes (de '.$r['en_anita'].' en Anita).'
        );
    }
}
