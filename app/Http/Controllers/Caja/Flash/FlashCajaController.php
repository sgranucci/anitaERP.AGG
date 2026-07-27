<?php

namespace App\Http\Controllers\Caja\Flash;

use App\Exports\Caja\Flash\FlashCajaHistoricoDiarioExport;
use App\Exports\Caja\Flash\FlashCajaListadoExport;
use App\Exports\Caja\Flash\FlashCajaReporteExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionFlashCaja;
use App\Models\Caja\Flash\FlashCaja;
use App\Repositories\Caja\Flash\FlashCajaRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\Flash\FlashCajaCalculoService;
use App\Support\Caja\Flash\FlashCajaHistoricoFiltros;
use App\Support\Caja\Flash\FlashCajaListadoFiltros;
use App\Support\Caja\Flash\FlashCajaReporteSupport;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\QueryRetornoListado;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;

class FlashCajaController extends Controller
{
    public function __construct(
        private readonly FlashCajaRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly FlashCajaCalculoService $calculoService,
    ) {}

    public function index(Request $request)
    {
        can('listar-flash-caja');

        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeFlashCaja($filtros, true);

        return view('caja.flash.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => FlashCajaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => FlashCajaListadoFiltros::CAMPOS,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        can('listar-flash-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->resolverFiltrosListado($request, $busqueda);

        switch ($formato) {
            case 'PDF':
                $datas = $this->repository->leeFlashCaja($filtros, false);
                $view = \View::make('caja.flash.listado', compact('datas'))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'listado_flash_caja';

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
                return (new FlashCajaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('flash_caja.xlsx');

            case 'CSV':
                return (new FlashCajaListadoExport($this->repository))
                    ->parametros($filtros)
                    ->download('flash_caja.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return redirect()->route('flash_caja', FlashCajaListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        can('crear-flash-caja');

        $data = new FlashCaja();
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, FlashCajaListadoFiltros::class);

        return view('caja.flash.crear', compact('data', 'empresa_query', 'filtrosQuery'));
    }

    public function guardar(ValidacionFlashCaja $request)
    {
        can('crear-flash-caja');

        $payload = $this->armarPayload($request);
        $this->repository->create($payload);

        return redirect()->route('flash_caja', QueryRetornoListado::desdeRequest($request, FlashCajaListadoFiltros::class))
            ->with('mensaje', 'Flash diario creado con éxito');
    }

    public function editar(Request $request, $id)
    {
        can('editar-flash-caja');

        $data = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $data->empresa_id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtrosQuery = QueryRetornoListado::desdeRequest($request, FlashCajaListadoFiltros::class);

        return view('caja.flash.editar', compact('data', 'empresa_query', 'filtrosQuery'));
    }

    public function actualizar(ValidacionFlashCaja $request, $id)
    {
        can('actualizar-flash-caja');

        $data = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $data->empresa_id);

        $payload = $this->armarPayload($request, $data);
        $this->repository->update($payload, $id);

        return redirect()->route('flash_caja', QueryRetornoListado::desdeRequest($request, FlashCajaListadoFiltros::class))
            ->with('mensaje', 'Flash diario actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-flash-caja');

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

    public function apiCalcular(Request $request)
    {
        if (! can('crear-flash-caja', false) && ! can('actualizar-flash-caja', false)) {
            abort(403);
        }

        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '512M');

        $request->validate([
            'empresa_id' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
        ]);

        $empresaId = (int) $request->input('empresa_id');
        $this->assertAccesoEmpresa($empresaId);

        $fecha = (string) $request->input('fecha');

        try {
            $calculado = $this->calculoService->calcular($empresaId, $fecha);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'datos' => $calculado,
        ]);
    }

    public function reporte(Request $request, $id, $formato = 'PDF')
    {
        can('exportar-reporte-flash-caja');

        $flash = $this->repository->findOrFail($id);
        $this->assertAccesoEmpresa((int) $flash->empresa_id);
        $conSeason = ! $request->has('con_season') || (int) $request->input('con_season') === 1;
        $reporte = FlashCajaReporteSupport::armar($flash, $conSeason);

        return match (strtoupper((string) $formato)) {
            'EXCEL' => (new FlashCajaReporteExport($reporte))
                ->download('flash_reporte_'.$flash->id.'.xlsx'),
            'CSV' => (new FlashCajaReporteExport($reporte))
                ->download('flash_reporte_'.$flash->id.'.csv', Excel::CSV),
            default => $this->descargarReportePdf(
                view('caja.flash.reporte', $reporte)->render(),
                'flash_reporte_'.$flash->id,
            ),
        };
    }

    public function reporteHistorico(Request $request)
    {
        can('exportar-reporte-flash-caja');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = FlashCajaHistoricoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsHistorico($filtros);

        $consultado = $request->boolean('consultar')
            && FlashCajaHistoricoFiltros::tieneCriteriosAplicados($filtros);

        $reporte = null;
        $empresaNombre = null;

        if ($consultado) {
            $this->assertAccesoEmpresa((int) $filtros['empresa_id']);
            $reporte = $this->generarReporteHistorico($filtros);
            $empresaNombre = $reporte['empresa']->nombre ?? null;
        }

        return view('caja.flash.reporte_historico.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => FlashCajaHistoricoFiltros::paraQueryString($filtros),
            'consultado' => $consultado,
            'reporte' => $reporte,
            'subtitulo' => FlashCajaHistoricoFiltros::subtitulo($filtros, $empresaNombre),
        ]);
    }

