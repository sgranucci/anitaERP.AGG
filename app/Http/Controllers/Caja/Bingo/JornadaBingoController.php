<?php

namespace App\Http\Controllers\Caja\Bingo;

use App\Http\Controllers\Controller;
use App\Models\Caja\Bingo\JornadaBingo;
use App\Repositories\Caja\Bingo\JornadaBingoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\Bingo\JornadaBingoService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class JornadaBingoController extends Controller
{
    public function __construct(
        private readonly JornadaBingoService $jornadaService,
        private readonly JornadaBingoRepositoryInterface $jornadaRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('gestionar-jornada-bingo');

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
            if ($jornadaHistorial->estado === JornadaBingo::ESTADO_CERRADA) {
                $anulacionCierrePorJornada[(int) $jornadaHistorial->id] = $this->jornadaService->resumenAnulacionCierre($jornadaHistorial);
            }
        }

        $eliminacionJornadaAbierta = null;
        if ($estado && ! empty($estado['jornada_id'])) {
            $jornadaAbiertaId = (int) $estado['jornada_id'];
            $eliminacionJornadaAbierta = $eliminacionPorJornada[$jornadaAbiertaId]
                ?? $this->jornadaService->resumenEliminacion($this->jornadaRepository->findOrFail($jornadaAbiertaId));
        }

        return view('caja.bingo.jornada.index', [
            'empresas' => $empresas,
            'empresa_id' => $empresaId,
            'estado' => $estado,
            'historial' => $historial,
            'eliminacion_por_jornada' => $eliminacionPorJornada,
            'anulacion_cierre_por_jornada' => $anulacionCierrePorJornada,
            'cierre_anulable' => can('cerrar-jornada-bingo', false) && $empresaId > 0
                ? $this->jornadaService->cierreAnulableParaEmpresa($empresaId)
                : null,
            'puede_anular_cierre' => can('cerrar-jornada-bingo', false),
            'fecha_hoy' => $estado['fecha_jornada_sugerida_abrir'] ?? now()->format('Y-m-d'),
            'fecha_jornada_minima' => $estado['fecha_jornada_minima_abrir'] ?? null,
            'fecha_maxima' => $estado['fecha_jornada_maxima_abrir'] ?? now()->format('Y-m-d'),
            'eliminacion_jornada_abierta' => $eliminacionJornadaAbierta,
            'puede_abrir' => can('abrir-jornada-bingo', false),
            'puede_cerrar' => can('cerrar-jornada-bingo', false),
            'puede_eliminar' => can('eliminar-jornada-bingo', false),
            'url_habilitacion_turno' => route('bingo_habilitacion_turno'),
        ]);
    }

    public function apiEstado(int $empresaId)
    {
        can('gestionar-jornada-bingo');
        $this->assertAccesoEmpresa($empresaId);

        return response()->json([
            'ok' => true,
            ...$this->jornadaService->estadoParaEmpresa($empresaId),
        ]);
    }

    public function apiAbrir(Request $request)
    {
        can('abrir-jornada-bingo');
        $empresaId = (int) $request->input('empresa_id', 0);
        $this->assertAccesoEmpresa($empresaId);

        try {
            $jornada = $this->jornadaService->abrir(
                $empresaId,
                (string) $request->input('fecha_jornada', now()->format('Y-m-d')),
                $request->input('observacion'),
            );

            return response()->json(['ok' => true, 'jornada_id' => (int) $jornada->id]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiCerrar(Request $request)
    {
        can('cerrar-jornada-bingo');
        $empresaId = (int) $request->input('empresa_id', 0);
        $this->assertAccesoEmpresa($empresaId);

        try {
            $this->jornadaService->cerrar($empresaId, $request->input('observacion'));

            return response()->json(['ok' => true]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiEliminar(Request $request)
    {
        can('eliminar-jornada-bingo');
        $jornadaId = (int) $request->input('jornada_id', 0);

        try {
            $jornada = $this->jornadaRepository->findOrFail($jornadaId);
            $this->assertAccesoEmpresa((int) $jornada->empresa_id);
            $this->jornadaService->eliminar($jornadaId);

            return response()->json(['ok' => true]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiAnularCierre(Request $request)
    {
        can('cerrar-jornada-bingo');
        $jornadaId = (int) $request->input('jornada_id', 0);
        $confirmacion = (string) $request->input('confirmacion', '');

        try {
            $jornada = $this->jornadaRepository->findOrFail($jornadaId);
            $this->assertAccesoEmpresa((int) $jornada->empresa_id);
            $resumen = $this->jornadaService->resumenAnulacionCierre($jornada);
            if ($confirmacion !== ($resumen['texto_confirmacion'] ?? '')) {
                return response()->json(['ok' => false, 'error' => 'Confirmación incorrecta.'], 422);
            }
            $this->jornadaService->anularCierre($jornadaId, (string) $request->input('motivo', ''));

            return response()->json(['ok' => true]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private function assertAccesoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }
}
