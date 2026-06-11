<?php

namespace App\Http\Controllers\Caja\Estacionamiento;

use App\Http\Controllers\Controller;
use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Repositories\Caja\Estacionamiento\JornadaEstacionamientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\Estacionamiento\JornadaEstacionamientoService;
use App\Services\Caja\RendicionEstacionamientoAuditoriaAnitaService;
use App\Support\Caja\Estacionamiento\EstacionamientoJornadaNumeracionComprobanteSupport;
use App\Support\Caja\Estacionamiento\EstacionamientoTurnoOperativoTotalesSupport;
use App\Support\Caja\EstacionamientoJornadaComprobantePermiso;
use App\Support\Configuracion\EmpresaLogoArchivo;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class JornadaEstacionamientoController extends Controller
{
    public function __construct(
        private readonly JornadaEstacionamientoService $jornadaService,
        private readonly JornadaEstacionamientoRepositoryInterface $jornadaRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('gestionar-jornada-estacionamiento');

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
            if ($jornadaHistorial->estado === JornadaEstacionamiento::ESTADO_CERRADA) {
                $anulacionCierrePorJornada[(int) $jornadaHistorial->id] = $this->jornadaService->resumenAnulacionCierre($jornadaHistorial);
            }
        }

        $puedeAnularCierre = can('cerrar-jornada-estacionamiento', false);

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

        return view('caja.estacionamiento.jornada.index', [
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
            'puede_abrir' => can('abrir-jornada-estacionamiento', false),
            'puede_cerrar' => can('cerrar-jornada-estacionamiento', false),
            'puede_eliminar' => can('eliminar-jornada-estacionamiento', false),
            'url_saneamiento_turno' => url('caja/estacionamiento/saneamiento-turno'),
        ]);
    }

    public function apiEstado(int $empresaId)
    {
        can('gestionar-jornada-estacionamiento');

        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa inválida.'], 422);
        }

        $this->assertAccesoEmpresa($empresaId);

        return response()->json([
            'ok' => true,
            ...$this->jornadaService->estadoParaEmpresa($empresaId),
        ]);
    }

    public function apiAbrir(Request $request)
    {
        if (! can('abrir-jornada-estacionamiento', false)) {
            return response()->json([
                'ok' => false,
                'error' => 'No tiene permiso para abrir jornadas (permiso: abrir-jornada-estacionamiento).',
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
                'error' => JornadaEstacionamientoService::mensajeDesdeExcepcion($e),
                'motivo' => 'error',
            ], 422);
        }
    }

    public function apiCerrar(Request $request)
    {
        if (! can('cerrar-jornada-estacionamiento', false)) {
            return response()->json([
                'ok' => false,
                'error' => 'No tiene permiso para cerrar jornadas (permiso: cerrar-jornada-estacionamiento).',
                'motivo' => 'permiso',
            ], 403);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $observacion = $request->input('observacion');

        $this->assertAccesoEmpresa($empresaId);

        try {
            $jornada = $this->jornadaService->cerrar($empresaId, is_string($observacion) ? $observacion : null);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Jornada cerrada correctamente.',
                'jornada' => [
                    'id' => $jornada->id,
                    'fecha_jornada' => $jornada->fecha_jornada->format('Y-m-d'),
                    'cierre_en' => $jornada->cierre_en?->format('Y-m-d H:i:s'),
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
                'error' => JornadaEstacionamientoService::mensajeDesdeExcepcion($e),
                'motivo' => 'error',
            ], 422);
        }
    }

    public function apiAnularCierre(Request $request)
    {
        if (! can('cerrar-jornada-estacionamiento', false)) {
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
                'error' => JornadaEstacionamientoService::mensajeDesdeExcepcion($e),
                'motivo' => 'error',
            ], 422);
        }
    }

    public function apiEliminar(Request $request)
    {
        if (! can('eliminar-jornada-estacionamiento', false)) {
            return response()->json([
                'ok' => false,
                'error' => 'No tiene permiso para eliminar jornadas (permiso: eliminar-jornada-estacionamiento).',
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
                'error' => JornadaEstacionamientoService::mensajeDesdeExcepcion($e),
                'motivo' => 'error',
            ], 422);
        }
    }

    public function comprobanteTotalesZ(Request $request, int $jornadaId)
    {
        if (! EstacionamientoJornadaComprobantePermiso::puedeVerComprobanteTotalesZ()) {
            abort(403, 'No tiene permiso para ver el reporte Totales Z de la jornada.');
        }

        $jornada = JornadaEstacionamiento::query()
            ->with(['empresa', 'usuarioApertura', 'usuarioCierre'])
            ->findOrFail($jornadaId);

        $this->assertAccesoEmpresa((int) $jornada->empresa_id);

        if ($jornada->estado !== JornadaEstacionamiento::ESTADO_CERRADA || $jornada->cierre_en === null) {
            abort(404, 'La jornada no está cerrada.');
        }

        $empresaNombre = (string) ($jornada->empresa?->nombre ?? '');
        $fechaJornada = $jornada->fecha_jornada?->format('d/m/Y') ?? '';
        $numeracion = EstacionamientoJornadaNumeracionComprobanteSupport::paraJornada($jornada);
        $totales = EstacionamientoTurnoOperativoTotalesSupport::calcularPorJornada($jornada);

        $filasZ = [];
        $auditoriaOk = null;
        $tolerancia = (float) config('rendicion_estacionamiento_anita.auditoria_diaria.tolerancia', 0.02);
        $auditoriaDisponible = (bool) config('rendicion_estacionamiento_anita.sincronizar', false);

        if ($auditoriaDisponible && $jornada->fecha_jornada !== null) {
            try {
                $auditoria = app(RendicionEstacionamientoAuditoriaAnitaService::class)->auditarFechaJornada(
                    (int) $jornada->empresa_id,
                    $jornada->fecha_jornada->format('Y-m-d'),
                    $tolerancia,
                );
                $filasZ = is_array($auditoria['filas'] ?? null) ? $auditoria['filas'] : [];
                $auditoriaOk = ! (bool) ($auditoria['resumen']['requiere_alerta'] ?? true);
            } catch (Throwable) {
                $auditoriaDisponible = false;
            }
        }

        $datos = [
            'titulo' => 'Totales Z — cierre de jornada estacionamiento',
            'subtitulo' => $empresaNombre.' · Jornada #'.$jornadaId,
            'logo' => EmpresaLogoArchivo::dataUriDesdeNombre($empresaNombre),
            'empresa_nombre' => $empresaNombre,
            'jornada_id' => $jornadaId,
            'fecha_jornada' => $fechaJornada,
            'fecha_emision_comprobante' => now()->format('d/m/Y H:i'),
            'apertura_jornada_en' => $jornada->apertura_en?->format('d/m/Y H:i') ?? '',
            'cierre_jornada_en' => $jornada->cierre_en?->format('d/m/Y H:i') ?? '',
            'usuario_apertura' => (string) ($jornada->usuarioApertura?->nombre ?? ''),
            'usuario_cierre' => (string) ($jornada->usuarioCierre?->nombre ?? ''),
            'numeracion_resumen' => (string) ($numeracion['resumen_etiqueta'] ?? ''),
            'numeracion_filas' => is_array($numeracion['filas'] ?? null) ? $numeracion['filas'] : [],
            'totales_jornada' => $totales,
            'filas_z' => $filasZ,
            'auditoria_disponible' => $auditoriaDisponible,
            'auditoria_ok' => $auditoriaOk,
            'tolerancia' => $tolerancia,
        ];

        $nombre = 'totales_z_jornada_estacionamiento_'.$jornadaId.'.pdf';
        $html = view('caja.estacionamiento.jornada.comprobante_totales_z', compact('datos'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'landscape');
        $pdf->loadHTML($html, 'UTF-8');

        return $request->boolean('inline', true)
            ? $pdf->stream($nombre)
            : $pdf->download($nombre);
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
}
