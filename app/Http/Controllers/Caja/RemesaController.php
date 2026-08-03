<?php

declare(strict_types=1);

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\RemesaListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\Remesa;
use App\Repositories\Caja\RemesaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\Remesa\RemesaConfiguracionService;
use App\Services\Caja\Remesa\RemesaService;
use App\Support\Caja\Remesa\RemesaSupport;
use App\Support\Caja\RemesaListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RemesaController extends Controller
{
    public function __construct(
        private readonly RemesaRepositoryInterface $repository,
        private readonly RemesaService $service,
        private readonly RemesaConfiguracionService $configuracionService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-remesa');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeRemesa($filtros, true);

        return view('caja.remesa.index', [
            'datas' => $datas,
            'tipo_enum' => Remesa::$enumTipo,
            'estado_enum' => Remesa::$enumEstado,
            'filtros' => $filtros,
            'filtrosQuery' => RemesaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => RemesaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-remesa');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeRemesa($filtros, false);
                $view = \View::make('caja.remesa.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_remesa';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new RemesaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('remesas.xlsx');

            case 'CSV':
                return (new RemesaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('remesas.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('remesa', RemesaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-remesa');

        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId > 0) {
            $this->assertAccesoEmpresa($empresaId);
        } else {
            $empresaId = $this->resolverEmpresaDefaultId($this->empresaRepository->allFiltrado());
        }

        $tipo = (string) $request->input('tipo', RemesaSupport::TIPO_EXTERNA);
        $datos = $empresaId > 0 ? $this->service->datosPantalla($empresaId, null, $tipo) : null;

        return view('caja.remesa.cargar', [
            'modo_edicion' => false,
            'remesa_id' => 0,
            'remesa' => null,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'empresa_id' => $empresaId,
            'fecha' => (string) $request->input('fecha', date('Y-m-d')),
            'tipo' => $tipo,
            'datos' => $datos,
            'filtrosQuery' => QueryRetornoListado::desdeRequest($request, RemesaListadoFiltros::class),
        ]);
    }

    public function guardar(Request $request)
    {
        can('crear-remesa');

        $empresaId = (int) $request->input('empresa_id', 0);
        $this->assertAccesoEmpresa($empresaId);

        try {
            $remesa = $this->service->guardar($request->all(), (int) auth()->id(), null);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errores', $e->getMessage());
        }

        return redirect()
            ->route('remesa', QueryRetornoListado::desdeRequest($request, RemesaListadoFiltros::class))
            ->with('mensaje', 'Remesa '.$remesa->numero.' creada con éxito.');
    }

    public function editar(Request $request, $id)
    {
        can('editar-remesa');

        $remesa = $this->repository->findOrFail((int) $id);
        $this->assertAccesoEmpresa((int) $remesa->empresa_id);

        $tipo = (string) $remesa->tipo;
        $datos = $this->service->datosPantalla((int) $remesa->empresa_id, $remesa, $tipo);

        return view('caja.remesa.cargar', [
            'modo_edicion' => true,
            'remesa_id' => (int) $remesa->id,
            'remesa' => $remesa,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'empresa_id' => (int) $remesa->empresa_id,
            'fecha' => $remesa->fecha?->format('Y-m-d') ?? date('Y-m-d'),
            'tipo' => $tipo,
            'datos' => $datos,
            'filtrosQuery' => QueryRetornoListado::desdeRequest($request, RemesaListadoFiltros::class),
        ]);
    }

    public function actualizar(Request $request, $id)
    {
        can('actualizar-remesa');

        $remesa = $this->repository->findOrFail((int) $id);
        $this->assertAccesoEmpresa((int) $remesa->empresa_id);

        try {
            $this->service->guardar($request->all(), (int) auth()->id(), (int) $id);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errores', $e->getMessage());
        }

        return redirect()
            ->route('remesa', QueryRetornoListado::desdeRequest($request, RemesaListadoFiltros::class))
            ->with('mensaje', 'Remesa actualizada con éxito.');
    }

    public function revertir(Request $request, $id)
    {
        can('revertir-remesa');

        $remesa = $this->repository->findOrFail((int) $id);
        $this->assertAccesoEmpresa((int) $remesa->empresa_id);

        try {
            $this->service->revertir((int) $id);
        } catch (InvalidArgumentException $e) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
            }

            return redirect()
                ->back()
                ->with('errores', $e->getMessage());
        }

        if ($request->ajax()) {
            return response()->json(['mensaje' => 'ok']);
        }

        return redirect()
            ->route('remesa', QueryRetornoListado::desdeRequest($request, RemesaListadoFiltros::class))
            ->with('mensaje', 'Remesa revertida: se grabaron asiento y movimiento de caja compensatorios con fecha de hoy.');
    }

    public function anular(Request $request, $id)
    {
        can('anular-remesa');

        $remesa = $this->repository->findOrFail((int) $id);
        $this->assertAccesoEmpresa((int) $remesa->empresa_id);

        try {
            $this->service->anular((int) $id);
        } catch (InvalidArgumentException $e) {
            if ($request->ajax()) {
                return response()->json(['mensaje' => 'ng', 'error' => $e->getMessage()], 422);
            }

            return redirect()
                ->back()
                ->with('errores', $e->getMessage());
        }

        if ($request->ajax()) {
            return response()->json(['mensaje' => 'ok']);
        }

        return redirect()
            ->route('remesa', QueryRetornoListado::desdeRequest($request, RemesaListadoFiltros::class))
            ->with('mensaje', 'Remesa eliminada físicamente (asiento, ctamov y movimientos de caja).');
    }

    public function configurar(Request $request)
    {
        can('configurar-remesa');

        return view('caja.remesa.configurar', [
            'grupos' => $this->configuracionService->grupos(),
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'filtrosQuery' => QueryRetornoListado::desdeRequest($request, RemesaListadoFiltros::class),
        ]);
    }

    public function configurarAgregar(Request $request)
    {
        can('configurar-remesa');

        $request->validate([
            'clave' => ['required', 'string', 'in:destino,origen_externa,origen_interna'],
            'cuentacaja_id' => ['nullable', 'integer', 'min:1'],
            'codigo' => ['nullable', 'string', 'max:40'],
            'empresa_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $clave = (string) $request->input('clave');
        $empresaId = (int) $request->input('empresa_id', 0);

        try {
            if ((int) $request->input('cuentacaja_id', 0) > 0) {
                $this->configuracionService->agregar($clave, (int) $request->input('cuentacaja_id'));
            } else {
                $this->configuracionService->agregarPorCodigo(
                    $clave,
                    (string) $request->input('codigo', ''),
                    $empresaId > 0 ? $empresaId : null
                );
            }
        } catch (InvalidArgumentException $e) {
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
            }

            return redirect()
                ->route('configurar_remesa', QueryRetornoListado::desdeRequest($request, RemesaListadoFiltros::class))
                ->with('error', $e->getMessage());
        }

        if ($request->ajax()) {
            return response()->json(['ok' => true, 'mensaje' => 'Cuenta vinculada al uso de remesa.']);
        }

        return redirect()
            ->route('configurar_remesa', QueryRetornoListado::desdeRequest($request, RemesaListadoFiltros::class))
            ->with('mensaje', 'Cuenta vinculada al uso de remesa.');
    }

    public function configurarQuitar(Request $request)
    {
        can('configurar-remesa');

        $request->validate([
            'clave' => ['required', 'string', 'in:destino,origen_externa,origen_interna'],
            'cuentacaja_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->configuracionService->quitar(
                (string) $request->input('clave'),
                (int) $request->input('cuentacaja_id')
            );
        } catch (InvalidArgumentException $e) {
            if ($request->ajax()) {
                return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
            }

            return redirect()
                ->route('configurar_remesa', QueryRetornoListado::desdeRequest($request, RemesaListadoFiltros::class))
                ->with('error', $e->getMessage());
        }

        if ($request->ajax()) {
            return response()->json(['ok' => true, 'mensaje' => 'Cuenta desafectada del uso de remesa.']);
        }

        return redirect()
            ->route('configurar_remesa', QueryRetornoListado::desdeRequest($request, RemesaListadoFiltros::class))
            ->with('mensaje', 'Cuenta desafectada del uso de remesa (solo se quitó el vínculo).');
    }

    public function apiLineasEmpresa(Request $request)
    {
        if (! can('crear-remesa', false)
            && ! can('editar-remesa', false)
            && ! can('actualizar-remesa', false)) {
            abort(403);
        }

        $request->validate([
            'empresa_id' => ['required', 'integer', 'min:1'],
            'tipo' => ['nullable', 'string', 'in:I,M'],
        ]);

        $empresaId = (int) $request->input('empresa_id');
        $this->assertAccesoEmpresa($empresaId);

        $remesaId = (int) $request->input('remesa_id', 0);
        $remesa = $remesaId > 0 ? $this->repository->find($remesaId) : null;
        $tipo = (string) $request->input('tipo', $remesa?->tipo ?? RemesaSupport::TIPO_EXTERNA);

        $datos = $this->service->datosPantalla($empresaId, $remesa, $tipo);

        return response()->json([
            'ok' => true,
            'destino' => $datos['destino'],
            'origen' => $datos['origen'],
            'uso_origen' => $datos['uso_origen'],
            'totales' => $datos['totales'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaDefault = $this->resolverEmpresaDefaultId($empresaQuery);
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();

        $filtros = RemesaListadoFiltros::resolverDesdeRequest($request, $busquedaRuta, $empresaDefault);
        $filtros['empresas_asignadas'] = $asignadas;

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if (
            ($filtros['empresa_scope'] ?? 'una') === 'una'
            && $empresaId > 0
            && count($asignadas) >= 1
            && ! in_array($empresaId, $asignadas, true)
        ) {
            $filtros['empresa_id'] = $empresaDefault > 0 ? $empresaDefault : null;
            if ($filtros['empresa_id'] === null) {
                $filtros['empresa_scope'] = 'todas';
            }
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
            abort(422, 'Empresa inválida.');
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }
}
