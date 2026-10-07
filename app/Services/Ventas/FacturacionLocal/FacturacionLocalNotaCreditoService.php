<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\Models\Ventas\FacturacionLocalEmision;
use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionService;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPosContextoSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalReceptorSupport;
use App\Support\Ventas\FacturacionLocal\MotivoDevolucionSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalPrecioIvaSupport;
use App\Support\Ventas\TipotransaccionOperacionStockSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Emite nota de crédito total desde Facturas Local (admin post-emisión).
 */
final class FacturacionLocalNotaCreditoService
{
    public function __construct(
        private readonly FacturacionService $facturacionService,
        private readonly FacturacionLocalCobranzaService $cobranzaService,
        private readonly FacturacionLocalTurnoService $turnoService,
    ) {
    }

    /**
     * @param  array{
     *   medios_forzados?:list<array{cuentacaja_id:int,moneda_id?:int,monto:float}>,
     *   local_venta_id?:int,
     *   venta_emision_ids?:list<int>,
     *   cantidades_por_emision?:array<int,float>
     * }|null  $opciones  Solo Facturación Local / marketplace Ferli (no gastronomía).
     *   venta_emision_ids limita la NC a esos ítems (cambio: solo lo devuelto).
     * @return array{ok:bool,venta_id?:int,factura?:string,pdf_urls?:list<string>,mensaje?:string,warn?:string,error?:string}
     */
    public function generarDesdeFactura(
        int $ventaFacturaId,
        ?Request $request = null,
        string $leyendaUsuario = '',
        ?array $opciones = null,
    ): array {
        unset($request);

        $opciones = $opciones ?? [];
        $localOverrideId = (int) ($opciones['local_venta_id'] ?? 0);

        $emision = FacturacionLocalEmision::query()
            ->where('venta_id', $ventaFacturaId)
            ->with([
                'venta.clientes',
                'venta.puntoventas',
                'venta.venta_emisiones.articulos',
                'venta.tipotransacciones',
                'localVenta.puntoventa',
                'localVenta.cuentacajas',
            ])
            ->first();

        // Ventas marketplace (TN) sin fila de emisión Local: puente mínimo para NC con medios forzados.
        if ((! $emision || ! $emision->venta) && $localOverrideId > 0 && isset($opciones['medios_forzados'])) {
            $ventaOrig = Venta::query()
                ->with(['clientes', 'puntoventas', 'venta_emisiones.articulos', 'tipotransacciones'])
                ->find($ventaFacturaId);
            $localOv = LocalVenta::query()->with(['puntoventa', 'cuentacajas'])->find($localOverrideId);
            if ($ventaOrig && $localOv) {
                $emision = FacturacionLocalEmision::query()->create([
                    'local_venta_id' => (int) $localOv->id,
                    'turno_operativo_local_id' => null,
                    'venta_id' => (int) $ventaOrig->id,
                    'venta_nc_id' => null,
                    'vale_cliente_local_id' => null,
                    'es_ticket_regalo' => false,
                    'payload_resumen_json' => [
                        'origen' => 'cambio_devolucion_marketplace',
                        'puente_nc' => true,
                    ],
                ]);
                $emision->setRelation('venta', $ventaOrig);
                $emision->setRelation('localVenta', $localOv);
            }
        }

        if (! $emision || ! $emision->venta) {
            return ['ok' => false, 'error' => 'La venta no corresponde a una emisión de Facturación Local.'];
        }

        if ((int) ($emision->venta_nc_id ?? 0) > 0
            || self::notaCreditoExistenteParaFactura($ventaFacturaId) !== null) {
            return ['ok' => false, 'error' => 'Ya existe una nota de crédito para esta factura.'];
        }

        if (! empty($emision->es_ticket_regalo)) {
            return ['ok' => false, 'error' => 'No se puede generar NC sobre un ticket regalo.'];
        }

        $ventaOrigen = $emision->venta;
        if ((float) $ventaOrigen->total < 0.01) {
            return ['ok' => false, 'error' => 'El comprobante no es una factura con importe positivo.'];
        }

        $tipoFactura = $ventaOrigen->tipotransacciones;
        if ($tipoFactura && $tipoFactura->signo !== 'S') {
            return ['ok' => false, 'error' => 'El comprobante origen no es una factura de venta.'];
        }

        try {
            $motivoDevolucion = MotivoDevolucionSupport::exigir((int) ($opciones['motivo_devolucion_id'] ?? 0));
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        $omitirReingresoStock = array_key_exists('omitir_reingreso_stock', $opciones)
            ? (bool) $opciones['omitir_reingreso_stock']
            : ! $motivoDevolucion->vuelve_stock;
        $registrarHistorial = ! array_key_exists('registrar_historial', $opciones)
            || (bool) $opciones['registrar_historial'];

        /** @var LocalVenta|null $local */
        $local = $emision->localVenta;
        if (! $local) {
            return ['ok' => false, 'error' => 'No se encontró el local de la emisión.'];
        }

        $turno = $this->turnoService->turnoAbierto((int) $local->id);
        if ($turno === null) {
            return ['ok' => false, 'error' => 'Debe haber un turno abierto en el local para generar la nota de crédito.'];
        }

        $tipoNcId = (int) $local->tipoNcId();
        $tipoNc = Tipotransaccion::query()->find($tipoNcId);
        if (! $tipoNc) {
            return ['ok' => false, 'error' => 'Tipo de transacción de nota de crédito inexistente. Configure el local.'];
        }
        if ($tipoNc->signo === 'S') {
            return ['ok' => false, 'error' => 'El tipo de transacción de NC del local debe tener signo Resta.'];
        }

        $idsNc = self::idsEmisionOpcion($opciones['venta_emision_ids'] ?? null);
        $cantidadesNc = self::cantidadesPorEmisionOpcion($opciones['cantidades_por_emision'] ?? null);

        try {
            $payload = $this->armarPayloadNotaCredito(
                $ventaOrigen,
                $local,
                $tipoNcId,
                $leyendaUsuario,
                $idsNc,
                $cantidadesNc,
            );
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        if ($omitirReingresoStock) {
            $payload['opciones_emision']['omitir_movimiento_stock'] = true;
            $nItems = count($payload['articulo_ids'] ?? []);
            $payload['omitir_stock_por_item'] = $nItems > 0 ? array_fill(0, $nItems, true) : [];
        }

        $mediosForzados = $opciones['medios_forzados'] ?? null;
        $mediosPago = is_array($mediosForzados) && $mediosForzados !== []
            ? $mediosForzados
            : $this->cobranzaService->mediosDesdeVentaFac($ventaFacturaId);
        if (is_array($mediosForzados) && $mediosForzados !== []) {
            $payload['opciones_emision']['asiento_medios_pago'] = $mediosForzados;
        }

        try {
            $resultadoTx = DB::transaction(function () use (
                $ventaFacturaId,
                $ventaOrigen,
                $local,
                $payload,
                $mediosPago,
                $emision,
                $turno,
                $motivoDevolucion,
                $registrarHistorial,
                $idsNc,
            ) {
                $resultado = $this->facturacionService->generaComprobanteGeneral($payload);

                if (! is_array($resultado) || ! empty($resultado['error'])) {
                    throw new InvalidArgumentException(
                        trim((string) ($resultado['mensaje'] ?? $resultado['error'] ?? 'Error al emitir la NC'))
                    );
                }

                $ventaNc = $this->resolverVentaEmitida($ventaOrigen, $resultado);

                $lineasStock = $this->lineasStockDesdeEmisiones($ventaOrigen, $idsNc);
                if ($registrarHistorial) {
                    app(DevolucionHistorialService::class)->registrarNotaCredito(
                        $local,
                        $ventaNc,
                        $ventaOrigen,
                        $motivoDevolucion,
                        $lineasStock,
                    );
                }

                if ($mediosPago !== []) {
                    $this->cobranzaService->registrar($ventaNc->fresh(), $local, $mediosPago, true);
                }

                if (! empty($resultado['cae_pendiente']) && is_array($resultado['cae_pendiente'])) {
                    $this->facturacionService->completarSolicitudCaePendiente($resultado['cae_pendiente']);
                }

                $emision->venta_nc_id = (int) $ventaNc->id;
                if ((int) ($emision->turno_operativo_local_id ?? 0) <= 0) {
                    $emision->turno_operativo_local_id = $turno->id;
                }
                $emision->save();

                // Importe real de la NC (puede ser parcial). El cierre atribuye la NC al turno
                // por ventana de emisión (created_at), no por el turno de la factura origen.
                $this->turnoService->sumarFacturacion($turno, -1 * abs((float) $ventaNc->total));

                $facturaTxt = trim((string) ($resultado['factura'] ?? $ventaNc->codigo));
                $pdfUrl = url('ventas/listaunafactura/'.$ventaNc->id);

                return [
                    'ok' => true,
                    'venta_id' => (int) $ventaNc->id,
                    'factura' => $facturaTxt,
                    'pdf_urls' => [$pdfUrl],
                    'mensaje' => 'Nota de crédito '.$facturaTxt.' generada correctamente.',
                ];
            });

            return $this->respuestaClienteNotaCredito($resultadoTx);
        } catch (Throwable $e) {
            $claseError = ArcaWsfeEmisionResiliencia::clasificarError($e->getMessage());
            Log::error('facturacion_local.nota_credito.fallo', [
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
        $id = FacturacionLocalEmision::query()
            ->where('venta_id', $ventaFacturaId)
            ->whereNotNull('venta_nc_id')
            ->value('venta_nc_id');

        return $id !== null && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * @param  list<int>|null  $idsFiltro
     * @param  array<int, float>|null  $cantidadesPorEmision
     * @return array<string, mixed>
     */
    private function armarPayloadNotaCredito(
        Venta $ventaOrigen,
        LocalVenta $local,
        int $tipoNcId,
        string $leyendaUsuario = '',
        ?array $idsFiltro = null,
        ?array $cantidadesPorEmision = null,
    ): array {
        $ventaOrigen->loadMissing(['venta_emisiones.articulos']);
        $esParcial = $idsFiltro !== null;

        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descuentos = [];
        $descripciones = [];
        $combinacionIds = [];
        $talleIds = [];
        $colorIds = [];
        $impuestoIds = [];
        $incluyeImpuestos = [];
        $idsLinea = [];

        $brutoTodos = 0.;
        $brutoSeleccionado = 0.;
        $idsVistos = [];
        foreach ($ventaOrigen->venta_emisiones->sortBy('numeroitem') as $em) {
            $cantidadFacturada = (float) ($em->cantidad ?? 0);
            $precio = (float) ($em->precio ?? 0);
            $articuloId = (int) ($em->articulo_id ?? 0);
            $emisionId = (int) $em->id;
            if ($articuloId <= 0 || ($cantidadFacturada <= 0 && abs($precio) < 0.00001)) {
                continue;
            }
            $brutoLinea = round($cantidadFacturada * $precio, 2);
            $brutoTodos = round($brutoTodos + $brutoLinea, 2);
            if ($esParcial && ! in_array($emisionId, $idsFiltro, true)) {
                continue;
            }

            $cantidad = $cantidadFacturada;
            if ($cantidadesPorEmision !== null && array_key_exists($emisionId, $cantidadesPorEmision)) {
                $cantidad = (float) $cantidadesPorEmision[$emisionId];
                if ($cantidad <= 0) {
                    throw new InvalidArgumentException('La cantidad a devolver del ítem '.$emisionId.' debe ser mayor a cero.');
                }
                if ($cantidad - $cantidadFacturada > 0.0001) {
                    throw new InvalidArgumentException('La cantidad a devolver supera la facturada en el ítem '.$emisionId.'.');
                }
            }
            $idsVistos[] = $emisionId;

            $detalle = trim((string) ($em->detalle ?? ''));
            if ($detalle === '') {
                $detalle = trim((string) ($em->articulos?->descripcion ?? 'Ítem'));
            }

            $articuloIds[] = $articuloId;
            $cantidades[] = $cantidad;
            $precios[] = $precio;
            $descuentos[] = (float) ($em->descuento ?? 0);
            $descripciones[] = $detalle;
            $combinacionIds[] = (int) ($em->combinacion_id ?? 0);
            $talleIds[] = (int) ($em->talle_id ?? 0);
            $colorIds[] = (int) ($em->color_id ?? 0);
            $idsLinea[] = $emisionId;
            $impuestoId = (int) ($em->impuesto_id ?? 0);
            $impuestoIds[] = $impuestoId > 0
                ? $impuestoId
                : (int) ($em->articulos?->impuesto_id ?: 3);
            $incl = (string) ($em->incluyeimpuesto ?? '1');
            $incluyeImpuestos[] = in_array($incl, ['S', '1', 'Y'], true) ? '1' : 'N';
            $brutoSeleccionado = round($brutoSeleccionado + round($cantidad * $precio, 2), 2);
        }

        if ($articuloIds === []) {
            throw new InvalidArgumentException('La factura no tiene ítems para revertir.');
        }
        if ($esParcial) {
            $faltan = array_values(array_diff($idsFiltro, $idsVistos));
            if ($faltan !== []) {
                throw new InvalidArgumentException('Hay líneas a devolver que no están en la factura original.');
            }
        }

        $descuentoPie = (float) ($ventaOrigen->descuento ?? 0);
        if ($esParcial && $descuentoPie > 0.004 && $brutoTodos > 0.004) {
            $descuentoPie = round($descuentoPie * ($brutoSeleccionado / $brutoTodos), 2);
        }

        $letra = $this->resolverLetraComprobante($ventaOrigen);
        $listaId = (int) ($local->listaprecio_id ?: 0);
        $prepIva = FacturacionLocalPrecioIvaSupport::prepararParaEmision(
            $precios,
            $articuloIds,
            $listaId,
            $letra,
            false,
        );

        $fechaHoy = Carbon::now()->format('Y-m-d');
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

        $empresaId = (int) ($local->empresa_id ?: $ventaOrigen->empresa_id ?: 0);
        $puntoventaId = (int) ($ventaOrigen->puntoventa_id
            ?: $local->puntoventaDefaultId()
            ?: $local->puntoventa_id
            ?: 0);

        $payload = [
            'empresa_id' => $empresaId,
            'puntoventa_id' => $puntoventaId,
            'tipotransaccion_id' => $tipoNcId,
            'cliente_id' => (int) ($ventaOrigen->cliente_id ?: 0),
            'fechafactura' => $fechaHoy,
            'fecha' => $fechaHoy,
            'deposito_id' => (int) ($ventaOrigen->deposito_id ?: $local->deposito_id),
            'listaprecio_id' => $listaId,
            'moneda_id' => (int) ($ventaOrigen->moneda_id ?: config('facturacion_local.moneda_id', 1)),
            'cotizacion' => (float) ($ventaOrigen->cotizacion ?: 1.),
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $prepIva['precios'],
            'incluyeimpuestos' => $prepIva['incluyeimpuestos'],
            'impuesto_ids' => $impuestoIds,
            'descuentolinea' => 0,
            'descripciones' => $descripciones,
            'descripcionarticulos' => $descripciones,
            'combinacion_ids' => $combinacionIds,
            'talle_ids' => $talleIds,
            'color_ids' => $colorIds,
            // Mismo orden que los renglones. Ferli copia combinación/talle desde la FAC
            // por este id; sin él, una NC parcial toma el primer ítem de la factura.
            'ids' => $idsLinea,
            'descuentopie' => $descuentoPie,
            'descuentoimportepie' => 0.,
            'vendedor_id' => Auth::id(),
            'leyendafactura' => $leyendaNc,
            'venta_id' => (int) $ventaOrigen->id,
            'comprobanteasociado_id' => (int) $ventaOrigen->id,
            'venta_id_asociada' => (int) $ventaOrigen->id,
            'opciones_emision' => [
                'omitir_movimiento_stock' => false,
                'forzar_operacion_stock' => TipotransaccionOperacionStockSupport::ENTRADA,
                'permitir_caea' => false,
                'origen_facturacion_local' => true,
                'omitir_cuenta_corriente' => true,
                'omitir_solicitud_arca_cae' => true,
                // Si no hay asiento FAC para invertir, imputar medios de la cobranza (no deudores).
                'asiento_medios_pago' => $this->cobranzaService->mediosDesdeVentaFac((int) $ventaOrigen->id),
            ],
            '_descuentos_linea_item' => $descuentos,
        ];

        // Si el PV tiene webservice, numeración + CAE van por ARCA.
        $pv = $puntoventaId > 0 ? Puntoventa::query()->find($puntoventaId) : null;
        if (FacturacionLocalPosContextoSupport::pvUsaWebservice($pv)
            && strtoupper(trim((string) ($pv->modofacturacion ?? ''))) === 'M'
        ) {
            $payload['forzar_modofacturacion'] = 'C';
        }

        $docVenta = preg_replace('/\D/', '', (string) ($ventaOrigen->nroinscripcion ?? '')) ?? '';
        if (trim((string) ($ventaOrigen->nombre ?? '')) !== '' || ($docVenta !== '' && (int) $docVenta > 0)) {
            $docArca = FacturacionLocalReceptorSupport::documentoArcaDesdeNumero($docVenta);
            $payload['venta_receptor'] = [
                'nombre' => $ventaOrigen->nombre,
                'numerodocumento' => $docArca['numerodocumento'],
                'domicilio' => $ventaOrigen->domicilio,
                'provincia_id' => (int) ($ventaOrigen->provincia_id ?: 0) ?: null,
                'localidad_id' => (int) ($ventaOrigen->localidad_id ?: 0) ?: null,
            ];
            $payload['arca_receptor'] = array_filter([
                'tipodoc' => $docArca['tipodoc'],
                'nombre' => $ventaOrigen->nombre,
                'numerodocumento' => $docArca['numerodocumento'],
                'domicilio' => $ventaOrigen->domicilio,
            ], fn ($v) => $v !== null && $v !== '');
        }
        if ((int) ($ventaOrigen->provincia_id ?: 0) > 0) {
            $payload['provincia_id'] = (int) $ventaOrigen->provincia_id;
        }

        return $payload;
    }

    private function resolverLetraComprobante(Venta $venta): string
    {
        $codigo = trim((string) ($venta->codigo ?? ''));
        if (preg_match('/\b([A-Z])\b/', $codigo, $m)) {
            return strtoupper($m[1]);
        }
        $letra = strtoupper(substr($codigo, -1) ?: 'B');

        return in_array($letra, ['A', 'B', 'C', 'E', 'M'], true) ? $letra : 'B';
    }

    /**
     * @param  list<int>|null  $idsFiltro
     * @return list<array<string,mixed>>
     */
    private function lineasStockDesdeEmisiones(Venta $ventaOrigen, ?array $idsFiltro = null): array
    {
        $lineas = [];
        foreach ($ventaOrigen->venta_emisiones->sortBy('numeroitem') as $em) {
            $articuloId = (int) ($em->articulo_id ?? 0);
            if ($articuloId <= 0) {
                continue;
            }
            if ($idsFiltro !== null && ! in_array((int) $em->id, $idsFiltro, true)) {
                continue;
            }
            $lineas[] = [
                'articulo_id' => $articuloId,
                'cantidad' => (float) ($em->cantidad ?? 0),
                'precio' => (float) ($em->precio ?? 0),
                'talle_id' => (int) ($em->talle_id ?? 0),
                'combinacion_id' => (int) ($em->combinacion_id ?? 0),
            ];
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
     * @param  array<string, mixed>  $resultado
     * @return array{ok:bool,venta_id?:int,factura?:string,pdf_urls?:list<string>,mensaje?:string,warn?:string}
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
            'pdf_urls' => $resultado['pdf_urls'] ?? null,
            'mensaje' => $mensaje,
        ];

        $warn = trim((string) ($resultado['warn'] ?? ''));
        if ($warn !== '' && $warn !== $mensaje) {
            $respuesta['warn'] = mb_strlen($warn) > 180 ? mb_substr($warn, 0, 177).'…' : $warn;
        }

        return array_filter($respuesta, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @return list<int>|null
     */
    private static function idsEmisionOpcion(mixed $ids): ?array
    {
        if (! is_array($ids)) {
            return null;
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
    }

    /**
     * @return array<int, float>|null
     */
    private static function cantidadesPorEmisionOpcion(mixed $cantidades): ?array
    {
        if (! is_array($cantidades)) {
            return null;
        }

        $out = [];
        foreach ($cantidades as $id => $cantidad) {
            $emisionId = (int) $id;
            if ($emisionId > 0) {
                $out[$emisionId] = (float) $cantidad;
            }
        }

        return $out;
    }
}
