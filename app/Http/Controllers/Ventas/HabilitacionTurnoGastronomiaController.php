<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Repositories\Ventas\TurnoGastronomiaRepositoryInterface;
use App\Services\Ventas\Gastronomia\GastronomiaCuentaService;
use App\Services\Ventas\Gastronomia\GastronomiaJornadaService;
use App\Services\Ventas\Gastronomia\GastronomiaTurnoOperativoService;
use App\Support\Ventas\GastronomiaCierreTurnoReporteSupport;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;
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
        private readonly GastronomiaCierreTurnoReporteSupport $reporteSupport,
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

        $accion = (string) $request->query('accion', '');
        if (! in_array($accion, ['cierre_parcial', 'cierre_definitivo'], true)) {
            $accion = '';
        }

        return view('ventas.gastronomia.habilitacion_turno.index', [
            'modo_caja_directo' => false,
            'cfg' => $cfg,
            'identificador_pc' => $pc,
            'estado' => $estado,
            'turnos' => $turnos,
            'jornada' => $cfg ? $this->jornadaService->estadoParaEmpresa((int) $cfg->empresa_id) : null,
            'puede_habilitar' => can('habilitar-turno-gastronomia', false),
            'puede_cierre_parcial' => can('cierre-parcial-turno-gastronomia', false),
            'puede_cerrar' => can('cerrar-turno-operativo-gastronomia', false),
            'puede_ver_factura' => can('ver-factura-gastronomia', false),
            'accion' => $accion,
            'url_factura_ver_base' => url('ventas/gastronomia/facturas-dia'),
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
        $estado = $this->turnoOperativoService->estadoParaTerminal($cfg, $pc);
        $estado['url_factura_ver_base'] = url('ventas/gastronomia/facturas-dia');

        return response()->json([
            'ok' => true,
            ...$estado,
        ]);
    }

    public function apiConciliacionTurno(Request $request)
    {
        if (! can('gestionar-habilitacion-turno-gastronomia', false)) {
            return response()->json(['ok' => false, 'error' => 'Sin permiso para gestionar habilitación de turno.'], 403);
        }

        $cfg = $this->cuentaService->resolverConfiguracionPv($request);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV.'], 422);
        }

        $pc = GastronomiaIdentificadorPc::resolver($request);
        $activo = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);
        if ($activo === null) {
            return response()->json(['ok' => false, 'error' => 'No hay turno habilitado.'], 422);
        }

        $activo->loadMissing('jornada');
        $fechaJornada = $activo->jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');

        $page = (int) $request->input('page', 0);
        $perPage = (int) $request->input('per_page', GastronomiaTurnoOperativoTotalesSupport::CONCILIACION_FILAS_POR_PAGINA);
        $soloDiferencias = $request->boolean('solo_diferencias');

        try {
            $grilla = GastronomiaTurnoOperativoTotalesSupport::grillaConciliacionRespuesta(
                $pc,
                (int) $cfg->empresa_id,
                $fechaJornada,
                $activo->habilitacion_en,
                $page,
                $perPage,
                $soloDiferencias,
            );
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Error al armar la conciliación: '.$e->getMessage(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'grilla' => $grilla,
            'url_factura_ver_base' => url('ventas/gastronomia/facturas-dia'),
        ]);
    }

    public function apiConciliacionMedio(Request $request)
    {
        if (! can('gestionar-habilitacion-turno-gastronomia', false)) {
            return response()->json(['ok' => false, 'error' => 'Sin permiso para gestionar habilitación de turno.'], 403);
        }

        $cfg = $this->cuentaService->resolverConfiguracionPv($request);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV.'], 422);
        }

        $cuentacajaId = (int) $request->input('cuentacaja_id', 0);
        if ($cuentacajaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Medio de pago inválido.'], 422);
        }

        $pc = GastronomiaIdentificadorPc::resolver($request);
        $activo = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);
        if ($activo === null) {
            return response()->json(['ok' => false, 'error' => 'No hay turno habilitado.'], 422);
        }

        $activo->loadMissing('jornada');
        $fechaJornada = $activo->jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');

        $facturas = GastronomiaTurnoOperativoTotalesSupport::facturasPorMedioPago(
            $pc,
            (int) $cfg->empresa_id,
            $fechaJornada,
            $cuentacajaId,
            $activo->habilitacion_en,
        );

        $totales = GastronomiaTurnoOperativoTotalesSupport::calcular(
            $pc,
            (int) $cfg->empresa_id,
            $fechaJornada,
            $activo->habilitacion_en,
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
            'medio_nombre' => $medioNombre,
            'facturas' => $facturas,
            'url_factura_ver_base' => url('ventas/gastronomia/facturas-dia'),
        ]);
    }

    public function informeMozoPdf(Request $request)
    {
        can('cierre-parcial-turno-gastronomia');

        $cfg = $this->cuentaService->resolverConfiguracionPv($request);
        if ($cfg === null) {
            abort(422, 'Sin configuración PV.');
        }

        $pc = GastronomiaIdentificadorPc::resolver($request);
        $activo = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);
        if ($activo === null) {
            abort(422, 'No hay turno habilitado.');
        }

        $datos = $this->reporteSupport->datosInformeSoloMozo($activo, $pc);
        $nombre = 'informe_mozo_turno_'.$activo->id.'_'.now()->format('Ymd_His').'.pdf';

        return $this->pdfComprobante($datos, $nombre, $request->boolean('inline', true));
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

    public function apiCierreParcial(Request $request)
    {
        can('cierre-parcial-turno-gastronomia');

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
            $activo->loadMissing('jornada');
            $soloMozo = $request->boolean('solo_totales_mozo');
            $parcial = $this->turnoOperativoService->registrarCierreParcial($activo, $pc, $soloMozo);

            return response()->json([
                'ok' => true,
                'mensaje' => $soloMozo
                    ? 'Informe por mozo registrado (turno sigue habilitado).'
                    : 'Cierre parcial #'.$parcial->numero_parcial.' registrado.',
                'url_comprobante_pdf' => route('gastronomia_cierre_turno_comprobante_parcial', [
                    'id' => $parcial->id,
                    'inline' => 1,
                ]),
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

    /**
     * @param  array<string, mixed>  $datos
     */
    private function pdfComprobante(array $datos, string $nombreArchivo, bool $inline)
    {
        $html = view('ventas.gastronomia.cierres_turno.comprobante', compact('datos'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html, 'UTF-8');

        return $inline
            ? $pdf->stream($nombreArchivo)
            : $pdf->download($nombreArchivo);
    }
}
