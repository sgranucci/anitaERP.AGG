<?php

namespace App\Http\Controllers\Caja\Bingo;

use App\Http\Controllers\Controller;
use App\Repositories\Caja\Bingo\TurnoBingoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\Bingo\BingoPvService;
use App\Services\Caja\Bingo\BingoTurnoOperativoService;
use App\Services\Caja\Bingo\JornadaBingoService;
use App\Support\Caja\Bingo\BingoIdentificadorPc;
use Illuminate\Http\Request;
use InvalidArgumentException;

class HabilitacionTurnoBingoController extends Controller
{
    public function __construct(
        private readonly BingoTurnoOperativoService $turnoOperativoService,
        private readonly JornadaBingoService $jornadaService,
        private readonly BingoPvService $pvService,
        private readonly TurnoBingoRepositoryInterface $turnoRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        can('gestionar-habilitacion-turno-bingo');

        if (! BingoTurnoOperativoService::requiereHabilitacionTurno()) {
            return view('caja.bingo.habilitacion_turno.index', [
                'modo_caja_directo' => true,
                'identificador_pc' => BingoIdentificadorPc::resolver($request),
            ]);
        }

        $empresasAsignadas = $this->empresaRepository->allFiltrado();
        $pc = BingoIdentificadorPc::resolver($request);
        $empresasOperables = $this->pvService->empresasConPvEnTerminal($pc, $empresasAsignadas);
        $empresasSinPv = $this->pvService->empresasSinPvEnTerminal($pc, $empresasAsignadas);
        $configsPvAsignadas = $this->pvService->configuracionesPvParaEmpresasAsignadas($empresasAsignadas);

        $empresaId = (int) $request->input('empresa_id', $empresasOperables->first()->id ?? 0);
        if ($empresaId > 0 && ! $empresasOperables->contains('id', $empresaId)) {
            $empresaId = (int) ($empresasOperables->first()->id ?? 0);
        }
        if ($empresaId > 0) {
            $this->assertAccesoEmpresa($empresaId);
        }

        $cfg = $empresaId > 0 ? $this->pvService->resolverConfiguracionPv($request, $empresaId) : null;
        $estado = $cfg !== null ? $this->turnoOperativoService->estadoParaTerminal($cfg, $pc) : null;
        $turnos = $empresaId > 0 ? $this->turnoRepository->listarParaSelect($empresaId) : collect();

        return view('caja.bingo.habilitacion_turno.index', [
            'modo_caja_directo' => false,
            'empresa_query' => $empresasOperables,
            'empresas_sin_pv' => $empresasSinPv,
            'configs_pv_asignadas' => $configsPvAsignadas,
            'empresa_id' => $empresaId,
            'cfg' => $cfg,
            'identificador_pc' => $pc,
            'estado' => $estado,
            'turnos' => $turnos,
            'jornada' => $empresaId > 0 ? $this->jornadaService->estadoParaEmpresa($empresaId) : null,
            'puede_habilitar' => can('habilitar-turno-bingo', false),
            'puede_cierre_parcial' => can('cierre-parcial-turno-bingo', false),
            'puede_cerrar' => can('cerrar-turno-operativo-bingo', false),
        ]);
    }

    public function apiEstado(Request $request)
    {
        can('gestionar-habilitacion-turno-bingo');
        $empresaId = (int) $request->input('empresa_id', 0);
        $this->assertAccesoEmpresa($empresaId);

        $cfg = $this->pvService->resolverConfiguracionPv($request, $empresaId);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración de terminal para esta empresa.'], 422);
        }

        return response()->json([
            'ok' => true,
            ...$this->turnoOperativoService->estadoParaTerminal($cfg, BingoIdentificadorPc::resolver($request)),
        ]);
    }

    public function apiHabilitar(Request $request)
    {
        can('habilitar-turno-bingo');
        $empresaId = (int) $request->input('empresa_id', 0);
        $this->assertAccesoEmpresa($empresaId);

        $cfg = $this->pvService->resolverConfiguracionPv($request, $empresaId);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración de terminal.'], 422);
        }

        try {
            $turno = $this->turnoOperativoService->habilitar(
                $cfg,
                BingoIdentificadorPc::resolver($request),
                (int) $request->input('turno_bingo_id', 0),
                (float) $request->input('monto_habilitacion', 0),
                (int) $request->input('usuario_habilitado_id', 0),
                $request->input('observacion'),
            );

            return response()->json(['ok' => true, 'turno_operativo_id' => (int) $turno->id]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiCierreParcial(Request $request)
    {
        can('cierre-parcial-turno-bingo');
        $pc = BingoIdentificadorPc::resolver($request);
        $turno = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);
        if ($turno === null) {
            return response()->json(['ok' => false, 'error' => 'No hay turno habilitado.'], 422);
        }

        try {
            $parcial = $this->turnoOperativoService->registrarCierreParcial($turno, $pc);

            return response()->json(['ok' => true, 'numero_parcial' => (int) $parcial->numero_parcial]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiCerrar(Request $request)
    {
        can('cerrar-turno-operativo-bingo');
        $pc = BingoIdentificadorPc::resolver($request);
        $turno = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);
        if ($turno === null) {
            return response()->json(['ok' => false, 'error' => 'No hay turno habilitado.'], 422);
        }

        try {
            $this->turnoOperativoService->cerrar($turno, $pc, [
                'redondeo' => $request->input('redondeo'),
                'sobrante_faltante' => $request->input('sobrante_faltante'),
                'vales' => $request->input('vales'),
                'deposito' => $request->input('deposito'),
                'monto_rendicion' => $request->input('monto_rendicion'),
                'medios_contado' => $request->input('medios_contado'),
                'cartones' => $request->input('cartones'),
                'conceptos' => $request->input('conceptos'),
                'montos_manuales' => $request->input('montos_manuales'),
                'observacion_cierre' => $request->input('observacion_cierre'),
            ]);

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
