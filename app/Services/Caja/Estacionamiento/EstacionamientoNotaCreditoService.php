<?php

namespace App\Services\Caja\Estacionamiento;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Caja\Estacionamiento\CuentaEstacionamiento;
use App\Models\Caja\Estacionamiento\VentaEstacionamientoEmision;
use App\Models\Configuracion\Actividad_Arca;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Support\Caja\Estacionamiento\EstacionamientoFacturaPayloadSupport;
use App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc;
use App\Support\Caja\Estacionamiento\EstacionamientoVentaDetalleSupport;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Emite nota de crédito desde Facturas del día (estacionamiento).
 * Sin reverso de stock ni insumos Anita; réplica cabecera NC en Informix post-commit.
 */
final class EstacionamientoNotaCreditoService
{
    public function __construct(
        private readonly EstacionamientoFacturacionService $facturacionService,
        private readonly EstacionamientoCobranzaService $cobranzaService,
        private readonly JornadaEstacionamientoService $jornadaService,
        private readonly EstacionamientoPvService $pvService,
        private readonly EstacionamientoTurnoOperativoService $turnoOperativoService,
        private readonly EstacionamientoFacturaTicketService $facturaTicketService,
    ) {
    }

    /**
     * @return array{ok:bool,venta_id?:int,factura?:string,mensaje?:string,warn?:string,error?:string}
     */
    public function generarDesdeFactura(int $ventaFacturaId, ?Request $request = null, string $leyendaUsuario = ''): array
    {
        $emision = VentaEstacionamientoEmision::query()
            ->where('venta_id', $ventaFacturaId)
            ->with([
                'venta.clientes',
                'venta.puntoventas',
                'venta.venta_emisiones.articulos',
                'venta.tipotransacciones',
                'venta.cobranzasDirectas',
                'venta.caja_movimientos.cobranzas',
                'configuracionPuntoventa',
                'ticket',
            ])
            ->first();

        if (! $emision || ! $emision->venta) {
            return ['ok' => false, 'error' => 'La venta no corresponde a una emisión estacionamiento.'];
        }

        if ($emision->venta_factura_origen_id !== null) {
            return ['ok' => false, 'error' => 'No puede generar nota de crédito sobre otro comprobante de ajuste.'];
        }

        if (self::notaCreditoExistenteParaFactura($ventaFacturaId) !== null) {
            return ['ok' => false, 'error' => 'Ya existe una nota de crédito para esta factura.'];
        }

        $ventaOrigen = $emision->venta;
        if ((float) $ventaOrigen->total < 0.01) {
            return ['ok' => false, 'error' => 'El comprobante no es una factura con importe positivo.'];
        }

        $tipoFactura = $ventaOrigen->tipotransacciones;
        if ($tipoFactura && $tipoFactura->signo !== 'S') {
            return ['ok' => false, 'error' => 'El comprobante origen no es una factura de venta.'];
        }

        $cfg = $this->pvService->resolverConfiguracionPv($request)
            ?? $emision->configuracionPuntoventa;

        if (! $cfg) {
            return ['ok' => false, 'error' => 'No hay configuración de punto de venta estacionamiento para esta terminal.'];
        }

        $identificadorPc = EstacionamientoIdentificadorPc::resolver($request);
        $empresaId = (int) $cfg->empresa_id;

        try {
            if (config('estacionamiento.jornada_obligatoria', true)) {
                $this->jornadaService->exigirJornadaAbierta($empresaId);
            }
            $this->turnoOperativoService->exigirTurnoHabilitadoSiConfigurado($identificadorPc, $empresaId);
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $tipoNcId = self::resolverTipotransaccionNotaCreditoId($cfg);
        $tipoNc = Tipotransaccion::query()->find($tipoNcId);
        if (! $tipoNc) {
            return ['ok' => false, 'error' => 'Tipo de transacción de nota de crédito inexistente.'];
        }
        if ($tipoNc->signo === 'S') {
            return ['ok' => false, 'error' => 'El tipo de transacción configurado para nota de crédito debe tener signo Resta.'];
        }

        $cuenta = $this->resolverCuentaParaNotaCredito($ventaOrigen, $cfg);

        try {
            $payload = $this->armarPayloadNotaCredito($ventaOrigen, $tipoNcId, $cfg, $leyendaUsuario);
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        $mediosPago = $this->armarMediosCobranzaDevolucion($ventaOrigen);

        try {
            $resultadoTx = DB::transaction(function () use (
                $ventaFacturaId,
                $ventaOrigen,
                $cuenta,
                $cfg,
                $payload,
                $mediosPago,
                $emision,
                $identificadorPc,
            ) {
                $resultado = $this->facturacionService->emitirComprobante($payload, $cuenta);

                if (! empty($resultado['error'])) {
                    throw new InvalidArgumentException((string) ($resultado['mensaje'] ?? $resultado['error']));
                }

                $ventaNc = $this->resolverVentaEmitida($ventaOrigen, $resultado);

                $sinCobranza = ! empty($resultado['sin_cobranza']);
                if (! $sinCobranza && $mediosPago !== []) {
                    $this->cobranzaService->registrarCobranzaPos(
                        $ventaNc->fresh(),
                        $mediosPago,
                        $cfg,
                        esDevolucion: true,
                    );
                }

                if (! empty($resultado['cae_pendiente']) && is_array($resultado['cae_pendiente'])) {
                    $this->facturacionService->completarSolicitudCaePendiente($resultado['cae_pendiente']);
                }

                $turno = $this->turnoOperativoService->turnoHabilitadoEnPc($identificadorPc);

                VentaEstacionamientoEmision::updateOrCreate(
                    ['venta_id' => $ventaNc->id],
                    [
                        'ticket_estacionamiento_id' => $emision->ticket_estacionamiento_id,
                        'identificador_pc' => $identificadorPc,
                        'configuracion_puntoventa_estacionamiento_id' => $cfg->id,
                        'jornada_estacionamiento_id' => $payload['jornada_estacionamiento_id'] ?? $emision->jornada_estacionamiento_id,
                        'turno_operativo_estacionamiento_id' => $turno?->id ?? $emision->turno_operativo_estacionamiento_id,
                        'venta_factura_origen_id' => $ventaFacturaId,
                    ],
                );

                $facturaTxt = trim((string) ($resultado['factura'] ?? $ventaNc->codigo));

                return [
                    'ok' => true,
                    'venta_id' => $ventaNc->id,
                    'factura' => $facturaTxt,
                    'mensaje' => 'Nota de crédito '.$facturaTxt.' generada correctamente.',
                    'anita_pendiente' => (! empty($resultado['anita_pendiente']) && is_array($resultado['anita_pendiente']))
                        ? $resultado['anita_pendiente']
                        : null,
                ];
            });

            $resultadoTx = $this->facturacionService->completarAnitaPendienteTrasEmision($resultadoTx);

            return $this->respuestaClienteNotaCredito(
                $this->aplicarImpresionTicketTrasNotaCredito($resultadoTx, $cfg, $cuenta)
            );
        } catch (Throwable $e) {
            $claseError = ArcaWsfeEmisionResiliencia::clasificarError($e->getMessage());
            Log::error('estacionamiento.nota_credito.fallo', [
                'venta_factura_id' => $ventaFacturaId,
                'clase_error' => $claseError,
                'msg' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'clase_error' => $claseError,
            ];
        }
    }

    public static function notaCreditoExistenteParaFactura(int $ventaFacturaId): ?int
    {
        $id = VentaEstacionamientoEmision::query()
            ->where('venta_factura_origen_id', $ventaFacturaId)
            ->value('venta_id');

        return $id !== null ? (int) $id : null;
    }

    public static function resolverTipotransaccionNotaCreditoId(ConfiguracionPuntoventaEstacionamiento $cfg): int
    {
        $id = (int) ($cfg->tipotransaccion_nota_credito_id ?? 0);
        if ($id <= 0) {
            $id = (int) config('estacionamiento.tipotransaccion_nota_credito_id', 0);
        }
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Configure el tipo de transacción de nota de crédito en Caja → Configuración punto de venta estacionamiento'
                .' o defina ESTACIONAMIENTO_TIPO_TRANSACCION_NOTA_CREDITO_ID.'
            );
        }

        return $id;
    }

    private function resolverCuentaParaNotaCredito(Venta $ventaOrigen, ConfiguracionPuntoventaEstacionamiento $cfg): CuentaEstacionamiento
    {
        $cuenta = CuentaEstacionamiento::query()
            ->where('venta_id', $ventaOrigen->id)
            ->first();

        if ($cuenta instanceof CuentaEstacionamiento) {
            return $cuenta;
        }

        return new CuentaEstacionamiento([
            'empresa_id' => (int) ($ventaOrigen->empresa_id ?: $cfg->empresa_id),
            'cliente_id' => (int) ($ventaOrigen->cliente_id ?? 0),
            'estado' => CuentaEstacionamiento::ESTADO_FACTURADA,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function armarPayloadNotaCredito(
        Venta $ventaOrigen,
        int $tipoNcId,
        ConfiguracionPuntoventaEstacionamiento $cfg,
        string $leyendaUsuario = '',
    ): array {
        $ventaOrigen->loadMissing(['venta_emisiones']);

        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descripciones = [];
        $impuestoIds = [];
        $incluyeImpuestos = [];

        foreach ($ventaOrigen->venta_emisiones->sortBy('numeroitem') as $em) {
            $cantidad = (float) ($em->cantidad ?? 0);
            $precio = (float) ($em->precio ?? 0);
            if ($cantidad <= 0 && abs($precio) < 0.00001) {
                continue;
            }

            $detalle = trim((string) ($em->detalle ?? ''));
            if ($detalle === '') {
                $detalle = 'Ítem estacionamiento';
            }

            // Estacionamiento emite con articulo_id=0 y detalle [EST-ITEM:n] (sin stock).
            $articuloIds[] = (int) ($em->articulo_id ?? 0);
            $cantidades[] = $cantidad;
            $precios[] = $precio;
            $descripciones[] = $detalle;
            $impuestoId = (int) ($em->impuesto_id ?? 0);
            $impuestoIds[] = $impuestoId > 0
                ? $impuestoId
                : EstacionamientoFacturaPayloadSupport::impuestoGravadoId();
            $incl = (string) ($em->incluyeimpuesto ?? '1');
            $incluyeImpuestos[] = in_array($incl, ['S', '1', 'Y'], true) ? '1' : 'N';
        }

        if ($articuloIds === []) {
            throw new InvalidArgumentException('La factura no tiene ítems para revertir.');
        }

        $fechaHoy = now()->format('Y-m-d');
        $leyendaManual = trim($leyendaUsuario);
        $referenciaCompro = (string) ($ventaOrigen->codigo ?? $ventaOrigen->id);
        if ($leyendaManual !== '') {
            $leyendaNc = $leyendaManual.' (NC por comprobante '.$referenciaCompro.')';
        } else {
            $leyendaNc = 'NC por comprobante '.$referenciaCompro;
            $leyendaOrigen = trim((string) ($ventaOrigen->leyenda ?? ''));
            if ($leyendaOrigen !== '') {
                $leyendaNc .= ' — '.$leyendaOrigen;
            }
        }
        if (mb_strlen($leyendaNc) > 255) {
            $leyendaNc = mb_substr($leyendaNc, 0, 255);
        }

        $payload = [
            'venta_id' => (int) $ventaOrigen->id,
            'tipotransaccion_id' => $tipoNcId,
            'puntoventa_id' => (int) $ventaOrigen->puntoventa_id,
            'fechafactura' => $fechaHoy,
            'leyendafactura' => $leyendaNc,
            'actividad_arca_id' => (int) ($ventaOrigen->actividad_arca_id ?? Actividad_Arca::query()->orderBy('id')->value('id') ?? 1),
            'cliente_id' => (int) $ventaOrigen->cliente_id,
            'moneda_id' => (int) $ventaOrigen->moneda_id,
            'listaprecio_id' => (int) config('estacionamiento.listaprecio_id', 1),
            'descuentolinea' => 0.,
            'descuentopie' => (float) ($ventaOrigen->descuento ?? 0),
            'descuentoimportepie' => 0.,
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $precios,
            'descripcionarticulos' => $descripciones,
            'impuesto_ids' => $impuestoIds,
            'incluyeimpuestos' => $incluyeImpuestos,
            'omitir_stkmov_anita_por_item' => array_fill(0, count($articuloIds), true),
        ];

        if (trim((string) ($ventaOrigen->nombre ?? '')) !== '' || trim((string) ($ventaOrigen->numerodocumento ?? '')) !== '') {
            $payload['venta_receptor'] = [
                'nombre' => $ventaOrigen->nombre,
                'numerodocumento' => $ventaOrigen->numerodocumento,
                'domicilio' => $ventaOrigen->domicilio,
            ];
            $payload['arca_receptor'] = array_filter([
                'nombre' => $ventaOrigen->nombre,
                'numerodocumento' => $ventaOrigen->numerodocumento,
                'domicilio' => $ventaOrigen->domicilio,
            ], fn ($v) => $v !== null && $v !== '');
        }

        $empresaId = (int) ($ventaOrigen->empresa_id ?: $cfg->empresa_id);

        return $this->jornadaService->aplicarFechasAlPayload($payload, $empresaId);
    }

    /**
     * @return list<array{cuentacaja_id:int,moneda_id:int,monto:float,cotizacion?:float,observacion?:string}>
     */
    private function armarMediosCobranzaDevolucion(Venta $ventaOrigen): array
    {
        $cobranzas = EstacionamientoVentaDetalleSupport::cobranzasDeVenta($ventaOrigen);
        if ($cobranzas->isEmpty()) {
            return [];
        }

        $movimientos = Caja_Movimiento::query()
            ->whereIn('cobranza_id', $cobranzas->pluck('id'))
            ->with(['caja_movimiento_cuentacajas'])
            ->get();

        $lineas = [];
        foreach ($movimientos as $mov) {
            foreach ($mov->caja_movimiento_cuentacajas as $cc) {
                $cuentacajaId = (int) ($cc->cuentacaja_id ?? 0);
                $monedaId = (int) ($cc->moneda_id ?? 1);
                $monto = (float) ($cc->monto ?? 0);
                if ($cuentacajaId <= 0 || $monto <= 0.) {
                    continue;
                }
                $lineas[] = [
                    'cuentacaja_id' => $cuentacajaId,
                    'moneda_id' => $monedaId > 0 ? $monedaId : 1,
                    'monto' => $monto,
                    'cotizacion' => (float) ($cc->cotizacion ?? 1.) ?: 1.,
                    'observacion' => 'Devolución NC estacionamiento',
                ];
            }
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function resolverVentaEmitida(Venta $ventaOrigen, array $resultado): Venta
    {
        $ventaId = (int) ($resultado['venta_id'] ?? 0);
        if ($ventaId > 0) {
            $venta = Venta::query()->find($ventaId);
            if ($venta) {
                return $venta;
            }
        }

        $facturaTxt = trim((string) ($resultado['factura'] ?? ''));
        if ($facturaTxt === '') {
            throw new RuntimeException('No se pudo recuperar la nota de crédito generada.');
        }

        if (preg_match('/^\S+\s+\S\s+(\d+)-(\d+)$/u', $facturaTxt, $m)) {
            $numero = (int) $m[2];
            $venta = Venta::query()
                ->where('puntoventa_id', $ventaOrigen->puntoventa_id)
                ->where('numerocomprobante', $numero)
                ->orderByDesc('id')
                ->first();
            if ($venta) {
                return $venta;
            }
        }

        throw new RuntimeException('No se pudo recuperar la venta interna de la nota de crédito '.$facturaTxt.'.');
    }

    /**
     * Respuesta breve al navegador (sin duplicar avisos técnicos).
     *
     * @param  array<string, mixed>  $resultado
     * @return array{ok:bool,venta_id?:int,factura?:string,mensaje?:string,warn?:string}
     */
    private function respuestaClienteNotaCredito(array $resultado): array
    {
        $factura = trim((string) ($resultado['factura'] ?? ''));
        $mensaje = $factura !== ''
            ? 'Nota de crédito '.$factura.' generada.'
            : trim((string) ($resultado['mensaje'] ?? 'Nota de crédito generada.'));

        $respuesta = [
            'ok' => ! empty($resultado['ok']),
            'venta_id' => isset($resultado['venta_id']) ? (int) $resultado['venta_id'] : null,
            'factura' => $factura !== '' ? $factura : null,
            'mensaje' => $mensaje,
        ];

        $warn = trim((string) ($resultado['warn'] ?? ''));
        if ($warn !== '' && $warn !== $mensaje) {
            $respuesta['warn'] = mb_strlen($warn) > 180 ? mb_substr($warn, 0, 177).'…' : $warn;
        }

        return array_filter($respuesta, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array{ok:bool,venta_id?:int,factura?:string,mensaje?:string,warn?:string}  $resultado
     * @return array{ok:bool,venta_id?:int,factura?:string,mensaje?:string,warn?:string,impresion_ticket?:string,impresion_ticket_mensaje?:string}
     */
    private function aplicarImpresionTicketTrasNotaCredito(
        array $resultado,
        ConfiguracionPuntoventaEstacionamiento $cfg,
        CuentaEstacionamiento $cuenta,
    ): array {
        $ventaId = (int) ($resultado['venta_id'] ?? 0);
        if ($ventaId <= 0) {
            return $resultado;
        }

        // Encolar impresión post-respuesta (mismo criterio que factura POS) para no bloquear la NC.
        $imp = $this->facturaTicketService->imprimirTrasEmisionEncolado($ventaId, $cfg, $cuenta);
        if (! empty($imp['omitida']) || ! empty($imp['encolada']) || ! empty($imp['ok'])) {
            return $resultado;
        }

        $resultado['impresion_ticket'] = 'error';
        $resultado['impresion_ticket_mensaje'] = trim((string) ($imp['mensaje'] ?? ''));
        $resultado['warn'] = 'NC generada; no se pudo encolar el ticket térmico.';

        return $resultado;
    }
}
