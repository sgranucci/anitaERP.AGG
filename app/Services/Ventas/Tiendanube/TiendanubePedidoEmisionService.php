<?php

namespace App\Services\Ventas\Tiendanube;

use App\Models\Ventas\TiendanubePedido;
use App\Models\Ventas\TiendanubePedidoLinea;
use App\Models\Ventas\TiendanubePedidoVenta;
use App\Models\Ventas\Venta;
use App\Services\Caja\CobranzaService;
use App\Services\Ventas\FacturaMailEnvioService;
use App\Services\Ventas\FacturacionService;
use App\Support\Caja\CotizacionTesoreriaConsultaSupport;
use App\Support\Ventas\Tiendanube\TiendanubePedidoEstadoSupport;
use App\Support\Ventas\Tiendanube\TiendanubePedidoListoSupport;
use App\Support\Ventas\Tiendanube\TiendanubePedidoMaestrosSupport;
use App\Support\Ventas\Tiendanube\TiendanubePedidoReceptorSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Emite factura ERP desde un pedido Tiendanube stageado.
 */
final class TiendanubePedidoEmisionService
{
    private const MONEDA_PESOS_ID = 1;

    public function __construct(
        private readonly FacturacionService $facturacionService,
        private readonly CobranzaService $cobranzaService,
    ) {
    }

