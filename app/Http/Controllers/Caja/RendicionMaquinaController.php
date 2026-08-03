<?php

namespace App\Http\Controllers\Caja;

use App\Exports\Caja\RendicionMaquinaListadoExport;
use App\Http\Controllers\Controller;
use App\Models\Caja\RendicionMaquina;
use App\Repositories\Caja\RendicionMaquinaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\RendicionMaquina\RendicionMaquinaService;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaAjusteWigosSupport;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaTurno;
use App\Support\Caja\RendicionMaquinaListadoFiltros;
use App\Support\Listado\QueryRetornoListado;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RendicionMaquinaController extends Controller
{
    public function __construct(
        private readonly RendicionMaquinaRepositoryInterface $repository,
        private readonly RendicionMaquinaService $service,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('listar-rendicion-maquina');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeRendicionMaquina($filtros, true);

        return view('caja.rendicion_maquina.index', [
            'datas' => $datas,
            'estado_enum' => RendicionMaquina::$enumEstado,
            'filtros' => $filtros,
            'filtrosQuery' => RendicionMaquinaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => RendicionMaquinaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-rendicion-maquina');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeRendicionMaquina($filtros, false);
                $view = \View::make('caja.rendicion_maquina.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_rendicion_maquina';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new RendicionMaquinaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('rendiciones_maquina.xlsx');

            case 'CSV':
                return (new RendicionMaquinaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('rendiciones_maquina.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('rendicion_maquina', RendicionMaquinaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-rendicion-maquina');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fecha = (string) $request->input('fecha', date('Y-m-d'));
        $turno = (string) $request->input('turno', RendicionMaquinaTurno::MANIANA);

        if ($empresaId > 0) {
            $this->assertAccesoEmpresa($empresaId);
        } else {
            $empresaId = $this->resolverEmpresaDefaultId($this->empresaRepository->allFiltrado());
        }

        try {
            $turno = RendicionMaquinaTurno::normalizar($turno);
        } catch (InvalidArgumentException) {
            $turno = RendicionMaquinaTurno::MANIANA;
        }

        $datos = $empresaId > 0
            ? $this->service->datosPantalla($empresaId, $fecha, $turno, null)
            : null;

        return view('caja.rendicion_maquina.cargar', [
            'modo_edicion' => false,
            'rendicion_id' => 0,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'empresa_id' => $empresaId,
            'fecha' => $fecha,
            'turno' => $turno,
            'datos' => $datos,
            'filtrosQuery' => QueryRetornoListado::desdeRequest($request, RendicionMaquinaListadoFiltros::class),
            'puede_ajustar_wigos' => RendicionMaquinaAjusteWigosSupport::usuarioPuedeAjustar(),
            'puede_ver_log_wigos' => RendicionMaquinaAjusteWigosSupport::usuarioPuedeVerLog(),
        ]);
    }

    public function editar(Request $request, $id)
    {
        can('editar-rendicion-maquina');

        $rendicion = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $rendicion->empresa_id);

        $datos = $this->service->datosPantalla(
            (int) $rendicion->empresa_id,
            $rendicion->fecha?->format('Y-m-d'),
            (string) $rendicion->turno,
            (int) $rendicion->id,
        );

        return view('caja.rendicion_maquina.cargar', [
            'modo_edicion' => true,
            'rendicion_id' => (int) $rendicion->id,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'empresa_id' => (int) $rendicion->empresa_id,
            'fecha' => $rendicion->fecha?->format('Y-m-d') ?? date('Y-m-d'),
            'turno' => (string) $rendicion->turno,
            'datos' => $datos,
            'filtrosQuery' => QueryRetornoListado::desdeRequest($request, RendicionMaquinaListadoFiltros::class),
            'puede_ajustar_wigos' => RendicionMaquinaAjusteWigosSupport::usuarioPuedeAjustar(),
            'puede_ver_log_wigos' => RendicionMaquinaAjusteWigosSupport::usuarioPuedeVerLog(),
        ]);
    }

    public function apiCalcular(Request $request)
    {
        if (! can('crear-rendicion-maquina', false) && ! can('actualizar-rendicion-maquina', false)) {
            abort(403);
        }

        $request->validate([
            'empresa_id' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'turno' => ['required', 'string', 'max:1'],
        ]);

        $empresaId = (int) $request->input('empresa_id');
        $this->assertAccesoEmpresa($empresaId);

        try {
            $resultado = $this->service->calcularDesdePayload($request->all());
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'totales' => $resultado->totalesCierre(),
            'variables' => $resultado->variables,
            'rastro' => $resultado->rastro,
            'modo_wigos' => $resultado->modoWigos,
        ]);
    }

    public function apiGuardar(Request $request)
    {
        $id = (int) $request->input('id', 0);
        if ($id > 0) {
            can('actualizar-rendicion-maquina');
            $existente = $this->repository->findOrFail($id);
            $this->assertAccesoEmpresa((int) $existente->empresa_id);
        } else {
            can('crear-rendicion-maquina');
        }

        $request->validate([
            'empresa_id' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'turno' => ['required', 'string', 'max:1'],
        ]);

        $empresaId = (int) $request->input('empresa_id');
        $this->assertAccesoEmpresa($empresaId);

        try {
            $rendicion = $this->repository->guardar(
                $request->all(),
                $id > 0 ? $id : null,
                (int) auth()->id(),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }

        $respuesta = [
            'ok' => true,
            'id' => (int) $rendicion->id,
            'codigo' => $rendicion->codigo,
            'nro_oper_anita' => (int) ($rendicion->nro_oper_anita ?? 0),
            'mensaje' => $id > 0 ? 'Rendición actualizada.' : 'Rendición guardada.',
            'url_index' => route('rendicion_maquina'),
            'url_editar' => route('editar_rendicion_maquina', ['id' => $rendicion->id]),
        ];

        if (can('imprimir-rendicion-maquina', false)) {
            $respuesta['url_comprobante_pdf'] = route('imprimir_rendicion_maquina', [
                'id' => $rendicion->id,
                'inline' => 1,
            ]);
        }

        return response()->json($respuesta);
    }

    public function apiTraerWigos(Request $request)
    {
        if (! can('crear-rendicion-maquina', false) && ! can('actualizar-rendicion-maquina', false)) {
            abort(403);
        }

        $request->validate([
            'empresa_id' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'turno' => ['required', 'string', 'max:1'],
        ]);

        $empresaId = (int) $request->input('empresa_id');
        $this->assertAccesoEmpresa($empresaId);

        $fecha = (string) $request->input('fecha');
        $turno = (string) $request->input('turno');

        try {
            $turno = RendicionMaquinaTurno::normalizar($turno);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $inputsActuales = is_array($request->input('inputs'))
            ? $request->input('inputs')
            : null;

        $datos = $this->service->traerWigos($empresaId, $fecha, $turno, $inputsActuales);

        return response()->json([
            'ok' => true,
            'inputs' => $datos['inputs'],
            'wigos_json' => $datos['wigos_json'],
            'previas' => $datos['previas'] ?? [],
            'calc_orquestador' => $datos['calc_orquestador'] ?? [],
            'meta' => $datos['meta'],
            'mensaje' => $datos['meta']['mensaje'] ?? null,
        ]);
    }

    public function imprimir(Request $request, int $id)
    {
        can('imprimir-rendicion-maquina');

        $rendicion = RendicionMaquina::query()
            ->with([
                'empresa',
                'valores.cuentacaja',
                'gastos.aperturaGasto',
                'creoUsuario',
                'supervisorUsuario',
                'auxiliarUsuario',
                'cajeroUsuario',
            ])
            ->findOrFail($id);

        $this->assertAccesoEmpresa((int) $rendicion->empresa_id);

        $view = view('caja.rendicion_maquina.comprobante', ['rendicion' => $rendicion])->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'portrait');
        $pdf->loadHTML($view);

        $nombre = 'rendicion_maquina_'.($rendicion->codigo ?: $id).'.pdf';

        if ($request->boolean('inline')) {
            return $pdf->stream($nombre);
        }

        return $pdf->download($nombre);
    }

    /**
     * Recarga valores (cuentacaja) y gastos de apertura al cambiar empresa en alta.
     */
    public function apiLineasEmpresa(Request $request)
    {
        if (! can('crear-rendicion-maquina', false) && ! can('actualizar-rendicion-maquina', false)) {
            abort(403);
        }

        $request->validate([
            'empresa_id' => ['required', 'integer', 'min:1'],
        ]);

        $empresaId = (int) $request->input('empresa_id');
        $this->assertAccesoEmpresa($empresaId);

        $lineas = $this->service->lineasValorYGastoParaEmpresa($empresaId);

        return response()->json([
            'ok' => true,
            'cuentas_valor' => $lineas['cuentas_valor'],
            'gastos' => $lineas['gastos'],
        ]);
    }

    public function apiAjustes(Request $request)
    {
        if (! RendicionMaquinaAjusteWigosSupport::usuarioPuedeVerLog()) {
            abort(403);
        }

        if ($request->isMethod('post')) {
            if (! RendicionMaquinaAjusteWigosSupport::usuarioPuedeAjustar()) {
                abort(403);
            }

            $request->validate([
                'empresa_id' => ['required', 'integer', 'min:1'],
                'fecha' => ['required', 'date'],
                'turno' => ['required', 'string', 'max:1'],
                'campo' => ['required', 'string'],
                'valor_wigos' => ['required', 'numeric'],
                'valor_ajustado' => ['required', 'numeric'],
            ]);

            $this->assertAccesoEmpresa((int) $request->input('empresa_id'));

            try {
                $registro = RendicionMaquinaAjusteWigosSupport::registrar([
                    'rendicion_maquina_id' => (int) $request->input('rendicion_maquina_id', 0) ?: null,
                    'empresa_id' => (int) $request->input('empresa_id'),
                    'fecha' => (string) $request->input('fecha'),
                    'turno' => (string) $request->input('turno'),
                    'campo' => (string) $request->input('campo'),
                    'valor_wigos' => (float) $request->input('valor_wigos'),
                    'valor_ajustado' => (float) $request->input('valor_ajustado'),
                    'motivo' => $request->input('motivo'),
                    'usuario_id' => (int) auth()->id(),
                ]);
            } catch (\Throwable $e) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
            }

            return response()->json(['ok' => true, 'registro' => $registro]);
        }

        $rendicionId = (int) $request->input('rendicion_maquina_id', 0);
        $empresaId = (int) $request->input('empresa_id', 0);
        $fecha = $request->input('fecha');
        $turno = $request->input('turno');

        if ($empresaId > 0) {
            $this->assertAccesoEmpresa($empresaId);
        }

        $ajustes = RendicionMaquinaAjusteWigosSupport::listarPorRendicion(
            $rendicionId > 0 ? $rendicionId : null,
            $empresaId > 0 ? $empresaId : null,
            is_string($fecha) ? $fecha : null,
            is_string($turno) ? $turno : null,
        );

        return response()->json([
            'ok' => true,
            'ajustes' => $ajustes->map(fn ($row) => [
                'id' => (int) $row->id,
                'campo' => $row->campo,
                'etiqueta' => $row->etiqueta,
                'valor_wigos' => (float) $row->valor_wigos,
                'valor_ajustado' => (float) $row->valor_ajustado,
                'delta' => (float) $row->delta,
                'motivo' => $row->motivo,
                'usuario' => $row->usuario?->nombre,
                'created_at' => optional($row->created_at)->format('d/m/Y H:i'),
            ])->values(),
        ]);
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-rendicion-maquina');

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
        $empresaQuery = $this->empresaRepository->allFiltrado();
        $empresaDefault = optional($empresaQuery->first())->id;
        $filtros = RendicionMaquinaListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $empresaDefault ? (int) $empresaDefault : null
        );

        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        $filtros['empresas_asignadas'] = $asignadas;

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0 && $asignadas !== [] && ! in_array($empresaId, $asignadas, true)) {
            $filtros['empresa_id'] = $this->resolverEmpresaDefaultId($empresaQuery);
            $filtros['empresa_scope'] = ((int) $filtros['empresa_id']) > 0 ? 'una' : 'todas';
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
