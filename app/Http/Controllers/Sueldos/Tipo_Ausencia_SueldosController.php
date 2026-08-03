<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\TipoAusenciaSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTipo_Ausencia_Sueldos;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Repositories\Sueldos\Tipo_Ausencia_SueldosRepositoryInterface;
use App\Support\Sueldos\TipoAusenciaSueldosListadoFiltros;
use Illuminate\Http\Request;

class Tipo_Ausencia_SueldosController extends Controller
{
    private Tipo_Ausencia_SueldosRepositoryInterface $repository;

    public function __construct(Tipo_Ausencia_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-tipo-ausencia-sueldos');

        $filtros = TipoAusenciaSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeTipoAusencia($filtros, true);

        return view('sueldos.tipo_ausencia.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => TipoAusenciaSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => TipoAusenciaSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-tipo-ausencia-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = TipoAusenciaSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeTipoAusencia($filtros, false);

                $view = \View::make('sueldos.tipo_ausencia.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_tipo_ausencia_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(TipoAusenciaSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('tipo_ausencia_sueldos.xlsx');

            case 'CSV':
                return app(TipoAusenciaSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('tipo_ausencia_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_tipo_ausencia_sueldos', TipoAusenciaSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-tipo-ausencia-sueldos');

        return view('sueldos.tipo_ausencia.crear', ['conceptos' => $this->conceptos()]);
    }

    public function guardar(ValidacionTipo_Ausencia_Sueldos $request)
    {
        can('crear-tipo-ausencia-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/tipo-ausencia')
            ->with('mensaje', 'Tipo de ausencia creado con éxito');
    }

    public function editar($id)
    {
        can('editar-tipo-ausencia-sueldos');
        $data = $this->repository->findOrFail($id);
        $data->load('concepto');

        return view('sueldos.tipo_ausencia.editar', ['data' => $data, 'conceptos' => $this->conceptos()]);
    }

    public function actualizar(ValidacionTipo_Ausencia_Sueldos $request, $id)
    {
        can('actualizar-tipo-ausencia-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/tipo-ausencia')
            ->with('mensaje', 'Tipo de ausencia actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-tipo-ausencia-sueldos');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    private function conceptos()
    {
        return Concepto_Sueldos::query()->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'descripcion']);
    }
}
