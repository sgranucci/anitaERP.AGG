<?php

namespace App\Services\Ventas\Tiendanube;

use App\Models\Ventas\TiendanubePedido;
use App\Models\Ventas\TiendanubePedidoLinea;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\Tiendanube\TiendanubeApiHealthSupport;
use App\Support\Ventas\Tiendanube\TiendanubePedidoEstadoSupport;
use App\Support\Ventas\Tiendanube\TiendanubePedidoMaestrosSupport;
use App\Support\Ventas\Tiendanube\TiendanubePedidoReceptorSupport;
use App\Support\Ventas\Tiendanube\TiendanubePedidoSkuResolverSupport;
use App\Support\Ventas\Tiendanube\TiendanubeTiendasSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trae pedidos pagados de Tiendanube y los stagea en tiendanube_pedido.
 */
final class TiendanubePedidoSyncService
{
    public function __construct(
        private readonly TiendanubeApiClient $api,
    ) {
    }

    /**
     * @return array{
     *   ok:bool,
     *   error?:string,
     *   status?:int,
     *   creados:int,
     *   actualizados:int,
     *   omitidos:int,
     *   paginas:int,
     *   advertencias:?string
     * }
     */
    public function sincronizarRango(?string $desdeYmd, ?string $hastaYmd, int $maxPaginas = 20): array
    {
        $tiendas = TiendanubeTiendasSupport::configuradas();
        if ($tiendas === []) {
            $this->api->assertConfigurado();
        }

        $creados = 0;
        $actualizados = 0;
        $omitidos = 0;
        $paginas = 0;
        $fallos = [];
        $okAlguna = false;

        foreach ($tiendas as $tienda) {
            $api = TiendanubeApiClient::paraStoreId($tienda['store_id']);
            $r = $this->sincronizarTienda($api, $tienda['nombre'], $desdeYmd, $hastaYmd, $maxPaginas);
            $creados += (int) $r['creados'];
            $actualizados += (int) $r['actualizados'];
            $omitidos += (int) $r['omitidos'];
            $paginas += (int) $r['paginas'];
            if ($r['ok']) {
                $okAlguna = true;
            } else {
                $fallos[] = $tienda['nombre'].': '.($r['error'] ?? 'error');
            }
        }

        if (! $okAlguna) {
            return [
                'ok' => false,
                'error' => $fallos !== [] ? implode(' | ', $fallos) : 'Ninguna tienda configurada',
                'creados' => $creados,
                'actualizados' => $actualizados,
                'omitidos' => $omitidos,
                'paginas' => $paginas,
                'advertencias' => null,
            ];
        }

        Log::info('tiendanube.sync.ok', compact('creados', 'actualizados', 'omitidos', 'paginas', 'desdeYmd', 'hastaYmd'));

        $rematch = $this->rematchearSkusPendientes();

        return [
            'ok' => true,
            'creados' => $creados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
            'paginas' => $paginas,
            'skus_rematch' => $rematch['actualizadas'],
            'skus_sin_match' => $rematch['sin_match'],
            'advertencias' => $fallos !== [] ? implode(' | ', $fallos) : null,
        ];
    }

    /**
     * @return array{ok:bool,error?:string,status?:int,creados:int,actualizados:int,omitidos:int,paginas:int}
     */
    private function sincronizarTienda(
        TiendanubeApiClient $api,
        string $nombre,
        ?string $desdeYmd,
        ?string $hastaYmd,
        int $maxPaginas,
    ): array {
        $filtros = [
            'payment_status' => 'paid',
        ];
        if ($desdeYmd) {
            $filtros['created_at_min'] = Carbon::parse($desdeYmd)->startOfDay()->toIso8601String();
        }
        if ($hastaYmd) {
            $filtros['created_at_max'] = Carbon::parse($hastaYmd)->endOfDay()->toIso8601String();
        }

        $creados = 0;
        $actualizados = 0;
        $omitidos = 0;
        $paginas = 0;
        $storeId = $api->storeId();

        for ($page = 1; $page <= $maxPaginas; $page++) {
            $resp = $api->listarPedidos($filtros, $page);
            if (! $resp['ok']) {
                $status = (int) ($resp['status'] ?? 0);
                $error = (string) ($resp['error'] ?? 'Error al listar pedidos');
                TiendanubeApiHealthSupport::marcarSyncError($nombre.': '.$error, $status, $storeId);

                return [
                    'ok' => false,
                    'error' => $error,
                    'status' => $status,
                    'creados' => $creados,
                    'actualizados' => $actualizados,
                    'omitidos' => $omitidos,
                    'paginas' => $paginas,
                ];
            }

            $data = $resp['data'] ?? [];
            if (! is_array($data) || $data === []) {
                break;
            }

            if (isset($data['orders']) && is_array($data['orders'])) {
                $data = $data['orders'];
            }

            $paginas++;
            foreach ($data as $order) {
                if (! is_array($order)) {
                    $omitidos++;
                    continue;
                }
                $r = $this->upsertDesdeApi($order, $storeId);
                if ($r === 'created') {
                    $creados++;
                } elseif ($r === 'updated') {
                    $actualizados++;
                } else {
                    $omitidos++;
                }
            }

            if (count($data) < (int) config('tiendanube.sync_page_size', 50)) {
                break;
            }
        }

        TiendanubeApiHealthSupport::marcarSyncOk($storeId);

        return [
            'ok' => true,
            'creados' => $creados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
            'paginas' => $paginas,
        ];
    }