    public function exportarReporteHistorico(Request $request, ?string $formato = null)
    {
        can('exportar-reporte-flash-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = FlashCajaHistoricoFiltros::resolverDesdeRequest($request);
        if (! FlashCajaHistoricoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('flash_caja_reporte_historico');
        }

        $this->assertAccesoEmpresa((int) $filtros['empresa_id']);
        $reporte = $this->generarReporteHistorico($filtros);
        $slug = 'flash_historico_'.$filtros['empresa_id'].'_'.$filtros['fecha_desde'].'_'.$filtros['fecha_hasta'];

        switch (strtoupper((string) $formato)) {
            case 'EXCEL':
                return (new FlashCajaHistoricoDiarioExport($reporte))
                    ->download($slug.'.xlsx');

            case 'CSV':
                return (new FlashCajaHistoricoDiarioExport($reporte))
                    ->download($slug.'.csv', Excel::CSV);

            case 'PDF':
                $html = view('caja.flash.reporte_historico.listado', compact('reporte'))->render();

                return $this->descargarReportePdf($html, $slug);

            default:
                return redirect()->route('flash_caja_reporte_historico', FlashCajaHistoricoFiltros::paraQueryString($filtros));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function generarReporteHistorico(array $filtros): array
    {
        $empresaId = (int) $filtros['empresa_id'];
        $filas = $this->repository->leeFlashPorRango(
            $empresaId,
            (string) $filtros['fecha_desde'],
            (string) $filtros['fecha_hasta'],
        );

        $empresa = $this->empresaRepository->find($empresaId);

        return FlashCajaReporteSupport::armarHistorico(
            $filas,
            $empresa,
            (string) $filtros['fecha_desde'],
            (string) $filtros['fecha_hasta'],
            (int) ($filtros['con_season'] ?? 1) === 1,
        );
    }

    /**
     * @param  array{empresa_id: int, fecha_desde: string, fecha_hasta: string, con_season?: int}  $filtros
     * @return array{empresa_id: int, fecha_desde: string, fecha_hasta: string, con_season: int}
     */
    private function aplicarDefaultsHistorico(array $filtros): array
    {
        if ($filtros['fecha_desde'] === '' && $filtros['fecha_hasta'] === '') {
            $filtros['fecha_desde'] = Carbon::today()->startOfMonth()->format('Y-m-d');
            $filtros['fecha_hasta'] = Carbon::today()->format('Y-m-d');
        }
        if (! isset($filtros['con_season'])) {
            $filtros['con_season'] = 1;
        }

        return $filtros;
    }

    private function descargarReportePdf(string $html, string $nombreBase)
    {
        $path = storage_path('pdf/listados');
        $nombrePdf = $nombreBase;

        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('legal', 'landscape');
        $pdf->loadHTML($html)->save($path.'/'.$nombrePdf.'.pdf');

        return response()->download($path.'/'.$nombrePdf.'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function armarPayload(ValidacionFlashCaja $request, ?FlashCaja $existente = null): array
    {
        $empresaId = (int) $request->input('empresa_id');
        $fecha = (string) $request->input('fecha');
        $this->assertAccesoEmpresa($empresaId);

        $payload = $request->only([
            'empresa_id',
            'fecha',
            'att',
            'comentario',
            'cotizacion',
            'pos_online',
            'show',
        ]);

        $camposCalculados = [
            'ayb', 'slot_coin_in', 'slot_d', 'slot_r', 'soft_count', 'hard_count', 'cant_slots',
            'rul_coin_in', 'rul_d', 'rul_r', 'soft_rul', 'hard_rul', 'cant_rul',
            'bingo_cant_carton', 'bingo_total_venta', 'bingo_resultado',
            'win_ol_slot', 'win_ol_rul', 'estac', 'vending', 'cant_vehic',
        ];

        // Crear sin valores previos → recalcular. Si ya calcularon/editaron en pantalla, respetar el form.
        $usarFormulario = $request->boolean('flash_valores_desde_formulario')
            && ! $request->boolean('recalcular');
        if ($existente !== null && ! $request->boolean('recalcular')) {
            $usarFormulario = true;
        }

        if ($usarFormulario) {
            $payload = array_merge($payload, $request->only($camposCalculados));
            if ($existente === null) {
                $payload['calculado_en'] = now();
            }
        } else {
            $calculado = $this->calculoService->calcular($empresaId, $fecha);
            unset($calculado['advertencias_wigos']);
            $payload = array_merge($payload, $calculado);
        }

        if ($existente === null) {
            $payload['creousuario_id'] = auth()->id();
        } else {
            $payload['actualizousuario_id'] = auth()->id();
        }

        unset($payload['recalcular'], $payload['flash_valores_desde_formulario']);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolverFiltrosListado(Request $request, ?string $busquedaRuta = null): array
    {
        $filtros = FlashCajaListadoFiltros::resolverDesdeRequest($request, $busquedaRuta);
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
