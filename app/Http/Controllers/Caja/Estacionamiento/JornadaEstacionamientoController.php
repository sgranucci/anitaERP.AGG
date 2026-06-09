<?php

namespace App\Http\Controllers\Caja\Estacionamiento;

use App\Http\Controllers\Controller;
use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Repositories\Caja\Estacionamiento\JornadaEstacionamientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\Estacionamiento\JornadaEstacionamientoService;
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
            'puede_abrir' => can('abrir-jornada-estacionamiento', false),
            'puede_cerrar' => can('cerrar-jornada-estacionamiento', false),
            'puede_eliminar' => can('eliminar-jornada-estacionamiento', false),
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
