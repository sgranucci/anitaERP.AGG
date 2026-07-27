<?php

namespace App\Http\Controllers\Caja\Estacionamiento;

use App\Http\Controllers\Controller;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Caja\Estacionamiento\TurnoEstacionamientoRepositoryInterface;
use App\Services\Caja\Estacionamiento\EstacionamientoPvService;
use App\Services\Caja\Estacionamiento\JornadaEstacionamientoService;
use App\Services\Caja\Estacionamiento\EstacionamientoTurnoOperativoService;
use App\Support\Caja\Estacionamiento\EstacionamientoCierreTurnoReporteSupport;
use App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc;
use App\Support\Caja\Estacionamiento\EstacionamientoTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class HabilitacionTurnoEstacionamientoController extends Controller
{
    public function __construct(
        private readonly EstacionamientoTurnoOperativoService $turnoOperativoService,
        private readonly JornadaEstacionamientoService $jornadaService,
        private readonly EstacionamientoPvService $pvService,
        private readonly TurnoEstacionamientoRepositoryInterface $turnoEstacionamientoRepository,
        private readonly EstacionamientoCierreTurnoReporteSupport $reporteSupport,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('gestionar-habilitacion-turno-estacionamiento');

        if (! EstacionamientoTurnoOperativoService::requiereHabilitacionTurno()) {
            return view('caja.estacionamiento.habilitacion_turno.index', [
                'modo_caja_directo' => true,
                'identificador_pc' => EstacionamientoIdentificadorPc::resolver($request),
            ]);
        }

        $empresasAsignadas = $this->empresaRepository->allFiltrado();
        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $empresasOperables = $this->pvService->empresasConPvEnTerminal($pc, $empresasAsignadas);
        $empresasSinPv = $this->pvService->empresasSinPvEnTerminal($pc, $empresasAsignadas);
        $configsPvAsignadas = $this->pvService->configuracionesPvParaEmpresasAsignadas($empresasAsignadas);

        $empresaId = $this->resolverEmpresaId($request, $empresasOperables);
        if ($empresaId > 0 && ! $empresasOperables->contains('id', $empresaId)) {
            $empresaId = (int) ($empresasOperables->first()->id ?? 0);
        }
        $this->assertAccesoEmpresa($empresaId);

        $cfg = $empresaId > 0
            ? $this->resolverConfiguracionParaRequest($request, $empresaId)
            : null;
        $estado = null;
        $turnos = collect();

        if ($cfg !== null) {
            $estado = $this->turnoOperativoService->estadoParaTerminal($cfg, $pc);
            $turnos = $this->turnoEstacionamientoRepository->listarParaSelect((int) $cfg->empresa_id);
        } elseif ($empresaId > 0) {
            $turnos = $this->turnoEstacionamientoRepository->listarParaSelect($empresaId);
        }

        $accion = (string) $request->query('accion', '');
        if (! in_array($accion, ['cierre_parcial', 'cierre_definitivo'], true)) {
            $accion = '';
        }

        return view('caja.estacionamiento.habilitacion_turno.index', [
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
            'puede_habilitar' => can('habilitar-turno-estacionamiento', false),
            'puede_cierre_parcial' => can('cierre-parcial-turno-estacionamiento', false),
            'puede_cerrar' => can('cerrar-turno-operativo-estacionamiento', false),
            'puede_anular_cierre' => can('anular-cierre-turno-estacionamiento', false),
            'puede_modificar_monto_habilitacion' => can('modificar-monto-habilitacion-turno-estacionamiento', false),
            'puede_ver_factura' => can('ver-factura-estacionamiento', false),
            'accion' => $accion,
            'url_factura_ver_base' => url('caja/estacionamiento/facturas-dia'),
        ]);
    }

    public function apiEstado(Request $request)
    {
        can('gestionar-habilitacion-turno-estacionamiento');

        $empresaId = $this->empresaOperativaDesdeRequest($request);

        $cfg = $this->resolverConfiguracionParaRequest($request, $empresaId);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV para esta terminal y empresa.'], 422);
        }

        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $estado = $this->turnoOperativoService->estadoParaTerminal($cfg, $pc);
        $estado['url_factura_ver_base'] = url('caja/estacionamiento/facturas-dia');
        if (can('anular-cierre-turno-estacionamiento', false)) {
            $estado['cierre_anulable'] = $this->turnoOperativoService->describirCierreAnulable(
                (int) $cfg->empresa_id,
                $pc,
            );
        }

        return response()->json([
            'ok' => true,
            ...$estado,
        ]);
    }

    public function apiAnularCierre(Request $request)
    {
        can('anular-cierre-turno-estacionamiento');

        $empresaId = $this->empresaOperativaDesdeRequest($request);
        $cfg = $this->resolverConfiguracionParaRequest($request, $empresaId);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV para esta terminal y empresa.'], 422);
        }

        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $turnoOperativoId = (int) $request->input('turno_operativo_id', 0);
        if ($turnoOperativoId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Debe indicar el turno operativo a anular.'], 422);
        }

        try {
            $resultado = $this->turnoOperativoService->anularCierreDefinitivo(
                $turnoOperativoId,
                $pc,
                (string) $request->input('confirmacion', ''),
                $request->input('motivo'),
            );

            return response()->json([
                'ok' => true,
                'mensaje' => $resultado['mensaje'],
                'turno_operativo_id' => (int) $resultado['turno']->id,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiConciliacionTurno(Request $request)
    {
        if (! can('gestionar-habilitacion-turno-estacionamiento', false)) {
            return response()->json(['ok' => false, 'error' => 'Sin permiso para gestionar habilitación de turno.'], 403);
        }

        $empresaId = $this->empresaOperativaDesdeRequest($request);
        $cfg = $this->resolverConfiguracionParaRequest($request, $empresaId);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV.'], 422);
        }

        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $activo = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);
        if ($activo === null) {
            return response()->json(['ok' => false, 'error' => 'No hay turno habilitado.'], 422);
        }

        $activo->loadMissing('jornada');
        $fechaJornada = $activo->jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');

        $page = (int) $request->input('page', 0);
        $perPage = (int) $request->input('per_page', EstacionamientoTurnoOperativoTotalesSupport::CONCILIACION_FILAS_POR_PAGINA);
        $soloDiferencias = $request->boolean('solo_diferencias');

        try {
            $grilla = EstacionamientoTurnoOperativoTotalesSupport::grillaConciliacionRespuesta(
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
            'url_factura_ver_base' => url('caja/estacionamiento/facturas-dia'),
        ]);
    }

    public function apiConciliacionMedio(Request $request)
    {
        if (! can('gestionar-habilitacion-turno-estacionamiento', false)) {
            return response()->json(['ok' => false, 'error' => 'Sin permiso para gestionar habilitación de turno.'], 403);
        }

        $empresaId = $this->empresaOperativaDesdeRequest($request);
        $cfg = $this->resolverConfiguracionParaRequest($request, $empresaId);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV.'], 422);
        }

        $cuentacajaId = (int) $request->input('cuentacaja_id', 0);
        if ($cuentacajaId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Medio de pago inválido.'], 422);
        }

        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $activo = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);
        if ($activo === null) {
            return response()->json(['ok' => false, 'error' => 'No hay turno habilitado.'], 422);
        }

        $activo->loadMissing('jornada');
        $fechaJornada = $activo->jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');

        $usuarioId = (int) $request->input('usuario_id', $request->input('usuario_habilitado_id', 0));
        $usuarioId = $usuarioId > 0 ? $usuarioId : null;

        $facturas = EstacionamientoTurnoOperativoTotalesSupport::facturasPorMedioPago(
            $pc,
            (int) $cfg->empresa_id,
            $fechaJornada,
            $cuentacajaId,
            $activo->habilitacion_en,
            $usuarioId,
        );

        $totales = EstacionamientoTurnoOperativoTotalesSupport::calcular(
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
            'url_factura_ver_base' => url('caja/estacionamiento/facturas-dia'),
        ]);
    }

    public function apiConciliacionNotasCredito(Request $request)
    {
        if (! can('gestionar-habilitacion-turno-estacionamiento', false)) {
            return response()->json(['ok' => false, 'error' => 'Sin permiso para gestionar habilitación de turno.'], 403);
        }

        $empresaId = $this->empresaOperativaDesdeRequest($request);
        $cfg = $this->resolverConfiguracionParaRequest($request, $empresaId);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV.'], 422);
        }

        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $activo = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);
        if ($activo === null) {
            return response()->json(['ok' => false, 'error' => 'No hay turno habilitado.'], 422);
        }

        $activo->loadMissing('jornada');
        $fechaJornada = $activo->jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');

        $usuarioId = (int) $request->input('usuario_id', $request->input('usuario_habilitado_id', 0));
        $usuarioId = $usuarioId > 0 ? $usuarioId : null;

        $notas = EstacionamientoTurnoOperativoTotalesSupport::notasCreditoDelTurno(
            $pc,
            (int) $cfg->empresa_id,
            $fechaJornada,
            $activo->habilitacion_en,
            $usuarioId,
        );

        return response()->json([
            'ok' => true,
                        'notas_credito' => $notas,
            'cantidad' => count($notas),
            'total' => round(array_sum(array_map(
                fn (array $n) => (float) ($n['monto_nota_credito'] ?? 0),
                $notas
            )), 2),
            'url_factura_ver_base' => url('caja/estacionamiento/facturas-dia'),
        ]);
    }

    public function apiConciliacionInvitaciones(Request $request)
    {
        if (! can('gestionar-habilitacion-turno-estacionamiento', false)) {
            return response()->json(['ok' => false, 'error' => 'Sin permiso para gestionar habilitación de turno.'], 403);
        }

        $empresaId = $this->empresaOperativaDesdeRequest($request);
        $cfg = $this->resolverConfiguracionParaRequest($request, $empresaId);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV.'], 422);
        }

        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $activo = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);
        if ($activo === null) {
            return response()->json(['ok' => false, 'error' => 'No hay turno habilitado.'], 422);
        }

        $activo->loadMissing('jornada');
        $fechaJornada = $activo->jornada?->fecha_jornada?->format('Y-m-d')
            ?? Carbon::today()->format('Y-m-d');

        $usuarioId = (int) $request->input('usuario_id', $request->input('usuario_habilitado_id', 0));
        $usuarioId = $usuarioId > 0 ? $usuarioId : null;

        $facturas = EstacionamientoTurnoOperativoTotalesSupport::invitacionesDelTurno(
            $pc,
            (int) $cfg->empresa_id,
            $fechaJornada,
            $activo->habilitacion_en,
            $usuarioId,
        );

        return response()->json([
            'ok' => true,
            'facturas' => $facturas,
            'cantidad' => count($facturas),
            'total' => round(array_sum(array_map(
                fn (array $f) => (float) ($f['total_facturado'] ?? 0),
                $facturas
            )), 2),
            'url_factura_ver_base' => url('caja/estacionamiento/facturas-dia'),
        ]);
    }

    public function apiHabilitar(Request $request)
    {
        can('habilitar-turno-estacionamiento');

        $empresaId = $this->empresaOperativaDesdeRequest($request);

        $cfg = $this->resolverConfiguracionParaRequest($request, $empresaId);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV para esta terminal y empresa.'], 422);
        }

        try {
            $turno = $this->turnoOperativoService->habilitar(
                $cfg,
                EstacionamientoIdentificadorPc::resolver($request),
                (int) $request->input('turno_estacionamiento_id', 0),
                (float) $request->input('monto_habilitacion', 0),
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

    public function apiActualizarMontoHabilitacion(Request $request)
    {
        can('modificar-monto-habilitacion-turno-estacionamiento');

        $empresaId = $this->empresaOperativaDesdeRequest($request);

        $cfg = $this->resolverConfiguracionParaRequest($request, $empresaId);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV para esta terminal y empresa.'], 422);
        }

        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $turnoOperativoId = (int) $request->input('turno_operativo_id', 0);
        if ($turnoOperativoId <= 0) {
            return response()->json(['ok' => false, 'error' => 'Debe indicar el turno operativo.'], 422);
        }

        try {
            $turno = $this->turnoOperativoService->actualizarMontoHabilitacion(
                $turnoOperativoId,
                $pc,
                (float) $request->input('monto_habilitacion', 0),
                $request->input('motivo'),
            );

            return response()->json([
                'ok' => true,
                'mensaje' => 'Monto de habilitación actualizado a $'
                    .number_format((float) $turno->monto_habilitacion, 2, ',', '.').'.',
                'monto_habilitacion' => (float) $turno->monto_habilitacion,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function apiCierreParcial(Request $request)
    {
        can('cierre-parcial-turno-estacionamiento');

        $empresaId = $this->empresaOperativaDesdeRequest($request);

        $cfg = $this->resolverConfiguracionParaRequest($request, $empresaId);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV para esta terminal y empresa.'], 422);
        }

        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $activo = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);
        if ($activo === null) {
            return response()->json(['ok' => false, 'error' => 'No hay turno habilitado en esta terminal.'], 422);
        }

        try {
            $activo->loadMissing('jornada');
            $parcial = $this->turnoOperativoService->registrarCierreParcial($activo, $pc);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Cierre parcial #'.$parcial->numero_parcial.' registrado.',
                'url_comprobante_pdf' => route('estacionamiento_cierre_turno_comprobante_parcial', [
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
        can('cerrar-turno-operativo-estacionamiento');

        $empresaId = $this->empresaOperativaDesdeRequest($request);

        $cfg = $this->resolverConfiguracionParaRequest($request, $empresaId);
        if ($cfg === null) {
            return response()->json(['ok' => false, 'error' => 'Sin configuración PV para esta terminal y empresa.'], 422);
        }

        $pc = EstacionamientoIdentificadorPc::resolver($request);
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
                'medios_contado' => $request->input('medios_contado'),
            ]);

            return response()->json([
                'ok' => true,
                'mensaje' => 'Turno cerrado correctamente.',
                'url_comprobante_pdf' => route('estacionamiento_cierre_turno_comprobante_cierre', [
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
     * @param  \Illuminate\Support\Collection<int, \App\Models\Configuracion\Empresa>  $empresas
     */
    private function resolverEmpresaId(Request $request, $empresas): int
    {
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0 && $empresas->count() >= 1) {
            $empresaId = (int) $empresas->first()->id;
        }

        return $empresaId;
    }

    private function empresaIdDesdeRequest(Request $request): int
    {
        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0) {
            $pc = EstacionamientoIdentificadorPc::resolver($request);
            $operables = $this->pvService->empresasConPvEnTerminal(
                $pc,
                $this->empresaRepository->allFiltrado(),
            );
            if ($operables->count() >= 1) {
                $empresaId = (int) $operables->first()->id;
            }
        }

        return $empresaId;
    }

    private function assertEmpresaOperableEnTerminal(Request $request, int $empresaId): void
    {
        if ($empresaId <= 0) {
            abort(422, 'Debe indicar una empresa con punto de venta configurado en esta terminal.');
        }

        $pc = EstacionamientoIdentificadorPc::resolver($request);
        $operables = $this->pvService->empresasConPvEnTerminal(
            $pc,
            $this->empresaRepository->allFiltrado(),
        );

        if (! $operables->contains('id', $empresaId)) {
            abort(422, 'La empresa seleccionada no tiene punto de venta configurado para esta terminal.');
        }
    }

    private function empresaOperativaDesdeRequest(Request $request): int
    {
        $empresaId = $this->empresaIdDesdeRequest($request);
        $this->assertAccesoEmpresa($empresaId);
        $this->assertEmpresaOperableEnTerminal($request, $empresaId);

        return $empresaId;
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

    private function resolverConfiguracionParaRequest(Request $request, int $empresaId)
    {
        try {
            return $this->pvService->resolverConfiguracionPv(
                $request,
                $empresaId > 0 ? $empresaId : null,
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function pdfComprobante(array $datos, string $nombreArchivo, bool $inline)
    {
        $html = view('caja.estacionamiento.cierres_turno.comprobante', compact('datos'))->render();
        $pdf = \App::make('dompdf.wrapper');
        $pdf->setPaper('a4', 'portrait');
        $pdf->loadHTML($html, 'UTF-8');

        return $inline
            ? $pdf->stream($nombreArchivo)
            : $pdf->download($nombreArchivo);
    }
}
