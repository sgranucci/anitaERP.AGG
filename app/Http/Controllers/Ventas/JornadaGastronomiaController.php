<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\JornadaGastronomiaRepositoryInterface;
use App\Models\Ventas\CierreTotemJornadaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Caja\Usocuentacaja;
use App\Services\Ventas\Gastronomia\GastronomiaCierreTotemInformeZService;
use App\Services\Ventas\Gastronomia\GastronomiaCierreTotemJornadaService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\Waitry\WaitryInformeZConciliacionSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class JornadaGastronomiaController extends Controller
{
    public function __construct(
        private readonly GastronomiaJornadaService $jornadaService,
        private readonly GastronomiaCierreTotemJornadaService $cierreTotemJornadaService,
        private readonly GastronomiaCierreTotemInformeZService $informeZService,
        private readonly JornadaGastronomiaRepositoryInterface $jornadaRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('gestionar-jornada-gastronomia');

        $empresas = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', $empresas->first()->id ?? 0);
        $this->assertAccesoEmpresa($empresaId);

        $estado = $empresaId > 0 ? $this->jornadaService->estadoParaEmpresa($empresaId) : null;
        $historial = $empresaId > 0
            ? $this->jornadaRepository->historialPorEmpresa($empresaId, 40)
            : collect();

        $eliminacionPorJornada = [];
        $anulacionCierrePorJornada = [];
        foreach ($historial as $jornadaHistorial) {
            $eliminacionPorJornada[(int) $jornadaHistorial->id] = $this->jornadaService->resumenEliminacion($jornadaHistorial);
            if ($jornadaHistorial->estado === JornadaGastronomia::ESTADO_CERRADA) {
                $anulacionCierrePorJornada[(int) $jornadaHistorial->id] = $this->jornadaService->resumenAnulacionCierre($jornadaHistorial);
            }
        }

        $puedeAnularCierre = can('cerrar-jornada-gastronomia', false);

        $eliminacionJornadaAbierta = null;
        if ($estado && ! empty($estado['jornada_id'])) {
            $jornadaAbiertaId = (int) $estado['jornada_id'];
            if (! isset($eliminacionPorJornada[$jornadaAbiertaId])) {
                $eliminacionPorJornada[$jornadaAbiertaId] = $this->jornadaService->resumenEliminacion(
                    $this->jornadaRepository->findOrFail($jornadaAbiertaId)
                );
            }
            $eliminacionJornadaAbierta = $eliminacionPorJornada[$jornadaAbiertaId];
        }

        return view('ventas.gastronomia.jornada.index', [
            'empresas' => $empresas,
            'empresa_id' => $empresaId,
            'estado' => $estado,
            'historial' => $historial,
            'eliminacion_por_jornada' => $eliminacionPorJornada,
            'anulacion_cierre_por_jornada' => $anulacionCierrePorJornada,
            'cierre_anulable' => $puedeAnularCierre && $empresaId > 0
                ? $this->jornadaService->cierreAnulableParaEmpresa($empresaId)
                : null,
            'puede_anular_cierre' => $puedeAnularCierre,
            'fecha_hoy' => $estado['fecha_jornada_sugerida_abrir'] ?? now()->format('Y-m-d'),
            'fecha_jornada_minima' => $estado['fecha_jornada_minima_abrir'] ?? null,
            'fecha_maxima' => $estado['fecha_jornada_maxima_abrir'] ?? now()->format('Y-m-d'),
            'eliminacion_jornada_abierta' => $eliminacionJornadaAbierta,
            'puede_abrir' => can('abrir-jornada-gastronomia', false),
            'puede_cerrar' => can('cerrar-jornada-gastronomia', false),
            'puede_eliminar' => can('eliminar-jornada-gastronomia', false),
            'url_saneamiento_turno' => url('ventas/gastronomia/saneamiento-turno'),
            'cierre_totem_habilitado' => $this->cierreTotemJornadaService->habilitado(),
            'ultimo_waitry_order_id' => $empresaId > 0
                ? $this->cierreTotemJornadaService->ultimoWaitryOrderIdHasta($empresaId)
                : 0,
            'usocuentacaja_gastronomia_id' => $this->usoCuentacajaGastronomiaId(),
        ]);
    }

    private function usoCuentacajaGastronomiaId(): ?int
    {
        $configured = config('gastronomia.usocuentacaja_id');
        if ($configured !== null && $configured !== '') {
            return (int) $configured;
        }

        if (! Schema::hasTable('usocuentacaja')) {
            return null;
        }

        $id = Usocuentacaja::query()->where('nombre', 'Gastronomia')->value('id');

        return $id ? (int) $id : null;
    }

    public function apiInformeZDatos(int $jornadaId)
    {
        can('gestionar-jornada-gastronomia');

        try {
            $cierre = CierreTotemJornadaGastronomia::query()
                ->where('jornada_gastronomia_id', $jornadaId)
                ->firstOrFail();
            $this->assertAccesoEmpresa((int) $cierre->empresa_id);

            return response()->json([
                'ok' => true,
                ...$this->informeZService->datosParaConciliacion($jornadaId),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiInformeZGuardar(Request $request)
    {
        can('gestionar-jornada-gastronomia');

        $jornadaId = (int) $request->input('jornada_id', 0);
        if ($jornadaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Jornada inválida.'], 422);
        }

        try {
            $cierre = CierreTotemJornadaGastronomia::query()
                ->where('jornada_gastronomia_id', $jornadaId)
                ->firstOrFail();
            $this->assertAccesoEmpresa((int) $cierre->empresa_id);

            $resultado = $this->informeZService->guardarInformeZ($jornadaId, $request->all());

            return response()->json($resultado);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiInformeZBorradorGuardar(Request $request)
    {
        can('gestionar-jornada-gastronomia');

        $jornadaId = (int) $request->input('jornada_id', 0);
        if ($jornadaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Jornada inválida.'], 422);
        }

        try {
            $jornada = $this->jornadaRepository->findOrFail($jornadaId);
            $this->assertAccesoEmpresa((int) $jornada->empresa_id);

            if ($jornada->estado !== JornadaGastronomia::ESTADO_ABIERTA) {
                return response()->json([
                    'ok' => false,
                    'error' => 'La jornada ya está cerrada. Use el Informe Z del historial.',
                ], 422);
            }

            $snapshotRequest = $request->input('snapshot_cierre');
            $snapshot = is_array($snapshotRequest) && is_array($snapshotRequest['resumen_totems'] ?? null)
                ? $snapshotRequest
                : null;

            if ($snapshot === null) {
                $preview = $this->cierreTotemJornadaService->previewParaJornadaAbierta($jornada);
                if ($preview === null) {
                    return response()->json(['ok' => false, 'error' => 'Cierre tótem Waitry no habilitado.'], 422);
                }
                $snapshot = is_array($preview['snapshot_cierre'] ?? null) ? $preview['snapshot_cierre'] : null;
                $resumen = is_array($preview['resumen_informe_z'] ?? null)
                    ? $preview['resumen_informe_z']
                    : [
                        'por_totem' => [],
                        'total_general' => ['cantidad_ordenes' => 0, 'total_ingreso' => 0.0, 'por_medio_pago' => []],
                    ];
            } else {
                $resumen = is_array($snapshot['resumen_informe_z'] ?? null)
                    ? $snapshot['resumen_informe_z']
                    : \App\Support\Ventas\Waitry\WaitryInformeZConciliacionSupport::filtrarResumenSoloCreditCardPosnet(
                        is_array($snapshot['resumen_totems'] ?? null) ? $snapshot['resumen_totems'] : [],
                    );
            }

            $resultado = $this->informeZService->guardarBorradorJornadaAbierta(
                $jornada,
                $request->all(),
                $resumen,
                $snapshot,
            );

            return response()->json($resultado);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function comprobanteCierreTotem(Request $request, int $jornadaId)
    {
        if (! \App\Support\Ventas\GastronomiaJornadaComprobantePermiso::puedeVerComprobanteCierreTotem()) {
            abort(403, 'No tiene permiso para ver el comprobante de cierre tótem.');
        }

        $cierre = $this->resolverCierreTotemParaComprobante($request, $jornadaId);
        if ($cierre === null) {
            abort(404, 'No hay comprobante de cierre tótem registrado para esta jornada.');
        }

        $this->assertAccesoEmpresa((int) $cierre->empresa_id);

        // PDF liviano: solo Informe Z cargado por pantalla (sin detalle Waitry/discrepancias).
        $informeZ = is_array($cierre->informe_z_json) ? $cierre->informe_z_json : null;
        $empresaId = (int) $cierre->empresa_id;
        $fechaJornadaYmd = $cierre->jornada?->fecha_jornada?->format('Y-m-d') ?? null;

        $facturasCantidad = 0;
        $facturasTotal = 0.0;
        if ($fechaJornadaYmd !== null) {
            $row = DB::table('venta_gastronomia_emision as vge')
                ->join('venta as v', 'vge.venta_id', '=', 'v.id')
                ->join('puntoventa as pv', 'v.puntoventa_id', '=', 'pv.id')
                ->where('pv.empresa_id', $empresaId)
                ->whereNull('v.deleted_at')
                ->whereNull('pv.deleted_at')
                ->where(function ($q) use ($fechaJornadaYmd) {
                    $q->whereDate('v.fechajornada', $fechaJornadaYmd)
                        ->orWhere(function ($legacy) use ($fechaJornadaYmd) {
                            $legacy->whereNull('v.fechajornada')
                                ->whereDate('v.fecha', $fechaJornadaYmd);
                        });
                })
                ->selectRaw('COUNT(DISTINCT v.id) as cantidad, COALESCE(SUM(v.total), 0) as total')
                ->first();

            $facturasCantidad = (int) ($row->cantidad ?? 0);
            $facturasTotal = (float) ($row->total ?? 0);
        }

        $rangoWaitry = $this->cierreTotemJornadaService->etiquetaRango(
            (int) ($cierre->waitry_order_id_anterior ?? 0),
            $cierre->waitry_order_id_desde !== null ? (int) $cierre->waitry_order_id_desde : null,
            $cierre->waitry_order_id_hasta !== null ? (int) $cierre->waitry_order_id_hasta : null,
        );
        $presentacion = WaitryInformeZConciliacionSupport::conciliacionPresentacionDesdeCierre($cierre);
        $conciliacion = $presentacion['conciliacion'] ?? null;
        $totemsPdf = is_array($conciliacion)
            ? WaitryInformeZConciliacionSupport::bloquesInformeZConciliacionParaPdf($conciliacion)
            : [];

        $empresaNombre = (string) ($cierre->empresa?->nombre ?? '');
        $fechaJornadaFmt = $cierre->jornada?->fecha_jornada?->format('d/m/Y') ?? '';
        $jornadaIdComprobante = (int) $cierre->jornada_gastronomia_id;

        $datos = [
            'titulo' => 'Cierre de jornada — '.$fechaJornadaFmt,
            'subtitulo' => $empresaNombre.' · Jornada #'.$jornadaIdComprobante.' · Informe Z Waitry (por medio de pago)',
            'logo' => EmpresaLogoArchivo::dataUriDesdeNombre($empresaNombre),
            'empresa_nombre' => $empresaNombre,
            'jornada_id' => $jornadaIdComprobante,
            'fecha_jornada' => $fechaJornadaFmt,
            'apertura_jornada_en' => $cierre->jornada?->apertura_en?->format('d/m/Y H:i') ?? '',
            'cierre_jornada_en' => $cierre->jornada?->cierre_en?->format('d/m/Y H:i') ?? '',
            'rango_waitry' => $rangoWaitry,
            'waitry_order_id_anterior' => (int) ($cierre->waitry_order_id_anterior ?? 0),
            'waitry_order_id_desde' => $cierre->waitry_order_id_desde !== null ? (int) $cierre->waitry_order_id_desde : null,
            'waitry_order_id_hasta' => $cierre->waitry_order_id_hasta !== null ? (int) $cierre->waitry_order_id_hasta : null,
            'facturas_cantidad' => $facturasCantidad,
            'facturas_total' => $facturasTotal,
            'fecha_emision_comprobante' => now()->format('d/m/Y H:i'),
            'informe_z' => $informeZ,
            'conciliacion' => $conciliacion,
            'totems' => $totemsPdf,
        ];

        $nombre = 'cierre_jornada_'.($fechaJornadaYmd ?? 'sin_fecha').'_'.$jornadaId.'.pdf';

        $html = view('ventas.gastronomia.jornada.comprobante_informe_z_totem', compact('datos'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'landscape');
        $pdf->loadHTML($html, 'UTF-8');

        return $request->boolean('inline')
            ? $pdf->stream($nombre)
            : $pdf->download($nombre);
    }

    private function resolverCierreTotemParaComprobante(Request $request, int $jornadaId): ?CierreTotemJornadaGastronomia
    {
        $cierreTotemId = (int) $request->input('cierre_totem_id', 0);

        $q = CierreTotemJornadaGastronomia::query()->with(['jornada', 'empresa']);

        if ($cierreTotemId > 0) {
            $porId = (clone $q)->find($cierreTotemId);
            if ($porId !== null && (int) $porId->jornada_gastronomia_id === $jornadaId) {
                return $porId;
            }
        }

        return $q->where('jornada_gastronomia_id', $jornadaId)->first();
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403, 'Empresa no permitida para su usuario.');
        }
    }

    public function apiEstado(int $empresaId)
    {
        can('gestionar-jornada-gastronomia');

        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa inválida.'], 422);
        }

        $this->assertAccesoEmpresa($empresaId);

        return response()->json([
            'ok' => true,
            ...$this->jornadaService->estadoParaEmpresa($empresaId),
        ]);
    }

    public function apiPreviewCierreTotem(int $empresaId)
    {
        can('gestionar-jornada-gastronomia');

        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa inválida.'], 422);
        }

        $this->assertAccesoEmpresa($empresaId);

        $jornada = $this->jornadaService->jornadaAbierta($empresaId);
        if ($jornada === null) {
            return response()->json([
                'ok' => true,
                'preview' => null,
            ]);
        }

        if (! $this->cierreTotemJornadaService->habilitado()) {
            return response()->json([
                'ok' => true,
                'preview' => null,
            ]);
        }

        try {
            return response()->json([
                'ok' => true,
                'preview' => $this->cierreTotemJornadaService->previewParaJornadaAbierta($jornada),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
            ], 422);
        }
    }

    public function apiAbrir(Request $request)
    {
        if (! can('abrir-jornada-gastronomia', false)) {
            return response()->json([
                'ok' => false,
                'error' => 'No tiene permiso para abrir jornadas (permiso: abrir-jornada-gastronomia).',
                'motivo' => 'permiso',
            ], 403);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $fechaJornada = (string) $request->input('fecha_jornada', now()->format('Y-m-d'));
        $observacion = $request->input('observacion');

        $this->assertAccesoEmpresa($empresaId);

        try {
            $jornada = $this->jornadaService->abrir($empresaId, $fechaJornada, is_string($observacion) ? $observacion : null);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Jornada abierta correctamente.',
                'jornada' => [
                    'id' => $jornada->id,
                    'fecha_jornada' => $jornada->fecha_jornada->format('Y-m-d'),
                    'fecha_factura_hoy' => now()->format('Y-m-d'),
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'motivo' => 'validacion',
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
                'motivo' => 'error',
            ], 422);
        }
    }

    public function apiCerrar(Request $request)
    {
        if (! can('cerrar-jornada-gastronomia', false)) {
            return response()->json([
                'ok' => false,
                'error' => 'No tiene permiso para cerrar jornadas (permiso: cerrar-jornada-gastronomia).',
                'motivo' => 'permiso',
            ], 403);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $observacion = $request->input('observacion');
        $informeZTotems = $request->input('informe_z_totems');
        $informeZTotemsArr = is_array($informeZTotems) ? $informeZTotems : null;
        $snapshotRequest = $request->input('cierre_totem_snapshot');
        $cierreTotemSnapshot = is_array($snapshotRequest) ? $snapshotRequest : null;

        $this->assertAccesoEmpresa($empresaId);

        try {
            $jornada = $this->jornadaService->cerrar(
                $empresaId,
                is_string($observacion) ? $observacion : null,
                $informeZTotemsArr,
                $cierreTotemSnapshot,
            );

            $jornada->load('cierreTotem');
            $cierreTotem = $jornada->cierreTotem;
            $payloadJornada = [
                'id' => $jornada->id,
                'fecha_jornada' => $jornada->fecha_jornada->format('Y-m-d'),
                'cierre_en' => $jornada->cierre_en?->format('Y-m-d H:i:s'),
            ];

            if ($cierreTotem !== null) {
                $detalle = is_array($cierreTotem->detalle_json) ? $cierreTotem->detalle_json : [];
                $totalGeneral = $detalle['resumen_totems']['total_general'] ?? [];
                $payloadJornada['cierre_totem'] = [
                    'waitry_order_id_hasta' => $cierreTotem->waitry_order_id_hasta,
                    'waitry_order_id_desde' => $cierreTotem->waitry_order_id_desde,
                    'cantidad_lineas' => (int) $cierreTotem->cantidad_lineas,
                    'total_ingreso_totem' => (float) ($totalGeneral['total_ingreso'] ?? 0),
                    'cantidad_ingreso_totem' => (int) ($totalGeneral['cantidad_ordenes'] ?? 0),
                    'proximo_waitry_order_id' => ((int) ($cierreTotem->waitry_order_id_hasta ?? 0)) + 1,
                    'url_comprobante_pdf' => route('gastronomia_jornada_comprobante_cierre_totem', [
                        'jornadaId' => $jornada->id,
                        'inline' => 1,
                    ]),
                ];
                $payloadJornada['informe_z_cargado'] = is_array($cierreTotem->informe_z_json)
                    && isset($cierreTotem->informe_z_json['totems']);
            }

            return response()->json([
                'ok' => true,
                'mensaje' => 'Jornada cerrada correctamente.',
                'jornada' => $payloadJornada,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'motivo' => 'validacion',
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
                'motivo' => 'error',
            ], 422);
        }
    }

    public function apiAnularCierre(Request $request)
    {
        if (! can('cerrar-jornada-gastronomia', false)) {
            return response()->json([
                'ok' => false,
                'error' => 'No tiene permiso para anular el cierre de jornada.',
                'motivo' => 'permiso',
            ], 403);
        }

        $jornadaId = (int) $request->input('jornada_id', 0);
        $motivo = trim((string) $request->input('motivo', ''));
        $confirmacion = trim((string) $request->input('confirmacion', ''));

        if ($jornadaId <= 0) {
            return response()->json([
                'ok' => false,
                'error' => 'Debe indicar la jornada.',
                'motivo' => 'validacion',
            ], 422);
        }

        if ($confirmacion !== 'ANULAR-JORNADA-'.$jornadaId) {
            return response()->json([
                'ok' => false,
                'error' => 'Confirmación incorrecta. Escriba exactamente: ANULAR-JORNADA-'.$jornadaId,
                'motivo' => 'validacion',
            ], 422);
        }

        if ($motivo === '') {
            return response()->json([
                'ok' => false,
                'error' => 'Debe indicar el motivo de la anulación.',
                'motivo' => 'validacion',
            ], 422);
        }

        try {
            $jornada = $this->jornadaRepository->findOrFail($jornadaId);
            $this->assertAccesoEmpresa((int) $jornada->empresa_id);

            $this->jornadaService->anularCierre($jornadaId, $motivo);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Cierre de jornada anulado. La jornada quedó nuevamente abierta.',
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'motivo' => 'validacion',
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
                'motivo' => 'error',
            ], 422);
        }
    }

    public function apiEliminar(Request $request)
    {
        if (! can('eliminar-jornada-gastronomia', false)) {
            return response()->json([
                'ok' => false,
                'error' => 'No tiene permiso para eliminar jornadas (permiso: eliminar-jornada-gastronomia).',
                'motivo' => 'permiso',
            ], 403);
        }

        $jornadaId = (int) $request->input('jornada_id', 0);
        if ($jornadaId <= 0) {
            return response()->json([
                'ok' => false,
                'error' => 'Debe indicar la jornada a eliminar.',
                'motivo' => 'validacion',
            ], 422);
        }

        try {
            $jornada = $this->jornadaRepository->findOrFail($jornadaId);
            $this->assertAccesoEmpresa((int) $jornada->empresa_id);

            $this->jornadaService->eliminar($jornadaId);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Jornada eliminada correctamente.',
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'motivo' => 'validacion',
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
                'motivo' => 'error',
            ], 422);
        }
    }
}
