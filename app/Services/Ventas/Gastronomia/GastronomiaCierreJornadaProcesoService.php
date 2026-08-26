<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Support\Ventas\Gastronomia\CierreJornadaAnitaCompensacionOverlaySupport;
use App\Support\Ventas\Gastronomia\CierreJornadaCuadroDetalleSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaFacturadoAnitaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoAsientosPreviewSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoClasificacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoGrillaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoConfigSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoMedioSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoPuntoventaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoFacturaRecuperacionSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoJornadaSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoRedistribucionSupport;
use App\Support\Ventas\GastronomiaCuentacajaTotem;
use App\Services\Caja\Flash\FlashCajaAybRecalculoService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryAnalyticsOrdenesService;
use App\Services\Ventas\Gastronomia\Waitry\WaitryOrdenesExternasService;
use App\Support\Ventas\Gastronomia\VentaGastronomiaEmisionWaitrySupport;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use App\Support\Ventas\Waitry\WaitryCierreJornadaVentanaSupport;
use App\Support\Ventas\Waitry\WaitryOrdenEstadoSupport;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Proceso de cierre de jornada gastronomía: conciliación Waitry, redistribución QR/efectivo y preview de asientos.
 */
final class GastronomiaCierreJornadaProcesoService
{
    public const GRUPO_ANITA_SIN_WAITRY = 'anita_sin_waitry';

    public function __construct(
        private readonly GastronomiaCierreTotemJornadaService $cierreTotemJornadaService,
        private readonly WaitryAnalyticsOrdenesService $analyticsOrdenesService,
        private readonly WaitryOrdenesExternasService $ordenesExternasService,
    ) {
    }

    public function habilitado(): bool
    {
        return $this->cierreTotemJornadaService->habilitado();
    }

