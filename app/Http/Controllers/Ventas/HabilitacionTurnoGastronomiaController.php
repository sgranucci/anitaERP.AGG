<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Repositories\Ventas\TurnoGastronomiaRepositoryInterface;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Services\Ventas\Gastronomia\GastronomiaTurnoOperativoService;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class HabilitacionTurnoGastronomiaController extends Controller
{
    public function __construct(
        private readonly GastronomiaTurnoOperativoService $turnoOperativoService,
        private readonly GastronomiaJornadaService $jornadaService,
        private readonly GastronomiaCuentaService $cuentaService,
        private readonly TurnoGastronomiaRepositoryInterface $turnoGastronomiaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('gestionar-habilitacion-turno-gastronomia');

        if (! GastronomiaTurnoOperativoService::requiereHabilitacionTurno()) {
            return view('ventas.gastronomia.habilitacion_turno.index', [
                'modo_caja_directo' => true,
                'identificador_pc' => GastronomiaIdentificadorPc::resolver($request),
            ]);
        }

        $cfg = $this->cuentaService->resolverConfiguracionPv($request);
        $pc = GastronomiaIdentificadorPc::resolver($request);
        $estado = null;
        $turnos = collect();

        if ($cfg !== null) {
            $estado = $this->turnoOperativoService->estadoParaTerminal($cfg, $pc);
            $turnos = $this->turnoGastronomiaRepository->listarParaSelect((int) $cfg->empresa_id);
        }

        return view('ventas.gastronomia.habilitacion_turno.index', [
            'modo_caja_directo' => false,
            'cfg' => $cfg,
            'identificador_pc' => $pc,
            'estado' => $estado,
            'turnos' => $turnos,
            'jornada' => $cfg ? $this->jornadaService->estadoParaEmpresa((int) $cfg->empresa_id) : null,
            'puede_habilitar' => can('habilitar-turno-gastronomia', false),
            'puede_cerrar' => can('cerrar-turno-operativo-gastronomia', false),
        ]);
    }

    public function apiEstado(Request $request)
    {
        can('gestionar-habilitacion-turno-gastronomia');

        $cfg = $this->cuentaService->resolverConfiguracionPv($request);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV para esta terminal.'], 422);
        }

        $pc = GastronomiaIdentificadorPc::resolver($request);

        return response()->json([
            'ok' => true,
            ...$this->turnoOperativoService->estadoParaTerminal($cfg, $pc),
        ]);
    }

    public function apiHabilitar(Request $request)
    {
        can('habilitar-turno-gastronomia');

        $cfg = $this->cuentaService->resolverConfiguracionPv($request);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV para esta terminal.'], 422);
        }

        try {
            $turno = $this->turnoOperativoService->habilitar(
                $cfg,
                GastronomiaIdentificadorPc::resolver($request),
                (int) $request->input('turno_gastronomia_id', 0),
                (float) $request->input('monto_habilitacion', 0),
                (int) $request->input('usuario_habilitado_id', 0),
                $request->input('observacion'),
            );

            return response()->json([
                'ok' => true,
                'mensaje' => 'Turno habilitado correctamente.',
                'turno_operativo_id' => $turno->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiCerrar(Request $request)
    {
        can('cerrar-turno-operativo-gastronomia');

        $cfg = $this->cuentaService->resolverConfiguracionPv($request);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV para esta terminal.'], 422);
        }

        $pc = GastronomiaIdentificadorPc::resolver($request);
        $activo = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);
        if ($activo === null) {
            return response()->json(['ok' => false, 'error' => 'No hay turno habilitado en esta terminal.'], 422);
        }

        try {
            $turno = $this->turnoOperativoService->cerrar($activo, $pc, [
                'redondeo_invitaciones' => $request->input('redondeo_invitaciones'),
                'redondeo_turno' => $request->input('redondeo_turno'),
                'sobrante_faltante' => $request->input('sobrante_faltante'),
                'observacion_cierre' => $request->input('observacion_cierre'),
            ]);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Turno cerrado correctamente.',
                'url_comprobante_pdf' => route('gastronomia_cierre_turno_comprobante_cierre', [
                    'id' => $turno->id,
                    'inline' => 1,
                ]),
                'turno' => [
                    'id' => $turno->id,
                    'monto_facturacion_turno' => $turno->monto_facturacion_turno,
                    'monto_facturacion_dia' => $turno->monto_facturacion_dia,
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
