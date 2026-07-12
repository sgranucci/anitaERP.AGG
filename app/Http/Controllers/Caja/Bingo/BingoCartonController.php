<?php

namespace App\Http\Controllers\Caja\Bingo;

use App\Exports\Caja\Bingo\BingoCartonListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionBingoCarton;
use App\Models\Caja\Bingo\BingoCarton;
use App\Repositories\Caja\Bingo\BingoCartonRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\Bingo\BingoCartonListadoFiltros;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;

class BingoCartonController extends Controller
{
    public function __construct(
        private readonly BingoCartonRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-bingo-carton');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeBingoCarton($filtros, true);
        $estado_enum = BingoCarton::$enumEstado;

        return view('caja.bingo.carton.index', [
            'datas' => $datas,
            'estado_enum' => $estado_enum,
            'filtros' => $filtros,
            'filtrosQuery' => BingoCartonListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => BingoCartonListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-bingo-carton');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeBingoCarton($filtros, false);
                $view = \View::make('caja.bingo.carton.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_bingo_carton';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new BingoCartonListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('bingo_cartones.xlsx');

            case 'CSV':
                return (new BingoCartonListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('bingo_cartones.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('bingo_carton', BingoCartonListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-bingo-carton');
        $data = new BingoCarton();
        $estado_enum = BingoCarton::$enumEstado;
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, BingoCartonListadoFiltros::class);

        return view('caja.bingo.carton.crear', compact('data', 'estado_enum', 'empresa_query', 'filtrosQuery'));
    }

    public function guardar(ValidacionBingoCarton $request)
    {
        can('crear-bingo-carton');
        $this->repository->create($request->all());

        return redirect()->route('bingo_carton', QueryRetornoListado::desdeRequest($request, BingoCartonListadoFiltros::class))
            ->with('mensaje', 'Cartón creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-bingo-carton');
        $data = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $data->empresa_id);
        $estado_enum = BingoCarton::$enumEstado;
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, BingoCartonListadoFiltros::class);

        return view('caja.bingo.carton.editar', compact('data', 'estado_enum', 'empresa_query', 'filtrosQuery'));
    }

    public function actualizar(ValidacionBingoCarton $request, $id)
    {
        can('actualizar-bingo-carton');
        $data = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $data->empresa_id);
        $this->repository->update($request->all(), $id);

        return redirect()->route('bingo_carton', QueryRetornoListado::desdeRequest($request, BingoCartonListadoFiltros::class))
            ->with('mensaje', 'Cartón actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-bingo-carton');

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
        $filtros = BingoCartonListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
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
