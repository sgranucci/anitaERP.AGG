<?php

namespace App\Services\Caja\Estacionamiento;

use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Caja\Estacionamiento\CuentaEstacionamiento;
use App\Models\Caja\Estacionamiento\TicketEstacionamiento;
use App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision;
use App\Models\Configuracion\Actividad_Arca;
use App\Models\Ventas\Venta;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Support\Caja\Estacionamiento\EstacionamientoFacturaPayloadSupport;
use App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia;
use App\Support\Ventas\CaeaEmisionNumeracionSupport;
use App\Support\Ventas\GastronomiaPuntoventaEmisionLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Orquesta la emisión de factura desde una cuenta estacionamiento.
 */
final class EstacionamientoFacturaEmisionService
{
    public function __construct(
        private readonly EstacionamientoFacturacionService $facturacionService,
        private readonly EstacionamientoCuentaService $cuentaService,
        private readonly EstacionamientoReceptorFacturacionService $receptorFacturacionService,
        private readonly EstacionamientoCobranzaService $cobranzaService,
        private readonly EstacionamientoPreflightEmisionService $preflightService,
        private readonly JornadaEstacionamientoService $jornadaService,
        private readonly EstacionamientoFacturaTicketService $facturaTicketService,
        private readonly EstacionamientoTurnoOperativoService $turnoOperativoService,
    ) {
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return list<string>
     */
    public function erroresPreflightEmision(
        CuentaEstacionamiento $cuenta,
        int $monedaId,
        array $mediosPago,
        bool $facturacionConDescuento = false,
    ): array {
        $cuenta->loadMissing([
            'lineas.itemEstacionamiento',
            'cliente',
            'categoriaAutomovil',
            'descuentoEstacionamiento',
            'configuracionPuntoventa',
        ]);

        $cfg = $cuenta->configuracionPuntoventa ?? $this->cuentaService->resolverConfiguracionPv();
        if (! $cfg) {
            return $this->preflightService->erroresAntesDeEmitir(
                $cuenta,
                null,
                $monedaId,
                $mediosPago,
                [],
            );
        }

        $payload = $this->armarPayloadFacturaParaCuenta($cuenta, $cfg, $monedaId);
        if (isset($payload['error'])) {
            return [(string) $payload['error']];
        }

        return $this->preflightService->erroresAntesDeEmitir(
            $cuenta,
            $cfg,
            $monedaId,
            $mediosPago,
            $payload,
            $facturacionConDescuento,
        );
    }

    /**
     * @return array{total:float,sin_cobranza:bool,factura_cortesia:bool,error?:string}
     */
    public function previewTotalesParaCuenta(CuentaEstacionamiento $cuenta, ?int $monedaId = null): array
    {
        $monedaId ??= (int) config('estacionamiento.moneda_factura_id', 1);

        $cuenta->loadMissing([
            'lineas.itemEstacionamiento',
            'cliente',
            'descuentoEstacionamiento',
            'configuracionPuntoventa',
        ]);

        if ($cuenta->lineas->isEmpty()) {
            return ['total' => 0., 'sin_cobranza' => false, 'factura_cortesia' => false];
        }

        $cfg = $cuenta->configuracionPuntoventa ?? $this->cuentaService->resolverConfiguracionPv();
        if (! $cfg) {
            return ['total' => 0., 'sin_cobranza' => false, 'factura_cortesia' => false];
        }

        $payload = $this->armarPayloadFacturaParaCuenta($cuenta, $cfg, $monedaId);
        if (isset($payload['error'])) {
            return [
                'total' => 0.,
                'sin_cobranza' => false,
                'factura_cortesia' => false,
                'error' => (string) $payload['error'],
            ];
        }

        return $this->facturacionService->previewTotalesEmision($payload, $cuenta);
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{venta_id?:int,factura?:string,warn?:string,error?:string,sin_cobranza?:bool,cobranza_id?:int,mensaje?:string}
     */
    public function emitirFacturaDesdeCuenta(
        CuentaEstacionamiento $cuenta,
        int $monedaId,
        ?int $actividadArcaId = null,
        bool $forzarPvCaea = false,
        array $mediosPago = [],
        bool $bloqueoPvYaAdquirido = false,
        bool $facturacionConDescuento = false,
    ): array {
        $cuenta->loadMissing([
            'lineas.itemEstacionamiento',
            'cliente',
            'categoriaAutomovil',
            'descuentoEstacionamiento',
            'configuracionPuntoventa',
            'turnoOperativo.usuarioHabilitado',
        ]);

        if ($cuenta->lineas->isEmpty()) {
            return ['error' => 'La cuenta no tiene ítems cargados.'];
        }

        if ($cuenta->estado !== CuentaEstacionamiento::ESTADO_ABIERTA) {
            return ['error' => 'La cuenta no está abierta.'];
        }

        $cfg = $cuenta->configuracionPuntoventa ?? $this->cuentaService->resolverConfiguracionPv();
        if (! $cfg) {
            return ['error' => 'No hay configuración de punto de venta estacionamiento para este equipo ('.EstacionamientoIdentificadorPc::resolver().').'];
        }

        $payload = $this->armarPayloadFacturaParaCuenta($cuenta, $cfg, $monedaId, $actividadArcaId, $forzarPvCaea);
        if (isset($payload['error'])) {
            return ['error' => (string) $payload['error']];
        }

        $puntoventaId = (int) ($payload['puntoventa_id'] ?? 0);

        try {
            $this->preflightService->exigirListoParaEmitir(
                $cuenta,
                $cfg,
                $monedaId,
                $mediosPago,
                $payload,
                $facturacionConDescuento,
            );
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }

        $puntoventa = Puntoventa::query()->find($puntoventaId);
        $emisionCaea = $puntoventa !== null && ($puntoventa->modofacturacion ?? '') === 'A';
        if ($emisionCaea && empty($payload['numerocomprobante_forzado'])) {
            $tipo = Tipotransaccion::query()->find((int) ($payload['tipotransaccion_id'] ?? 0));
            if ($tipo === null) {
                return ['error' => 'Tipo de transacción de factura inexistente.'];
            }
            $letra = 'B';
            $clienteId = (int) ($payload['cliente_id'] ?? 0);
            if ($clienteId > 0) {
                $cliente = \App\Models\Ventas\Cliente::query()->find($clienteId);
                if ($cliente !== null && $cliente->condicioniva_id) {
                    $letra = (string) (\App\Models\Configuracion\Condicioniva::query()
                        ->whereKey($cliente->condicioniva_id)
                        ->value('letra') ?? 'B');
                }
            }
            $errorReserva = CaeaEmisionNumeracionSupport::aplicarReservaNumeracionAlPayload(
                $payload,
                $puntoventa,
                $tipo,
                trim($letra) !== '' ? $letra : 'B',
            );
            if ($errorReserva !== null) {
                return ['error' => $errorReserva];
            }
        }

        $lockPv = null;
        $mantenerLockEmisionCompleta = ! $emisionCaea && ! $bloqueoPvYaAdquirido;

        if ($mantenerLockEmisionCompleta) {
            try {
                $lockPv = GastronomiaPuntoventaEmisionLock::adquirir($puntoventaId);
            } catch (InvalidArgumentException $e) {
                return ['error' => $e->getMessage()];
            }
        }

        try {
            try {
                $resultado = DB::transaction(function () use (
                    $cuenta,
                    $cfg,
                    $payload,
                    $mediosPago,
                    $monedaId,
                    $puntoventaId,
                ) {
                    return $this->ejecutarEmisionEnTransaccion(
                        $cuenta,
                        $cfg,
                        $payload,
                        $mediosPago,
                        $monedaId,
                        $puntoventaId,
                    );
                });

                $resultado = $this->facturacionService->completarAnitaPendienteTrasEmision($resultado);

                $resultado = $this->aplicarImpresionTicketTrasEmision($resultado, $cfg, $cuenta);
                $resultado['mensaje'] = 'Factura '.trim((string) ($resultado['factura'] ?? '')).' emitida correctamente.';

                return $resultado;
            } catch (Throwable $e) {
                $claseError = ArcaWsfeEmisionResiliencia::clasificarError($e->getMessage());
                Log::error('estacionamiento.emitir_factura.fallo', [
                    'cuenta_id' => $cuenta->id,
                    'pv_intentado' => $puntoventaId,
                    'clase_error' => $claseError,
                    'msg' => $e->getMessage(),
                ]);

                if (! $forzarPvCaea && ArcaWsfeEmisionResiliencia::debeReintentarTransaccionConCaea($e->getMessage(), false)) {
                    return $this->emitirFacturaDesdeCuenta(
                        $cuenta,
                        $monedaId,
                        $actividadArcaId,
                        true,
                        $mediosPago,
                        true,
                    );
                }

                return ['error' => $e->getMessage()];
            }
        } finally {
            if ($mantenerLockEmisionCompleta) {
                GastronomiaPuntoventaEmisionLock::liberar($lockPv);
            }
        }
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     * @return array{venta_id:int,factura:string,warn?:string,sin_cobranza?:bool,cobranza_id?:int}
     */
    private function ejecutarEmisionEnTransaccion(
        CuentaEstacionamiento $cuenta,
        ConfiguracionPuntoventaEstacionamiento $cfg,
        array $payload,
        array $mediosPago,
        int $monedaId,
        int $puntoventaId,
    ): array {
        $ventaAnitaRevertir = null;

        $cuenta = CuentaEstacionamiento::query()
            ->whereKey($cuenta->id)
            ->lockForUpdate()
            ->firstOrFail();

        try {
            $resultado = $this->facturacionService->emitirComprobante($payload, $cuenta);

            if (! empty($resultado['error'])) {
                throw new InvalidArgumentException((string) $resultado['error']);
            }

            $facturaTxt = trim((string) ($resultado['factura'] ?? ''));
            if ($facturaTxt === '') {
                throw new RuntimeException('El servicio de facturación no devolvió el número de comprobante.');
            }

            $ventaId = (int) ($resultado['venta_id'] ?? 0);
            $venta = $ventaId > 0 ? Venta::query()->find($ventaId) : null;
            if (! $venta) {
                $venta = $this->resolverVentaPorEtiqueta($puntoventaId, $facturaTxt);
            }
            if (! $venta) {
                throw new RuntimeException(
                    'No se pudo recuperar la venta interna tras emitir el comprobante '.$facturaTxt.'.'
                );
            }

            if (config('estacionamiento.sincronizar_anita_al_facturar', false) && empty($resultado['anita_pendiente'])) {
                $ventaAnitaRevertir = $venta;
            }

            $sinCobranza = ! empty($resultado['sin_cobranza']);
            $cobranzaId = null;

            if (! $sinCobranza) {
                $cobRes = $this->cobranzaService->registrarCobranzaPos(
                    $venta->fresh(),
                    $mediosPago,
                    $cfg,
                );
                $cobranzaId = isset($cobRes['cobranza_id']) ? (int) $cobRes['cobranza_id'] : null;
            }

            if (! empty($resultado['cae_pendiente']) && is_array($resultado['cae_pendiente'])) {
                $this->facturacionService->completarSolicitudCaePendiente($resultado['cae_pendiente']);
            }

            $pc = EstacionamientoIdentificadorPc::resolver();
            $turno = $this->turnoOperativoService->turnoHabilitadoEnPc($pc);

            VentaEstacionamientoEmision::updateOrCreate(
                ['venta_id' => $venta->id],
                [
                    'ticket_estacionamiento_id' => $cuenta->ticket_estacionamiento_id,
                    'identificador_pc' => $pc,
                    'configuracion_puntoventa_estacionamiento_id' => $cfg->id,
                    'jornada_estacionamiento_id' => $payload['jornada_estacionamiento_id'] ?? $cuenta->jornada_estacionamiento_id,
                    'turno_operativo_estacionamiento_id' => $turno?->id ?? $cuenta->turno_operativo_estacionamiento_id,
                ],
            );

            if ((int) ($cuenta->ticket_estacionamiento_id ?? 0) > 0) {
                TicketEstacionamiento::query()
                    ->whereKey($cuenta->ticket_estacionamiento_id)
                    ->where('estado', TicketEstacionamiento::ESTADO_INGRESO)
                    ->update([
                        'estado' => TicketEstacionamiento::ESTADO_FACTURADO,
                        'venta_id' => $venta->id,
                        'facturado_en' => now(),
                    ]);
            }

            $this->cuentaService->marcarFacturada($cuenta->fresh(), $venta->id);

            $ventaAnitaRevertir = null;

            $warn = ArcaWsfeEmisionResiliencia::mensajeAvisoModoCaeaForzado();
            if (! empty($resultado['factura_cortesia_total'])) {
                $warnCortesia = 'Factura de cortesía ($'.number_format(
                    EstacionamientoFacturacionService::IMPORTE_MINIMO_FACTURA,
                    2,
                    ',',
                    '.',
                ).'); no requiere cobranza.';
                $warn = $warn ? $warn."\n\n".$warnCortesia : $warnCortesia;
            }

            return array_filter([
                'venta_id' => $venta->id,
                'factura' => $facturaTxt,
                'warn' => $warn,
                'sin_cobranza' => $sinCobranza,
                'cobranza_id' => $cobranzaId,
                'anita_pendiente' => (! empty($resultado['anita_pendiente']) && is_array($resultado['anita_pendiente']))
                    ? $resultado['anita_pendiente']
                    : null,
            ], fn ($v) => $v !== null && $v !== '' && $v !== false);
        } catch (Throwable $e) {
            if ($ventaAnitaRevertir !== null) {
                $this->facturacionService->revertirVentaEnAnitaSiHabilitado($ventaAnitaRevertir);
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function armarPayloadFacturaParaCuenta(
        CuentaEstacionamiento $cuenta,
        ConfiguracionPuntoventaEstacionamiento $cfg,
        int $monedaId,
        ?int $actividadArcaId = null,
        bool $forzarPvCaea = false,
    ): array {
        $tipoFacturaId = (int) ($cfg->tipotransaccion_id ?? 0);
        if ($tipoFacturaId <= 0) {
            $tipoFacturaId = (int) config('estacionamiento.tipotransaccion_factura_id', 0);
        }
        if ($tipoFacturaId <= 0) {
            return ['error' => 'Configure el tipo de transacción (factura) en la configuración del punto de venta estacionamiento.'];
        }

        try {
            $pvResolucion = ArcaWsfeEmisionResiliencia::resolverPuntoventaEmision(
                (int) $cfg->puntoventa_cae_id,
                (int) $cfg->puntoventa_caea_id,
                $forzarPvCaea,
            );
            $puntoventaId = $pvResolucion['puntoventa_id'];
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }

        try {
            $receptor = $this->receptorFacturacionService->resolverParaFacturar($cuenta);
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }

        [$articuloIds, $cantidades, $precios, $descripciones] = EstacionamientoFacturaPayloadSupport::arraysDesdeCuenta($cuenta);
        $nLineas = count($articuloIds);

        $payload = [
            'tipotransaccion_id' => $tipoFacturaId,
            'puntoventa_id' => $puntoventaId,
            'fechafactura' => now()->format('Y-m-d'),
            'leyendafactura' => $this->leyendaCuenta($cuenta),
            'actividad_arca_id' => $actividadArcaId ?? (int) (Actividad_Arca::query()->orderBy('id')->value('id') ?? 1),
            'cliente_id' => $receptor['cliente_id'],
            'moneda_id' => $monedaId,
            'listaprecio_id' => (int) config('estacionamiento.listaprecio_id', 1),
            'descuentolinea' => 0.,
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $precios,
            'descripcionarticulos' => $descripciones,
            'impuesto_ids' => EstacionamientoFacturaPayloadSupport::impuestoIdsParaLineas($nLineas),
            'incluyeimpuestos' => array_fill(0, $nLineas, 'N'),
            // Sin artículos de stock: nunca stkmov en Anita aunque se reactive la sincronización.
            'omitir_stkmov_anita_por_item' => array_fill(0, $nLineas, true),
        ];

        $this->receptorFacturacionService->aplicarReceptorAlPayloadFacturacion($payload, $receptor);

        try {
            $payload = $this->jornadaService->aplicarFechasAlPayload($payload, (int) $cuenta->empresa_id);
        } catch (InvalidArgumentException $e) {
            return ['error' => $e->getMessage()];
        }

        return $payload;
    }

    private function leyendaCuenta(CuentaEstacionamiento $cuenta): string
    {
        $partes = ['Estacionamiento'];
        if ($cuenta->categoriaAutomovil) {
            $partes[] = 'Cat. '.$cuenta->categoriaAutomovil->nombre;
        }
        $patente = trim((string) ($cuenta->patente ?? ''));
        if ($patente !== '') {
            $partes[] = 'Pat. '.$patente;
        }

        return implode(' — ', $partes);
    }

    private function resolverVentaPorEtiqueta(int $puntoventaId, string $facturaTxt): ?Venta
    {
        if ($puntoventaId <= 0 || $facturaTxt === '') {
            return null;
        }

        return Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->where('codigo', 'like', '%'.$facturaTxt.'%')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    private function aplicarImpresionTicketTrasEmision(
        array $resultado,
        ConfiguracionPuntoventaEstacionamiento $cfg,
        CuentaEstacionamiento $cuenta,
    ): array {
        $ventaId = (int) ($resultado['venta_id'] ?? 0);
        if ($ventaId <= 0) {
            return $resultado;
        }

        $imp = $this->facturaTicketService->imprimirTrasEmisionEncolado($ventaId, $cfg, $cuenta);
        if (empty($imp['ok'])) {
            $aviso = 'No se pudo imprimir el ticket: '.trim((string) ($imp['mensaje'] ?? 'error desconocido'));
            $warnPrevio = trim((string) ($resultado['warn'] ?? ''));
            $resultado['warn'] = $warnPrevio !== '' ? $warnPrevio."\n\n".$aviso : $aviso;
        }

        return $resultado;
    }
}
