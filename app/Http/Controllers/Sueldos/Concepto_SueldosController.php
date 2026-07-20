<?php

namespace App\Http\Controllers\Sueldos;

use App\Exports\Sueldos\ConceptoSueldosListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConcepto_Sueldos;
use App\Models\Sueldos\Acumulador_Sueldos;
use App\Repositories\Sueldos\Concepto_SueldosRepositoryInterface;
use App\Support\Sueldos\ConceptoSueldosListadoFiltros;
use Illuminate\Http\Request;

class Concepto_SueldosController extends Controller
{
    private Concepto_SueldosRepositoryInterface $repository;

    public function __construct(Concepto_SueldosRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        can('listar-concepto-sueldos');

        $filtros = ConceptoSueldosListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeConcepto($filtros, true);

        return view('sueldos.concepto.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ConceptoSueldosListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ConceptoSueldosListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-concepto-sueldos');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = ConceptoSueldosListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeConcepto($filtros, false);

                $view = \View::make('sueldos.concepto.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }
                $nombre_pdf = 'listado_concepto_sueldos';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombre_pdf.'.pdf');

                return response()->download($path.'/'.$nombre_pdf.'.pdf');

            case 'EXCEL':
                return app(ConceptoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('concepto_sueldos.xlsx');

            case 'CSV':
                return app(ConceptoSueldosListadoExport::class)
                    ->parametros($filtros)
                    ->download('concepto_sueldos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_concepto_sueldos', ConceptoSueldosListadoFiltros::paraQueryString($filtros));
    }

    public function crear()
    {
        can('crear-concepto-sueldos');

        return view('sueldos.concepto.crear', [
            'acumuladores' => $this->acumuladores(),
            'overridesMap' => [],
        ]);
    }

    public function guardar(ValidacionConcepto_Sueldos $request)
    {
        can('crear-concepto-sueldos');
        $this->repository->create($request->validated());

        return redirect('sueldos/concepto')
            ->with('mensaje', 'Concepto creado con éxito');
    }

    public function editar($id)
    {
        can('editar-concepto-sueldos');
        $data = $this->repository->findOrFail($id);
        $data->load('acumuladoresOverride');

        $overridesMap = [];
        foreach ($data->acumuladoresOverride as $ov) {
            $overridesMap[$ov->acumulador_id] = [
                'accion' => $ov->excluir ? 'excluir' : 'incluir',
                'signo' => (int) $ov->signo,
            ];
        }

        return view('sueldos.concepto.editar', [
            'data' => $data,
            'acumuladores' => $this->acumuladores(),
            'overridesMap' => $overridesMap,
        ]);
    }

    private function acumuladores()
    {
        return Acumulador_Sueldos::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descripcion', 'tipos_incluye', 'signo']);
    }

    public function actualizar(ValidacionConcepto_Sueldos $request, $id)
    {
        can('actualizar-concepto-sueldos');
        $this->repository->update($request->validated(), $id);

        return redirect('sueldos/concepto')
            ->with('mensaje', 'Concepto actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-concepto-sueldos');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
