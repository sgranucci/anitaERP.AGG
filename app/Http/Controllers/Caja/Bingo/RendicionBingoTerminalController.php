<?php

namespace App\Http\Controllers\Caja\Bingo;

use App\Http\Controllers\Controller;
use App\Models\Caja\Bingo\TurnoOperativoBingo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Caja\Bingo\BingoPvService;
use App\Services\Caja\Bingo\RendicionBingoCajaService;
use App\Support\Caja\Bingo\BingoIdentificadorPc;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RendicionBingoTerminalController extends Controller
{
    public function __construct(
        private readonly RendicionBingoCajaService $service,
        private readonly BingoPvService $pvService,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function cargar(Request $request)
    {
        can('crear-rendicion-bingo-caja');

        $pc = BingoIdentificadorPc::resolver($request);
        $empresas = $this->empresaRepository->allFiltrado();
        $empresasOperables = $this->pvService->empresasConPvEnTerminal($pc, $empresas);
        $empresaId = (int) $request->input('empresa_id', $empresasOperables->first()->id ?? 0);

        if ($empresaId > 0) {
            $this->assertEmpresaPermitida($empresaId);
        }

        $datos = null;
        $error = null;
        if ($empresaId > 0) {
            try {
                $datos = $this->service->datosPantallaCarga($empresaId, $pc);
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        return view('caja.bingo.rendicion.cargar', [
            'empresa_query' => $empresasOperables,
            'empresa_id' => $empresaId,
            'identificador_pc' => $pc,
            'datos' => $datos,
            'error' => $error,
            'modo_edicion' => false,
            'turno_id' => 0,
        ]);
    }

    public function editar(Request $request, int $turno)
    {
        can('crear-rendicion-bingo-caja');

        $turnoModel = TurnoOperativoBingo::query()->findOrFail($turno);
        $this->assertEmpresaPermitida((int) $turnoModel->empresa_id);

        $datos = null;
        $error = null;
        try {
            $datos = $this->service->datosPantallaEdicion($turno);
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        }

        $empresas = $this->empresaRepository->allFiltrado();

        return view('caja.bingo.rendicion.cargar', [
            'empresa_query' => $empresas,
            'empresa_id' => (int) $turnoModel->empresa_id,
            'identificador_pc' => (string) $turnoModel->identificador_pc,
            'datos' => $datos,
            'error' => $error,
            'modo_edicion' => true,
            'turno_id' => $turno,
        ]);
    }

    public function apiCalcular(Request $request)
    {
        can('crear-rendicion-bingo-caja');
        $empresaId = (int) $request->input('empresa_id', 0);
        $this->assertEmpresaPermitida($empresaId);

        $calculo = $this->service->calcular(
            $empresaId,
            is_array($request->input('cartones')) ? $request->input('cartones') : [],
            is_array($request->input('montos_manuales')) ? $request->input('montos_manuales') : [],
        );

        return response()->json(['ok' => true, 'calculo' => $calculo]);
    }

    public function apiGuardar(Request $request)
    {
        can('crear-rendicion-bingo-caja');

        $turnoId = (int) $request->input('turno_operativo_id', 0);

        try {
            if ($turnoId > 0) {
                $turnoModel = TurnoOperativoBingo::query()->findOrFail($turnoId);
                $this->assertEmpresaPermitida((int) $turnoModel->empresa_id);
                $turno = $this->service->guardarEdicionTurnoCerrado($turnoId, $request->all());

            return response()->json([
                'ok' => true,
                'turno_operativo_id' => (int) $turno->id,
                'mensaje' => 'Rendición actualizada. Presente en Caja → Rendiciones bingo cuando esté lista.',
                'url_comprobante_pdf' => route('bingo_cierre_turno_comprobante_cierre', [
                    'id' => $turno->id,
                    'inline' => 1,
                ]),
            ]);
            }

            $pc = BingoIdentificadorPc::resolver($request);
            $turno = $this->service->guardarCierreTerminal($pc, $request->all());

            return response()->json([
                'ok' => true,
                'turno_operativo_id' => (int) $turno->id,
                'mensaje' => 'Turno cerrado. Presente la rendición en Caja → Rendiciones bingo.',
                'url_comprobante_pdf' => route('bingo_cierre_turno_comprobante_cierre', [
                    'id' => $turno->id,
                    'inline' => 1,
                ]),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }
}
