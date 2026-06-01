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
use Illuminate\Http\Request;
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

            $preview = $this->cierreTotemJornadaService->previewParaJornadaAbierta($jornada);
            if ($preview === null) {
                return response()->json(['ok' => false, 'error' => 'Cierre tótem Waitry no habilitado.'], 422);
            }

            $resumen = [
                'por_totem' => $preview['por_totem'] ?? [],
                'total_general' => $preview['total_general'] ?? [],
            ];

            $resultado = $this->informeZService->guardarBorradorJornadaAbierta(
                $jornada,
                $request->all(),
                $resumen,
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

        $datos = $this->cierreTotemJornadaService->datosComprobantePdf($cierre);
        $nombre = 'cierre_totem_jornada_'.$jornadaId.'.pdf';

        $html = view('ventas.gastronomia.jornada.comprobante_cierre_totem', compact('datos'))->render();
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

        $asignadas = $this->empresaRepository->traeEmpresasAsignadas();
        if (count($asignadas) > 1 && ! in_array($empresaId, $asignadas, true)) {
            abort(403, 'Empresa no permitida para su usuario.');
        }
    }

    public function apiEstado(int $empresaId)
    {
        can('gestionar-jornada-gastronomia');

        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa inválida.'], 422);
        }

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
            @set_time_limit(120);

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

        try {
            $jornada = $this->jornadaService->cerrar(
                $empresaId,
                is_string($observacion) ? $observacion : null,
                $informeZTotemsArr,
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
