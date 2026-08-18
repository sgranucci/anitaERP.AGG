<?php

namespace App\Http\Controllers\Caja\Flash;

use App\Exports\Caja\Flash\FlashCajaDesgloseWigosExport;
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
use App\Support\Caja\Flash\FlashCajaOrigenTotalSupport;
use App\Support\Caja\Flash\FlashCajaReporteSupport;
use App\Support\Listado\QueryRetornoListado;
use App\Support\Reportes\ReportePreferenciasUsuario;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Jurosh\PDFMerge\PDFMerger;
use Maatwebsite\Excel\Excel;

class FlashCajaController extends Controller
{
    private const PREFERENCIAS_HISTORICO = 'flash_caja_historico';

    public function __construct(
        private readonly FlashCajaRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly FlashCajaCalculoService $calculoService,
    ) {}

    public function index(Request $request)
    {
        can('listar-flash-caja');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $filtros = $this->resolverFiltrosListado($request);
        $datas = $this->repository->leeFlashCaja($filtros, true);

        return view('caja.flash.index', [
            'datas' => $datas,
            'filtros' => $filtros,
            'filtrosQuery' => FlashCajaListadoFiltros::paraQueryString($filtros),
            'camposFiltro' => FlashCajaListadoFiltros::CAMPOS,
            'empresa_query' => $empresa_query,
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

    public function apiOrigenTotal(Request $request)
    {
        if (! can('crear-flash-caja', false) && ! can('actualizar-flash-caja', false)) {
            abort(403);
        }

        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '512M');

        $request->validate([
            'empresa_id' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
            'campo' => ['required', 'string', 'in:'.implode(',', FlashCajaOrigenTotalSupport::camposSoportados())],
            'valor_pantalla' => ['nullable', 'numeric'],
        ]);

        $empresaId = (int) $request->input('empresa_id');
        $this->assertAccesoEmpresa($empresaId);

        $valorPantalla = $request->filled('valor_pantalla')
            ? (float) $request->input('valor_pantalla')
            : null;

        try {
            $origen = FlashCajaOrigenTotalSupport::armar(
                $empresaId,
                (string) $request->input('fecha'),
                (string) $request->input('campo'),
                $valorPantalla,
            );
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'origen' => $origen,
        ]);
    }

    public function exportarDesgloseWigos(Request $request)
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
            return redirect()
                ->back()
                ->with('error', 'No se pudo armar el desglose Wigos: '.$e->getMessage());
        }

        $desglose = $calculado['desglose_wigos'] ?? null;
        if (! is_array($desglose)) {
            return redirect()
                ->back()
                ->with('error', 'El cálculo no devolvió desglose Wigos.');
        }

        $empresa = $this->empresaRepository->find($empresaId);
        $empresaNombre = (string) ($empresa->nombre ?? '');
        $slugFecha = str_replace('-', '', $fecha);

        return (new FlashCajaDesgloseWigosExport($desglose, $empresaNombre))
            ->download('flash_desglose_wigos_'.$empresaId.'_'.$slugFecha.'.xlsx');
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
        $filtros = $this->aplicarDefaultsHistorico($request, $filtros, $empresaQuery);

        $consultado = $request->boolean('consultar')
            && FlashCajaHistoricoFiltros::tieneCriteriosAplicados($filtros);

        $reporte = null;
        $empresasTexto = null;

        if ($consultado) {
            $this->assertAccesoEmpresas(FlashCajaHistoricoFiltros::empresaIds($filtros));
            ReportePreferenciasUsuario::persistir(self::PREFERENCIAS_HISTORICO, [
                'empresa_ids' => FlashCajaHistoricoFiltros::empresaIds($filtros),
                'consolidar_empresas' => (bool) ($filtros['consolidar_empresas'] ?? true),
            ]);
            $reporte = $this->generarReporteHistorico($filtros);
            $empresasTexto = $reporte['empresas_texto'] ?? null;
        }

