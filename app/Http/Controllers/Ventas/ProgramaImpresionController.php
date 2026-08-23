<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\Ventas\ProgramaImpresionListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionProgramaImpresion;
use App\Models\Configuracion\Provincia;
use App\Models\Configuracion\Salida;
use App\Models\Ventas\ComprobanteImpresionPrograma;
use App\Models\Ventas\Transporte;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\ComprobanteImpresionProgramaRepositoryInterface;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Ventas\ComprobanteImpresionCopiaPreset;
use App\Support\Ventas\ComprobanteImpresionFormulario;
use App\Support\Ventas\ComprobanteImpresionReglaClave;
use App\Support\Ventas\ProgramaImpresionListadoFiltros;
use Illuminate\Http\Request;

class ProgramaImpresionController extends Controller
{
    public function __construct(
        private ComprobanteImpresionProgramaRepositoryInterface $repository,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-programa-impresion');
        $filtros = ProgramaImpresionListadoFiltros::resolverDesdeRequest($request);
        $datas = $this->repository->leeProgramas($filtros, true);

        return view('ventas.programa_impresion.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ProgramaImpresionListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ProgramaImpresionListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-programa-impresion');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');
        $filtros = ProgramaImpresionListadoFiltros::resolverDesdeRequest($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeProgramas($filtros, false);
                $view = \View::make('ventas.programa_impresion.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                if (! is_dir($path)) {
                    mkdir($path, 0775, true);
                }
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/listado_programa_impresion.pdf');

                return response()->download($path.'/listado_programa_impresion.pdf');
            case 'EXCEL':
                return (new ProgramaImpresionListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('programa_impresion.xlsx');
            case 'CSV':
                return (new ProgramaImpresionListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('programa_impresion.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('consultar_programa_impresion', ProgramaImpresionListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-programa-impresion');
        $data = new ComprobanteImpresionPrograma;
        $data->setRelation('formularios', collect());
        $data->setRelation('reglas', collect());

        return view('ventas.programa_impresion.crear', array_merge(
            $this->datosFormulario($data),
            ['filtrosQuery' => QueryRetornoListado::desdeRequest($request, ProgramaImpresionListadoFiltros::class)]
        ));
    }

    public function guardar(ValidacionProgramaImpresion $request)
    {
        can('crear-programa-impresion');
        $this->repository->create($request->validated());

        return redirect()
            ->route('consultar_programa_impresion', QueryRetornoListado::desdeRequest($request, ProgramaImpresionListadoFiltros::class))
            ->with('mensaje', 'Programa de impresión creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-programa-impresion');
        $data = $this->repository->findOrFail($id);

        return view('ventas.programa_impresion.editar', array_merge(
            $this->datosFormulario($data),
            ['filtrosQuery' => QueryRetornoListado::desdeRequest($request, ProgramaImpresionListadoFiltros::class)]
        ));
    }

    public function actualizar(ValidacionProgramaImpresion $request, $id)
    {
        can('actualizar-programa-impresion');
        $this->repository->update($request->validated(), $id);

        return redirect()
            ->route('consultar_programa_impresion', QueryRetornoListado::desdeRequest($request, ProgramaImpresionListadoFiltros::class))
            ->with('mensaje', 'Programa de impresión actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-programa-impresion');
        if (! $request->ajax()) {
            abort(404);
        }

        return response()->json(['mensaje' => $this->repository->delete($id) ? 'ok' : 'ng']);
    }

    /** @return array<string, mixed> */
    private function datosFormulario(ComprobanteImpresionPrograma $data): array
    {
        return [
            'data' => $data,
            'formulariosEnum' => ComprobanteImpresionFormulario::etiquetas(),
            'copiasPreset' => ComprobanteImpresionCopiaPreset::todos(),
            'reglasEnum' => ComprobanteImpresionReglaClave::etiquetas(),
            'salidas' => Salida::query()->orderBy('nombre')->get(['id', 'nombre']),
            'empresas' => $this->empresaRepository->allFiltrado(),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'transportes' => Transporte::query()->orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            'provincias' => Provincia::query()->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }
}
