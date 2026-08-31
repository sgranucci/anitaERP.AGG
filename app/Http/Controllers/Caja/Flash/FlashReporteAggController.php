<?php

namespace App\Http\Controllers\Caja\Flash;

use App\Http\Controllers\Controller;
use App\Models\Caja\Flash\FlashCaja;
use App\Models\Caja\Flash\FlashReporteSuscripcion;
use App\Services\Caja\Flash\FlashReporteAggDistribucionService;
use App\Services\Caja\Flash\FlashReporteAggExcelService;
use App\Support\Caja\Flash\FlashReporteAggFechaProduccionSupport;
use App\Support\Caja\Flash\FlashReporteAggSuscripcionSupport;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class FlashReporteAggController extends Controller
{
    public function __construct(
        private readonly FlashReporteAggExcelService $excelService,
        private readonly FlashReporteAggSuscripcionSupport $suscripcionSupport,
        private readonly FlashReporteAggDistribucionService $distribucionService,
    ) {}

    public function index(Request $request): View
    {
        can('listar-flash-reporte-agg');

        $periodo = $this->resolverPeriodo($request);

        return view('caja.flash.reporte_agg.index', [
            'mes' => $periodo['desde']->format('Y-m'),
            'fecha_hasta' => $periodo['hasta']->format('Y-m-d'),
            'resumen' => $this->resumenPeriodo($periodo['desde'], $periodo['hasta']),
            'suscripciones' => $this->suscripcionSupport->listar(),
            'suscripcion_editar' => $this->suscripcionEditar($request),
            'periodicidades' => FlashReporteSuscripcion::periodicidades(),
            'periodos_relativos' => FlashReporteSuscripcion::periodosRelativos(),
            'dias_semana' => FlashReporteSuscripcion::diasSemana(),
        ]);
    }

    public function exportar(Request $request): BinaryFileResponse|RedirectResponse
    {
        can('exportar-flash-reporte-agg');

        $periodo = $this->resolverPeriodo($request);

        try {
            $archivo = $this->excelService->generar($periodo['desde'], $periodo['hasta']);
        } catch (Throwable $e) {
            return redirect()
                ->route('flash_reporte_agg', [
                    'mes' => $periodo['desde']->format('Y-m'),
                    'fecha_hasta' => $periodo['hasta']->format('Y-m-d'),
                ])
                ->with('mensaje', $e->getMessage())
                ->with('titulo_mensaje', 'No se pudo generar el Flash Report')
                ->with('tipo_mensaje', 'danger');
        }

        return response()
            ->download($archivo['path'], $archivo['nombre'], [
                'Content-Type' => $archivo['mime'],
            ])
            ->deleteFileAfterSend(true);
    }

    public function guardarSuscripcion(Request $request): RedirectResponse
    {
        can('administrar-flash-reporte-agg');

        $this->suscripcionSupport->crear($request->all(), auth()->id());

        return redirect()
            ->route('flash_reporte_agg')
            ->with('mensaje', 'Envío automático guardado.')
            ->with('titulo_mensaje', 'Flash Report AGG')
            ->with('tipo_mensaje', 'success');
    }

    public function actualizarSuscripcion(Request $request, $id): RedirectResponse
    {
        can('administrar-flash-reporte-agg');

        $this->suscripcionSupport->actualizar((int) $id, $request->all());

        return redirect()
            ->route('flash_reporte_agg')
            ->with('mensaje', 'Envío automático actualizado.')
            ->with('titulo_mensaje', 'Flash Report AGG')
            ->with('tipo_mensaje', 'success');
    }

    public function eliminarSuscripcion($id): RedirectResponse
    {
        can('administrar-flash-reporte-agg');

        $this->suscripcionSupport->eliminar((int) $id);

        return redirect()
            ->route('flash_reporte_agg')
            ->with('mensaje', 'Envío automático eliminado.')
            ->with('titulo_mensaje', 'Flash Report AGG')
            ->with('tipo_mensaje', 'success');
    }

    public function probarSuscripcion(Request $request, $id): RedirectResponse
    {
        can('administrar-flash-reporte-agg');

        $suscripcion = FlashReporteSuscripcion::query()->whereKey((int) $id)->first();
        if ($suscripcion === null) {
            return redirect()->route('flash_reporte_agg')
                ->with('mensaje', 'No existe ese envío.')
                ->with('tipo_mensaje', 'danger');
        }

        $dryRun = $request->boolean('dry_run');
        try {
            $resultado = $this->distribucionService->enviar($suscripcion, $dryRun);
        } catch (Throwable $e) {
            $this->suscripcionSupport->registrarResultado(
                $suscripcion,
                FlashReporteSuscripcion::ESTADO_ERROR,
                $e->getMessage()
            );

            return redirect()->route('flash_reporte_agg')
                ->with('mensaje', $e->getMessage())
                ->with('titulo_mensaje', 'Prueba de envío')
                ->with('tipo_mensaje', 'danger');
        }

        if (! $dryRun) {
            $this->suscripcionSupport->registrarResultado(
                $suscripcion,
                $resultado['estado'],
                $resultado['mensaje']
            );
        }

        $tipo = $resultado['estado'] === FlashReporteSuscripcion::ESTADO_OK ? 'success' : 'warning';

        return redirect()
            ->route('flash_reporte_agg')
            ->with('mensaje', $resultado['mensaje'])
            ->with('titulo_mensaje', $dryRun ? 'Simulación' : 'Prueba de envío')
            ->with('tipo_mensaje', $tipo);
    }

    /**
     * @return array{desde: Carbon, hasta: Carbon}
     */
    private function resolverPeriodo(Request $request): array
    {
        $produccion = FlashReporteAggFechaProduccionSupport::fecha();

        $mes = trim((string) $request->input('mes', ''));
        if (! preg_match('/^\d{4}-\d{2}$/', $mes)) {
            // Sin mes en el request: mes de la fecha de producción (no el calendario de hoy).
            $mes = $produccion->format('Y-m');
        }
        $desde = Carbon::createFromFormat('Y-m-d', $mes.'-01')?->startOfMonth() ?? $produccion->copy()->startOfMonth();

        $hastaRaw = trim((string) $request->input('fecha_hasta', ''));
        $hasta = $hastaRaw !== ''
            ? Carbon::parse($hastaRaw)->startOfDay()
            : $produccion->copy();

        if ($hasta->lt($desde)) {
            $hasta = $desde->copy();
        }
        $finMes = $desde->copy()->endOfMonth()->startOfDay();
        if ($hasta->gt($finMes)) {
            $hasta = $finMes;
        }

        return ['desde' => $desde, 'hasta' => $hasta];
    }

    /**
     * @return list<array{empresa_id: int, nombre: string, dias: int}>
     */
    private function resumenPeriodo(Carbon $desde, Carbon $hasta): array
    {
        $mapa = $this->excelService->mapaEmpresas();
        $out = [];
        foreach ($mapa as $empresaId => $hojas) {
            $dias = FlashCaja::query()
                ->where('empresa_id', $empresaId)
                ->whereBetween('fecha', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')])
                ->count();
            $out[] = [
                'empresa_id' => $empresaId,
                'nombre' => (string) $hojas['hoja'],
                'dias' => $dias,
            ];
        }

        return $out;
    }

    private function suscripcionEditar(Request $request): ?FlashReporteSuscripcion
    {
        $id = (int) $request->input('editar', 0);
        if ($id <= 0) {
            return null;
        }

        return FlashReporteSuscripcion::query()->whereKey($id)->first();
    }
}