    /**
     * @param  array{
     *   puntoventa_id:int,
     *   deposito_id:int,
     *   cliente_id?:int|null,
     *   receptor?:array<string,mixed>,
     *   medios_pago:list<array{cuentacaja_id:int,moneda_id?:int,monto:float}>,
     *   listaprecio_id?:int|null,
     *   descuentoimportepie?:float,
     *   forzar_cf?:bool,
     *   lineas?:array<int,float>|null
     * }  $input
     * @return array{ok:bool,error?:string,errores?:list<string>,venta_id?:int,cae?:string,parcial?:bool}
     */
    public function emitir(TiendanubePedido $pedido, array $input): array
    {
        if ($pedido->estaFacturado()) {
            return ['ok' => false, 'error' => 'El pedido ya está facturado (venta #'.$pedido->venta_id.').'];
        }
        if (! $pedido->estaPagado()) {
            return ['ok' => false, 'error' => 'Solo se facturan pedidos pagados (payment_status=paid).'];
        }

        $pedido->loadMissing('lineas');
        $seleccion = $this->resolverSeleccion($pedido, $input);
        if ($seleccion['error'] !== null) {
            return ['ok' => false, 'error' => $seleccion['error']];
        }

        $errores = $this->validarPreflight($pedido, $input, $seleccion['lineas'], $seleccion['total']);
        if ($errores !== []) {
            $pedido->estado_erp = $pedido->tieneAlgoFacturado()
                ? TiendanubePedidoEstadoSupport::PARCIAL
                : TiendanubePedidoEstadoSupport::BLOQUEADO_FISCAL;
            $pedido->error_mensaje = $errores[0];
            $pedido->save();

            return ['ok' => false, 'errores' => $errores, 'error' => $errores[0]];
        }

        try {
            return DB::transaction(function () use ($pedido, $input, $seleccion) {
                $payload = $this->armarPayload($pedido, $input, $seleccion['lineas'], $seleccion['cubre_pendiente']);
                $resultado = $this->facturacionService->generaComprobanteGeneral($payload);
                if (! is_array($resultado) || ! empty($resultado['error'])) {
                    $msg = trim((string) ($resultado['mensaje'] ?? $resultado['error'] ?? 'Error al emitir factura'));
                    throw new InvalidArgumentException($msg);
                }

                $ventaId = (int) ($resultado['venta_id'] ?? 0);
                $venta = Venta::query()->find($ventaId);
                if (! $venta) {
                    throw new InvalidArgumentException('No se obtuvo la venta emitida.');
                }

                $this->registrarCobranza($venta, $input['medios_pago'] ?? []);
                $this->registrarCantidadesFacturadas($pedido, $seleccion['lineas'], $venta, $seleccion['total']);

                $completo = $pedido->cubiertoPorCompleto();
                $pedido->venta_id = $venta->id;
                $pedido->cliente_id = (int) ($venta->cliente_id ?: ($input['cliente_id'] ?? 0)) ?: $pedido->cliente_id;
                $pedido->estado_erp = $completo
                    ? TiendanubePedidoEstadoSupport::FACTURADO
                    : TiendanubePedidoEstadoSupport::PARCIAL;
                $pedido->error_mensaje = null;
                $pedido->facturado_at = now();
                $pedido->facturado_por_usuario_id = Auth::id();
                $pedido->save();

                $this->intentarPublicarInvoice($pedido, $venta, $resultado);
                $this->intentarEnviarMailCliente($pedido, $venta, $input);

                Log::info('tiendanube.emision.ok', [
                    'tiendanube_order_id' => $pedido->tiendanube_order_id,
                    'venta_id' => $venta->id,
                    'parcial' => ! $completo,
                    'cae' => $resultado['cae'] ?? $venta->cae ?? null,
                ]);

                return [
                    'ok' => true,
                    'venta_id' => $venta->id,
                    'cae' => (string) ($resultado['cae'] ?? $venta->cae ?? ''),
                    'parcial' => ! $completo,
                ];
            });
        } catch (Throwable $e) {
            Log::error('tiendanube.emision.error', [
                'tiendanube_order_id' => $pedido->tiendanube_order_id,
                'error' => $e->getMessage(),
            ]);
            $pedido->refresh();
            $pedido->load('lineas');
            $pedido->estado_erp = $pedido->tieneAlgoFacturado() && ! $pedido->cubiertoPorCompleto()
                ? TiendanubePedidoEstadoSupport::PARCIAL
                : TiendanubePedidoEstadoSupport::ERROR;
            $pedido->error_mensaje = mb_substr($e->getMessage(), 0, 1000);
            $pedido->save();

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sin clave `lineas` factura todo lo pendiente (masivo). Con clave, solo esas cantidades.
     *
     * @param  array<string,mixed>  $input
     * @return array{
     *   lineas:list<array{linea:TiendanubePedidoLinea,cantidad:float}>,
     *   total:float,
     *   cubre_pendiente:bool,
     *   error:?string
     * }
     */
    private function resolverSeleccion(TiendanubePedido $pedido, array $input): array
    {
        $vacio = ['lineas' => [], 'total' => 0.0, 'cubre_pendiente' => false, 'error' => null];
        $manual = array_key_exists('lineas', $input);
        /** @var array<int,float> $mapa */
        $mapa = $manual && is_array($input['lineas']) ? $input['lineas'] : [];

        $elegidas = [];
        foreach ($pedido->lineas as $linea) {
            $pendiente = $linea->cantidadPendiente();
            if ($pendiente <= 0.0001) {
                continue;
            }
            if ($manual) {
                if (! array_key_exists((int) $linea->id, $mapa) && ! array_key_exists((string) $linea->id, $mapa)) {
                    continue;
                }
                $cant = (float) ($mapa[(int) $linea->id] ?? $mapa[(string) $linea->id] ?? 0);
            } else {
                $cant = $pendiente;
            }
            if ($cant <= 0.0001) {
                continue;
            }
            if ($cant - $pendiente > 0.0001) {
                $vacio['error'] = 'La cantidad de «'.($linea->nombre ?: $linea->sku).'» supera lo pendiente ('.$pendiente.').';

                return $vacio;
            }
            $elegidas[] = ['linea' => $linea, 'cantidad' => round($cant, 4)];
        }

        if ($elegidas === []) {
            $vacio['error'] = 'Elegí al menos un artículo con cantidad pendiente.';

            return $vacio;
        }

        $total = 0.0;
        $pendienteTotal = 0.0;
        foreach ($pedido->lineas as $linea) {
            $pendienteTotal += $linea->cantidadPendiente() * (float) $linea->price;
        }
        foreach ($elegidas as $item) {
            $total += $item['cantidad'] * (float) $item['linea']->price;
        }
        $total = round($total, 2);
        if ($total <= 0.0001) {
            $vacio['error'] = 'El total a facturar debe ser mayor a cero.';

            return $vacio;
        }

        return [
            'lineas' => $elegidas,
            'total' => $total,
            'cubre_pendiente' => abs($total - round($pendienteTotal, 2)) <= 0.05
                && count($elegidas) === $pedido->lineas->filter(
                    static fn (TiendanubePedidoLinea $linea): bool => $linea->cantidadPendiente() > 0.0001
                )->count(),
            'error' => null,
        ];
    }

    /**
     * @param  list<array{linea:TiendanubePedidoLinea,cantidad:float}>  $lineas
     */
    private function registrarCantidadesFacturadas(TiendanubePedido $pedido, array $lineas, Venta $venta, float $total): void
    {
        foreach ($lineas as $item) {
            $linea = $item['linea'];
            $linea->cantidad_facturada = round((float) $linea->cantidad_facturada + $item['cantidad'], 4);
            $linea->save();
        }

        TiendanubePedidoVenta::query()->create([
            'tiendanube_pedido_id' => $pedido->id,
            'venta_id' => $venta->id,
            'total' => $total,
        ]);
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  list<array{linea:TiendanubePedidoLinea,cantidad:float}>  $lineas
     * @return list<string>
     */
    private function validarPreflight(TiendanubePedido $pedido, array $input, array $lineas, float $totalFactura): array
    {
        $errores = [];
        $pvId = (int) ($input['puntoventa_id'] ?? 0);
        $depId = (int) ($input['deposito_id'] ?? 0);
        if ($pvId <= 0) {
            $errores[] = 'Seleccione punto de venta.';
        }
        if ($depId <= 0) {
            $errores[] = 'Seleccione depósito.';
        }

        $medios = $input['medios_pago'] ?? [];
        if ($medios === []) {
            $errores[] = 'Indique al menos un medio de pago.';
        }
        $sumaMedios = 0.;
        foreach ($medios as $m) {
            $sumaMedios += (float) ($m['monto'] ?? 0);
            if ((int) ($m['cuentacaja_id'] ?? 0) <= 0) {
                $errores[] = 'Cada medio debe tener cuenta de caja.';
            }
        }
        if (abs($sumaMedios - $totalFactura) > 0.05 && $medios !== []) {
            $errores[] = sprintf(
                'La suma de medios (%.2f) no coincide con el total a facturar (%.2f).',
                $sumaMedios,
                $totalFactura
            );
        }

        if ($lineas === []) {
            $errores[] = 'Elegí al menos un artículo con cantidad pendiente.';
        }
        foreach ($lineas as $item) {
            $linea = $item['linea'];
            if ($linea->tipo === 'descuento' && ! $linea->articulo_id) {
                continue;
            }
            if ($linea->tipo === 'envio' && ! $linea->articulo_id) {
                $errores[] = 'Configure TIENDANUBE_ARTICULO_ENVIO_SKU o asocie un artículo a la línea de envío.';
                continue;
            }
            if (! $linea->articulo_id) {
                $errores[] = 'SKU no encontrado en ERP: '.($linea->sku ?: $linea->nombre);
            }
        }

        $clienteId = (int) ($input['cliente_id'] ?? $pedido->cliente_id ?? 0);
        $receptorIn = is_array($input['receptor'] ?? null) ? $input['receptor'] : [];
        $forzarCf = ! empty($input['forzar_cf']);
        $letra = strtoupper(trim((string) ($input['letra'] ?? TiendanubePedidoReceptorSupport::LETRA_B)));
        if ($forzarCf) {
            $letra = TiendanubePedidoReceptorSupport::LETRA_B;
        }
        $doc = preg_replace('/\D+/', '', (string) ($receptorIn['numerodocumento'] ?? $receptorIn['nrodoc'] ?? $pedido->customer_doc ?? '')) ?: '';
        $limite = (float) config('facturacion_local.limite_resto', 400000);

        if ($letra === TiendanubePedidoReceptorSupport::LETRA_A && strlen($doc) < 11) {
            $errores[] = 'Factura A requiere CUIT del comprador (11 dígitos).';
        }

        if ($letra === TiendanubePedidoReceptorSupport::LETRA_B
            && $clienteId <= 1
            && $doc === ''
            && ! $forzarCf
            && $totalFactura > $limite) {
            $errores[] = 'Faltan datos fiscales del cliente (CUIT/DNI). Complete el receptor o asocie un cliente.';
        }

        return array_values(array_unique($errores));
    }

    /**
     * @param  array<string,mixed>  $input
     * @param  list<array{linea:TiendanubePedidoLinea,cantidad:float}>  $lineas
     * @return array<string,mixed>
     */
    private function armarPayload(TiendanubePedido $pedido, array $input, array $lineas, bool $cubrePendiente): array
    {
        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descuentos = [];
        $descripciones = [];
        $combinacionIds = [];
        $talleIds = [];
        $colorIds = [];
        $descuentoPieImporte = (float) ($input['descuentoimportepie'] ?? 0);

        foreach ($lineas as $item) {
            $linea = $item['linea'];
            $cant = (float) $item['cantidad'];
            if ($linea->tipo === 'descuento' && ! $linea->articulo_id) {
                $descuentoPieImporte += abs((float) $linea->price) * ($cant > 0 ? $cant : 1.);
                continue;
            }
            if (! $linea->articulo_id) {
                continue;
            }
            $precio = (float) $linea->price;
            if ($linea->tipo === 'descuento') {
                $cant = abs($cant) > 0 ? abs($cant) : 1.;
                $precio = -1 * abs($precio);
            }
            $articuloIds[] = (int) $linea->articulo_id;
            $cantidades[] = $cant;
            $precios[] = $precio;
            $descuentos[] = 0.;
            $descripciones[] = (string) ($linea->nombre ?? '');
            $combinacionIds[] = (int) ($linea->combinacion_id ?? 0);
            $talleIds[] = (int) ($linea->talle_id ?? 0);
            $colorIds[] = (int) ($linea->color_id ?? 0);
        }

        if ($articuloIds === []) {
            throw new InvalidArgumentException('No hay líneas facturables con artículo ERP.');
        }

        $letra = strtoupper(trim((string) ($input['letra'] ?? TiendanubePedidoReceptorSupport::LETRA_B)));
        if (! empty($input['forzar_cf'])) {
            $letra = TiendanubePedidoReceptorSupport::LETRA_B;
        }
        $receptorIn = is_array($input['receptor'] ?? null) ? $input['receptor'] : [];
        $fiscal = TiendanubePedidoReceptorSupport::armar($pedido, $receptorIn, $letra);

        $clienteId = $fiscal['cliente_id'];
        if ((int) ($input['cliente_id'] ?? 0) > 0 && $fiscal['letra'] === TiendanubePedidoReceptorSupport::LETRA_A) {
            // Permite forzar un cliente RI del maestro en Factura A
            $clienteId = (int) $input['cliente_id'];
        }

        $listaId = (int) ($input['listaprecio_id'] ?? 0);
        if ($listaId <= 0) {
            $listaId = TiendanubePedidoMaestrosSupport::listaprecioIdDefault();
        }

        $nroPedido = (string) ($pedido->order_number ?: $pedido->tiendanube_order_id);
        $marcaParcial = $cubrePendiente ? '' : ' (parcial)';
        $fecha = Carbon::now()->format('Y-m-d');
        $payload = [
            'empresa_id' => (int) config('tiendanube.empresa_id', 1),
            'puntoventa_id' => (int) $input['puntoventa_id'],
            'tipotransaccion_id' => (int) config('tiendanube.tipotransaccion_fac_id', 1),
            'cliente_id' => $clienteId,
            'fechafactura' => $fecha,
            'fecha' => $fecha,
            'deposito_id' => (int) $input['deposito_id'],
            'listaprecio_id' => $listaId,
            'moneda_id' => (int) config('tiendanube.moneda_id', 1),
            'cotizacion' => 1.,
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $precios,
            'descuentolinea' => $descuentos,
            'descripcionarticulos' => $descripciones,
            'combinacion_ids' => $combinacionIds,
            'talle_ids' => $talleIds,
            'color_ids' => $colorIds,
            'descuentopie' => 0.,
            'descuentoimportepie' => $descuentoPieImporte,
            'vendedor_id' => Auth::id(),
            'leyendafactura' => 'Tiendanube pedido #'.$nroPedido.$marcaParcial,
            'observacion' => 'Tiendanube pedido #'.$nroPedido.$marcaParcial,
            'venta_receptor' => $fiscal['venta_receptor'],
            'arca_receptor' => $fiscal['arca_receptor'],
            'opciones_emision' => [
                'omitir_movimiento_stock' => false,
                'permitir_caea' => false,
                'origen_tiendanube' => true,
                'omitir_solicitud_arca_cae' => false,
            ],
        ];

        // Factura A: FacturacionService aplica percepciones (no omitir).
        // Factura B: se omiten IIBB/IVA perc. salvo reglas especiales.
        if ($fiscal['letra'] === TiendanubePedidoReceptorSupport::LETRA_B) {
            $payload['omitir_percepciones'] = true;
        }

        $provinciaId = TiendanubePedidoReceptorSupport::provinciaIdDesdePedido($pedido);
        if ($provinciaId !== null) {
            $payload['provincia_id'] = $provinciaId;
        }

        return $payload;
    }

    /**
     * @param  list<array{cuentacaja_id:int,moneda_id?:int,monto:float,cotizacion?:float|null,observacion?:string|null}>  $mediosPago
     */
    private function registrarCobranza(Venta $venta, array $mediosPago): void
    {
        $tipoCajaId = (int) config('tiendanube.tipotransaccion_caja_id', 1);
        if ($tipoCajaId <= 0) {
            throw new InvalidArgumentException('Configure TIENDANUBE_TIPO_CAJA_ID.');
        }

        $empresaId = (int) ($venta->empresa_id ?: config('tiendanube.empresa_id', 1));
        $lineas = [];
        $total = 0.;
        foreach ($mediosPago as $medio) {
            $cuentacajaId = (int) ($medio['cuentacaja_id'] ?? 0);
            $monedaId = (int) ($medio['moneda_id'] ?? self::MONEDA_PESOS_ID);
            $monto = (float) ($medio['monto'] ?? 0);
            if ($cuentacajaId <= 0 || $monto <= 0.) {
                throw new InvalidArgumentException('Cada medio debe tener cuenta de caja y monto > 0.');
            }
            $cotizacion = isset($medio['cotizacion']) && (float) $medio['cotizacion'] > 0
                ? (float) $medio['cotizacion']
                : $this->cotizacion($venta->fecha, $monedaId, $empresaId);
            $lineas[] = [
                'cuentacaja_id' => $cuentacajaId,
                'moneda_id' => $monedaId,
                'monto' => $monto,
                'cotizacion' => $cotizacion,
                'observacion' => trim((string) ($medio['observacion'] ?? '')) ?: 'Cobranza Tiendanube',
            ];
            $total += $monto * $cotizacion;
        }

        $this->cobranzaService->guardaCobranzaGastronomia([
            'venta' => $venta,
            'empresa_id' => $empresaId,
            'tipotransaccion_caja_id' => $tipoCajaId,
            'totalfinalcobranza' => round($total, 2),
            'monedafinalcobranza_id' => self::MONEDA_PESOS_ID,
            'cotizacion_cobranza' => 1.,
            'lineas' => $lineas,
            'genera_contabilidad' => (bool) config('tiendanube.genera_contabilidad_cobranza', false),
            'detalle' => 'Cobranza Tiendanube — '.$venta->codigo
                .(isset($venta->leyenda) && $venta->leyenda ? ' / '.$venta->leyenda : ''),
        ]);
    }

    /**
     * @param  array<string,mixed>  $input
     */
    private function intentarEnviarMailCliente(TiendanubePedido $pedido, Venta $venta, array $input): void
    {
        if (! config('tiendanube.enviar_factura_mail', true)) {
            return;
        }

        $email = trim((string) (
            $input['receptor']['email']
            ?? $pedido->customer_email
            ?? $venta->email
            ?? ''
        ));
        if ($email === '') {
            return;
        }

        try {
            $resp = app(FacturaMailEnvioService::class)->enviarDesdeTiendanube((int) $venta->id, $email);
            if (! ($resp['ok'] ?? false)) {
                Log::warning('tiendanube.mail.fail', [
                    'order_id' => $pedido->tiendanube_order_id,
                    'venta_id' => $venta->id,
                    'mensaje' => $resp['mensaje'] ?? '',
                ]);

                return;
            }
            Log::info('tiendanube.mail.ok', [
                'order_id' => $pedido->tiendanube_order_id,
                'venta_id' => $venta->id,
                'destinatarios' => $resp['destinatarios'] ?? [],
            ]);
        } catch (Throwable $e) {
            Log::warning('tiendanube.mail.exception', [
                'order_id' => $pedido->tiendanube_order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string,mixed>  $resultadoEmision
     */
    private function intentarPublicarInvoice(TiendanubePedido $pedido, Venta $venta, array $resultadoEmision): void
    {
        if (! config('tiendanube.publicar_factura_en_pedido')) {
            return;
        }

        try {
            $cae = trim((string) ($resultadoEmision['cae'] ?? $venta->cae ?? ''));
            $numero = trim((string) ($venta->codigo
                ?? (($venta->letra ?? '').($venta->sucursal ?? '').'-'.($venta->numero ?? ''))));
            // Metafield nfe/list: key = CAE o número fiscal; link = PDF ERP
            $key = $cae !== '' ? $cae : ($numero !== '' && $numero !== '-' ? $numero : 'venta-'.$venta->id);
            $url = route('lista_una_factura_pdf', ['id' => $venta->id], true);

            $resp = TiendanubeApiClient::paraStoreId((string) $pedido->store_id)->crearInvoice((int) $pedido->tiendanube_order_id, [
                'key' => $key,
                'link' => $url,
            ]);
            if (! ($resp['ok'] ?? false)) {
                Log::warning('tiendanube.invoice.publish_fail', [
                    'order_id' => $pedido->tiendanube_order_id,
                    'venta_id' => $venta->id,
                    'error' => $resp['error'] ?? '',
                    'status' => $resp['status'] ?? 0,
                ]);

                return;
            }
            Log::info('tiendanube.invoice.publish_ok', [
                'order_id' => $pedido->tiendanube_order_id,
                'venta_id' => $venta->id,
                'key' => $key,
            ]);
        } catch (Throwable $e) {
            Log::warning('tiendanube.invoice.publish_exception', [
                'order_id' => $pedido->tiendanube_order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Factura en lote solo pedidos evaluados como listos.
     *
     * @param  list<int>  $pedidoIds
     * @return array{
     *   ok:bool,
     *   facturados:int,
     *   omitidos:int,
     *   errores:int,
     *   detalle:list<array{id:int,order_number:?string,ok:bool,motivo?:string,venta_id?:int}>
     * }
     */
    public function emitirMasivo(array $pedidoIds, int $max = 30): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $pedidoIds))));
        if ($ids === []) {
            return [
                'ok' => false,
                'facturados' => 0,
                'omitidos' => 0,
                'errores' => 0,
                'detalle' => [['id' => 0, 'order_number' => null, 'ok' => false, 'motivo' => 'No hay pedidos seleccionados']],
            ];
        }
        if (count($ids) > $max) {
            $ids = array_slice($ids, 0, $max);
        }

        $facturados = 0;
        $omitidos = 0;
        $errores = 0;
        $detalle = [];

        $pedidos = TiendanubePedido::query()
            ->with('lineas')
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        foreach ($ids as $id) {
            /** @var TiendanubePedido|null $pedido */
            $pedido = $pedidos->get($id);
            if (! $pedido) {
                $omitidos++;
                $detalle[] = ['id' => $id, 'order_number' => null, 'ok' => false, 'motivo' => 'Pedido inexistente'];
                continue;
            }

            $eval = TiendanubePedidoListoSupport::evaluar($pedido);
            if (! $eval['listo']) {
                $omitidos++;
                $detalle[] = [
                    'id' => (int) $pedido->id,
                    'order_number' => $pedido->order_number,
                    'ok' => false,
                    'motivo' => implode('; ', $eval['motivos']),
                ];
                continue;
            }

            $resultado = $this->emitir($pedido->fresh(['lineas']), $eval['input']);
            if ($resultado['ok'] ?? false) {
                $facturados++;
                $detalle[] = [
                    'id' => (int) $pedido->id,
                    'order_number' => $pedido->order_number,
                    'ok' => true,
                    'venta_id' => (int) ($resultado['venta_id'] ?? 0),
                ];
            } else {
                $errores++;
                $detalle[] = [
                    'id' => (int) $pedido->id,
                    'order_number' => $pedido->order_number,
                    'ok' => false,
                    'motivo' => (string) ($resultado['error'] ?? 'Error al emitir'),
                ];
            }
        }

        Log::info('tiendanube.emision.masivo', compact('facturados', 'omitidos', 'errores'));

        return [
            'ok' => $errores === 0 && $facturados > 0,
            'facturados' => $facturados,
            'omitidos' => $omitidos,
            'errores' => $errores,
            'detalle' => $detalle,
        ];
    }

    private function cotizacion($fecha, int $monedaId, int $empresaId): float
    {
        if ($monedaId <= 1) {
            return 1.;
        }
        $ymd = is_string($fecha) ? $fecha : (string) ($fecha?->format('Y-m-d') ?? date('Y-m-d'));

        return (float) (CotizacionTesoreriaConsultaSupport::ventaPorMonedaId($ymd, $monedaId, $empresaId) ?: 1.);
    }
}