    /**
     * @return array<string, mixed>
     */
    public function analizarPorEmpresaYFecha(int $empresaId, string $fechaJornada, bool $refrescarWaitry = false): array
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Debe seleccionar una empresa.');
        }

        $fecha = $this->normalizarFecha($fechaJornada);

        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fecha)
            ->orderByDesc('id')
            ->first();

        if ($jornada === null) {
            throw new InvalidArgumentException(
                'No hay jornada gastronomía registrada para esta empresa y fecha. '
                .'Abra o cierre la jornada en Ventas → Gastronomía → Jornada antes de ejecutar el proceso.'
            );
        }

        return $this->analizarJornada((int) $jornada->id, $refrescarWaitry);
    }

    /**
     * @return array<string, mixed>
     */
    public function recalcularPorEmpresaYFecha(int $empresaId, string $fechaJornada, float $porcentaje): array
    {
        $jornada = $this->resolverJornadaPorEmpresaFecha($empresaId, $fechaJornada);

        return $this->recalcular((int) $jornada->id, $porcentaje);
    }

    /**
     * Preview de asientos de la factura única del proceso (consolidado + comandas incluidas).
     *
     * @return array<string, mixed>
     */
    public function previewFacturaProcesoPorEmpresaYFecha(
        int $empresaId,
        string $fechaJornada,
        float $porcentaje,
        int $paginaComandas = 1,
        int $porPaginaComandas = 50,
        string $comandasAlcance = CierreJornadaProcesoAsientosPreviewSupport::COMANDAS_ALCANCE_FACTURA_PROCESO,
    ): array {
        $jornada = $this->resolverJornadaPorEmpresaFecha($empresaId, $fechaJornada);

        return $this->previewFacturaProceso(
            (int) $jornada->id,
            $porcentaje,
            $paginaComandas,
            $porPaginaComandas,
            $comandasAlcance,
        );
    }

    /**
     * Preview de lotes CF para facturación del proceso (comandas atómicas).
     *
     * @return array<string, mixed>
     */
    public function previewLotesFacturaProcesoPorEmpresaYFecha(
        int $empresaId,
        string $fechaJornada,
        float $porcentaje,
    ): array {
        $jornada = $this->resolverJornadaPorEmpresaFecha($empresaId, $fechaJornada);

        return app(GastronomiaCierreJornadaFacturaProcesoEmisionService::class)->previewLotes(
            (int) $jornada->id,
            $porcentaje,
        );
    }

    /**
     * Emisión de facturas CF del proceso en lotes (requiere jornada cerrada y snapshot definitivo).
     *
     * @return array<string, mixed>
     */
    public function emitirFacturaProcesoPorEmpresaYFecha(
        int $empresaId,
        string $fechaJornada,
        float $porcentaje,
        int $puntoventaId = 0,
        ?string $fechaFactura = null,
        bool $usarRecuperacionSnapshot = false,
    ): array {
        $jornada = $this->resolverJornadaPorEmpresaFecha($empresaId, $fechaJornada);

        return $this->emitirFacturaProceso(
            (int) $jornada->id,
            $porcentaje,
            $puntoventaId,
            $fechaFactura,
            $usarRecuperacionSnapshot,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function emitirFacturaProceso(
        int $jornadaId,
        float $porcentaje,
        int $puntoventaId = 0,
        ?string $fechaFactura = null,
        bool $usarRecuperacionSnapshot = false,
    ): array {
        $jornada = $this->resolverJornada($jornadaId);
        $fecha = $fechaFactura ?? ($jornada->fecha_jornada?->format('Y-m-d') ?? '');
        $t0 = microtime(true);

        Log::info('cierre_jornada_waitry.proceso.emitir.inicio', [
            'jornada_id' => $jornadaId,
            'empresa_id' => (int) $jornada->empresa_id,
            'fecha_jornada' => $jornada->fecha_jornada?->format('Y-m-d'),
            'porcentaje' => $porcentaje,
            'puntoventa_id' => $puntoventaId,
            'usuario_id' => Auth::id(),
        ]);

        $resultado = app(GastronomiaCierreJornadaFacturaProcesoEmisionService::class)->emitir(
            $jornadaId,
            $porcentaje,
            $puntoventaId,
            $fecha,
            $usarRecuperacionSnapshot,
        );

        if (! ($resultado['ok'] ?? false)) {
            Log::warning('cierre_jornada_waitry.proceso.emitir.rechazado', [
                'jornada_id' => $jornadaId,
                'empresa_id' => (int) $jornada->empresa_id,
                'ms' => (int) round((microtime(true) - $t0) * 1000),
                'error' => $resultado['error'] ?? $resultado['mensaje'] ?? null,
            ]);

            return $resultado;
        }

        // Manual: emitir ≠ asientos. Rendgastro cuelga de las ventas emitidas.
        // Automático sigue grabando asientos en su propio paso.
        $resultado = $this->completarRendgastroTrasEmision($jornadaId, $resultado);
        $resultado = $this->recalcularFlashAybTrasCierreWaitry($jornada, $resultado);

        Log::info('cierre_jornada_waitry.proceso.emitir.fin', [
            'jornada_id' => $jornadaId,
            'empresa_id' => (int) $jornada->empresa_id,
            'ms' => (int) round((microtime(true) - $t0) * 1000),
            'venta_id' => $resultado['venta_id'] ?? ($resultado['facturas'][0]['venta_id'] ?? null),
            'total_factura' => $resultado['total_factura'] ?? null,
            'rendicion_anita_ok' => (bool) (($resultado['rendicion_anita']['ok'] ?? false)
                || (($resultado['jornada_proceso']['rendicion_anita_grabada'] ?? false))),
            'rendicion_anita_error' => $resultado['rendicion_anita_error'] ?? null,
            'flash_ayb_estado' => $resultado['flash_ayb']['estado'] ?? null,
        ]);

        return $resultado;
    }

    /**
     * Persiste rendgastro CIERRE-WAITRY a partir de las facturas del proceso (ventas),
     * sin depender de asientos contables.
     *
     * Si Anita falla, NO revierte la factura ya emitida: deja el proceso usable
     * (asientos pueden reintentar rendgastro) y registra el error en log + respuesta.
     *
     * @param  array<string, mixed>  $resultadoEmision
     * @return array<string, mixed>
     */
    private function completarRendgastroTrasEmision(int $jornadaId, array $resultadoEmision): array
    {
        $jornada = $this->resolverJornada($jornadaId);
        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();
        $payload = is_array($snapshot?->payload) ? $snapshot->payload : [];
        $emision = is_array($payload['factura_proceso_emision'] ?? null)
            ? $payload['factura_proceso_emision']
            : [];

        if (CierreJornadaProcesoJornadaSupport::emisionProcesoOmitida($emision)
            && CierreJornadaProcesoFacturaRecuperacionSupport::ventaIdsDesdeRecuperacion($emision) === []) {
            $resultadoEmision['jornada_proceso'] = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);

            return $resultadoEmision;
        }

        $t0 = microtime(true);
        try {
            $rendicion = app(GastronomiaCierreJornadaProcesoRendicionAnitaService::class)->grabar($jornadaId);
        } catch (Throwable $e) {
            Log::error('cierre_jornada_waitry.proceso.rendgastro.fallo_post_emision', [
                'jornada_id' => $jornadaId,
                'empresa_id' => (int) $jornada->empresa_id,
                'fecha_jornada' => $jornada->fecha_jornada?->format('Y-m-d'),
                'ms' => (int) round((microtime(true) - $t0) * 1000),
                'venta_id' => $resultadoEmision['venta_id'] ?? ($emision['venta_id'] ?? null),
                'total_factura' => $resultadoEmision['total_factura'] ?? ($emision['total_factura'] ?? null),
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
                ->where('jornada_gastronomia_id', $jornadaId)
                ->first();
            $resultadoEmision['rendicion_anita'] = [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
            $resultadoEmision['rendicion_anita_error'] = $e->getMessage();
            $base = trim((string) ($resultadoEmision['mensaje'] ?? 'Facturas del proceso emitidas.'));
            $resultadoEmision['mensaje'] = $base
                .' Atención: falló la rendición Anita (rendgastro): '.$e->getMessage()
                .'. Puede reintentarla con «Grabar asientos contables».';
            $resultadoEmision['jornada_proceso'] = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);

            return $resultadoEmision;
        }

        Log::info('cierre_jornada_waitry.proceso.rendgastro.ok', [
            'jornada_id' => $jornadaId,
            'empresa_id' => (int) $jornada->empresa_id,
            'ms' => (int) round((microtime(true) - $t0) * 1000),
            'nro_oper' => $rendicion['rendicion']['nro_oper'] ?? ($rendicion['nro_oper'] ?? null),
            'ya_existia' => (bool) ($rendicion['ya_existia'] ?? false),
        ]);

        $resultadoEmision['rendicion_anita'] = $rendicion;
        $msgRend = trim((string) ($rendicion['mensaje'] ?? ''));
        if ($msgRend !== '' && empty($rendicion['ya_existia'])) {
            $base = trim((string) ($resultadoEmision['mensaje'] ?? ''));
            $resultadoEmision['mensaje'] = $base === '' ? $msgRend : ($base.' '.$msgRend);
        }

        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();
        $resultadoEmision['jornada_proceso'] = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);

        return $resultadoEmision;
    }

    /**
     * Si tesorería ya armó el flash del día, completa AyB con el CAEA recién emitido.
     *
     * @param  array<string, mixed>  $resultadoEmision
     * @return array<string, mixed>
     */
    private function recalcularFlashAybTrasCierreWaitry(JornadaGastronomia $jornada, array $resultadoEmision): array
    {
        if (! ($resultadoEmision['ok'] ?? false)) {
            return $resultadoEmision;
        }

        $fecha = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        if ($fecha === '') {
            return $resultadoEmision;
        }

        try {
            $flashAyb = app(FlashCajaAybRecalculoService::class)
                ->recalcularDesdeJornadaWaitry((int) $jornada->empresa_id, $fecha);
            $resultadoEmision['flash_ayb'] = $flashAyb;
            $msgFlash = trim((string) ($flashAyb['mensaje'] ?? ''));
            if ($msgFlash !== '' && ($flashAyb['estado'] ?? '') === FlashCajaAybRecalculoService::ESTADO_ACTUALIZADO) {
                $base = trim((string) ($resultadoEmision['mensaje'] ?? ''));
                $resultadoEmision['mensaje'] = $base === '' ? $msgFlash : ($base.' '.$msgFlash);
            }
        } catch (Throwable $e) {
            Log::warning('cierre_jornada_waitry.proceso.flash_ayb.fallo', [
                'jornada_id' => (int) $jornada->id,
                'empresa_id' => (int) $jornada->empresa_id,
                'fecha_jornada' => $fecha,
                'error' => $e->getMessage(),
            ]);
            $resultadoEmision['flash_ayb'] = [
                'estado' => FlashCajaAybRecalculoService::ESTADO_ERROR,
                'mensaje' => $e->getMessage(),
            ];
        }

        return $resultadoEmision;
    }

    /**
     * @return array<string, mixed>
     */
    public function grabarAsientosProcesoPorEmpresaYFecha(
        int $empresaId,
        string $fechaJornada,
        float $porcentaje,
        ?string $fechaAsiento = null,
    ): array {
        $jornada = $this->resolverJornadaPorEmpresaFecha($empresaId, $fechaJornada);

        return $this->grabarAsientosProceso((int) $jornada->id, $porcentaje, $fechaAsiento);
    }

    /**
     * @return array<string, mixed>
     */
    public function grabarAsientosProceso(int $jornadaId, float $porcentaje, ?string $fechaAsiento = null): array
    {
        $this->asegurarEmisionOmitidaSiCorresponde($jornadaId, $porcentaje);

        return app(GastronomiaCierreJornadaAsientosGrabacionService::class)->grabar(
            $jornadaId,
            $porcentaje,
            $fechaAsiento,
        );
    }

    /**
     * Marca la emisión como omitida cuando no hay comandas Waitry sin facturar (solo asientos).
     */
    public function asegurarEmisionOmitidaSiCorresponde(int $jornadaId, ?float $porcentaje = null): void
    {
        $jornada = $this->resolverJornada($jornadaId);
        $clasificacion = $this->clasificacionParaJornada($jornada, $porcentaje);
        $this->sincronizarMetadatosEmisionSnapshot($jornada, $clasificacion, $porcentaje);
    }

    /**
     * @return array<string, mixed>
     */
    public function revertirProcesoPorEmpresaYFecha(int $empresaId, string $fechaJornada): array
    {
        $jornada = $this->resolverJornadaPorEmpresaFecha($empresaId, $fechaJornada);

        return app(GastronomiaCierreJornadaProcesoReversionService::class)->revertir((int) $jornada->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function resumenReversionProcesoPorEmpresaYFecha(int $empresaId, string $fechaJornada): array
    {
        $jornada = $this->resolverJornadaPorEmpresaFecha($empresaId, $fechaJornada);
        $snapshot = $this->snapshotDeJornada((int) $jornada->id);

        return app(GastronomiaCierreJornadaProcesoReversionService::class)->resumenDesdeSnapshot($snapshot);
    }

    /**
     * @return array<string, mixed>
     */
    public function movimientosGrupoPorEmpresaYFecha(
        int $empresaId,
        string $fechaJornada,
        string $grupo,
        int $pagina = 1,
        int $porPagina = 50,
    ): array {
        $jornada = $this->resolverJornadaPorEmpresaFecha($empresaId, $fechaJornada);

        return $this->movimientosGrupo((int) $jornada->id, $grupo, $pagina, $porPagina);
    }

    /**
     * Detalle de una celda del cuadro (fila × medio) para conciliar contra Waitry.
     *
     * @return array<string, mixed>
     */
    public function detalleCuadroCeldaPorEmpresaYFecha(
        int $empresaId,
        string $fechaJornada,
        string $fila,
        string $medio,
        int $pagina = 1,
        int $porPagina = 100,
        ?float $porcentaje = null,
    ): array {
        $jornada = $this->resolverJornadaPorEmpresaFecha($empresaId, $fechaJornada);

        return $this->detalleCuadroCelda((int) $jornada->id, $fila, $medio, $pagina, $porPagina, $porcentaje);
    }

    /**
     * @return array<string, mixed>
     */
    public function detalleCuadroCelda(
        int $jornadaId,
        string $fila,
        string $medio,
        int $pagina = 1,
        int $porPagina = 100,
        ?float $porcentaje = null,
    ): array {
        $jornada = $this->resolverJornada($jornadaId);
        $empresaId = (int) $jornada->empresa_id;
        $fecha = $jornada->fecha_jornada?->format('Y-m-d') ?? '';

        $cargado = $this->obtenerCargadoParaProceso($jornada, false);
        $clasificacion = $this->clasificacionParaJornada($jornada, $porcentaje);

        $detalle = CierreJornadaCuadroDetalleSupport::consultar(
            $empresaId,
            $fecha,
            $fila,
            $medio,
            $clasificacion['movimientos'],
            $pagina,
            $porPagina,
        );

        $detalle['meta'] = $cargado['meta'];
        $detalle['porcentaje_aplicado'] = $clasificacion['porcentaje'] ?? 0.0;

        return $detalle;
    }

    /**
     * @return array<string, mixed>
     */
    public function analizarJornada(int $jornadaId, bool $refrescarWaitry = false): array
    {
        $jornada = $this->resolverJornada($jornadaId);
        $empresaId = (int) $jornada->empresa_id;

        $cargado = $this->obtenerCargadoParaProceso($jornada, $refrescarWaitry);
        $clasificacion = $this->clasificacionParaJornada($jornada);
        $this->sincronizarMetadatosEmisionSnapshot($jornada, $clasificacion);
        $this->autoAplicarRecalculoInicialSiCorresponde($jornada, $clasificacion);
        $config = CierreJornadaProcesoConfigSupport::paraEmpresaConDetalle($empresaId);
        $snapshot = $this->snapshotDeJornada($jornadaId);
        if ($snapshot !== null) {
            $clasificacion = $this->clasificacionParaJornada($jornada, (float) ($snapshot->porcentaje ?? 0));
        }

        return [
            'ok' => true,
            'jornada' => $this->resumenJornada($jornada),
            'meta' => $cargado['meta'],
            'snapshot_congelado' => ! empty($cargado['meta']['congelado']),
            'snapshot_congelado_en' => $cargado['meta']['congelado_en'] ?? null,
            'snapshot_reutilizado' => ! empty($cargado['desde_snapshot']),
            'porcentaje_guardado' => $snapshot?->porcentaje,
            'porcentaje' => $clasificacion['porcentaje'] ?? 0.0,
            'objetivo_importe' => $clasificacion['objetivo_importe'] ?? 0.0,
            'redistribucion' => $clasificacion['redistribucion'] ?? null,
            'grilla' => $clasificacion['grilla'],
            'cuadro_filas' => $clasificacion['cuadro_filas'],
            'cuadro_columnas_medios' => $clasificacion['cuadro_columnas_medios'] ?? [],
            'total_facturacion' => $clasificacion['total_facturacion'],
            'total_anita_jornada_cuadro' => $clasificacion['total_anita_jornada_cuadro'] ?? 0.0,
            'total_anita_totem_cuadro' => $clasificacion['total_anita_totem_cuadro'] ?? 0.0,
            'total_anita_sin_waitry_cuadro' => $clasificacion['total_anita_sin_waitry'] ?? 0.0,
            'total_notas_credito' => $clasificacion['total_notas_credito'] ?? 0.0,
            'total_pendiente_facturar' => $clasificacion['total_pendiente_facturar'],
            'total_impago_waitry' => $clasificacion['total_impago_waitry'],
            'total_cuadro' => $clasificacion['total_cuadro'],
            'contexto_porcentaje' => $this->contextoRecodificacionPorcentaje($jornada),
            'conteos' => $clasificacion['conteos'],
            'grupos_resumen' => $this->resumenGrupos($clasificacion['grupos'], $jornada, $empresaId),
            'anita_sin_waitry' => $this->resumenAnitaSinWaitry($jornada, $empresaId),
            'config_contable' => $config,
            'config_faltante' => CierreJornadaProcesoConfigSupport::faltantes($config, $empresaId),
            'notas' => $this->notasProceso(),
            'jornada_proceso' => $this->contextoJornadaProceso($jornada, $snapshot),
            'tramo_ultimo_ticket_origen' => $cargado['meta']['tramo_ultimo_ticket_origen'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recalcular(int $jornadaId, float $porcentaje): array
    {
        $jornada = $this->resolverJornada($jornadaId);

        if ($this->snapshotDeJornada($jornadaId) === null) {
            throw new InvalidArgumentException(
                'Primero pulse «Analizar tramo de jornada (Waitry vs Anita)» para congelar el tramo Waitry; '
                .'después indique el porcentaje y «Recalcular medios».',
            );
        }

        $contextoPct = $this->contextoRecodificacionPorcentaje($jornada);
        CierreJornadaProcesoRedistribucionSupport::validarPorcentajeNoExcedeRecodificable(
            (float) $contextoPct['total_facturacion_anita'],
            $porcentaje,
            (float) $contextoPct['total_sin_facturar_recodificable'],
        );

        $clasificacion = $this->clasificacionConRedistribucion($jornada, $porcentaje);
        $this->persistirPorcentajeEnSnapshot($jornadaId, $porcentaje, $clasificacion);
        $this->sincronizarMetadatosEmisionSnapshot($jornada, $clasificacion, $porcentaje);

        return [
            'ok' => true,
            'porcentaje' => $clasificacion['porcentaje'],
            'objetivo_importe' => $clasificacion['objetivo_importe'],
            'redistribucion' => $clasificacion['redistribucion'],
            'grilla' => $clasificacion['grilla'],
            'cuadro_filas' => $clasificacion['cuadro_filas'],
            'cuadro_columnas_medios' => $clasificacion['cuadro_columnas_medios'] ?? [],
            'total_facturacion' => $clasificacion['total_facturacion'],
            'total_anita_jornada_cuadro' => $clasificacion['total_anita_jornada_cuadro'] ?? 0.0,
            'total_anita_totem_cuadro' => $clasificacion['total_anita_totem_cuadro'] ?? 0.0,
            'total_anita_sin_waitry_cuadro' => $clasificacion['total_anita_sin_waitry'] ?? 0.0,
            'total_notas_credito' => $clasificacion['total_notas_credito'] ?? 0.0,
            'total_pendiente_facturar' => $clasificacion['total_pendiente_facturar'],
            'total_impago_waitry' => $clasificacion['total_impago_waitry'],
            'total_cuadro' => $clasificacion['total_cuadro'],
            'contexto_porcentaje' => $contextoPct,
            'total_pendiente_qr_mp' => self::totalPendienteQrMpDesdeGrilla($clasificacion['grilla'] ?? []),
            'snapshot_congelado' => true,
            'jornada_proceso' => $this->contextoJornadaProceso($jornada, $this->snapshotDeJornada($jornadaId)),
            'movimientos' => $clasificacion['movimientos'],
        ];
    }

    /**
     * Clasificación con redistribución vigente (requiere snapshot congelado).
     *
     * @return array<string, mixed>
     */
    public function clasificacionActual(int $jornadaId, float $porcentaje): array
    {
        $jornada = $this->resolverJornada($jornadaId);
        if ($this->snapshotDeJornada($jornadaId) === null) {
            throw new InvalidArgumentException('Debe analizar el tramo antes de emitir la factura del proceso.');
        }

        return $this->clasificacionConRedistribucion($jornada, $porcentaje);
    }

    /**
     * Config + contexto cuadro compartido entre preview y grabación de asientos.
     *
     * @param  array<string, mixed>  $clasificacion
     * @return array{
     *   config: array<string, mixed>,
     *   contexto_cuadro: array<string, mixed>,
     *   datos_asiento_anita: array<string, mixed>
     * }
     */
    public function prepararDatosAsientosProceso(JornadaGastronomia $jornada, array $clasificacion): array
    {
        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        $config = CierreJornadaProcesoConfigSupport::paraEmpresaConDetalle($empresaId);
        $datosAsientoAnita = CierreJornadaFacturadoAnitaSupport::datosAsientoVentasJornadaExclTotem($empresaId, $fechaJornada);
        if (($clasificacion['porcentaje'] ?? 0) > 0.0001) {
            $datosAsientoAnita = CierreJornadaAnitaCompensacionOverlaySupport::aplicarDatosAsiento(
                $datosAsientoAnita,
                $clasificacion['movimientos'],
                $empresaId,
            );
        }

        return [
            'config' => $config,
            'datos_asiento_anita' => $datosAsientoAnita,
            'contexto_cuadro' => [
                'empresa_id' => $empresaId,
                'total_facturacion' => $clasificacion['total_facturacion'],
                'total_anita_jornada_cuadro' => $clasificacion['total_anita_jornada_cuadro'] ?? 0.0,
                'total_anita_totem_cuadro' => $clasificacion['total_anita_totem_cuadro'] ?? 0.0,
                'total_anita_sin_waitry_cuadro' => $clasificacion['total_anita_sin_waitry'] ?? 0.0,
                'total_notas_credito' => $clasificacion['total_notas_credito'] ?? 0.0,
                'total_pendiente_facturar' => $clasificacion['total_pendiente_facturar'],
                'waitry_pago_qr' => (float) ($clasificacion['grilla']['waitry_pago_qr'] ?? 0),
                'fecha_jornada' => $fechaJornada,
                'anita_jornada_fila' => self::filaCuadroPorTipo($clasificacion['cuadro_filas'] ?? [], 'anita_jornada'),
                'anita_asiento2_fila_ref' => self::filaReferenciaAsiento2($datosAsientoAnita, $empresaId),
                'total_anita_jornada_cuadro' => self::totalFilaCuadroPorTipo($clasificacion['cuadro_filas'] ?? [], 'anita_jornada'),
                'total_anita_asiento2' => round((float) ($datosAsientoAnita['total'] ?? 0), 2),
                'datos_asiento_anita' => $datosAsientoAnita,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function previewFacturaProceso(
        int $jornadaId,
        float $porcentaje,
        int $paginaComandas = 1,
        int $porPaginaComandas = 50,
        string $comandasAlcance = CierreJornadaProcesoAsientosPreviewSupport::COMANDAS_ALCANCE_FACTURA_PROCESO,
    ): array {
        $jornada = $this->resolverJornada($jornadaId);
        $empresaId = (int) $jornada->empresa_id;
        $porPaginaComandas = max(10, min(500, $porPaginaComandas));
        $paginaComandas = max(1, $paginaComandas);

        $clasificacion = $this->clasificacionConRedistribucion($jornada, $porcentaje);
        $datosAsientos = $this->prepararDatosAsientosProceso($jornada, $clasificacion);
        $config = $datosAsientos['config'];
        $contextoCuadro = $datosAsientos['contexto_cuadro'];
        $preview = CierreJornadaProcesoAsientosPreviewSupport::generarPreviewFacturaProceso(
            $clasificacion['movimientos'],
            $empresaId,
            $config,
        );
        $previewCompleto = CierreJornadaProcesoAsientosPreviewSupport::generarPreviewCompletoProceso(
            $clasificacion['movimientos'],
            $empresaId,
            $config,
            $contextoCuadro,
        );
        $previewCompleto['asientos'] = CierreJornadaProcesoAsientosPreviewSupport::enriquecerAsientosConEtiquetas(
            $previewCompleto['asientos'],
            $empresaId,
            $config,
        );
        $previewCompleto['cuentas_requeridas'] = CierreJornadaProcesoAsientosPreviewSupport::enriquecerCuentasRequeridas(
            $previewCompleto['cuentas_requeridas'] ?? [],
            $empresaId,
            $config,
        );
        if ($preview['asiento'] !== null) {
            $asientoEnriquecido = CierreJornadaProcesoAsientosPreviewSupport::enriquecerAsientosConEtiquetas(
                [$preview['asiento']],
                $empresaId,
                $config,
            );
            $preview['asiento'] = $asientoEnriquecido[0] ?? $preview['asiento'];
        }

        $comandasAlcance = CierreJornadaProcesoAsientosPreviewSupport::COMANDAS_ALCANCE_EFECTIVO_NO_FACTURADO === $comandasAlcance
            ? CierreJornadaProcesoAsientosPreviewSupport::COMANDAS_ALCANCE_EFECTIVO_NO_FACTURADO
            : CierreJornadaProcesoAsientosPreviewSupport::COMANDAS_ALCANCE_FACTURA_PROCESO;

        $comandas = CierreJornadaProcesoAsientosPreviewSupport::movimientosComandasPorAlcance(
            $clasificacion['movimientos'],
            $comandasAlcance,
        );
        $totalComandas = count($comandas);
        $offset = ($paginaComandas - 1) * $porPaginaComandas;
        $slice = array_slice($comandas, $offset, $porPaginaComandas);
        $totalImporteComandas = $comandasAlcance === CierreJornadaProcesoAsientosPreviewSupport::COMANDAS_ALCANCE_EFECTIVO_NO_FACTURADO
            ? round(array_sum(array_map(
                static fn (array $m) => CierreJornadaProcesoAsientosPreviewSupport::montoEfectivoNoFacturadoDesdeMov($m),
                $comandas,
            )), 2)
            : round(array_sum(array_map(
                static fn (array $m) => (float) ($m['total'] ?? 0),
                $comandas,
            )), 2);

        return [
            'ok' => true,
            'porcentaje' => $clasificacion['porcentaje'],
            'objetivo_importe' => $clasificacion['objetivo_importe'],
            'factura_proceso' => $preview,
            'asientos_proceso' => $previewCompleto,
            'config_contable' => $config,
            'puntoventa' => CierreJornadaProcesoPuntoventaSupport::resolverParaEmpresa($empresaId),
            'comandas_alcance' => $comandasAlcance,
            'comandas' => [
                'alcance' => $comandasAlcance,
                'pagina' => $paginaComandas,
                'por_pagina' => $porPaginaComandas,
                'total' => $totalComandas,
                'total_paginas' => (int) max(1, ceil($totalComandas / $porPaginaComandas)),
                'total_importe' => $totalImporteComandas,
                'items' => $this->compactarMovimientosParaCliente($slice),
            ],
            'jornada_proceso' => $this->contextoJornadaProceso($jornada, $this->snapshotDeJornada($jornadaId)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clasificacionConRedistribucion(JornadaGastronomia $jornada, float $porcentaje): array
    {
        return $this->clasificacionParaJornada($jornada, $porcentaje);
    }

    /**
     * Clasificación usando snapshot congelado (sin reconsultar Waitry).
     *
     * @return array<string, mixed>
     */
    private function clasificacionParaJornada(JornadaGastronomia $jornada, ?float $porcentaje = null): array
    {
        $empresaId = (int) $jornada->empresa_id;
        $fecha = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        $cargado = $this->obtenerCargadoParaProceso($jornada, false);
        $lineas = $this->filtrarLineasOperativasWaitry($cargado['lineas']);
        $totalesAnita = CierreJornadaFacturadoAnitaSupport::totalesJornadaEmpresa($empresaId, $fecha);

        $pct = $porcentaje;
        if ($pct === null) {
            $pct = (float) ($this->snapshotDeJornada((int) $jornada->id)?->porcentaje ?? 0);
        }

        if ($pct > 0.0001) {
            $contextoPct = $this->contextoRecodificacionPorcentaje($jornada);
            CierreJornadaProcesoRedistribucionSupport::validarPorcentajeNoExcedeRecodificable(
                (float) $contextoPct['total_facturacion_anita'],
                $pct,
                (float) $contextoPct['total_sin_facturar_recodificable'],
            );

            $clasificacionBase = CierreJornadaProcesoClasificacionSupport::clasificar($lineas, $empresaId, $totalesAnita);
            $facturasAnitaEfectivo = CierreJornadaFacturadoAnitaSupport::movimientosEfectivoParaCompensacion(
                $empresaId,
                $fecha,
            );
            $redistribucion = CierreJornadaProcesoRedistribucionSupport::aplicar(
                $clasificacionBase['movimientos'],
                $clasificacionBase['total_facturacion'],
                $pct,
                $facturasAnitaEfectivo,
            );
            $totalesAnita = CierreJornadaAnitaCompensacionOverlaySupport::aplicarTotalesAnita(
                $totalesAnita,
                $redistribucion['movimientos'],
                $empresaId,
            );
            $clasificacion = CierreJornadaProcesoClasificacionSupport::clasificar(
                $redistribucion['movimientos'],
                $empresaId,
                $totalesAnita,
            );

            return [
                'movimientos' => $clasificacion['movimientos'],
                'grupos' => $clasificacion['grupos'],
                'conteos' => $clasificacion['conteos'],
                'grilla' => $clasificacion['grilla'],
                'cuadro_filas' => $clasificacion['cuadro_filas'],
                'cuadro_columnas_medios' => $clasificacion['cuadro_columnas_medios'] ?? [],
                'total_facturacion' => $clasificacion['total_facturacion'],
                'total_pendiente_facturar' => $clasificacion['total_pendiente_facturar'],
                'total_impago_waitry' => $clasificacion['total_impago_waitry'],
                'total_cuadro' => $clasificacion['total_cuadro'],
                'porcentaje' => $redistribucion['porcentaje'],
                'objetivo_importe' => $redistribucion['objetivo_importe'],
                'redistribucion' => [
                    'asignado_sin_facturar_a_efectivo' => $redistribucion['asignado_sin_facturar_a_efectivo'],
                    'asignado_facturado_efectivo_a_qr' => $redistribucion['asignado_facturado_efectivo_a_qr'],
                    'asignado_facturado_efectivo_a_mp' => $redistribucion['asignado_facturado_efectivo_a_mp'] ?? 0.0,
                    'asignado_facturado_efectivo_compensacion' => $redistribucion['asignado_facturado_efectivo_compensacion']
                        ?? $redistribucion['asignado_facturado_efectivo_a_qr'],
                    'asignado_efectivo_por_medio_origen' => $redistribucion['asignado_efectivo_por_medio_origen'] ?? [],
                    'asignado_facturado_efectivo_por_medio' => $redistribucion['asignado_facturado_efectivo_por_medio'] ?? [],
                    'cuadre_qr_z_ok' => $redistribucion['cuadre_qr_z_ok'] ?? false,
                    'ajustes' => $redistribucion['ajustes'],
                ],
            ];
        }

        $clasificacion = CierreJornadaProcesoClasificacionSupport::clasificar($lineas, $empresaId, $totalesAnita);
        $clasificacion['porcentaje'] = 0.0;
        $clasificacion['objetivo_importe'] = 0.0;
        $clasificacion['redistribucion'] = null;

        return $clasificacion;
    }

    /**
     * Tramo Waitry + enriquecimiento Anita: se congela en BD al primer análisis; no se vuelve a llamar a Waitry.
     *
     * @return array{lineas: list<array<string, mixed>>, meta: array<string, mixed>, desde_snapshot?: bool}
     */
    private function obtenerCargadoParaProceso(JornadaGastronomia $jornada, bool $refrescarWaitry): array
    {
        if ($refrescarWaitry) {
            GastronomiaCierreJornadaProcesoSnapshot::query()
                ->where('jornada_gastronomia_id', $jornada->id)
                ->delete();
        } else {
            $snapshot = $this->snapshotDeJornada((int) $jornada->id);
            if ($snapshot !== null) {
                if (CierreJornadaProcesoJornadaSupport::debeInvalidarSnapshot($jornada, $snapshot)) {
                    $snapshot->delete();
                } else {
                    $meta = $snapshot->meta();
                    $meta['congelado'] = true;
                    $meta['congelado_en'] = $snapshot->congelado_en?->toIso8601String();
                    $meta['snapshot_id'] = (int) $snapshot->id;
                    $meta['snapshot_provisional'] = CierreJornadaProcesoJornadaSupport::snapshotEsProvisional($snapshot);

                    $lineas = $this->normalizarLineasSnapshotDescuento($jornada, $snapshot->lineas());

                    return [
                        'lineas' => $lineas,
                        'meta' => $meta,
                        'desde_snapshot' => true,
                    ];
                }
            }
        }

        $cargado = $this->cierreTotemJornadaService->movimientosParaProcesoCaja($jornada);
        $empresaId = (int) $jornada->empresa_id;
        $cargado['lineas'] = $this->enriquecerLineasConCobranzaAnita($cargado['lineas'], $empresaId);
        $this->guardarSnapshotCongelado($jornada, $cargado);
        $cargado['meta']['congelado'] = true;
        $cargado['meta']['congelado_en'] = now()->toIso8601String();

        return $cargado;
    }

    private function snapshotDeJornada(int $jornadaId): ?GastronomiaCierreJornadaProcesoSnapshot
    {
        if ($jornadaId <= 0) {
            return null;
        }

        return GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();
    }

    /**
     * Totales base para el % (sin redistribución): facturado Anita vs Waitry sin facturar recodificable.
     *
     * @return array{
     *   total_facturacion_anita: float,
     *   total_sin_facturar_recodificable: float,
     *   total_pendiente_qr_mp: float,
     *   porcentaje_maximo_recodificacion: float,
     *   porcentaje_objetivo: float,
     *   porcentaje_aplicar: float
     * }
     */
    private function contextoRecodificacionPorcentaje(JornadaGastronomia $jornada): array
    {
        $empresaId = (int) $jornada->empresa_id;
        $fecha = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        $cargado = $this->obtenerCargadoParaProceso($jornada, false);
        $lineas = $this->filtrarLineasOperativasWaitry($cargado['lineas']);
        $totalesAnita = CierreJornadaFacturadoAnitaSupport::totalesJornadaEmpresa($empresaId, $fecha);
        $clasificacion = CierreJornadaProcesoClasificacionSupport::clasificar($lineas, $empresaId, $totalesAnita);
        $recodificable = CierreJornadaProcesoRedistribucionSupport::totalSinFacturarRecodificable(
            $clasificacion['movimientos'],
        );
        $totalFacturacion = round((float) ($clasificacion['total_facturacion'] ?? 0), 2);
        $maximo = CierreJornadaProcesoRedistribucionSupport::porcentajeMaximoSobreFacturacion(
            $totalFacturacion,
            $recodificable,
        );
        $objetivo = CierreJornadaProcesoConfigSupport::resolverPorcentajeParaEmpresa($empresaId);

        return [
            'total_facturacion_anita' => $totalFacturacion,
            'total_sin_facturar_recodificable' => $recodificable,
            'total_pendiente_qr_mp' => self::totalPendienteQrMpDesdeGrilla($clasificacion['grilla'] ?? []),
            'porcentaje_maximo_recodificacion' => $maximo,
            'porcentaje_objetivo' => $objetivo,
            'porcentaje_aplicar' => CierreJornadaProcesoConfigSupport::resolverPorcentajeParaJornada(
                $empresaId,
                $maximo,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $grilla
     */
    private static function totalPendienteQrMpDesdeGrilla(array $grilla): float
    {
        return CierreJornadaProcesoRedistribucionSupport::pesos(
            (float) ($grilla['waitry_pago_qr'] ?? 0) + (float) ($grilla['waitry_pago_mp'] ?? 0),
        );
    }

    /**
     * @param  array{lineas: list<array<string, mixed>>, meta: array<string, mixed>}  $cargado
     */
    private function guardarSnapshotCongelado(JornadaGastronomia $jornada, array $cargado): void
    {
        $fecha = $jornada->fecha_jornada?->format('Y-m-d') ?? Carbon::today()->format('Y-m-d');
        $usuarioId = Auth::id();
        $ctx = CierreJornadaProcesoJornadaSupport::contexto($jornada);
        $provisional = $ctx['modo'] === 'auditoria_en_curso';

        GastronomiaCierreJornadaProcesoSnapshot::query()->updateOrCreate(
            ['jornada_gastronomia_id' => (int) $jornada->id],
            [
                'empresa_id' => (int) $jornada->empresa_id,
                'fecha_jornada' => $fecha,
                'payload' => [
                    'lineas' => $cargado['lineas'],
                    'meta' => $cargado['meta'],
                    'snapshot_provisional' => $provisional,
                    'jornada_estado' => (string) ($jornada->estado ?? ''),
                    'jornada_cierre_en' => $jornada->cierre_en?->toIso8601String(),
                    'waitry_order_id_hasta' => $cargado['meta']['waitry_order_id_hasta'] ?? null,
                    'tramo_ultimo_ticket_origen' => $cargado['meta']['tramo_ultimo_ticket_origen'] ?? null,
                    'lineas_schema_version' => CierreJornadaProcesoJornadaSupport::LINEAS_SCHEMA_VERSION,
                ],
                'porcentaje' => null,
                'usuario_id' => $usuarioId ? (int) $usuarioId : null,
                'congelado_en' => now(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function contextoJornadaProceso(JornadaGastronomia $jornada, ?GastronomiaCierreJornadaProcesoSnapshot $snapshot = null): array
    {
        return CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);
    }

    /**
     * @param  array<string, mixed>  $clasificacion
     */
    private function persistirPorcentajeEnSnapshot(int $jornadaId, float $porcentaje, array $clasificacion): void
    {
        $snapshot = $this->snapshotDeJornada($jornadaId);
        if ($snapshot === null) {
            return;
        }

        $payload = $snapshot->payload;
        if (! is_array($payload)) {
            $payload = [];
        }
        $payload['porcentaje'] = round($porcentaje, 4);
        $payload['redistribucion'] = $clasificacion['redistribucion'] ?? null;
        $payload['recalculo_aplicado_en'] = now()->toIso8601String();

        $snapshot->porcentaje = round($porcentaje, 4);
        $snapshot->payload = $payload;
        $snapshot->save();
    }

    /**
     * Objetivo de empresa limitado al disponible recodificable de esta jornada (3er asiento).
     */
    public function porcentajeAplicarParaJornada(JornadaGastronomia $jornada): float
    {
        $ctx = $this->contextoRecodificacionPorcentaje($jornada);

        return CierreJornadaProcesoConfigSupport::resolverPorcentajeParaJornada(
            (int) $jornada->empresa_id,
            (float) ($ctx['porcentaje_maximo_recodificacion'] ?? 0),
        );
    }

    /**
     * Con comandas Waitry a facturar, al analizar el tramo definitivo aplica
     * min(objetivo empresa, tope recodificable) para armar el 3er asiento sin exigir «Recalcular».
     *
     * @param  array<string, mixed>  $clasificacion
     */
    private function autoAplicarRecalculoInicialSiCorresponde(JornadaGastronomia $jornada, array $clasificacion): void
    {
        $snapshot = $this->snapshotDeJornada((int) $jornada->id);
        if ($snapshot === null) {
            return;
        }

        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        if (! (bool) ($payload['requiere_emision_proceso'] ?? false)) {
            return;
        }

        if (CierreJornadaProcesoJornadaSupport::recalculoAplicadoEnSnapshot($payload)) {
            return;
        }

        $emision = is_array($payload['factura_proceso_emision'] ?? null)
            ? $payload['factura_proceso_emision']
            : null;
        if (CierreJornadaProcesoJornadaSupport::emisionProcesoOmitida($emision)) {
            return;
        }
        if (is_array($emision) && (
            ! empty($emision['facturas']) || ! empty($emision['venta_id']) || ! empty($emision['emitido_en'])
        )) {
            return;
        }

        $ctx = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);
        if (! $ctx['cerrada'] || ! $ctx['snapshot_definitivo']) {
            return;
        }

        $pct = $this->porcentajeAplicarParaJornada($jornada);
        if ($pct <= 0.0001) {
            $this->persistirPorcentajeEnSnapshot((int) $jornada->id, 0., $clasificacion);

            return;
        }

        $this->recalcular((int) $jornada->id, $pct);
    }

    /**
     * @param  array<string, mixed>  $clasificacion
     */
    private function sincronizarMetadatosEmisionSnapshot(
        JornadaGastronomia $jornada,
        array $clasificacion,
        ?float $porcentaje = null,
    ): void {
        $snapshot = $this->snapshotDeJornada((int) $jornada->id);
        if ($snapshot === null) {
            return;
        }

        $movimientos = is_array($clasificacion['movimientos'] ?? null) ? $clasificacion['movimientos'] : [];
        $requiereEmision = CierreJornadaProcesoJornadaSupport::requiereEmisionProcesoDesdeMovimientos($movimientos);
        $pct = $porcentaje ?? (float) ($clasificacion['porcentaje'] ?? $snapshot->porcentaje ?? 0);

        $payload = is_array($snapshot->payload) ? $snapshot->payload : [];
        $payload['requiere_emision_proceso'] = $requiereEmision;
        $payload['total_pendiente_facturar_proceso'] = round((float) ($clasificacion['total_pendiente_facturar'] ?? 0), 2);

        $emisionActual = is_array($payload['factura_proceso_emision'] ?? null)
            ? $payload['factura_proceso_emision']
            : null;
        $yaTieneEmisionReal = is_array($emisionActual)
            && ! CierreJornadaProcesoJornadaSupport::emisionProcesoOmitida($emisionActual)
            && CierreJornadaProcesoJornadaSupport::facturaProcesoConsideradaEmitida($emisionActual);

        if (! $requiereEmision && ! $yaTieneEmisionReal) {
            $ctx = CierreJornadaProcesoJornadaSupport::contexto($jornada, $snapshot);
            if ($ctx['cerrada'] && $ctx['snapshot_definitivo']) {
                $payload['factura_proceso_emision'] = CierreJornadaProcesoJornadaSupport::emisionOmitidaPayload($pct);
            }
        } elseif ($requiereEmision && CierreJornadaProcesoJornadaSupport::emisionProcesoOmitida($emisionActual)) {
            unset($payload['factura_proceso_emision']);
        }

        $snapshot->payload = $payload;
        if (! $requiereEmision && ($snapshot->porcentaje === null || abs((float) $snapshot->porcentaje) <= 0.0001)) {
            $snapshot->porcentaje = 0.;
        }
        $snapshot->save();
    }

    /**
     * Detalle paginado de un grupo (evita enviar miles de filas en el análisis inicial).
     *
     * @return array<string, mixed>
     */
    public function movimientosGrupo(int $jornadaId, string $grupo, int $pagina = 1, int $porPagina = 50): array
    {
        $jornada = $this->resolverJornada($jornadaId);
        $empresaId = (int) $jornada->empresa_id;
        $porPagina = max(10, min(500, $porPagina));
        $pagina = max(1, $pagina);

        if ($grupo === self::GRUPO_ANITA_SIN_WAITRY) {
            return $this->movimientosAnitaSinWaitry($jornada, $empresaId, $pagina, $porPagina);
        }

        $clasificacion = $this->clasificacionParaJornada($jornada);
        $items = $clasificacion['grupos'][$grupo] ?? [];
        $total = count($items);
        $offset = ($pagina - 1) * $porPagina;
        $slice = array_slice($items, $offset, $porPagina);

        return [
            'ok' => true,
            'grupo' => $grupo,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total' => $total,
            'total_paginas' => (int) max(1, ceil($total / $porPagina)),
            'items' => $this->compactarMovimientosParaCliente($slice),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function guardarConfig(int $empresaId, array $data): array
    {
        $cfg = CierreJornadaProcesoConfigSupport::guardar($empresaId, $data);

        return ['ok' => true, 'config' => $cfg];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function enriquecerLineasConCobranzaAnita(array $lineas, int $empresaId): array
    {
        $waitryIds = [];
        foreach ($lineas as $ln) {
            $wid = (int) ($ln['waitry_order_id'] ?? 0);
            if ($wid > 0 && ! empty($ln['facturada_erp'])) {
                $waitryIds[] = $wid;
            }
        }
        if ($waitryIds === []) {
            return $lineas;
        }

        $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);

        $emisiones = VentaGastronomiaEmision::query()
            ->with(['venta.cobranzasDirectas', 'venta.caja_movimientos.cobranzas', 'cuenta', 'waitryComandaEnvio'])
            ->whereHas('venta', fn ($q) => $q->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId)))
            ->orderByDesc('venta_id')
            ->get();

        $mapEmisiones = [];
        foreach ($emisiones as $emision) {
            $wid = VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision);
            if ($wid > 0 && in_array($wid, $waitryIds, true) && ! isset($mapEmisiones[$wid])) {
                $mapEmisiones[$wid] = $emision;
            }
        }

        $out = [];
        foreach ($lineas as $ln) {
            $wid = (int) ($ln['waitry_order_id'] ?? 0);
            $emision = $mapEmisiones[$wid] ?? null;
            if ($emision !== null) {
                $ln['venta_id'] = $emision->venta_id;
                $ln['venta_codigo'] = $emision->venta?->codigo ?? ($ln['venta_codigo'] ?? '');
                $ln['impuesto_interno'] = $this->sumarImpuestoInternoVenta($emision->venta);

                $medio = $this->primerMedioCobranza($emision, $empresaId);
                if ($medio !== null) {
                    $ln['anita_cuentacaja_id'] = $medio['cuentacaja_id'];
                    $ln['anita_cuentacaja_label'] = $medio['label'];
                    $ln['anita_es_totem'] = $totemId > 0 && (int) $medio['cuentacaja_id'] === $totemId;
                } else {
                    $ln['anita_es_totem'] = (bool) ($ln['waitry_cobro_totem'] ?? false);
                }
            } else {
                $ln['anita_es_totem'] = (bool) ($ln['waitry_cobro_totem'] ?? false);
            }
            $out[] = $ln;
        }

        return $out;
    }

    private function sumarImpuestoInternoVenta($venta): float
    {
        if ($venta === null) {
            return 0.;
        }
        $venta->loadMissing('venta_impuestos');
        $total = 0.;
        foreach ($venta->venta_impuestos ?? [] as $vi) {
            $concepto = mb_strtolower((string) ($vi->concepto ?? ''));
            if (str_contains($concepto, 'intern')) {
                $total += (float) ($vi->importe ?? 0);
            }
        }

        return round($total, 2);
    }

    /**
     * @return array{cuentacaja_id:int,label:string}|null
     */
    private function primerMedioCobranza(VentaGastronomiaEmision $emision, int $empresaId): ?array
    {
        $venta = $emision->venta;
        if ($venta === null) {
            return null;
        }

        $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
        $medios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
        foreach ($medios as $lineas) {
            foreach ($lineas as $medio) {
                $ccId = (int) ($medio->cuentacaja_id ?? 0);
                if ($ccId <= 0) {
                    continue;
                }
                $codigo = trim((string) ($medio->codigo ?? ''));
                $nombre = trim((string) ($medio->nombre ?? ''));
                $label = $codigo !== '' && $nombre !== ''
                    ? $codigo.' — '.$nombre
                    : ($codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '#'.$ccId));

                return ['cuentacaja_id' => $ccId, 'label' => $label];
            }
        }

        return null;
    }

    private function resolverJornada(int $jornadaId): JornadaGastronomia
    {
        if ($jornadaId <= 0) {
            throw new InvalidArgumentException('Jornada inválida.');
        }

        $jornada = JornadaGastronomia::query()->find($jornadaId);
        if ($jornada === null) {
            throw new InvalidArgumentException('Jornada no encontrada.');
        }

        return $jornada;
    }

    private function resolverJornadaPorEmpresaFecha(int $empresaId, string $fechaJornada): JornadaGastronomia
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Debe seleccionar una empresa.');
        }

        $fecha = $this->normalizarFecha($fechaJornada);
        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fecha)
            ->orderByDesc('id')
            ->first();

        if ($jornada === null) {
            throw new InvalidArgumentException('No hay jornada gastronomía para esta empresa y fecha.');
        }

        return $jornada;
    }

    private function normalizarFecha(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            throw new InvalidArgumentException('Debe indicar la fecha de jornada.');
        }

        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Throwable) {
            throw new InvalidArgumentException('Fecha de jornada inválida.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenJornada(JornadaGastronomia $jornada): array
    {
        return [
            'id' => (int) $jornada->id,
            'empresa_id' => (int) $jornada->empresa_id,
            'estado' => (string) $jornada->estado,
            'fecha_jornada' => $jornada->fecha_jornada?->format('Y-m-d'),
            'fecha_jornada_fmt' => $jornada->fecha_jornada?->format('d/m/Y'),
            'apertura_en' => $jornada->apertura_en?->format('d/m/Y H:i'),
            'cierre_en' => $jornada->cierre_en?->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function movimientosAnitaSinWaitry(
        JornadaGastronomia $jornada,
        int $empresaId,
        int $pagina,
        int $porPagina,
    ): array {
        $fecha = $jornada->fecha_jornada?->format('Y-m-d');
        if ($fecha === null || $fecha === '') {
            return [
                'ok' => true,
                'grupo' => self::GRUPO_ANITA_SIN_WAITRY,
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => 0,
                'total_paginas' => 1,
                'items' => [],
            ];
        }

        $query = VentaGastronomiaEmision::query()
            ->with(['venta', 'cuenta', 'waitryComandaEnvio', 'configuracionPuntoventa'])
            ->whereHas('configuracionPuntoventa', fn ($q) => $q->where('waitry_habilitado', false))
            ->whereHas('venta', function ($q) use ($empresaId, $fecha) {
                $q->whereDate('fechajornada', $fecha)
                    ->whereHas('puntoventas', fn ($pv) => $pv->where('empresa_id', $empresaId));
            })
            ->orderByDesc('venta_id');

        $total = (int) $query->count();
        $offset = ($pagina - 1) * $porPagina;
        $emisiones = $query->skip($offset)->take($porPagina)->get();

        return [
            'ok' => true,
            'grupo' => self::GRUPO_ANITA_SIN_WAITRY,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total' => $total,
            'total_paginas' => (int) max(1, ceil($total / $porPagina)),
            'items' => $this->compactarEmisionesAnitaParaCliente($emisiones, $empresaId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenAnitaSinWaitry(JornadaGastronomia $jornada, int $empresaId): array
    {
        $fecha = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        $emisiones = CierreJornadaFacturadoAnitaSupport::emisionesSinWaitryJornadaEmpresa($empresaId, $fecha);
        $cantidadNc = $emisiones->filter(
            fn ($e) => ($e->venta_factura_origen_id ?? null) !== null,
        )->count();

        return [
            'clave' => self::GRUPO_ANITA_SIN_WAITRY,
            'titulo' => 'Facturas POS — terminales sin integración Waitry',
            'cantidad' => $emisiones->count(),
            'cantidad_facturas' => $emisiones->count() - $cantidadNc,
            'cantidad_notas_credito' => $cantidadNc,
            'total' => round($emisiones->sum(fn ($e) => (float) ($e->venta?->total ?? 0)), 2),
        ];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $grupos
     * @return list<array<string, mixed>>
     */
    private function resumenGrupos(array $grupos, JornadaGastronomia $jornada, int $empresaId): array
    {
        $mapa = [
            CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_MEDIO_REAL => 'Facturados en Anita — cobro en cuenta real (QR, MP, efectivo…)',
            CierreJornadaProcesoClasificacionSupport::GRUPO_FACTURADO_TOTEM => 'Facturados — cobro TOTEM (medio real Waitry)',
            CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_QR => 'Sin facturar — QR / Mercado Pago (redistribuibles)',
            CierreJornadaProcesoClasificacionSupport::GRUPO_SIN_FACTURAR_OTRO => 'Sin facturar — otros medios',
            CierreJornadaProcesoClasificacionSupport::GRUPO_WAITRY_CASH_NO_FACTURAR => 'Waitry efectivo — no se factura',
            CierreJornadaProcesoClasificacionSupport::GRUPO_HUECO_AUDITORIA => 'Huecos de secuencia (auditoría)',
        ];

        $out = [];
        foreach ($mapa as $clave => $titulo) {
            $items = $grupos[$clave] ?? [];
            $total = round(array_sum(array_map(fn (array $m) => (float) ($m['total'] ?? 0), $items)), 2);
            $out[] = [
                'clave' => $clave,
                'titulo' => $titulo,
                'cantidad' => count($items),
                'total' => $total,
            ];
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, VentaGastronomiaEmision>  $emisiones
     * @return list<array<string, mixed>>
     */
    private function compactarEmisionesAnitaParaCliente($emisiones, int $empresaId): array
    {
        $totemId = (int) (GastronomiaCuentacajaTotem::cuentaParaEmpresa($empresaId)['id'] ?? 0);
        $out = [];
        foreach ($emisiones as $emision) {
            $venta = $emision->venta;
            $esNc = ($emision->venta_factura_origen_id ?? null) !== null;
            $waitryId = VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision);
            $medio = $this->primerMedioCobranza($emision, $empresaId);
            $waitryTipo = $emision->cuenta?->waitry_tipo_pago;
            $medioLabel = $medio['label'] ?? '';
            if ($medioLabel === '' && $waitryTipo !== null && $waitryTipo !== '') {
                $medioLabel = \App\Support\Ventas\Waitry\WaitryMedioPagoCuentacajaSupport::etiquetaTipo($waitryTipo);
            }

            $out[] = [
                'waitry_order_id' => $waitryId > 0 ? $waitryId : null,
                'display_id' => $waitryId > 0 ? '#'.$waitryId : (string) ($venta?->codigo ?? '—'),
                'fecha_hora_fmt' => $venta?->created_at?->format('d/m/Y H:i') ?? '',
                'total' => round((float) ($venta?->total ?? 0), 2),
                'venta_id' => (int) ($emision->venta_id ?? 0) > 0 ? (int) $emision->venta_id : null,
                'venta_codigo' => (string) ($venta?->codigo ?? ''),
                'waitry_medio_label' => $medioLabel !== '' ? $medioLabel : '—',
                'anita_cuentacaja_label' => $medio['label'] ?? '',
                'identificador_pc' => (string) ($emision->identificador_pc ?? $emision->configuracionPuntoventa?->identificador_pc ?? ''),
                'terminal_descripcion' => (string) ($emision->configuracionPuntoventa?->descripcion ?? ''),
                'es_nota_credito' => $esNc,
                'anita_es_totem' => $medio !== null
                    ? ($totemId > 0 && (int) $medio['cuentacaja_id'] === $totemId)
                    : (bool) ($emision->cuenta?->waitry_cobro_totem ?? false),
            ];
        }

        return $out;
    }

    /**
     * Reduce payload JSON para el navegador (detalle por grupo vía paginación).
     *
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    private function compactarMovimientosParaCliente(array $movimientos): array
    {
        $out = [];
        foreach ($movimientos as $m) {
            $ventaId = (int) ($m['venta_id'] ?? 0);
            $out[] = [
                'waitry_order_id' => $m['waitry_order_id'] ?? null,
                'display_id' => $m['display_id'] ?? '',
                'placed_at_fmt' => $m['placed_at_fmt'] ?? '',
                'placed_at' => $m['placed_at'] ?? null,
                'total' => round((float) ($m['total'] ?? 0), 2),
                'grupo' => $m['grupo'] ?? '',
                'facturada_erp' => ! empty($m['facturada_erp']),
                'venta_id' => $ventaId > 0 ? $ventaId : null,
                'venta_codigo' => $m['venta_codigo'] ?? '',
                'anita_cuentacaja_label' => $m['anita_cuentacaja_label'] ?? '',
                'waitry_medio_label' => $m['waitry_medio_label'] ?? '',
                'medio_anita_clave' => $m['medio_anita_clave'] ?? null,
                'medio_waitry_clave' => $m['medio_waitry_clave'] ?? null,
                'anita_es_totem' => ! empty($m['anita_es_totem']),
                'medio_pago_planificado' => $m['medio_pago_planificado'] ?? null,
                'medios_pago_planificados' => $m['medios_pago_planificados'] ?? null,
                'discrepancia_gap' => ! empty($m['discrepancia_gap']),
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function notasProceso(): array
    {
        return [
            'Al pulsar «Analizar tramo» se congela el tramo Waitry de esa jornada en base de datos: las consultas de detalle y el recálculo del % no vuelven a llamar a Waitry.',
            'Para volver a traer órdenes nuevas de Waitry debe eliminarse el snapshot (soporte) o usar analizar con ?refrescar_waitry=1.',
            'Con jornada gastronomía abierta, el último ticket del tramo es el último ID leído en ese análisis (auditoría en curso; puede cambiar).',
            'Tras el cierre en Ventas → Gastronomía → Jornada, el último ticket queda fijado en el registro de cierre tótem (waitry_order_id_hasta).',
            'Los movimientos incluyen órdenes Waitry desde el último ticket del cierre anterior de la empresa hasta el tope del tramo.',
            'El cuadro parte del total facturado en Anita (fechajornada), con fila aparte para facturas cobradas con TOTEM (medio real Waitry), más lo cobrado en Waitry sin facturar (candidatos a facturación) e impagos Waitry solo como referencia.',
            'Los medios de pago Waitry sin facturar se resuelven desde getOrdersPOS cuando getordersdetails no trae payment.type.',
            'El efectivo registrado en Waitry (cash) no se facturará; queda en fila aparte.',
            'Las facturas cobradas con TOTEM en Anita generan asiento puente (Debe medio real / Haber TOTEM); el QR de esas facturas entra en el cupo de redistribución a efectivo.',
            'El porcentaje objetivo (default 25% sobre facturado Anita) mueve QR/Totalcoin/MP→efectivo en Waitry sin facturar (orden waitry_order_id) y arma el 3er asiento (fondo fijo). Si el disponible recodificable es menor, se aplica ese tope (p. ej. Kandiko 18,46% el 19/08). Compensa el mismo importe en facturas Anita cobradas en efectivo→mismo medio (memoria).',
            'El % no puede implicar recodificar más de lo cobrado Waitry sin facturar en QR/Totalcoin + MP; si excede, el pendiente QR/MP a facturar quedaría negativo.',
            'Haga clic en un importe del cuadro para ver comandas con fecha/hora y conciliar contra Waitry.',
            'Consola: php artisan gastronomia:diagnostico-cuadro-cierre {empresa} {fecha} {fila} {medio} [--csv=ruta]',
            'El preview de asientos y comandas puede usarse con jornada abierta; «Emitir factura del proceso» exige jornada cerrada y snapshot definitivo.',
            'El punto de venta del proceso se resuelve por empresa (BD o GASTRONOMIA_CIERRE_JORNADA_PUNTOVENTA_CODIGO_POR_EMPRESA) y se validará al emitir.',
            'Las órdenes Waitry canceladas no entran en totales ni en candidatos a facturar.',
            'Las órdenes Waitry con descuento total (neto $0, sin cobro en kiosco) no entran en impagos ni totales operativos.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function filtrarLineasOperativasWaitry(array $lineas): array
    {
        return array_values(array_filter(
            $lineas,
            static fn (array $linea): bool => ! WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotalLinea($linea),
        ));
    }

    /**
     * Snapshot congelado antes del fix de descuento total: enriquecer impagos con getordersdetails.
     *
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function normalizarLineasSnapshotDescuento(JornadaGastronomia $jornada, array $lineas): array
    {
        if (! WaitryOrdenEstadoSupport::lineasRequierenEnriquecimientoDescuento($lineas)) {
            return $lineas;
        }

        $ordenesPorId = $this->mapOrdenesWaitryParaJornada($jornada);
        if ($ordenesPorId === []) {
            return $lineas;
        }

        $enriquecidas = WaitryOrdenEstadoSupport::enriquecerLineasImpagasConOrdenes($lineas, $ordenesPorId);
        $this->persistirLineasSnapshotEnriquecidas((int) $jornada->id, $enriquecidas);

        return $enriquecidas;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapOrdenesWaitryParaJornada(JornadaGastronomia $jornada): array
    {
        $fecha = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        if ($fecha === '') {
            return [];
        }

        $resuelto = WaitryCierreJornadaVentanaSupport::resolverParaCierreJornada(
            $fecha,
            $jornada->apertura_en,
            $jornada->cierre_en,
        );
        $rango = $resuelto['rango_calendario'];
        $waitry = $this->analyticsOrdenesService->ordenesPorRangoFecha(
            (int) $jornada->empresa_id,
            $rango['desde'],
            $rango['hasta'],
        );
        if ($waitry['ok'] ?? false) {
            $porId = [];
            foreach ($waitry['ordenes'] ?? [] as $orden) {
                if (! is_array($orden)) {
                    continue;
                }
                $id = (int) ($orden['orderId'] ?? $orden['id'] ?? 0);
                if ($id > 0) {
                    $porId[$id] = $orden;
                }
            }

            return $porId;
        }

        return $this->ordenesExternasService->mapOrdenesPosEnVentanaJornada(
            (int) $jornada->empresa_id,
            $fecha,
            $jornada->apertura_en,
            WaitryCierreJornadaVentanaSupport::resolverCierreHasta($jornada),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function persistirLineasSnapshotEnriquecidas(int $jornadaId, array $lineas): void
    {
        $snapshot = $this->snapshotDeJornada($jornadaId);
        if ($snapshot === null) {
            return;
        }

        $payload = $snapshot->payload;
        if (! is_array($payload)) {
            return;
        }

        $payload['lineas'] = $lineas;
        $payload['lineas_schema_version'] = CierreJornadaProcesoJornadaSupport::LINEAS_SCHEMA_VERSION;
        $snapshot->payload = $payload;
        $snapshot->save();
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function totalFilaCuadroPorTipo(array $filas, string $tipo): float
    {
        $fila = self::filaCuadroPorTipo($filas, $tipo);

        return round((float) ($fila['total'] ?? 0), 2);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>
     */
    private function filaCuadroPorTipo(array $filas, string $tipo): array
    {
        foreach ($filas as $fila) {
            if (($fila['tipo'] ?? '') === $tipo) {
                return $fila;
            }
        }

        return CierreJornadaProcesoGrillaSupport::filaVacia('', $tipo);
    }

    /**
     * Columnas de referencia del asiento 2 (toda la jornada Anita excl. TOTEM), desde cobranzas ERP.
     *
     * @param  array<string, mixed>  $datosAsientoAnita
     * @return array<string, float>
     */
    private function filaReferenciaAsiento2(array $datosAsientoAnita, int $empresaId): array
    {
        $ref = [
            'qr' => 0.,
            'mp' => 0.,
            'efectivo' => 0.,
            'otros' => 0.,
            'diferencia_caja' => round((float) ($datosAsientoAnita['debe_diferencia_caja'] ?? 0), 2),
        ];

        foreach ($datosAsientoAnita['debe_por_cuenta'] ?? [] as $ln) {
            $ccId = (int) ($ln['cuenta_id'] ?? 0);
            $monto = round((float) ($ln['debe'] ?? 0), 2);
            if ($ccId <= 0 || abs($monto) <= 0.0001) {
                continue;
            }
            $clave = CierreJornadaProcesoMedioSupport::claveDesdeCuentacaja(['id' => $ccId], $empresaId);
            $col = CierreJornadaFacturadoAnitaSupport::columnaCuadroDesdeClaveMedio($clave);
            $ref[$col] = round(($ref[$col] ?? 0.) + $monto, 2);
        }

        return $ref;
    }

    /**
     * Waitry pagado sin facturar en Anita para informe gerente (solo jornada abierta).
     * Consulta Waitry en vivo; no persiste snapshot.
     *
     * @return array{
     *   empresa_id:int,
     *   total:float,
     *   cantidad_ordenes:int,
     *   puntoventa_id:int,
     *   codigo:string,
     *   nombre:string
     * }|null
     */
    public function waitryPagadoSinFacturarParaInformeGerente(int $empresaId, string $fechaJornada): ?array
    {
        if (! $this->habilitado() || $empresaId <= 0) {
            return null;
        }

        $fecha = $this->normalizarFecha($fechaJornada);
        $jornada = JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fecha)
            ->orderByDesc('id')
            ->first();

        if ($jornada === null || (string) ($jornada->estado ?? '') !== JornadaGastronomia::ESTADO_ABIERTA) {
            return null;
        }

        $cargado = $this->cierreTotemJornadaService->movimientosParaProcesoCaja($jornada);
        $lineas = $this->filtrarLineasOperativasWaitry($cargado['lineas']);
        $lineas = $this->enriquecerLineasConCobranzaAnita($lineas, $empresaId);

        $totalesAnita = CierreJornadaFacturadoAnitaSupport::totalesJornadaEmpresa($empresaId, $fecha);
        $clasificacion = CierreJornadaProcesoClasificacionSupport::clasificar($lineas, $empresaId, $totalesAnita);
        $total = round((float) ($clasificacion['total_pendiente_facturar'] ?? 0), 2);
        if ($total <= 0.0001) {
            return null;
        }

        $pv = CierreJornadaProcesoPuntoventaSupport::resolverParaEmpresa($empresaId);

        return [
            'empresa_id' => $empresaId,
            'total' => $total,
            'cantidad_ordenes' => $this->contarWaitryPagadoSinFacturar($clasificacion['movimientos'] ?? []),
            'puntoventa_id' => (int) ($pv['id'] ?? 0),
            'codigo' => trim((string) ($pv['codigo'] ?? '')),
            'nombre' => trim((string) ($pv['nombre'] ?? '')),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     */
    private function contarWaitryPagadoSinFacturar(array $movimientos): int
    {
        $cantidad = 0;
        foreach ($movimientos as $mov) {
            if (! empty($mov['discrepancia_gap'])) {
                continue;
            }
            if (! empty($mov['facturada_erp'])) {
                continue;
            }
            if (WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotalLinea($mov)) {
                continue;
            }
            $total = round((float) ($mov['total'] ?? 0), 2);
            if ($total <= 0.0001) {
                continue;
            }
            if (CierreJornadaProcesoMedioSupport::esWaitryCash($mov['waitry_tipo_pago'] ?? null)) {
                continue;
            }
            if (! $this->movimientoCobradoEnWaitry($mov)) {
                continue;
            }
            $cantidad++;
        }

        return $cantidad;
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private function movimientoCobradoEnWaitry(array $mov): bool
    {
        if (! empty($mov['waitry_cobro_totem'])) {
            return true;
        }
        if (($mov['paid_waitry'] ?? null) === true) {
            return true;
        }

        return (float) ($mov['monto_cobro_waitry'] ?? 0) > 0.0001;
    }
}
