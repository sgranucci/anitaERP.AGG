<?php

namespace App\Http\Controllers\Caja\Estacionamiento;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Caja\Estacionamiento\TurnoEstacionamientoRepositoryInterface;
use App\Services\Caja\Estacionamiento\EstacionamientoTurnoSaneamientoService;
use App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc;
use App\Support\Caja\Estacionamiento\EstacionamientoSaneamientoTurnoReporteSupport;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class EstacionamientoSaneamientoTurnoController extends Controller
{
    public function __construct(
        private readonly EstacionamientoTurnoSaneamientoService $saneamientoService,
        private readonly EstacionamientoSaneamientoTurnoReporteSupport $reporteSupport,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly TurnoEstacionamientoRepositoryInterface $turnoEstacionamientoRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('gestionar-saneamiento-turno-estacionamiento');

        $empresas = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', $empresas->first()->id ?? 0);
        $identificadorPc = trim((string) $request->input('identificador_pc', ''));

        $turnos = $empresaId > 0
            ? $this->turnoEstacionamientoRepository->listarParaSelect($empresaId)
            : collect();

        return view('caja.estacionamiento.saneamiento_turno.index', [
            'empresas' => $empresas,
            'empresa_id' => $empresaId,
            'identificador_pc' => $identificadorPc,
            'turnos' => $turnos,
            'puede_ejecutar' => can('ejecutar-saneamiento-turno-estacionamiento', false),
            'url_facturas_dia' => url('caja/estacionamiento/facturas-dia'),
        ]);
    }

    public function apiDiagnostico(Request $request)
    {
        can('gestionar-saneamiento-turno-estacionamiento');

        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Empresa inválida.'], 422);
        }

        $pc = trim((string) $request->input('identificador_pc', ''));

        return response()->json(
            $this->saneamientoService->diagnostico(
                $empresaId,
                $pc !== '' ? $pc : null,
            ),
        );
    }

    public function apiExtenderCierre(Request $request)
    {
        can('ejecutar-saneamiento-turno-estacionamiento');

        try {
            $resultado = $this->saneamientoService->extenderCierreParaCubrirHuerfanas(
                (int) $request->input('turno_operativo_id', 0),
            );

            return response()->json(['ok' => true, ...$resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiCrearRetroactivo(Request $request)
    {
        can('ejecutar-saneamiento-turno-estacionamiento');

        $pc = trim((string) $request->input('identificador_pc', ''));
        if ($pc === '') {
            return response()->json(['ok' => false, 'error' => 'Indique la terminal (identificador_pc).'], 422);
        }

        $empresaId = (int) $request->input('empresa_id', 0);
        $cfg = \App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento::query()
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->where('identificador_pc', $pc)
            ->first();

        if ($cfg === null) {
            return response()->json([
                'ok' => false,
                'error' => 'No hay configuración PV para la terminal '.$pc.'.',
            ], 422);
        }

        try {
            $resultado = $this->saneamientoService->crearTurnoRetroactivoCerrado(
                $cfg,
                $pc,
                (int) $request->input('turno_estacionamiento_id', 0),
                (float) $request->input('monto_habilitacion', 0),
                $request->input('observacion'),
            );

            return response()->json(['ok' => true, ...$resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiCerrarTurnoRemoto(Request $request)
    {
        can('ejecutar-saneamiento-turno-estacionamiento');

        $turnoId = (int) $request->input('turno_operativo_id', 0);
        if ($turnoId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Turno operativo inválido.'], 422);
        }

        try {
            $resultado = $this->saneamientoService->cerrarTurnoRemoto(
                $turnoId,
                EstacionamientoIdentificadorPc::resolver($request),
                $request->input('observacion'),
                $request->has('redondeo_invitaciones')
                    ? (float) $request->input('redondeo_invitaciones')
                    : null,
                $request->has('redondeo_turno')
                    ? (float) $request->input('redondeo_turno')
                    : null,
                $request->has('sobrante_faltante')
                    ? (float) $request->input('sobrante_faltante')
                    : null,
            );

            return response()->json(['ok' => true, ...$resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiRecalcularTotales(Request $request)
    {
        can('ejecutar-saneamiento-turno-estacionamiento');

        $turnoId = (int) $request->input('turno_operativo_id', 0);
        if ($turnoId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Turno inválido.'], 422);
        }

        try {
            $turno = \App\Models\Caja\Estacionamiento\TurnoOperativoEstacionamiento::query()->findOrFail($turnoId);
            $resultado = $this->saneamientoService->recalcularMontosTurnoCerrado($turno);

            return response()->json(['ok' => true, ...$resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiCerrarCuentasPendientes(Request $request)
    {
        can('ejecutar-saneamiento-turno-estacionamiento');

        $turnoId = (int) $request->input('turno_operativo_id', 0);
        $empresaId = (int) $request->input('empresa_id', 0);
        $pc = trim((string) $request->input('identificador_pc', ''));
        $cuentaIds = $request->input('cuenta_ids', []);
        if (! is_array($cuentaIds)) {
            $cuentaIds = [];
        }

        if ($turnoId <= 0 && $cuentaIds === [] && ($empresaId <= 0 || $pc === '')) {
            return response()->json([
                'ok' => false,
                'error' => 'Indique turno_operativo_id, (empresa_id + identificador_pc) o (empresa_id + cuenta_ids[]).',
            ], 422);
        }

        try {
            if ($turnoId > 0) {
                $resultado = $this->saneamientoService->cerrarCuentasPendientesEnTerminal(
                    $turnoId,
                    (string) $request->input('confirmacion', ''),
                    $request->input('motivo'),
                );
            } elseif ($cuentaIds !== []) {
                if ($empresaId <= 0) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'Indique empresa_id cuando use cuenta_ids[].',
                    ], 422);
                }
                $resultado = $this->saneamientoService->cerrarCuentasPendientesPorIds(
                    $empresaId,
                    $cuentaIds,
                    (string) $request->input('confirmacion', ''),
                    $request->input('motivo'),
                );
            } else {
                $resultado = $this->saneamientoService->cerrarCuentasPendientesPorTerminal(
                    $empresaId,
                    $pc,
                    (string) $request->input('confirmacion', ''),
                    $request->input('motivo'),
                );
            }

            return response()->json(['ok' => true, ...$resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiAnularTicketsPendientes(Request $request)
    {
        can('ejecutar-saneamiento-turno-estacionamiento');

        $turnoId = (int) $request->input('turno_operativo_id', 0);
        $empresaId = (int) $request->input('empresa_id', 0);
        $pc = trim((string) $request->input('identificador_pc', ''));

        if ($turnoId <= 0 && ($empresaId <= 0 || $pc === '')) {
            return response()->json([
                'ok' => false,
                'error' => 'Indique turno_operativo_id o (empresa_id + identificador_pc).',
            ], 422);
        }

        try {
            if ($turnoId > 0) {
                $resultado = $this->saneamientoService->anularTicketsPendientesEnTerminal(
                    $turnoId,
                    (string) $request->input('confirmacion', ''),
                    $request->input('motivo'),
                );
            } else {
                $resultado = $this->saneamientoService->anularTicketsPendientesPorTerminal(
                    $empresaId,
                    $pc,
                    (string) $request->input('confirmacion', ''),
                    $request->input('motivo'),
                );
            }

            return response()->json(['ok' => true, ...$resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function informePdf(Request $request)
    {
        can('ejecutar-saneamiento-turno-estacionamiento');

        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0) {
            abort(422, 'Empresa inválida.');
        }

        $pc = trim((string) $request->input('identificador_pc', ''));
        $diagnostico = $this->saneamientoService->diagnostico(
            $empresaId,
            $pc !== '' ? $pc : null,
        );

        if (empty($diagnostico['ok'])) {
            abort(422, (string) ($diagnostico['error'] ?? 'No se pudo generar el diagnóstico.'));
        }

        $datos = $this->reporteSupport->datosInformePdf(
            $diagnostico,
            (string) ($diagnostico['empresa_nombre'] ?? ''),
        );

        $nombre = 'saneamiento_turno_emp'.$empresaId.'_'.now()->format('Ymd_His').'.pdf';
        $inline = $request->boolean('inline', true);

        $html = view('caja.estacionamiento.saneamiento_turno.informe_pdf', compact('datos'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html, 'UTF-8');

        return $inline
            ? $pdf->stream($nombre)
            : $pdf->download($nombre);
    }
}