    /**
     * Refresca un pedido puntual desde la API.
     *
     * @return array{ok:bool,error?:string,pedido?:TiendanubePedido}
     */
    public function refrescarPedido(TiendanubePedido $pedido): array
    {
        $api = TiendanubeApiClient::paraStoreId((string) $pedido->store_id);
        $resp = $api->obtenerPedido((int) $pedido->tiendanube_order_id);
        if (! $resp['ok'] || ! is_array($resp['data'] ?? null)) {
            return ['ok' => false, 'error' => $resp['error'] ?? 'No se pudo leer el pedido'];
        }
        $this->upsertDesdeApi($resp['data'], $api->storeId());
        $pedido->refresh();
        $this->rematchearSkusPendientes((int) $pedido->id);

        return ['ok' => true, 'pedido' => $pedido->fresh(['lineas'])];
    }

    /**
     * @param  array<string,mixed>  $order
     * @return 'created'|'updated'|'skipped'
     */
    public function upsertDesdeApi(array $order, ?string $storeId = null): string
    {
        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId <= 0) {
            return 'skipped';
        }

        $paymentStatus = strtolower((string) ($order['payment_status'] ?? ''));
        // Solo stageamos pagados (regla de negocio)
        if ($paymentStatus !== 'paid') {
            return 'skipped';
        }

        $storeId = trim((string) ($storeId ?: $this->api->storeId()));
        $customer = is_array($order['customer'] ?? null) ? $order['customer'] : [];
        $billing = is_array($order['billing_address'] ?? null) ? $order['billing_address'] : [];
        $shipping = is_array($order['shipping_address'] ?? null) ? $order['shipping_address'] : [];
        $payment = $this->extraerPago($order);

        $doc = $this->extraerDocumento($customer, $billing, $order);
        $nombre = TiendanubePedidoReceptorSupport::nombreDesdeOrder($order);

        $pvDefault = TiendanubePedidoMaestrosSupport::puntoventaDefault($storeId);
        $depDefault = TiendanubePedidoMaestrosSupport::depositoDefault(null, $storeId);

        $existente = TiendanubePedido::query()
            ->where('store_id', $storeId)
            ->where('tiendanube_order_id', $orderId)
            ->first();

        $attrs = [
            'store_id' => $storeId,
            'tiendanube_order_id' => $orderId,
            'order_number' => (string) ($order['number'] ?? $order['id'] ?? ''),
            'payment_status' => $paymentStatus,
            'status' => (string) ($order['status'] ?? ''),
            'total' => (float) ($order['total'] ?? 0),
            'currency' => (string) ($order['currency'] ?? 'ARS'),
            'customer_name' => $nombre !== '' ? $nombre : null,
            'customer_email' => $customer['email'] ?? $order['contact_email'] ?? null,
            'customer_doc' => $doc['numero'],
            'customer_doc_type' => $doc['tipo'],
            'gateway' => $payment['gateway'],
            'gateway_name' => $payment['name'],
            'paid_at' => $this->parseFecha($order['paid_at'] ?? $order['updated_at'] ?? null),
            'created_at_tn' => $this->parseFecha($order['created_at'] ?? null),
            'customer_json' => $customer,
            'shipping_json' => $shipping !== [] ? $shipping : ($order['shipping'] ?? null),
            'payment_json' => $payment['raw'],
            'payload_json' => $order,
            // Siempre el default de la tienda (no conservar sugeridos viejos / erróneos).
            'puntoventa_id_sugerido' => $pvDefault?->id,
            'deposito_id_sugerido' => $depDefault?->id,
            'synced_at' => now(),
        ];

