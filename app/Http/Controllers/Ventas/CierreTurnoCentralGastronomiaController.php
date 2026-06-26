<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Services\Ventas\Gastronomia\GastronomiaTurnoOperativoService;
use App\Services\Ventas\Gastronomia\GastronomiaTurnoSaneamientoService;
use App\Support\Ventas\GastronomiaCierreTurnoCentralSupport;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class CierreTurnoCentralGastronomiaController extends Controller
{
    public function __construct(
        private readonly GastronomiaTurnoOperativoService $turnoOperativoService,
        private readonly GastronomiaTurnoSaneamientoService $saneamientoService,
        private readonly GastronomiaJornadaService $jornadaService,
        private readonly GastronomiaCierreTurnoCentralSupport $cierreCentralSupport,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('gestionar-cierre-turno-central-gastronomia');

        $empresas = $this->empresaRepository->allFiltrado();
        $empresaId = (int) $request->input('empresa_id', $empresas->first()->id ?? 0);
        if ($empresaId > 0 && ! $empresas->contains('id', $empresaId)) {
            $empresaId = (int) ($empresas->first()->id ?? 0);
        }

        return view('ventas.gastronomia.cierre_turno_central.index', [
            'empresas' => $empresas,
            'empresa_id' => $empresaId,
            'puede_cerrar' => can('cerrar-turno-central-gastronomia', false),
            'puede_ver_factura' => can('ver-factura-gastronomia', false),
            'url_factura_ver_base' => url('ventas/gastronomia/facturas-dia'),
            'url_saneamiento_turno' => route('gastronomia_saneamiento_turno'),
        ]);
    }

    public function apiListarTurnos(Request $request)
    {
        can('gestionar-cierre-turno-central-gastronomia');

        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 || ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return response()->json(['ok' => false, 'error' => 'Empresa inválida.'], 422);
        }

        $jornada = $this->jornadaService->jornadaAbierta($empresaId);

        return response()->json([
            'ok' => true,
            'jornada_abierta' => $jornada !== null,
            'fecha_jornada_fmt' => $jornada?->fecha_jornada?->format('d/m/Y'),
            'turnos' => $this->turnoOperativoService->listarTurnosParaCierreCentral($empresaId, $jornada),
            'url_saneamiento_turno' => route('gastronomia_saneamiento_turno', ['empresa_id' => $empresaId]),
        ]);
    }

    public function apiEstadoTurno(Request $request)
    {
        can('gestionar-cierre-turno-central-gastronomia');

        $empresaId = (int) $request->input('empresa_id', 0);
        $turnoId = (int) $request->input('turno_operativo_id', 0);

        try {
            $turno = $this->cierreCentralSupport->resolverTurnoHabilitado($turnoId, $empresaId);
            $estado = $this->turnoOperativoService->estadoParaTurnoOperativo($turno);
            $estado['url_factura_ver_base'] = url('ventas/gastronomia/facturas-dia');

            return response()->json([
                'ok' => true,
                ...$estado,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiConciliacionTurno(Request $request)
    {
        can('gestionar-cierre-turno-central-gastronomia');

        $empresaId = (int) $request->input('empresa_id', 0);
        $turnoId = (int) $request->input('turno_operativo_id', 0);

        try {
            $turno = $this->cierreCentralSupport->resolverTurnoHabilitado($turnoId, $empresaId);
            $turno->loadMissing('jornada');
            $pc = (string) $turno->identificador_pc;
            $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
                ?? Carbon::today()->format('Y-m-d');

            $page = (int) $request->input('page', 0);
            $perPage = (int) $request->input('per_page', GastronomiaTurnoOperativoTotalesSupport::CONCILIACION_FILAS_POR_PAGINA);
            $soloDiferencias = $request->boolean('solo_diferencias');

            $grilla = GastronomiaTurnoOperativoTotalesSupport::grillaConciliacionRespuesta(
                $pc,
                $empresaId,
                $fechaJornada,
                $turno->habilitacion_en,
                $page,
                $perPage,
                $soloDiferencias,
            );

            return response()->json([
                'ok' => true,
                'grilla' => $grilla,
                'url_factura_ver_base' => url('ventas/gastronomia/facturas-dia'),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiConciliacionMedio(Request $request)
    {
        can('gestionar-cierre-turno-central-gastronomia');

        $empresaId = (int) $request->input('empresa_id', 0);
        $turnoId = (int) $request->input('turno_operativo_id', 0);
        $cuentacajaId = (int) $request->input('cuentacaja_id', 0);

        try {
            $turno = $this->cierreCentralSupport->resolverTurnoHabilitado($turnoId, $empresaId);
            $turno->loadMissing('jornada');
            $pc = (string) $turno->identificador_pc;
            $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
                ?? Carbon::today()->format('Y-m-d');

            $mozoIdInput = $request->input('mozo_id');
            $mozoId = ($mozoIdInput !== null && $mozoIdInput !== '' && (int) $mozoIdInput > 0)
                ? (int) $mozoIdInput
                : null;

            if ($cuentacajaId <= 0) {
                return response()->json(['ok' => false, 'error' => 'Medio de pago inválido.'], 422);
            }

            $facturas = GastronomiaTurnoOperativoTotalesSupport::facturasPorMedioPago(
                $pc,
                $empresaId,
                $fechaJornada,
                $cuentacajaId,
                $turno->habilitacion_en,
                $mozoId,
            );

            $totales = GastronomiaTurnoOperativoTotalesSupport::calcular(
                $pc,
                $empresaId,
                $fechaJornada,
                $turno->habilitacion_en,
            );
            $medioNombre = '';
            foreach ($totales['por_medio_pago'] ?? [] as $p) {
                if ((int) $p['cuentacaja_id'] === $cuentacajaId) {
                    $nombre = trim((string) ($p['nombre'] ?? ''));
                    $medioNombre = $nombre !== '' ? $nombre : (string) ($p['codigo'] ?? '');
                    break;
                }
            }

            return response()->json([
                'ok' => true,
                'cuentacaja_id' => $cuentacajaId,
                'mozo_id' => $mozoId,
                'medio_nombre' => $medioNombre,
                'facturas' => $facturas,
                'url_factura_ver_base' => url('ventas/gastronomia/facturas-dia'),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiConciliacionNotasCredito(Request $request)
    {
        return $this->conciliacionDetalleGenerico($request, 'notasCreditoDelTurno', 'notas_credito');
    }

    public function apiConciliacionInvitaciones(Request $request)
    {
        return $this->conciliacionDetalleGenerico($request, 'invitacionesDelTurno', 'invitaciones');
    }

    public function apiCerrar(Request $request)
    {
        can('cerrar-turno-central-gastronomia');

        $empresaId = (int) $request->input('empresa_id', 0);
        $turnoId = (int) $request->input('turno_operativo_id', 0);

        try {
            $this->cierreCentralSupport->resolverTurnoHabilitado($turnoId, $empresaId);

            $resultado = $this->saneamientoService->cerrarTurnoRemoto(
                $turnoId,
                GastronomiaIdentificadorPc::resolver($request),
                $request->input('observacion_cierre') ?? $request->input('observacion'),
                $request->has('redondeo_invitaciones')
                    ? (float) $request->input('redondeo_invitaciones')
                    : null,
                $request->has('redondeo_turno')
                    ? (float) $request->input('redondeo_turno')
                    : null,
                $request->has('sobrante_faltante')
                    ? (float) $request->input('sobrante_faltante')
                    : null,
                $request->input('medios_contado'),
                false,
            );

            return response()->json(['ok' => true, ...$resultado]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param  'notasCreditoDelTurno'|'invitacionesDelTurno'  $metodo
     */
    private function conciliacionDetalleGenerico(Request $request, string $metodo, string $claveRespuesta): \Illuminate\Http\JsonResponse
    {
        can('gestionar-cierre-turno-central-gastronomia');

        $empresaId = (int) $request->input('empresa_id', 0);
        $turnoId = (int) $request->input('turno_operativo_id', 0);

        try {
            $turno = $this->cierreCentralSupport->resolverTurnoHabilitado($turnoId, $empresaId);
            $turno->loadMissing('jornada');
            $pc = (string) $turno->identificador_pc;
            $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
                ?? Carbon::today()->format('Y-m-d');

            $mozoIdInput = $request->input('mozo_id');
            $mozoId = ($mozoIdInput !== null && $mozoIdInput !== '' && (int) $mozoIdInput > 0)
                ? (int) $mozoIdInput
                : null;

            $filas = GastronomiaTurnoOperativoTotalesSupport::$metodo(
                $pc,
                $empresaId,
                $fechaJornada,
                $turno->habilitacion_en,
                $mozoId,
            );

            return response()->json([
                'ok' => true,
                'mozo_id' => $mozoId,
                $claveRespuesta => $filas,
                'cantidad' => count($filas),
                'url_factura_ver_base' => url('ventas/gastronomia/facturas-dia'),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
