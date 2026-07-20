<?php

namespace App\Http\Controllers\Caja\Flash;

use App\Exports\Caja\Flash\FlashParametroListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionFlashParametro;
use App\Models\Caja\Flash\FlashParametro;
use App\Repositories\Caja\Flash\FlashParametroRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\Flash\FlashParametroService;
use App\Support\Caja\Flash\FlashParametroListadoFiltros;
use App\Support\Caja\Flash\FlashParametroPeriodoSupport;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class FlashParametroController extends Controller
{
    public function __construct(
        private readonly FlashParametroRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly FlashParametroService $service,
    ) {}

    public function index(Request $request)
    {
        can('listar-flash-parametro');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeFlashParametro($filtros, true);

        return view('caja.flash.parametro.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => FlashParametroListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => FlashParametroListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-flash-parametro');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeFlashParametro($filtros, false);
                $view = \View::make('caja.flash.parametro.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_flash_parametro';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new FlashParametroListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('flash_parametro.xlsx');

            case 'CSV':
                return (new FlashParametroListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('flash_parametro.csv', Excel::CSV);
        }

        return redirect()->route('flash_parametro', FlashParametroListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-flash-parametro');

        $data = new FlashParametro();
        $periodo = FlashParametro::periodoDesdeInput((string) $request->input('periodo', now()->format('Y-m')));
        if ($periodo === '') {
            $periodo = now()->format('Ym');
        }
        $data->periodo = $periodo;

        $indices = FlashParametroPeriodoSupport::diasVaciosParaPeriodo($periodo);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, FlashParametroListadoFiltros::class);

        return view('caja.flash.parametro.crear', compact('data', 'indices', 'empresa_query', 'filtrosQuery'));
    }

    public function guardar(ValidacionFlashParametro $request)
    {
        can('crear-flash-parametro');

        $empresaId = (int) $request->input('empresa_id');
        $this->assertAccesoEmpresa($empresaId);

        $payload = $this->payloadEncabezado($request);
        $payload['creousuario_id'] = auth()->id();
        $indices = array_values((array) $request->input('indices', []));

        $this->service->crear($payload, $indices);

        return redirect()->route('flash_parametro', QueryRetornoListado::desdeRequest($request, FlashParametroListadoFiltros::class))
            ->with('mensaje', 'Parámetros flash creados con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-flash-parametro');

        $data = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $data->empresa_id);

        $indicesExistentes = $data->indices->map(fn ($i) => [
            'fecha' => $i->fecha?->format('Y-m-d'),
            'customer' => $i->customer,
            'season_index' => $i->season_index,
            'sindex_bingo' => $i->sindex_bingo,
            'sindex_slot' => $i->sindex_slot,
            'sindex_rul' => $i->sindex_rul,
            'sindex_poker' => $i->sindex_poker,
            'sindex_estac' => $i->sindex_estac,
            'vehiculos' => $i->vehiculos,
        ])->all();

        $indices = FlashParametroPeriodoSupport::fusionarConDiasDelPeriodo((string) $data->periodo, $indicesExistentes);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, FlashParametroListadoFiltros::class);

        return view('caja.flash.parametro.editar', compact('data', 'indices', 'empresa_query', 'filtrosQuery'));
    }

    public function actualizar(ValidacionFlashParametro $request, $id)
    {
        can('actualizar-flash-parametro');

        $data = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $data->empresa_id);

        $payload = $this->payloadEncabezado($request);
        $payload['empresa_id'] = (int) $data->empresa_id;
        $payload['periodo'] = (string) $data->periodo;
        $payload['actualizousuario_id'] = auth()->id();
        $indices = array_values((array) $request->input('indices', []));

        $this->service->actualizar((int) $id, $payload, $indices);

        return redirect()->route('flash_parametro', QueryRetornoListado::desdeRequest($request, FlashParametroListadoFiltros::class))
            ->with('mensaje', 'Parámetros flash actualizados con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-flash-parametro');

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

    public function apiDiasPeriodo(Request $request)
    {
        if (! can('crear-flash-parametro', false) && ! can('editar-flash-parametro', false)) {
            abort(403);
        }

        $periodo = FlashParametro::periodoDesdeInput((string) $request->input('periodo', ''));
        if ($periodo === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Período inválido'], 422);
        }

        $indices = FlashParametroPeriodoSupport::diasVaciosParaPeriodo($periodo);
        $totales = FlashParametroPeriodoSupport::totalesSeasonDesdeIndices($indices);

        return response()->json([
            'ok' => true,
            'periodo' => $periodo,
            'periodo_label' => FlashParametroPeriodoSupport::labelPeriodo($periodo),
            'indices' => $indices,
            'totales' => $totales,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadEncabezado(ValidacionFlashParametro $request): array
    {
        return [
            'empresa_id' => (int) $request->input('empresa_id'),
            'periodo' => (string) $request->input('periodo'),
            'budget_total' => (float) $request->input('budget_total', 0),
            'budget_slot' => (float) $request->input('budget_slot', 0),
            'budget_rul' => (float) $request->input('budget_rul', 0),
            'budget_poker' => (float) $request->input('budget_poker', 0),
            'budget_bingo' => (float) $request->input('budget_bingo', 0),
            'budget_f_b' => (float) $request->input('budget_f_b', 0),
            'budget_pos' => (int) $request->input('budget_pos', 0),
            'budget_estac' => (float) $request->input('budget_estac', 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = FlashParametroListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
        $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return $filtros;
        }

        return $filtros;
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        if ($asignadas === [] || $asignadas === null) {
            return;
        }
        if (! in_array($empresaId, array_map('intval', (array) $asignadas), true)) {
            abort(403, 'Sin acceso a la empresa seleccionada.');
        }
    }
}
