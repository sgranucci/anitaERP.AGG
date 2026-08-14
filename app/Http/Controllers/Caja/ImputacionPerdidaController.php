<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\ImputacionPerdidaListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionImputacionPerdida;
use App\Models\Caja\ImputacionPerdida;
use App\Repositories\Caja\ImputacionPerdidaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Support\Caja\ImputacionPerdidaListadoFiltros;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;

class ImputacionPerdidaController extends Controller
{
    public function __construct(
        private readonly ImputacionPerdidaRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly CuentacontableRepositoryInterface $cuentacontableRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-imputacion-perdida');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeImputacionPerdida($filtros, true);

        return view('caja.imputacion_perdida.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => ImputacionPerdidaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => ImputacionPerdidaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-imputacion-perdida');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeImputacionPerdida($filtros, false);
                $view = \View::make('caja.imputacion_perdida.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_imputacion_perdida';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new ImputacionPerdidaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('imputacion_perdidas.xlsx');

            case 'CSV':
                return (new ImputacionPerdidaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('imputacion_perdidas.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('imputacion_perdida', ImputacionPerdidaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-imputacion-perdida');
        $data = new ImputacionPerdida();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ImputacionPerdidaListadoFiltros::class);

        return view('caja.imputacion_perdida.crear', compact(
            'data',
            'empresa_query',
            'filtrosQuery',
        ));
    }

    public function guardar(ValidacionImputacionPerdida $request)
    {
        can('crear-imputacion-perdida');
        $this->repository->create($request->validated());

        return redirect()->route('imputacion_perdida', QueryRetornoListado::desdeRequest($request, ImputacionPerdidaListadoFiltros::class))
            ->with('mensaje', 'Imputación de pérdida creada con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-imputacion-perdida');
        $data = $this->repository->findOrFail($id);
        $this->assertAccesoRegistro($data);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, ImputacionPerdidaListadoFiltros::class);

        return view('caja.imputacion_perdida.editar', compact(
            'data',
            'empresa_query',
            'filtrosQuery',
        ));
    }

    public function actualizar(ValidacionImputacionPerdida $request, $id)
    {
        can('actualizar-imputacion-perdida');
        $data = $this->repository->findOrFail($id);
        $this->assertAccesoRegistro($data);
        $this->repository->update($request->validated(), $id);

        return redirect()->route('imputacion_perdida', QueryRetornoListado::desdeRequest($request, ImputacionPerdidaListadoFiltros::class))
            ->with('mensaje', 'Imputación de pérdida actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-imputacion-perdida');

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
     * Replica la cuenta de una fila a las demás empresas del selector,
     * resolviendo el ID de cuenta por código en cada empresa.
     *
     * @return list<array<string, mixed>>
     */
    public function replicarCuentasPorEmpresa(Request $request, $empresa_id, $cuentacontable_id)
    {
        if (! can('crear-imputacion-perdida', false)
            && ! can('editar-imputacion-perdida', false)
            && ! can('actualizar-imputacion-perdida', false)) {
            abort(403);
        }

        $empresaOrigenId = (int) $empresa_id;
        $cuentaOrigenId = (int) $cuentacontable_id;

        if ($empresaOrigenId <= 0 || $cuentaOrigenId <= 0) {
            return response()->json([]);
        }

        $cuentaOrigen = $this->cuentacontableRepository->find($cuentaOrigenId);
        if ($cuentaOrigen === null) {
            return response()->json([]);
        }

        $codigoCuenta = (string) ($cuentaOrigen->codigo ?? '');

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

            $resultado[] = [
                'empresa_id' => $empresaId,
                'empresa_nombre' => (string) ($empresa->nombre ?? ''),
                'cuentacontable_id' => (int) $cuenta->id,
                'codigocuentacontable' => (string) ($cuenta->codigo ?? ''),
                'nombrecuentacontable' => (string) ($cuenta->nombre ?? ''),
            ];
        }

        return response()->json($resultado);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = ImputacionPerdidaListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
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

    private function assertAccesoRegistro(ImputacionPerdida $data): void
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
