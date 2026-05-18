<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Ventas\JornadaGastronomiaRepositoryInterface;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class JornadaGastronomiaController extends Controller
{
    public function __construct(
        private readonly GastronomiaJornadaService $jornadaService,
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

        return view('ventas.gastronomia.jornada.index', [
            'empresas' => $empresas,
            'empresa_id' => $empresaId,
            'estado' => $estado,
            'historial' => $historial,
            'fecha_hoy' => now()->format('Y-m-d'),
            'puede_abrir' => can('abrir-jornada-gastronomia', false),
            'puede_cerrar' => can('cerrar-jornada-gastronomia', false),
        ]);
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
                'error' => GastronomiaJornadaService::mensajeDesdeExcepcion($e),
                'motivo' => 'error',
            ], 422);
        }
    }
}
