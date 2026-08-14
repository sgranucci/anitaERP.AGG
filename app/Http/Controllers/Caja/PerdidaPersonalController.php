<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\PerdidaPersonalListadoExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionPerdidaPersonal;
use App\Models\Caja\ConceptoPerdida;
use App\Models\Caja\ImputacionPerdida;
use App\Models\Caja\PerdidaPersonal;
use App\Models\Contable\Centrocosto;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Repositories\Caja\PerdidaPersonalRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\PerdidaPersonalListadoFiltros;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;

class PerdidaPersonalController extends Controller
{
    public function __construct(
        private readonly PerdidaPersonalRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-perdida-personal');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leePerdidaPersonal($filtros, true);

        return view('caja.perdida_personal.index', [
            'datas' => $datas,
            'estado_enum' => PerdidaPersonal::$enumEstado,
            'turno_enum' => PerdidaPersonal::$enumTurno,
            'filtros' => $filtros,
            'filtrosQuery' => PerdidaPersonalListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => PerdidaPersonalListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-perdida-personal');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leePerdidaPersonal($filtros, false);
                $view = \View::make('caja.perdida_personal.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_perdida_personal';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new PerdidaPersonalListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('perdida_personal.xlsx');

            case 'CSV':
                return (new PerdidaPersonalListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('perdida_personal.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('perdida_personal', PerdidaPersonalListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-perdida-personal');
        $data = new PerdidaPersonal();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, PerdidaPersonalListadoFiltros::class);

        return view('caja.perdida_personal.crear', array_merge(
            $this->datosFormulario($data),
            compact('data', 'filtrosQuery')
        ));
    }

    public function guardar(ValidacionPerdidaPersonal $request)
    {
        can('crear-perdida-personal');
        $this->assertEmpresaPermitida((int) $request->input('empresa_id'));
        $this->repository->create($request->validated());

        return redirect()->route('perdida_personal', QueryRetornoListado::desdeRequest($request, PerdidaPersonalListadoFiltros::class))
            ->with('mensaje', 'Pérdida de personal creada con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-perdida-personal');
        $data = $this->repository->findOrFail($id);
        $this->assertAccesoRegistro($data);
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, PerdidaPersonalListadoFiltros::class);

        return view('caja.perdida_personal.editar', array_merge(
            $this->datosFormulario($data),
            compact('data', 'filtrosQuery')
        ));
    }

    public function actualizar(ValidacionPerdidaPersonal $request, $id)
    {
        can('actualizar-perdida-personal');
        $data = $this->repository->findOrFail($id);
        $this->assertAccesoRegistro($data);
        $this->assertEmpresaPermitida((int) $request->input('empresa_id'));
        $this->repository->update($request->validated(), $id);

        return redirect()->route('perdida_personal', QueryRetornoListado::desdeRequest($request, PerdidaPersonalListadoFiltros::class))
            ->with('mensaje', 'Pérdida de personal actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-perdida-personal');

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
     * Empleados de sueldos por empresa (empleado + supervisor selects).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function empleadosPorEmpresa(Request $request)
    {
        if (! can('crear-perdida-personal', false)
            && ! can('editar-perdida-personal', false)
            && ! can('actualizar-perdida-personal', false)
            && ! can('listar-perdida-personal', false)) {
            abort(403);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json([]);
        }

        $empleados = Empleado_Sueldos::query()
            ->select(['id', 'legajo', 'nombre'])
            ->where('empresa_id', $empresaId)
            ->orderBy('legajo')
            ->limit(2000)
            ->get()
            ->map(fn ($e) => [
                'id' => (int) $e->id,
                'legajo' => (int) $e->legajo,
                'nombre' => (string) $e->nombre,
            ])
            ->values()
            ->all();

        return response()->json($empleados);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(PerdidaPersonal $data): array
    {
        $empresaId = (int) old('empresa_id', $data->empresa_id ?? 0);
        $empleados = collect();
        if ($empresaId > 0 && $this->empresaRepository->empresaIdPermitida($empresaId)) {
            $empleados = Empleado_Sueldos::query()
                ->select(['id', 'legajo', 'nombre'])
                ->where('empresa_id', $empresaId)
                ->orderBy('legajo')
                ->limit(2000)
                ->get();
        }

        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'conceptos' => ConceptoPerdida::query()->orderBy('codigo')->get(),
            'imputaciones' => ImputacionPerdida::query()->orderBy('codigo')->get(),
            'centroscosto' => Centrocosto::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'empleados' => $empleados,
            'turno_enum' => PerdidaPersonal::$enumTurno,
            'estado_enum' => PerdidaPersonal::$enumEstado,
            'conceptos_con_maquina' => PerdidaPersonal::CONCEPTOS_CON_MAQUINA,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = PerdidaPersonalListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
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

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }

    private function assertAccesoRegistro(PerdidaPersonal $data): void
    {
        $this->assertEmpresaPermitida((int) $data->empresa_id);
    }
}
