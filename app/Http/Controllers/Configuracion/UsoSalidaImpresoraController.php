<?php

namespace App\Http\Controllers\Configuracion;

use App\Exports\Configuracion\UsoSalidaImpresoraListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionUsoSalidaImpresora;
use App\Models\Configuracion\UsoSalidaImpresora;
use App\Repositories\Configuracion\UsoSalidaImpresoraRepositoryInterface;
use App\Support\Configuracion\SeteoSalidaProgramaSupport;
use App\Support\Configuracion\UsoSalidaImpresoraListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;

class UsoSalidaImpresoraController extends Controller
{
    public function __construct(
        private readonly UsoSalidaImpresoraRepositoryInterface $repository,
    ) {}

    public function index(Request $request)
    {
        can('listar-uso-salida-impresora');

        $filtros = UsoSalidaImpresoraListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeUsoSalidaImpresora($filtros, true);

        return view('configuracion.uso_salida_impresora.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => UsoSalidaImpresoraListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => UsoSalidaImpresoraListadoFiltros::CAMPOS,
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-uso-salida-impresora');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = UsoSalidaImpresoraListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeUsoSalidaImpresora($filtros, false);
                $view = \View::make('configuracion.uso_salida_impresora.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_uso_salida_impresora';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new UsoSalidaImpresoraListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('uso_salida_impresora.xlsx');

            case 'CSV':
                return (new UsoSalidaImpresoraListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('uso_salida_impresora.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('uso_salida_impresora', UsoSalidaImpresoraListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-uso-salida-impresora');
        $data = new UsoSalidaImpresora();
        $programasSeteoOpciones = SeteoSalidaProgramaSupport::opcionesParaFormulario();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, UsoSalidaImpresoraListadoFiltros::class);

        return view('configuracion.uso_salida_impresora.crear', compact('data', 'programasSeteoOpciones', 'filtrosQuery'));
    }

    public function guardar(ValidacionUsoSalidaImpresora $request)
    {
        can('crear-uso-salida-impresora');
        $this->repository->create($request->all());

        return redirect()->route('uso_salida_impresora', QueryRetornoListado::desdeRequest($request, UsoSalidaImpresoraListadoFiltros::class))
            ->with('mensaje', 'Uso de salida de impresión creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-uso-salida-impresora');
        $data = $this->repository->findOrFail($id);
        $programasSeteoOpciones = SeteoSalidaProgramaSupport::opcionesParaFormulario();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, UsoSalidaImpresoraListadoFiltros::class);

        return view('configuracion.uso_salida_impresora.editar', compact('data', 'programasSeteoOpciones', 'filtrosQuery'));
    }

    public function actualizar(ValidacionUsoSalidaImpresora $request, $id)
    {
        can('actualizar-uso-salida-impresora');
        $this->repository->update($request->all(), $id);

        return redirect()->route('uso_salida_impresora', QueryRetornoListado::desdeRequest($request, UsoSalidaImpresoraListadoFiltros::class))
            ->with('mensaje', 'Uso de salida de impresión actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-uso-salida-impresora');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
