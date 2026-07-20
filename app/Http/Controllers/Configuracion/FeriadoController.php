<?php

namespace App\Http\Controllers\Configuracion;

use App\Exports\Configuracion\FeriadoListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionFeriado;
use App\Models\Configuracion\Feriado;
use App\Repositories\Configuracion\FeriadoRepositoryInterface;
use App\Services\Configuracion\FeriadoImportadorService;
use App\Support\Configuracion\FeriadoListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;

class FeriadoController extends Controller
{
    public function __construct(
        private readonly FeriadoRepositoryInterface $repository,
    ) {}

    public function index(Request $request)
    {
        can('listar-feriado');

        $filtros = FeriadoListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeFeriado($filtros, true);

        return view('configuracion.feriado.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => FeriadoListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => FeriadoListadoFiltros::CAMPOS,
            'anioSugerido' => (int) now()->format('Y'),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-feriado');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = FeriadoListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeFeriado($filtros, false);
                $view = \View::make('configuracion.feriado.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_feriado';

                if (! is_dir($path)) {
                    @mkdir($path, 0775, true);
                }

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new FeriadoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('feriados.xlsx');

            case 'CSV':
                return (new FeriadoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('feriados.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('feriado', FeriadoListadoFiltros::paraQueryString($filtros));
    }

    public function importar(Request $request, FeriadoImportadorService $importador)
    {
        can('importar-feriado');

        $anio = (int) $request->input('anio', now()->format('Y'));
        $resultado = $importador->importarAnio($anio);

        $filtrosQuery = QueryRetornoListado::desdeRequest($request, FeriadoListadoFiltros::class);

        return redirect()->route('feriado', $filtrosQuery)
            ->with($resultado['ok'] ? 'mensaje' : 'error', $resultado['mensaje']);
    }

    public function crear(Request $request)
    {
        can('crear-feriado');
        $data = new Feriado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, FeriadoListadoFiltros::class);

        return view('configuracion.feriado.crear', compact('data', 'filtrosQuery'));
    }

    public function guardar(ValidacionFeriado $request)
    {
        can('crear-feriado');
        $this->repository->create($request->all());

        return redirect()->route('feriado', QueryRetornoListado::desdeRequest($request, FeriadoListadoFiltros::class))
            ->with('mensaje', 'Feriado creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-feriado');
        $data = $this->repository->findOrFail($id);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, FeriadoListadoFiltros::class);

        return view('configuracion.feriado.editar', compact('data', 'filtrosQuery'));
    }

    public function actualizar(ValidacionFeriado $request, $id)
    {
        can('actualizar-feriado');
        $this->repository->update($request->all(), $id);

        return redirect()->route('feriado', QueryRetornoListado::desdeRequest($request, FeriadoListadoFiltros::class))
            ->with('mensaje', 'Feriado actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-feriado');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