        return view('caja.flash.reporte_historico.index', [
            'empresa_query' => $empresaQuery,
            'filtros' => $filtros,
            'filtrosQuery' => FlashCajaHistoricoFiltros::paraQueryString($filtros),
            'consultado' => $consultado,
            'reporte' => $reporte,
            'subtitulo' => FlashCajaHistoricoFiltros::subtitulo($filtros, $empresasTexto),
        ]);
    }

    public function exportarReporteHistorico(Request $request, ?string $formato = null)
    {
        can('exportar-reporte-flash-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaQuery = $this->empresaRepository->allFiltrado();
        $filtros = FlashCajaHistoricoFiltros::resolverDesdeRequest($request);
        $filtros = $this->aplicarDefaultsHistorico($request, $filtros, $empresaQuery);
        if (! FlashCajaHistoricoFiltros::tieneCriteriosAplicados($filtros)) {
            return redirect()->route('flash_caja_reporte_historico');
        }

        $empresaIds = FlashCajaHistoricoFiltros::empresaIds($filtros);
        $this->assertAccesoEmpresas($empresaIds);
        $reporte = $this->generarReporteHistorico($filtros);
        $slug = 'flash_historico_'.implode('-', $empresaIds).'_'.$filtros['fecha_desde'].'_'.$filtros['fecha_hasta'];

        switch (strtoupper((string) $formato)) {
            case 'EXCEL':
                return (new FlashCajaHistoricoDiarioExport($reporte))
                    ->download($slug.'.xlsx');

            case 'CSV':
                return (new FlashCajaHistoricoDiarioExport($reporte))
                    ->download($slug.'.csv', Excel::CSV);

            case 'PDF':
                if (count($empresaIds) > 1 && empty($filtros['consolidar_empresas'])) {
                    return $this->descargarPdfHistoricoPorEmpresa($reporte, $slug);
                }
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
        $empresaIds = FlashCajaHistoricoFiltros::empresaIds($filtros);
        $consolidar = ! empty($filtros['consolidar_empresas']) || count($empresaIds) <= 1;
        $conSeason = (int) ($filtros['con_season'] ?? 1) === 1;
        $fechaDesde = (string) $filtros['fecha_desde'];
        $fechaHasta = (string) $filtros['fecha_hasta'];

        $nombres = [];
        foreach ($empresaIds as $empresaId) {
            $nombres[$empresaId] = (string) ($this->empresaRepository->find($empresaId)?->nombre ?? ('#'.$empresaId));
        }
        $empresasTexto = implode(', ', array_values($nombres));

        if ($consolidar) {
            $empresaRef = $this->empresaRepository->find((int) $empresaIds[0]);
            $filas = $this->repository->leeFlashPorRango(
                (int) $empresaIds[0],
                $fechaDesde,
                $fechaHasta,
            );
            $reporte = FlashCajaReporteSupport::armarHistorico(
                $filas,
                $empresaRef,
                $fechaDesde,
                $fechaHasta,
                $conSeason,
                $empresaIds,
            );
            $reporte['empresas_texto'] = $empresasTexto;
            $reporte['consolidar_empresas'] = true;
            $reporte['multiempresa'] = count($empresaIds) > 1;

            return $reporte;
        }

        $secciones = [];
        foreach ($empresaIds as $empresaId) {
            $empresa = $this->empresaRepository->find($empresaId);
            $filas = $this->repository->leeFlashPorRango($empresaId, $fechaDesde, $fechaHasta);
            $seccion = FlashCajaReporteSupport::armarHistorico(
                $filas,
                $empresa,
                $fechaDesde,
                $fechaHasta,
                $conSeason,
                [$empresaId],
            );
            $seccion['empresas_texto'] = $nombres[$empresaId] ?? '';
            $secciones[] = $seccion;
        }

        return [
            'titulo' => 'Consolidated Income',
            'multiempresa' => true,
            'consolidar_empresas' => false,
            'empresa_ids' => $empresaIds,
            'empresas_texto' => $empresasTexto,
            'secciones' => $secciones,
            'empresa' => $this->empresaRepository->find((int) $empresaIds[0]),
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'periodo' => FlashCajaReporteSupport::formatearPeriodo($fechaDesde, $fechaHasta),
            'through_day' => Carbon::parse($fechaHasta)->format('d'),
            'es_historico' => true,
            'con_season' => $conSeason,
            'cantidad_dias' => collect($secciones)->sum(fn ($s) => (int) ($s['cantidad_dias'] ?? 0)),
            'filas_diarias' => [],
            'budget_mes' => $secciones[0]['budget_mes'] ?? [],
        ];
    }

    /**
     * @param  array{
     *   empresa_ids?: list<int>,
     *   empresa_id?: int,
     *   consolidar_empresas?: bool,
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   con_season?: int
     * }  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     * @return array<string, mixed>
     */
    private function aplicarDefaultsHistorico(Request $request, array $filtros, $empresaQuery): array
    {
        if ($filtros['fecha_desde'] === '' && $filtros['fecha_hasta'] === '') {
            $filtros['fecha_desde'] = Carbon::today()->startOfMonth()->format('Y-m-d');
            $filtros['fecha_hasta'] = Carbon::today()->format('Y-m-d');
        }
        if (! isset($filtros['con_season'])) {
            $filtros['con_season'] = 1;
        }

        $permitidos = $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (! $request->has('consolidar_empresas')) {
            $filtros['consolidar_empresas'] = ReportePreferenciasUsuario::leerBool(
                self::PREFERENCIAS_HISTORICO,
                'consolidar_empresas',
                true,
            );
        }

        if (FlashCajaHistoricoFiltros::empresaIds($filtros) === []) {
            $cached = ReportePreferenciasUsuario::leerEmpresaIds(self::PREFERENCIAS_HISTORICO);
            if ($cached !== null && $cached !== []) {
                $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas($cached, $permitidos);
            }
        }

        if (FlashCajaHistoricoFiltros::empresaIds($filtros) === [] && $empresaQuery->count() >= 1) {
            $filtros['empresa_ids'] = $empresaQuery->count() === 1
                ? [(int) $empresaQuery->first()->id]
                : $empresaQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $filtros['empresa_ids'] = ReportePreferenciasUsuario::filtrarEmpresaIdsPermitidas(
            FlashCajaHistoricoFiltros::empresaIds($filtros),
            $permitidos,
        );
        $filtros['empresa_id'] = (int) ($filtros['empresa_ids'][0] ?? 0);

        if (count($filtros['empresa_ids']) <= 1) {
            $filtros['consolidar_empresas'] = true;
        }

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $reporte
     */
    private function descargarPdfHistoricoPorEmpresa(array $reporte, string $slug)
    {
        $dir = storage_path('pdf/listados');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $temporales = [];
        try {
            foreach ($reporte['secciones'] ?? [] as $seccion) {
                $html = view('caja.flash.reporte_historico.listado', ['reporte' => $seccion])->render();
                $temp = $dir.'/flash_historico_tmp_'.uniqid('', true).'.pdf';
                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($html, 'UTF-8')->save($temp);
                $temporales[] = $temp;
            }

            $destino = $dir.'/'.$slug.'.pdf';
            if (count($temporales) === 1) {
                rename($temporales[0], $destino);
                $temporales = [];
            } else {
                $merger = new PDFMerger;
                foreach ($temporales as $ruta) {
                    $merger->addPDF($ruta, 'all', 'horizontal');
                }
                $merger->merge('file', $destino);
            }

            return response()->download($destino);
        } finally {
            foreach ($temporales as $ruta) {
                if (is_file($ruta)) {
                    @unlink($ruta);
                }
            }
        }
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
            $payload = array_merge($payload, FlashCajaCalculoService::payloadPersistible($calculado));
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
        $empresaDefault = optional($this->empresaRepository->allFiltrado()->first())->id;
        $filtros = FlashCajaListadoFiltros::resolverDesdeRequest(
            $request,
            $busquedaRuta,
            $empresaDefault ? (int) $empresaDefault : null
        );
        $filtros['empresas_asignadas'] = $this->empresaRepository->traeEmpresasAsignadas();

        return $filtros;
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        $this->assertAccesoEmpresas([$empresaId]);
    }

    /**
     * @param  list<int>  $empresaIds
     */
    private function assertAccesoEmpresas(array $empresaIds): void
    {
        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        if ($asignadas === [] || $asignadas === null) {
            return;
        }
        $permitidas = array_map('intval', (array) $asignadas);
        foreach ($empresaIds as $empresaId) {
            if (! in_array((int) $empresaId, $permitidas, true)) {
                abort(403, 'Sin acceso a la empresa seleccionada.');
            }
        }
    }
}
