<?php

namespace App\Http\Controllers\Caja\Estacionamiento;

use App\Exports\Caja\Estacionamiento\ListaPrecioEstacionamientoListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionEstacionamientoListaPrecio;
use App\Models\Caja\Estacionamiento\CategoriaAutomovil;
use App\Models\Caja\Estacionamiento\ItemEstacionamiento;
use App\Models\Caja\Estacionamiento\ListaPrecioEstacionamiento;
use App\Repositories\Caja\Estacionamiento\ListaPrecioEstacionamientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Services\Caja\Estacionamiento\ListaPrecioEstacionamientoService;
use App\Support\Caja\Estacionamiento\ListaPrecioEstacionamientoItemVigenteSupport;
use App\Support\Caja\Estacionamiento\ListaPrecioEstacionamientoListadoFiltros;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Http\Request;

class ListaPrecioEstacionamientoController extends Controller
{
    public function __construct(
        private readonly ListaPrecioEstacionamientoRepositoryInterface $repository,
        private readonly ListaPrecioEstacionamientoService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly MonedaRepositoryInterface $monedaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-estacionamiento-lista-precio');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeListaPrecioEstacionamiento($filtros, true);

        return view('caja.estacionamiento.lista_precio.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ListaPrecioEstacionamientoListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ListaPrecioEstacionamientoListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'categoria_query' => CategoriaAutomovil::orderBy('nombre')->get(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-estacionamiento-lista-precio');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeListaPrecioEstacionamiento($filtros, false);
                $fechaReferencia = $filtros['fecha_referencia'] ?? now()->toDateString();
                $view = \View::make('caja.estacionamiento.lista_precio.listado', compact('datas', 'fechaReferencia'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_estacionamiento_lista_precio';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ListaPrecioEstacionamientoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('estacionamiento_listas_precio.xlsx');

            case 'CSV':
                return (new ListaPrecioEstacionamientoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('estacionamiento_listas_precio.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('estacionamiento_lista_precio', ListaPrecioEstacionamientoListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-estacionamiento-lista-precio');

        $data = new ListaPrecioEstacionamiento();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $categoria_query = CategoriaAutomovil::orderBy('nombre')->get();
        $moneda_query = $this->monedaRepository->all();
        $moneda_peso_id = $this->resolverMonedaPesoId($moneda_query);
        $empresaId = (int) $request->query('empresa_id', 0);
        if ($empresaId <= 0) {
            $empresaId = $this->resolverEmpresaDefaultId($empresa_query);
        }
        $empresaId = (int) old('empresa_id', $empresaId);
        $items_empresa = $this->itemsActivosPorEmpresa($empresaId);

        return view('caja.estacionamiento.lista_precio.crear', [
            'data' => $data,
            'empresa_query' => $empresa_query,
            'categoria_query' => $categoria_query,
            'moneda_query' => $moneda_query,
            'moneda_peso_id' => $moneda_peso_id,
            'items_empresa' => $items_empresa,
            'empresa_form_id' => $empresaId,
            'empresa_guardada_id' => 0,
            'lista_id' => 0,
        ]);
    }

    public function guardar(ValidacionEstacionamientoListaPrecio $request)
    {
        can('crear-estacionamiento-lista-precio');

        $ret = $this->service->guarda($request->all());
        if ($ret['mensaje'] === 'ok') {
            return redirect()->route('estacionamiento_lista_precio')->with('mensaje', 'Lista de precios creada con éxito');
        }

        return redirect()->back()->withInput()->with('mensaje', $ret['errores'] ?? 'Error al guardar');
    }

    public function editar(Request $request, $id)
    {
        can('editar-estacionamiento-lista-precio');

        $data = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $data->empresa_id);

        $empresa_query = $this->empresaRepository->allFiltrado();
        $categoria_query = CategoriaAutomovil::orderBy('nombre')->get();
        $moneda_query = $this->monedaRepository->all();
        $moneda_peso_id = $this->resolverMonedaPesoId($moneda_query);

        $empresaGuardadaId = (int) $data->empresa_id;
        $empresaPreviewId = (int) $request->query('empresa_id', 0);
        $empresaFormId = $empresaGuardadaId;
        if ($empresaPreviewId > 0 && $this->empresaRepository->empresaIdPermitida($empresaPreviewId)) {
            $empresaFormId = $empresaPreviewId;
        }
        $empresaFormId = (int) old('empresa_id', $empresaFormId);

        $items_empresa = $this->itemsActivosPorEmpresa($empresaFormId);

        return view('caja.estacionamiento.lista_precio.editar', [
            'data' => $data,
            'empresa_query' => $empresa_query,
            'categoria_query' => $categoria_query,
            'moneda_query' => $moneda_query,
            'moneda_peso_id' => $moneda_peso_id,
            'items_empresa' => $items_empresa,
            'empresa_form_id' => $empresaFormId,
            'empresa_guardada_id' => $empresaGuardadaId,
            'lista_id' => (int) $id,
        ]);
    }

    public function actualizar(ValidacionEstacionamientoListaPrecio $request, $id)
    {
        can('actualizar-estacionamiento-lista-precio');

        $existente = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $existente->empresa_id);
        $this->assertAccesoEmpresa((int) $request->input('empresa_id'));

        $ret = $this->service->actualiza($request->all(), (int) $id);
        if ($ret['mensaje'] === 'ok') {
            return redirect()->route('estacionamiento_lista_precio')->with('mensaje', 'Precios y vigencias actualizados con éxito');
        }

        return redirect()->back()->withInput()->with('mensaje', $ret['errores'] ?? 'Error al actualizar');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-estacionamiento-lista-precio');

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

    public function itemsPorEmpresa(Request $request, int $empresaId)
    {
        if (! can('crear-estacionamiento-lista-precio', false) && ! can('editar-estacionamiento-lista-precio', false)) {
            abort(403);
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        $items = $this->itemsActivosPorEmpresa($empresaId);

        return response()->json($items->map(fn ($item) => [
            'id' => $item->id,
            'nombre' => $item->nombre,
        ]));
    }

    public function validarCabecera(Request $request)
    {
        if (! can('crear-estacionamiento-lista-precio', false) && ! can('actualizar-estacionamiento-lista-precio', false)) {
            abort(403);
        }

        $empresaId = (int) $request->input('empresa_id');
        $categoriaId = (int) $request->input('categoria_automovil_id');
        $excluirId = (int) $request->input('excluir_id', 0);

        if ($empresaId <= 0 || $categoriaId <= 0) {
            return response()->json([
                'disponible' => false,
                'mensaje' => 'Seleccione empresa y categoría.',
            ]);
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json([
                'disponible' => false,
                'mensaje' => 'No tiene acceso a la empresa seleccionada.',
            ]);
        }

        $existe = $this->repository->existeParaEmpresaCategoria(
            $empresaId,
            $categoriaId,
            $excluirId > 0 ? $excluirId : null
        );

        return response()->json([
            'disponible' => ! $existe,
            'mensaje' => $existe ? 'Ya existe una lista de precios para esta empresa y categoría.' : '',
        ]);
    }

    public function historiaItem(Request $request, int $id, int $itemId)
    {
        if (! can('editar-estacionamiento-lista-precio', false) && ! can('listar-estacionamiento-lista-precio', false)) {
            abort(403);
        }

        $lista = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $lista->empresa_id);

        $lineas = $lista->items()
            ->with(['itemEstacionamiento', 'usuarioUltCambio'])
            ->where('item_estacionamiento_id', $itemId)
            ->get();

        if ($lineas->isEmpty()) {
            return response()->json([
                'item' => ['id' => $itemId, 'nombre' => ''],
                'lineas' => [],
            ]);
        }

        $ordenadas = ListaPrecioEstacionamientoItemVigenteSupport::historialOrdenado($lineas);

        return response()->json([
            'item' => [
                'id' => $itemId,
                'nombre' => $lineas->first()->itemEstacionamiento->nombre ?? '',
            ],
            'lineas' => $ordenadas->map(fn ($row) => [
                'id' => $row->id,
                'precio' => $row->precio,
                'fecha_vigencia' => $row->fecha_vigencia?->format('Y-m-d') ?? substr((string) $row->fecha_vigencia, 0, 10),
                'usuario' => $row->usuarioUltCambio->nombre ?? '',
                'es_vigente' => (bool) ($row->es_vigente_actual ?? false),
            ])->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = ListaPrecioEstacionamientoListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
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

    /**
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection  $monedaQuery
     */
    private function resolverMonedaPesoId($monedaQuery): int
    {
        $ars = $monedaQuery->firstWhere('abreviatura', 'ARS');

        return $ars !== null ? (int) $ars->id : (int) ($monedaQuery->first()->id ?? 0);
    }

    /**
     * @return \Illuminate\Support\Collection<int, ItemEstacionamiento>
     */
    private function itemsActivosPorEmpresa(int $empresaId)
    {
        if ($empresaId <= 0) {
            return collect();
        }

        return ItemEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', ItemEstacionamiento::ESTADO_ACTIVO)
            ->orderBy('nombre')
            ->get();
    }
}
