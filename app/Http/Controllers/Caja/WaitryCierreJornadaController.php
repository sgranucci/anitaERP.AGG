<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\WaitryCierreJornadaService;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaProcesoAutomaticoService;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaProcesoService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Support\Caja\RendicionGastronomiaPdfPermiso;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaFacturaProcesoEmisionService;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosPreviewSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoPuntoventaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoConfigSupport;
use App\Support\Ventas\CaeaEmisionFechaCorrelatividadSupport;
use App\Models\Ventas\JornadaGastronomia;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class WaitryCierreJornadaController extends Controller
{
    public function __construct(
        private readonly WaitryCierreJornadaService $cierreJornadaService,
        private readonly GastronomiaCierreJornadaProcesoService $procesoService,
        private readonly GastronomiaCierreJornadaProcesoAutomaticoService $procesoAutomaticoService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly GastronomiaJornadaService $jornadaService,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-waitry-cierre-jornada-caja');

        $empresas = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', $empresas->first()->id ?? 0);
        $fechaJornada = $this->resolverFechaJornadaConsulta($request, $empresaId);

        $payload = null;
        $error = null;

        if ($request->has('empresa_id') && $request->has('fecha_jornada') && $empresaId > 0) {
            try {
                $this->prepararEntornoProcesoApi();
                $payload = $this->cierreJornadaService->conciliar($empresaId, $fechaJornada);
                if (! ($payload['ok'] ?? false)) {
                    $error = $payload['error'] ?? 'No se pudo conciliar la jornada.';
                }
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        $empresaNombre = $empresas->firstWhere('id', $empresaId)?->nombre ?? '';

        return view('caja.waitry_cierre_jornada.index', [
            'empresas' => $empresas,
            'empresa_id' => $empresaId,
            'empresa_nombre' => $empresaNombre,
            'fecha_jornada' => $fechaJornada,
            'payload' => $payload,
            'error' => $error,
            'consultado' => $request->has('empresa_id') && $request->has('fecha_jornada'),
            'proceso_habilitado' => $this->procesoService->habilitado(),
            'puede_proceso_cierre' => can('proceso-cierre-jornada-waitry-caja', false),
            'config_contable' => $empresaId > 0
                ? CierreJornadaProcesoConfigSupport::paraEmpresaConDetalle($empresaId)
                : [],
            'porcentaje_proceso_config' => $empresaId > 0
                ? CierreJornadaProcesoConfigSupport::resolverPorcentajeParaEmpresa($empresaId)
                : (float) config('gastronomia.cierre_jornada_porcentaje', 0),
            'url_movimientos_proceso_base' => str_replace(
                '__GRUPO__',
                '',
                route('waitry_cierre_jornada_api_proceso_movimientos', ['grupo' => '__GRUPO__']),
            ),
        ]);
    }

    public function apiProcesoAnalizar(Request $request)
    {
        $this->canProcesoCierre();

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaJornada = (string) $request->input('fecha_jornada', '');

        try {
            $this->prepararEntornoProcesoApi();

            $refrescar = filter_var($request->input('refrescar_waitry', false), FILTER_VALIDATE_BOOLEAN);

            return response()->json(
                $this->procesoService->analizarPorEmpresaYFecha($empresaId, $fechaJornada, $refrescar),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
            ], 422);
        }
    }

    public function apiProcesoRecalcular(Request $request)
    {
        $this->canProcesoCierre();

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'fecha_jornada' => 'required|date',
            'porcentaje' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $this->prepararEntornoProcesoApi();

            return response()->json($this->procesoService->recalcularPorEmpresaYFecha(
                (int) $request->input('empresa_id'),
                (string) $request->input('fecha_jornada'),
                (float) $request->input('porcentaje'),
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
            ], 422);
        }
    }

    public function apiProcesoEmitirFactura(Request $request)
    {
        $this->canProcesoCierre();

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'fecha_jornada' => 'required|date',
            'porcentaje' => 'nullable|numeric|min:0|max:100',
            'puntoventa_id' => 'nullable|integer|min:1',
            'fecha_factura' => 'nullable|date',
            'usar_recuperacion_snapshot' => 'nullable|boolean',
        ]);

        try {
            $this->prepararEntornoProcesoApi(300);

            return response()->json($this->procesoService->emitirFacturaProcesoPorEmpresaYFecha(
                (int) $request->input('empresa_id'),
                (string) $request->input('fecha_jornada'),
                (float) $request->input('porcentaje', 0),
                (int) $request->input('puntoventa_id', 0),
                $request->input('fecha_factura') ? (string) $request->input('fecha_factura') : null,
                $request->boolean('usar_recuperacion_snapshot'),
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
            ], 422);
        }
    }

    public function apiProcesoGrabarAsientos(Request $request)
    {
        $this->canProcesoCierre();

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'fecha_jornada' => 'required|date',
            'porcentaje' => 'nullable|numeric|min:0|max:100',
            'fecha_asiento' => 'nullable|date',
        ]);

        try {
            $this->prepararEntornoProcesoApi(300);

            return response()->json($this->procesoService->grabarAsientosProcesoPorEmpresaYFecha(
                (int) $request->input('empresa_id'),
                (string) $request->input('fecha_jornada'),
                (float) $request->input('porcentaje', 0),
                $request->input('fecha_asiento') ? (string) $request->input('fecha_asiento') : null,
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
            ], 422);
        }
    }

    public function apiProcesoRevertir(Request $request)
    {
        $this->canProcesoCierre();

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'fecha_jornada' => 'required|date',
        ]);

        try {
            $this->prepararEntornoProcesoApi(300);

            return response()->json($this->procesoService->revertirProcesoPorEmpresaYFecha(
                (int) $request->input('empresa_id'),
                (string) $request->input('fecha_jornada'),
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
            ], 422);
        }
    }

    public function apiProcesoPreviewLotesFactura(Request $request)
    {
        $this->canProcesoCierre();

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'fecha_jornada' => 'required|date',
            'porcentaje' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $this->prepararEntornoProcesoApi();

            return response()->json($this->procesoService->previewLotesFacturaProcesoPorEmpresaYFecha(
                (int) $request->input('empresa_id'),
                (string) $request->input('fecha_jornada'),
                (float) $request->input('porcentaje', 0),
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
            ], 422);
        }
    }

    public function apiProcesoPreviewFactura(Request $request)
    {
        $this->canProcesoCierre();

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'fecha_jornada' => 'required|date',
            'porcentaje' => 'nullable|numeric|min:0|max:100',
            'pagina' => 'nullable|integer|min:1',
            'por_pagina' => 'nullable|integer|min:10|max:500',
            'comandas_alcance' => 'nullable|string|in:factura_proceso,efectivo_no_facturado',
        ]);

        try {
            $this->prepararEntornoProcesoApi();

            return response()->json($this->procesoService->previewFacturaProcesoPorEmpresaYFecha(
                (int) $request->input('empresa_id'),
                (string) $request->input('fecha_jornada'),
                (float) $request->input('porcentaje', 0),
                (int) $request->input('pagina', 1),
                (int) $request->input('por_pagina', 50),
                (string) $request->input(
                    'comandas_alcance',
                    CierreJornadaProcesoAsientosPreviewSupport::COMANDAS_ALCANCE_FACTURA_PROCESO,
                ),
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
            ], 422);
        }
    }

    public function apiProcesoMovimientosGrupo(Request $request, string $grupo)
    {
        $this->canProcesoCierre();

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaJornada = (string) $request->input('fecha_jornada', '');
        $pagina = max(1, (int) $request->input('pagina', 1));
        $porPagina = max(10, min(500, (int) $request->input('por_pagina', 50)));

        try {
            $this->prepararEntornoProcesoApi();

            return response()->json($this->procesoService->movimientosGrupoPorEmpresaYFecha(
                $empresaId,
                $fechaJornada,
                $grupo,
                $pagina,
                $porPagina,
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
            ], 422);
        }
    }

    public function apiProcesoCuadroDetalle(Request $request, string $fila, string $medio)
    {
        $this->canProcesoCierre();

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaJornada = (string) $request->input('fecha_jornada', '');
        $pagina = max(1, (int) $request->input('pagina', 1));
        $porPagina = max(10, min(500, (int) $request->input('por_pagina', 50)));
        $porcentaje = $request->has('porcentaje')
            ? (float) $request->input('porcentaje')
            : null;

        try {
            $this->prepararEntornoProcesoApi();

            return response()->json($this->procesoService->detalleCuadroCeldaPorEmpresaYFecha(
                $empresaId,
                $fechaJornada,
                mb_strtolower(trim($fila)),
                mb_strtolower(trim($medio)),
                $pagina,
                $porPagina,
                $porcentaje,
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
            ], 422);
        }
    }

    public function apiProcesoOpcionesEmitir(Request $request)
    {
        $this->canProcesoCierre();

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'fecha_jornada' => 'required|date',
        ]);

        $empresaId = (int) $request->input('empresa_id');
        $fechaJornada = (string) $request->input('fecha_jornada');
        $emisionService = app(GastronomiaCierreJornadaFacturaProcesoEmisionService::class);
        $pvDefault = CierreJornadaProcesoPuntoventaSupport::resolverParaEmpresa($empresaId);

        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderByDesc('id')
            ->first();
        $fechaFacturaDefault = CaeaEmisionFechaCorrelatividadSupport::fechaCalendarioCierre(
            $jornada?->cierre_en,
            $jornada?->fecha_jornada ?? (new \DateTimeImmutable($fechaJornada)),
        );

        return response()->json([
            'ok' => true,
            'fecha_jornada' => $fechaJornada,
            'fecha_factura_default' => $fechaFacturaDefault,
            'puntoventa_default' => $pvDefault,
            'puntoventas' => $emisionService->listarPuntosVentaElectronicos($empresaId),
        ]);
    }

    public function apiProcesoConfig(int $empresaId)
    {
        $this->canProcesoCierre();

        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa inválida.'], 422);
        }

        $cfg = CierreJornadaProcesoConfigSupport::paraEmpresaConDetalle($empresaId);

        return response()->json([
            'ok' => true,
            'config' => $cfg,
            'faltantes' => CierreJornadaProcesoConfigSupport::faltantes($cfg, $empresaId),
        ]);
    }

    public function apiProcesoEjecutarAutomatico(Request $request)
    {
        $this->canProcesoCierre();

        $request->validate([
            'empresa_id' => 'required|integer|min:1',
            'fecha_jornada' => 'nullable|date',
            'enviar_mail' => 'nullable|boolean',
        ]);

        try {
            $this->prepararEntornoProcesoApi(600);

            $empresaId = (int) $request->input('empresa_id');
            $fechaJornada = $request->filled('fecha_jornada')
                ? (string) $request->input('fecha_jornada')
                : null;
            $enviarMail = $request->boolean('enviar_mail', true);

            $resultado = $this->procesoAutomaticoService->ejecutarEmpresa($empresaId, $fechaJornada);

            if ($enviarMail) {
                $informe = [
                    'ejecutado_en' => now()->toIso8601String(),
                    'empresas' => [$resultado],
                    'resumen' => [
                        'procesadas' => in_array($resultado['estado'] ?? '', ['completado', 'reanudado'], true) ? 1 : 0,
                        'omitidas' => in_array($resultado['estado'] ?? '', ['omitido', 'sin_pendiente'], true) ? 1 : 0,
                        'errores' => ($resultado['ok'] ?? false) ? 0 : (
                            in_array($resultado['estado'] ?? '', ['omitido', 'sin_pendiente'], true) ? 0 : 1
                        ),
                    ],
                ];
                $informe['ok'] = ($informe['resumen']['errores'] ?? 0) === 0;
                $this->procesoAutomaticoService->enviarMailInforme($informe);
            }

            $status = ($resultado['ok'] ?? false) || in_array($resultado['estado'] ?? '', ['omitido', 'sin_pendiente'], true)
                ? 200
                : 422;

            return response()->json($resultado, $status);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
            ], 422);
        }
    }

    public function apiProcesoGuardarConfig(Request $request, int $empresaId)
    {
        $this->canProcesoCierre();

        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa inválida.'], 422);
        }

        try {
            return response()->json(
                $this->procesoService->guardarConfig($empresaId, $request->all()),
            );
        } catch (InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private function canProcesoCierre(): void
    {
        can('proceso-cierre-jornada-waitry-caja');
    }

    private function prepararEntornoProcesoApi(int $timeLimitSegundos = 180): void
    {
        $memory = (string) config('gastronomia.cierre_jornada_proceso_memory_limit', '256M');
        if ($memory !== '') {
            @ini_set('memory_limit', $memory);
        }
        @set_time_limit(max(60, $timeLimitSegundos));
    }

    private function resolverFechaJornadaConsulta(Request $request, int $empresaId): string
    {
        $fechaJornada = trim((string) $request->input('fecha_jornada', ''));
        if ($fechaJornada === '' && $empresaId > 0) {
            $estado = $this->jornadaService->estadoParaEmpresa($empresaId);
            if (! empty($estado['fecha_jornada'])) {
                $fechaJornada = (string) $estado['fecha_jornada'];
            }
        }

        if ($fechaJornada === '') {
            $fechaJornada = now()->format('Y-m-d');
        }

        return $fechaJornada;
    }

    public function listar(Request $request, ?string $formato = null)
    {
        can('listar-waitry-cierre-jornada-caja');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaJornada = $this->resolverFechaJornadaConsulta($request, $empresaId);
        $empresas = $this->empresaRepository->allFiltrado();
        $empresaNombre = $empresas->firstWhere('id', $empresaId)?->nombre ?? '';

        try {
            $payload = $this->cierreJornadaService->conciliar($empresaId, $fechaJornada);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('waitry_cierre_jornada', [
                    'empresa_id' => $empresaId,
                    'fecha_jornada' => $fechaJornada,
                ])
                ->with('mensaje', $e->getMessage());
        }

        if (! ($payload['ok'] ?? false)) {
            return redirect()
                ->route('waitry_cierre_jornada', [
                    'empresa_id' => $empresaId,
                    'fecha_jornada' => $fechaJornada,
                ])
                ->with('mensaje', $payload['error'] ?? 'No se pudo exportar el cierre.');
        }

        $filas = $payload['filas'] ?? [];
        $resumen = $payload['resumen'] ?? [];
        $titulo = 'Cierre jornada Waitry — '.$empresaNombre.' — '.($payload['fecha_jornada_fmt'] ?? $fechaJornada);

        switch ($formato) {
            case 'PDF':
                if (! RendicionGastronomiaPdfPermiso::puedeVerPdfWaitry()) {
                    abort(403, 'No tiene permiso para exportar el PDF de cierre Waitry.');
                }
                $view = \View::make('caja.waitry_cierre_jornada.listado', compact(
                    'filas',
                    'resumen',
                    'titulo',
                    'empresaNombre',
                    'payload',
                ))->render();
                $path = storage_path('pdf/listados');
                $nombrePdf = 'waitry_cierre_jornada_'.$empresaId.'_'.$fechaJornada;

                $pdf = \App::make('dompdf.wrapper');
                $pdf->setPaper('legal', 'landscape');
                $pdf->loadHTML($view)->save($path.'/'.$nombrePdf.'.pdf');

                return response()->download($path.'/'.$nombrePdf.'.pdf');

            case 'EXCEL':
            case 'CSV':
                $mime = $formato === 'CSV'
                    ? \Maatwebsite\Excel\Excel::CSV
                    : \Maatwebsite\Excel\Excel::XLSX;
                $ext = $formato === 'CSV' ? 'csv' : 'xlsx';

                return \Maatwebsite\Excel\Facades\Excel::download(
                    new \App\Exports\Caja\WaitryCierreJornadaExport($filas, $resumen, $titulo, $empresaNombre, $formato === 'CSV'),
                    'waitry_cierre_jornada_'.$empresaId.'_'.$fechaJornada.'.'.$ext,
                    $mime,
                );
        }

        return redirect()->route('waitry_cierre_jornada', [
            'empresa_id' => $empresaId,
            'fecha_jornada' => $fechaJornada,
        ]);
    }
}
