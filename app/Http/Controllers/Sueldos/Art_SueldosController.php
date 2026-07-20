<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\ArtSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionArt_Sueldos;
use App\Repositories\Sueldos\Art_SueldosRepositoryInterface;
use App\Support\Sueldos\ArtSueldosListadoFiltros;
use Illuminate\Http\Request;

class Art_SueldosController extends Controller
{
    private Art_SueldosRepositoryInterface $repository;

    public function __construct(Art_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-art-sueldos');

        $filtros = ArtSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeArt($filtros, true);

        return view('sueldos.art.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ArtSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ArtSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-art-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ArtSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeArt($filtros, false);

                $view = \View::make('sueldos.art.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_art_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(ArtSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('art_sueldos.xlsx');

            case 'CSV':
                return app(ArtSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('art_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_art_sueldos', ArtSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-art-sueldos');

        return view('sueldos.art.crear');
    }

    public function guardar(ValidacionArt_Sueldos $request)
    {
        can('crear-art-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/art')
            ->with('mensaje', 'ART creada con éxito');
    }

    public function editar($id)
    {
        can('editar-art-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.art.editar', compact('data'));
    }

    public function actualizar(ValidacionArt_Sueldos $request, $id)
    {
        can('actualizar-art-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/art')
            ->with('mensaje', 'ART actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-art-sueldos');

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
        can('actualizar-art-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $resultado = $this->repository->sincronizarConAnita();

        if (! empty($resultado['errores'])) {
            return redirect('sueldos/art')
                ->with('error', 'No se pudo sincronizar con Anita: '.implode(' | ', $resultado['errores']));
        }

        return redirect('sueldos/art')->with(
            'mensaje',
            'Sincronización con Anita: '.$resultado['importados'].' importados, '
                .$resultado['omitidos'].' ya existentes (de '.$resultado['en_anita'].' en Anita).'
        );
    }
}
