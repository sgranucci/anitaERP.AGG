<?php

namespace App\Http\Controllers\Caja\Estacionamiento;

use App\Exports\Caja\Estacionamiento\ItemEstacionamientoListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionEstacionamientoItem;
use App\Models\Caja\Estacionamiento\ItemEstacionamiento;
use App\Repositories\Caja\Estacionamiento\ItemEstacionamientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\Estacionamiento\ItemEstacionamientoListadoFiltros;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;

class ItemEstacionamientoController extends Controller
{
    public function __construct(
        private readonly ItemEstacionamientoRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-estacionamiento-item');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeItemEstacionamiento($filtros, true);
        $estado_enum = ItemEstacionamiento::$enumEstado;

        return view('caja.estacionamiento.item.index', [
            'datas' => $datas,
            'estado_enum' => $estado_enum,
            'filtros' => $filtros,
            'filtrosQuery' => ItemEstacionamientoListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ItemEstacionamientoListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-estacionamiento-item');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeItemEstacionamiento($filtros, false);
                $view = \View::make('caja.estacionamiento.item.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_estacionamiento_item';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ItemEstacionamientoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('estacionamiento_items.xlsx');

            case 'CSV':
                return (new ItemEstacionamientoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('estacionamiento_items.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('estacionamiento_item', ItemEstacionamientoListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-estacionamiento-item');
        $data = new ItemEstacionamiento();
        $estado_enum = ItemEstacionamiento::$enumEstado;
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ItemEstacionamientoListadoFiltros::class);

        return view('caja.estacionamiento.item.crear', compact('data', 'estado_enum', 'empresa_query', 'filtrosQuery'));
    }

    public function guardar(ValidacionEstacionamientoItem $request)
    {
        can('crear-estacionamiento-item');
        $this->repository->create($request->all());

        return redirect()->route('estacionamiento_item', QueryRetornoListado::desdeRequest($request, ItemEstacionamientoListadoFiltros::class))
            ->with('mensaje', 'Ítem creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-estacionamiento-item');
        $data = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $data->empresa_id);
        $estado_enum = ItemEstacionamiento::$enumEstado;
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ItemEstacionamientoListadoFiltros::class);

        return view('caja.estacionamiento.item.editar', compact('data', 'estado_enum', 'empresa_query', 'filtrosQuery'));
    }

    public function actualizar(ValidacionEstacionamientoItem $request, $id)
    {
        can('actualizar-estacionamiento-item');
        $data = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $data->empresa_id);
        $this->repository->update($request->all(), $id);

        return redirect()->route('estacionamiento_item', QueryRetornoListado::desdeRequest($request, ItemEstacionamientoListadoFiltros::class))
            ->with('mensaje', 'Ítem actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-estacionamiento-item');

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
        $filtros = ItemEstacionamientoListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
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
