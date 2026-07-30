<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\AperturaGastoListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionAperturaGasto;
use App\Models\Caja\AperturaGasto;
use App\Models\Contable\Centrocosto;
use App\Repositories\Caja\AperturaGastoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Support\Caja\AperturaGastoListadoFiltros;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;

class AperturaGastoController extends Controller
{
    public function __construct(
        private readonly AperturaGastoRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly CuentacontableRepositoryInterface $cuentacontableRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-apertura-gasto');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeAperturaGasto($filtros, true);
        $estado_enum = AperturaGasto::$enumEstado;

        return view('caja.apertura_gasto.index', [
            'datas' => $datas,
            'estado_enum' => $estado_enum,
            'filtros' => $filtros,
            'filtrosQuery' => AperturaGastoListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => AperturaGastoListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-apertura-gasto');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeAperturaGasto($filtros, false);
                $view = \View::make('caja.apertura_gasto.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_apertura_gasto';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new AperturaGastoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('apertura_gastos.xlsx');

            case 'CSV':
                return (new AperturaGastoListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('apertura_gastos.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('apertura_gasto', AperturaGastoListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-apertura-gasto');
        $data = new AperturaGasto();
        $estado_enum = AperturaGasto::$enumEstado;
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, AperturaGastoListadoFiltros::class);

        return view('caja.apertura_gasto.crear', compact(
            'data',
            'estado_enum',
            'empresa_query',
            'filtrosQuery',
        ));
    }

    public function guardar(ValidacionAperturaGasto $request)
    {
        can('crear-apertura-gasto');
        $this->repository->create($request->validated());

        return redirect()->route('apertura_gasto', QueryRetornoListado::desdeRequest($request, AperturaGastoListadoFiltros::class))
            ->with('mensaje', 'Apertura de gasto creada con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-apertura-gasto');
        $data = $this->repository->findOrFail($id);
        $this->assertAccesoRegistro($data);
        $estado_enum = AperturaGasto::$enumEstado;
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, AperturaGastoListadoFiltros::class);

        return view('caja.apertura_gasto.editar', compact(
            'data',
            'estado_enum',
            'empresa_query',
            'filtrosQuery',
        ));
    }

    public function actualizar(ValidacionAperturaGasto $request, $id)
    {
        can('actualizar-apertura-gasto');
        $data = $this->repository->findOrFail($id);
        $this->assertAccesoRegistro($data);
        $this->repository->update($request->validated(), $id);

        return redirect()->route('apertura_gasto', QueryRetornoListado::desdeRequest($request, AperturaGastoListadoFiltros::class))
            ->with('mensaje', 'Apertura de gasto actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-apertura-gasto');

        if ($request->ajax()) {
            $data = $this->repository->find($id);
            if ($data !== null) {
                $this->assertAccesoRegistro($data);
            }

            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    /**
     * Replica cuentas de una fila a las demás empresas del selector,
     * resolviendo el ID de cuenta / contrapartida por código en cada empresa.
     *
     * @return list<array<string, mixed>>
     */
    public function replicarCuentasPorEmpresa(Request $request, $empresa_id, $cuentacontable_id)
    {
        if (! can('crear-apertura-gasto', false)
            && ! can('editar-apertura-gasto', false)
            && ! can('actualizar-apertura-gasto', false)) {
            abort(403);
        }

        $empresaOrigenId = (int) $empresa_id;
        $cuentaOrigenId = (int) $cuentacontable_id;
        $contrapOrigenId = (int) $request->input('contrapartida_id', 0);
        $centrocostoId = (int) $request->input('centrocosto_id', 0);

        if ($empresaOrigenId <= 0 || $cuentaOrigenId <= 0) {
            return response()->json([]);
        }

        $cuentaOrigen = $this->cuentacontableRepository->find($cuentaOrigenId);
        if ($cuentaOrigen === null) {
            return response()->json([]);
        }

        $codigoCuenta = (string) ($cuentaOrigen->codigo ?? '');
        $codigoContrap = '';
        if ($contrapOrigenId > 0) {
            $contrapOrigen = $this->cuentacontableRepository->find($contrapOrigenId);
            $codigoContrap = (string) ($contrapOrigen->codigo ?? '');
        }

        $centrocostoCodigo = '';
        $centrocostoNombre = '';
        if ($centrocostoId > 0) {
            $cc = Centrocosto::query()->select('id', 'codigo', 'nombre')->find($centrocostoId);
            if ($cc !== null) {
                $centrocostoCodigo = (string) ($cc->codigo ?? '');
                $centrocostoNombre = (string) ($cc->nombre ?? '');
            } else {
                $centrocostoId = 0;
            }
        }

        $resultado = [];
        foreach ($this->empresaRepository->allFiltrado() as $empresa) {
            $empresaId = (int) $empresa->id;
            if ($empresaId <= 0 || $empresaId === $empresaOrigenId) {
                continue;
            }

            $cuenta = $this->cuentacontableRepository->findPorCodigo($empresaId, $codigoCuenta);
            if ($cuenta === null) {
                continue;
            }

            $contrapId = null;
            $contrapCodigo = '';
            $contrapNombre = '';
            if ($codigoContrap !== '') {
                $contrap = $this->cuentacontableRepository->findPorCodigo($empresaId, $codigoContrap);
                if ($contrap !== null) {
                    $contrapId = (int) $contrap->id;
                    $contrapCodigo = (string) ($contrap->codigo ?? '');
                    $contrapNombre = (string) ($contrap->nombre ?? '');
                }
            }

            $resultado[] = [
                'empresa_id' => $empresaId,
                'empresa_nombre' => (string) ($empresa->nombre ?? ''),
                'cuentacontable_id' => (int) $cuenta->id,
                'codigocuentacontable' => (string) ($cuenta->codigo ?? ''),
                'nombrecuentacontable' => (string) ($cuenta->nombre ?? ''),
                'cuentacontable_contrapartida_id' => $contrapId,
                'codigocontrapartida' => $contrapCodigo,
                'nombrecontrapartida' => $contrapNombre,
                'centrocosto_id' => $centrocostoId > 0 ? $centrocostoId : null,
                'codigocentrocosto' => $centrocostoCodigo,
                'nombrecentrocosto' => $centrocostoNombre,
            ];
        }

        return response()->json($resultado);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = AperturaGastoListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
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

    private function assertAccesoRegistro(AperturaGasto $data): void
    {
        $empresaIds = $data->empresas
            ->pluck('empresa_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($empresaIds === []) {
            return;
        }

        foreach ($empresaIds as $empresaId) {
            if ($this->empresaRepository->empresaIdPermitida($empresaId)) {
                return;
            }
        }

        abort(403);
    }
}