        return DB::transaction(function () use ($existente, $attrs, $order, $doc) {
            if ($existente && $existente->preservaStagingFacturado()) {
                // No pisar estado facturado; solo refresh de metadatos seguros
                $existente->fill(array_diff_key($attrs, array_flip([
                    'estado_erp', 'venta_id', 'facturado_at', 'facturado_por_usuario_id', 'error_mensaje',
                ])));
                $existente->save();

                return 'updated';
            }

            if ($existente) {
                $pedido = $existente;
                $pedido->fill($attrs);
            } else {
                $pedido = new TiendanubePedido($attrs);
            }

            $pedido->estado_erp = $this->resolverEstadoInicial($doc['numero'], (float) $attrs['total']);
            $pedido->error_mensaje = null;
            $pedido->save();

            $this->reemplazarLineas($pedido, $order);

            return $existente ? 'updated' : 'created';
        });
    }

    /**
     * @param  array<string,mixed>  $order
     */
    private function reemplazarLineas(TiendanubePedido $pedido, array $order): void
    {
        EloquentAuditDeleteSupport::each(
            TiendanubePedidoLinea::query()->where('tiendanube_pedido_id', $pedido->id)
        );

        $orden = 0;
        $products = is_array($order['products'] ?? null) ? $order['products'] : [];
        foreach ($products as $p) {
            if (! is_array($p)) {
                continue;
            }
            $sku = trim((string) ($p['sku'] ?? ''));
            $resuelto = TiendanubePedidoSkuResolverSupport::resolver($sku);
            TiendanubePedidoLinea::query()->create([
                'tiendanube_pedido_id' => $pedido->id,
                'tipo' => 'producto',
                'sku' => $sku !== '' ? $sku : null,
                'nombre' => (string) ($p['name'] ?? $p['product_name'] ?? ''),
                'quantity' => (float) ($p['quantity'] ?? 0),
                'price' => (float) ($p['price'] ?? 0),
                'variant_id' => isset($p['variant_id']) ? (int) $p['variant_id'] : null,
                'product_id' => isset($p['product_id']) ? (int) $p['product_id'] : null,
                'articulo_id' => $resuelto['articulo_id'],
                'combinacion_id' => $resuelto['combinacion_id'],
                'talle_id' => $resuelto['talle_id'],
                'color_id' => $resuelto['color_id'],
                'orden' => $orden++,
            ]);
        }

        $shippingCost = (float) ($order['shipping_cost_customer'] ?? $order['shipping']['cost'] ?? 0);
        if ($shippingCost > 0.0001) {
            $artEnvio = TiendanubePedidoMaestrosSupport::articuloEnvio($storeId);
            TiendanubePedidoLinea::query()->create([
                'tiendanube_pedido_id' => $pedido->id,
                'tipo' => 'envio',
                'sku' => $artEnvio?->sku,
                'nombre' => 'Envío / flete',
                'quantity' => 1,
                'price' => $shippingCost,
                'articulo_id' => $artEnvio?->id,
                'orden' => $orden++,
            ]);
        }

        $discount = (float) ($order['discount'] ?? $order['coupon'][0]['value'] ?? 0);
        if ($discount > 0.0001) {
            $artDesc = TiendanubePedidoMaestrosSupport::articuloDescuento($storeId);
            TiendanubePedidoLinea::query()->create([
                'tiendanube_pedido_id' => $pedido->id,
                'tipo' => 'descuento',
                'sku' => $artDesc?->sku,
                'nombre' => 'Descuento / cupón',
                'quantity' => 1,
                'price' => -1 * abs($discount),
                'articulo_id' => $artDesc?->id,
                'orden' => $orden++,
            ]);
        }
    }

    /**
     * Re-resuelve SKU compuestos en líneas producto ya stageadas (sin tocar facturados).
     *
     * @return array{ok:bool,actualizadas:int,sin_match:int}
     */
    public function rematchearSkusPendientes(?int $pedidoId = null): array
    {
        $q = TiendanubePedidoLinea::query()
            ->where('tipo', 'producto')
            ->where(function ($w) {
                $w->whereNull('articulo_id')
                    ->orWhereNull('combinacion_id')
                    ->orWhereNull('talle_id');
            })
            ->whereHas('pedido', function ($p) {
                $p->where('estado_erp', '!=', TiendanubePedidoEstadoSupport::FACTURADO);
            });
        if ($pedidoId !== null && $pedidoId > 0) {
            $q->where('tiendanube_pedido_id', $pedidoId);
        }

        $actualizadas = 0;
        $sinMatch = 0;
        foreach ($q->cursor() as $linea) {
            $res = TiendanubePedidoSkuResolverSupport::resolver($linea->sku);
            if (! $res['articulo_id']) {
                $sinMatch++;
                continue;
            }
            $linea->articulo_id = $res['articulo_id'];
            $linea->combinacion_id = $res['combinacion_id'];
            $linea->talle_id = $res['talle_id'];
            $linea->color_id = $res['color_id'];
            $linea->save();
            if ($res['ok']) {
                $actualizadas++;
            } else {
                $sinMatch++;
            }
        }

        return ['ok' => true, 'actualizadas' => $actualizadas, 'sin_match' => $sinMatch];
    }

    /**
     * @param  array<string,mixed>  $customer
     * @param  array<string,mixed>  $billing
     * @param  array<string,mixed>  $order
     * @return array{tipo:?string,numero:?string}
     */
    private function extraerDocumento(array $customer, array $billing, array $order): array
    {
        $cuit = preg_replace('/\D+/', '', (string) (
            $customer['identification']
            ?? $billing['identification']
            ?? $order['contact_identification']
            ?? ''
        )) ?: null;

        $tipo = null;
        if ($cuit !== null) {
            $tipo = strlen($cuit) === 11 ? 'CUIT' : (strlen($cuit) === 8 || strlen($cuit) === 7 ? 'DNI' : 'DOC');
        }

        return ['tipo' => $tipo, 'numero' => $cuit];
    }

    /**
     * Extrae procesador de pago TN (gateway) + detalle del medio (tarjeta/método).
     * Importante: `payment_details.method` es credit_card/debit_card/wallet — NO es el gateway.
     * El gateway real viene en `order.gateway` / `gateway_name` (pago-nube, offline, gocuotas…).
     *
     * @param  array<string,mixed>  $order
     * @return array{gateway:?string,name:?string,raw:array<string,mixed>}
     */
    public function extraerPago(array $order): array
    {
        $details = is_array($order['payment_details'] ?? null) ? $order['payment_details'] : [];

        $gateway = null;
        if (is_string($order['gateway'] ?? null) && trim((string) $order['gateway']) !== '') {
            $gateway = strtolower(trim((string) $order['gateway']));
        } elseif (is_array($order['gateway'] ?? null)) {
            $fromObj = trim((string) ($order['gateway']['name'] ?? $order['gateway']['id'] ?? ''));
            $gateway = $fromObj !== '' ? strtolower($fromObj) : null;
        }

        $name = null;
        if (is_string($order['gateway_name'] ?? null) && trim((string) $order['gateway_name']) !== '') {
            $name = trim((string) $order['gateway_name']);
        } elseif (is_array($order['gateway'] ?? null) && trim((string) ($order['gateway']['name'] ?? '')) !== '') {
            $name = trim((string) $order['gateway']['name']);
        }

        // Sin gateway de orden: último recurso el método (pedidos viejos / incompletos)
        if ($gateway === null || $gateway === '') {
            $method = trim((string) ($details['method'] ?? ''));
            $gateway = $method !== '' ? strtolower($method) : null;
        }
        if ($name === null || $name === '') {
            $name = $gateway;
        }

        $raw = array_filter([
            'gateway' => $gateway,
            'gateway_name' => $name,
            'gateway_id' => $order['gateway_id'] ?? null,
            'method' => isset($details['method']) ? strtolower(trim((string) $details['method'])) : null,
            'credit_card_company' => isset($details['credit_card_company'])
                ? strtolower(trim((string) $details['credit_card_company']))
                : null,
            'installments' => $details['installments'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        // Conservar otros campos del payment_details (sin pisar claves canónicas)
        foreach ($details as $k => $v) {
            if (! array_key_exists($k, $raw)) {
                $raw[$k] = $v;
            }
        }

        return [
            'gateway' => $gateway,
            'name' => $name,
            'raw' => $raw,
        ];
    }

    private function resolverEstadoInicial(?string $doc, float $total): string
    {
        // Interactive: sin documento y monto alto → bloqueado; sin doc y monto bajo → listo (CF)
        $limite = (float) config('facturacion_local.limite_resto', 400000);
        if ($doc === null || $doc === '') {
            if ($total > $limite) {
                return TiendanubePedidoEstadoSupport::BLOQUEADO_FISCAL;
            }
        }

        return TiendanubePedidoEstadoSupport::LISTO;
    }

    private function parseFecha(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $valor);
        } catch (\Throwable) {
            return null;
        }
    }
}
