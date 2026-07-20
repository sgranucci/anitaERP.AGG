<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\ObrasocialSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionObrasocial_Sueldos;
use App\Repositories\Sueldos\Obrasocial_SueldosRepositoryInterface;
use App\Support\Sueldos\ObrasocialSueldosListadoFiltros;
use Illuminate\Http\Request;

class Obrasocial_SueldosController extends Controller
{
    private Obrasocial_SueldosRepositoryInterface $repository;

    public function __construct(Obrasocial_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-obrasocial-sueldos');

        $filtros = ObrasocialSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeObrasocial($filtros, true);

        return view('sueldos.obrasocial.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ObrasocialSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ObrasocialSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-obrasocial-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ObrasocialSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeObrasocial($filtros, false);

                $view = \View::make('sueldos.obrasocial.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_obrasocial_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(ObrasocialSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('obrasocial_sueldos.xlsx');

            case 'CSV':
                return app(ObrasocialSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('obrasocial_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_obrasocial_sueldos', ObrasocialSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-obrasocial-sueldos');

        return view('sueldos.obrasocial.crear');
    }

    public function guardar(ValidacionObrasocial_Sueldos $request)
    {
        can('crear-obrasocial-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/obrasocial')->with('mensaje', 'Obra social creada con éxito');
    }

    public function editar($id)
    {
        can('editar-obrasocial-sueldos');
        $data = $this->repository->findOrFail($id);

        return view('sueldos.obrasocial.editar', compact('data'));
    }

    public function actualizar(ValidacionObrasocial_Sueldos $request, $id)
    {
        can('actualizar-obrasocial-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/obrasocial')->with('mensaje', 'Obra social actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-obrasocial-sueldos');

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
        can('actualizar-obrasocial-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $r = $this->repository->sincronizarConAnita();

        if (! empty($r['errores'])) {
            return redirect('sueldos/obrasocial')
                ->with('error', 'No se pudo sincronizar con Anita: '.implode(' | ', $r['errores']));
        }

        return redirect('sueldos/obrasocial')->with(
            'mensaje',
            'Sincronización con Anita: '.$r['importados'].' importadas, '.$r['omitidos'].' ya existentes (de '.$r['en_anita'].' en Anita).'
        );
    }
}
