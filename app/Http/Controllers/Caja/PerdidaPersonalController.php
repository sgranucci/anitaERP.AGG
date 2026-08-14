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

    public function consultarCatalogo(Request $request)
    {
        $this->assertPuedeConsultarCatalogos();

        $tipo = (string) $request->query('tipo', '');
        $empresaId = (int) $request->query('empresa_id', 0);
        $consulta = trim((string) $request->query('consulta', ''));

        $query = $this->queryCatalogo($tipo, $empresaId);
        if ($consulta !== '') {
            $query->where(function ($q) use ($tipo, $consulta) {
                $q->where('nombre', 'like', '%'.$consulta.'%');
                if ($tipo === 'empleado') {
                    $q->orWhere('legajo', 'like', '%'.$consulta.'%')
                        ->orWhere('documento', 'like', '%'.$consulta.'%');
                } else {
                    $q->orWhere('codigo', 'like', '%'.$consulta.'%');
                }
            });
        }

        $campoCodigo = $tipo === 'empleado' ? 'legajo' : 'codigo';
        $filas = $query
            ->orderBy($campoCodigo)
            ->limit(100)
            ->get()
            ->map(fn ($fila) => [
                'id' => (int) $fila->id,
                'codigo' => (string) $fila->{$campoCodigo},
                'nombre' => (string) $fila->nombre,
                'consultar_url' => $this->urlConsultaAbmCatalogo($tipo, (int) $fila->id),
            ])
            ->values();

        return response()->json(['data' => $filas]);
    }

    public function resolverCatalogo(Request $request)
    {
        $this->assertPuedeConsultarCatalogos();

        $tipo = (string) $request->query('tipo', '');
        $empresaId = (int) $request->query('empresa_id', 0);
        $valor = trim((string) $request->query('valor', ''));
        if ($valor === '') {
            return response()->json(['ok' => false]);
        }

        $campoCodigo = $tipo === 'empleado' ? 'legajo' : 'codigo';
        $fila = $this->queryCatalogo($tipo, $empresaId)
            ->where($campoCodigo, $valor)
            ->orderBy('id')
            ->first();

        if ($fila === null) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No se encontró el registro indicado.',
            ]);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $fila->id,
            'codigo' => (string) $fila->{$campoCodigo},
            'nombre' => (string) $fila->nombre,
            'consultar_url' => $this->urlConsultaAbmCatalogo($tipo, (int) $fila->id),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(PerdidaPersonal $data): array
    {
        $empresaId = (int) old('empresa_id', $data->empresa_id ?? 0);
        $centrocostoId = (int) old('centrocosto_id', $data->centrocosto_id ?? 0);
        $imputacionId = (int) old('imputacion_perdida_id', $data->imputacion_perdida_id ?? 0);
        $conceptoId = (int) old('concepto_perdida_id', $data->concepto_perdida_id ?? 0);
        $empleadoId = (int) old('empleado_sueldos_id', $data->empleado_sueldos_id ?? 0);
        $supervisorId = (int) old('supervisor_empleado_sueldos_id', $data->supervisor_empleado_sueldos_id ?? 0);

        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'centrocostoSeleccionado' => $centrocostoId > 0
                ? Centrocosto::query()->select('id', 'codigo', 'nombre')->find($centrocostoId)
                : null,
            'imputacionSeleccionada' => $imputacionId > 0
                ? ImputacionPerdida::query()->select('id', 'codigo', 'nombre')->find($imputacionId)
                : null,
            'conceptoSeleccionado' => $conceptoId > 0
                ? ConceptoPerdida::query()->select('id', 'codigo', 'nombre')->find($conceptoId)
                : null,
            'empleadoSeleccionado' => $this->empleadoSeleccionado($empleadoId, $empresaId),
            'supervisorSeleccionado' => $this->empleadoSeleccionado($supervisorId, $empresaId),
            'turno_enum' => PerdidaPersonal::$enumTurno,
            'estado_enum' => PerdidaPersonal::$enumEstado,
            'conceptos_con_maquina' => PerdidaPersonal::CONCEPTOS_CON_MAQUINA,
        ];
    }

    private function queryCatalogo(string $tipo, int $empresaId)
    {
        if ($tipo === 'concepto') {
            return ConceptoPerdida::query()->select('id', 'codigo', 'nombre');
        }

        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }

        if ($tipo === 'imputacion') {
            return ImputacionPerdida::query()
                ->select('imputacion_perdida.id', 'imputacion_perdida.codigo', 'imputacion_perdida.nombre')
                ->whereHas('empresas', fn ($q) => $q->where('empresa_id', $empresaId));
        }

        if ($tipo === 'empleado') {
            return Empleado_Sueldos::query()
                ->select('id', 'legajo', 'nombre', 'documento')
                ->where('empresa_id', $empresaId);
        }

        abort(404);
    }

    private function empleadoSeleccionado(int $id, int $empresaId): ?Empleado_Sueldos
    {
        if ($id <= 0 || $empresaId <= 0) {
            return null;
        }

        return Empleado_Sueldos::query()
            ->select('id', 'legajo', 'nombre', 'empresa_id')
            ->where('empresa_id', $empresaId)
            ->find($id);
    }

    private function urlConsultaAbmCatalogo(string $tipo, int $id): ?string
    {
        if ($tipo === 'concepto' && can('editar-concepto-perdida', false)) {
            return route('editar_concepto_perdida', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
        }

        if ($tipo === 'imputacion' && can('editar-imputacion-perdida', false)) {
            return route('editar_imputacion_perdida', [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
        }

        if ($tipo === 'empleado' && can('editar-empleado-sueldos', false)) {
            return route('editar_empleado_sueldos', ['id' => $id]);
        }

        return null;
    }

    private function assertPuedeConsultarCatalogos(): void
    {
        foreach ([
            'crear-perdida-personal',
            'editar-perdida-personal',
            'actualizar-perdida-personal',
            'listar-perdida-personal',
        ] as $permiso) {
            if (can($permiso, false)) {
                return;
            }
        }

        abort(403);
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
