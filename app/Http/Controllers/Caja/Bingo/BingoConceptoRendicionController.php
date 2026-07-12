<?php

namespace App\Http\Controllers\Caja\Bingo;

use App\Exports\Caja\Bingo\BingoConceptoRendicionListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionBingoConceptoRendicion;
use App\Models\Caja\Bingo\BingoConceptoRendicion;
use App\Repositories\Caja\Bingo\BingoConceptoRendicionRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\Bingo\BingoConceptoRendicionListadoFiltros;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;

class BingoConceptoRendicionController extends Controller
{
    public function __construct(
        private readonly BingoConceptoRendicionRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-bingo-concepto-rendicion');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeBingoConceptoRendicion($filtros, true);
        $estado_enum = BingoConceptoRendicion::$enumEstado;
        $signo_enum = BingoConceptoRendicion::$enumSigno;
        $base_calculo_enum = BingoConceptoRendicion::$enumBaseCalculo;

        return view('caja.bingo.concepto_rendicion.index', [
            'datas' => $datas,
            'estado_enum' => $estado_enum,
            'signo_enum' => $signo_enum,
            'base_calculo_enum' => $base_calculo_enum,
            'filtros' => $filtros,
            'filtrosQuery' => BingoConceptoRendicionListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => BingoConceptoRendicionListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-bingo-concepto-rendicion');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeBingoConceptoRendicion($filtros, false);
                $view = \View::make('caja.bingo.concepto_rendicion.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_bingo_concepto_rendicion';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new BingoConceptoRendicionListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('bingo_conceptos_rendicion.xlsx');

            case 'CSV':
                return (new BingoConceptoRendicionListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('bingo_conceptos_rendicion.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('bingo_concepto_rendicion', BingoConceptoRendicionListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-bingo-concepto-rendicion');
        $data = new BingoConceptoRendicion();
        $estado_enum = BingoConceptoRendicion::$enumEstado;
        $signo_enum = BingoConceptoRendicion::$enumSigno;
        $base_calculo_enum = BingoConceptoRendicion::$enumBaseCalculo;
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, BingoConceptoRendicionListadoFiltros::class);

        return view('caja.bingo.concepto_rendicion.crear', compact(
            'data',
            'estado_enum',
            'signo_enum',
            'base_calculo_enum',
            'empresa_query',
            'filtrosQuery',
        ));
    }

    public function guardar(ValidacionBingoConceptoRendicion $request)
    {
        can('crear-bingo-concepto-rendicion');
        $this->repository->create($request->all());

        return redirect()->route('bingo_concepto_rendicion', QueryRetornoListado::desdeRequest($request, BingoConceptoRendicionListadoFiltros::class))
            ->with('mensaje', 'Concepto de rendición creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-bingo-concepto-rendicion');
        $data = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $data->empresa_id);
        $estado_enum = BingoConceptoRendicion::$enumEstado;
        $signo_enum = BingoConceptoRendicion::$enumSigno;
        $base_calculo_enum = BingoConceptoRendicion::$enumBaseCalculo;
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, BingoConceptoRendicionListadoFiltros::class);

        return view('caja.bingo.concepto_rendicion.editar', compact(
            'data',
            'estado_enum',
            'signo_enum',
            'base_calculo_enum',
            'empresa_query',
            'filtrosQuery',
        ));
    }

    public function actualizar(ValidacionBingoConceptoRendicion $request, $id)
    {
        can('actualizar-bingo-concepto-rendicion');
        $data = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $data->empresa_id);
        $this->repository->update($request->all(), $id);

        return redirect()->route('bingo_concepto_rendicion', QueryRetornoListado::desdeRequest($request, BingoConceptoRendicionListadoFiltros::class))
            ->with('mensaje', 'Concepto de rendición actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-bingo-concepto-rendicion');

        if ($request->ajax()) {
            $data = $this->repository->find($id);
            if ($data !== null) {
                $this->assertAccesoEmpresa((int) $data->empresa_id);
            }

            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = BingoConceptoRendicionListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        $filtros['empresas_asignadas'] = $asignadas;

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return $filtros;
        }

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);

        if ($empresaId <= 0 && count($asignadas) === 1 && ! $request->has('empresa_id')) {
            $filtros['empresa_id'] = $this->resolverEmpresaDefaultId($empresaQuery);
        } elseif ($empresaId > 0 && count($asignadas) >= 1 && ! in_array($empresaId, $asignadas, true)) {
            $filtros['empresa_id'] = $this->resolverEmpresaDefaultId($empresaQuery);
        }

        return $filtros;
    }

    private function resolverEmpresaDefaultId($empresaQuery): int
    {
        $first = $empresaQuery->first();

        return $first !== null ? (int) $first->id : 0;
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            abort(404);
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }
}
