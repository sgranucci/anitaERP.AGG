<?php

namespace App\Services\Ventas\Mercadolibre;

use App\Models\Ventas\MercadolibrePedido;
use App\Models\Ventas\MercadolibrePedidoLinea;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\Mercadolibre\MercadolibreDefaultsSupport;
use App\Support\Ventas\Mercadolibre\MercadolibrePedidoEstadoSupport;
use App\Support\Ventas\Mercadolibre\MercadolibrePedidoSkuResolverSupport;
use App\Support\Ventas\Mercadolibre\MercadolibreTokenSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Baja órdenes pagadas de MeLi → staging mercadolibre_pedido.
 */
final class MercadolibrePedidoSyncService
{
    public function __construct(
        private readonly MercadolibreApiClient $api,
    ) {
    }

    /**
     * @return array{ok:bool,creados:int,actualizados:int,paginas:int,error?:string,advertencias?:string}
     */
    public function sincronizarRango(string $desdeYmd, string $hastaYmd): array
    {
        if (! $this->api->configurado()) {
            return ['ok' => false, 'creados' => 0, 'actualizados' => 0, 'paginas' => 0, 'error' => 'API MeLi no autorizada.'];
        }

        $creados = 0;
        $actualizados = 0;
        $paginas = 0;
        $offset = 0;
        $limit = 50;
        $advertencias = [];

        $desde = Carbon::parse($desdeYmd)->startOfDay()->utc()->format('Y-m-d\TH:i:s.000\Z');
        $hasta = Carbon::parse($hastaYmd)->endOfDay()->utc()->format('Y-m-d\TH:i:s.000\Z');

        do {
            $resp = $this->api->buscarOrdenes([
                'order.status' => 'paid',
                'order.date_created.from' => $desde,
                'order.date_created.to' => $hasta,
                'offset' => $offset,
                'limit' => $limit,
            ]);
            $paginas++;
            if (! ($resp['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'creados' => $creados,
                    'actualizados' => $actualizados,
                    'paginas' => $paginas,
                    'error' => $resp['error'] ?? 'Error buscando órdenes',
                ];
            }

            $results = $resp['data']['results'] ?? [];
            if (! is_array($results)) {
                break;
            }
            foreach ($results as $resumen) {
                $orderId = (int) ($resumen['id'] ?? 0);
                if ($orderId <= 0) {
                    continue;
                }
                $detalle = $this->api->obtenerOrden($orderId);
                if (! ($detalle['ok'] ?? false) || ! is_array($detalle['data'] ?? null)) {
                    $advertencias[] = 'Orden '.$orderId.': '.($detalle['error'] ?? 'sin detalle');
                    continue;
                }
                $r = $this->upsertDesdeApi($detalle['data']);
                if ($r === 'creado') {
                    $creados++;
                } elseif ($r === 'actualizado') {
                    $actualizados++;
                }
            }

            $paging = $resp['data']['paging'] ?? [];
            $total = (int) ($paging['total'] ?? 0);
            $offset += $limit;
            $hayMas = $offset < $total && count($results) > 0;
        } while ($hayMas && $paginas < 40);

        return [
            'ok' => true,
            'creados' => $creados,
            'actualizados' => $actualizados,
            'paginas' => $paginas,
            'advertencias' => $advertencias !== [] ? implode('; ', array_slice($advertencias, 0, 5)) : null,
        ];
    }

    /**
     * @return array{ok:bool,error?:string,pedido_id?:int}
     */
    public function refrescarOrden(int $orderId): array
    {
        $resp = $this->api->obtenerOrden($orderId);
        if (! ($resp['ok'] ?? false) || ! is_array($resp['data'] ?? null)) {
            return ['ok' => false, 'error' => $resp['error'] ?? 'No se pudo leer la orden'];
        }
        $pedido = $this->upsertDesdeApi($resp['data'], true);

        return ['ok' => true, 'pedido_id' => is_object($pedido) ? (int) $pedido->id : null];
    }

    /**
     * @param  array<string,mixed>  $order
     * @return 'creado'|'actualizado'|MercadolibrePedido|null
     */
    public function upsertDesdeApi(array $order, bool $devolverModelo = false): mixed
    {
        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId <= 0) {
            return null;
        }

        $sellerId = MercadolibreTokenSupport::sellerId() ?: (string) ($order['seller']['id'] ?? '');
        $pedido = MercadolibrePedido::query()
            ->where('seller_id', $sellerId)
            ->where('order_id', $orderId)
            ->first();

        $esNuevo = $pedido === null;
        if ($esNuevo) {
            $pedido = new MercadolibrePedido();
            $pedido->seller_id = $sellerId !== '' ? $sellerId : null;
            $pedido->order_id = $orderId;
            $pedido->estado_erp = MercadolibrePedidoEstadoSupport::PENDIENTE;
        }

        if ($pedido->estaFacturado()) {
            // Actualiza payload/status pero no pisa venta_id / facturado.
            $statusFact = strtolower((string) ($order['status'] ?? ''));
            $pedido->status = $statusFact !== '' ? $statusFact : $pedido->status;
            $pedido->payload_json = $order;
            $pedido->synced_at = now();
            $pedido->save();

            if (in_array($statusFact, ['cancelled', 'canceled'], true)) {
                try {
                    app(MercadolibrePedidoNotaCreditoService::class)->emitirSiCorresponde($pedido->fresh());
                } catch (\Throwable $e) {
                    Log::warning('mercadolibre.sync.nc_facturado', [
                        'order_id' => $orderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $devolverModelo ? $pedido->fresh(['lineas']) : 'actualizado';
        }

        $shipping = is_array($order['shipping'] ?? null) ? $order['shipping'] : [];
        $defaults = MercadolibreDefaultsSupport::resolver($shipping);
        $payments = is_array($order['payments'] ?? null) ? $order['payments'] : [];
        $paymentId = null;
        foreach ($payments as $pay) {
            if (is_array($pay) && ! empty($pay['id'])) {
                $paymentId = (string) $pay['id'];
                break;
            }
        }

        $buyer = is_array($order['buyer'] ?? null) ? $order['buyer'] : [];
        $billingInfoId = (string) (
            $order['buyer']['billing_info']['id']
            ?? $order['billing_info']['id']
            ?? ''
        );

        $billing = null;
        $letra = 'B';
        $doc = '';
        $docType = '';
        $buyerName = trim(((string) ($buyer['first_name'] ?? '')).' '.((string) ($buyer['last_name'] ?? '')));
        if ($billingInfoId !== '') {
            $billResp = $this->api->billingInfo($billingInfoId);
            if (($billResp['ok'] ?? false) && is_array($billResp['data'] ?? null)) {
                $billing = $billResp['data'];
                [$letra, $doc, $docType, $nombreBill] = $this->extraerFiscal($billing);
                if ($nombreBill !== '') {
                    $buyerName = $nombreBill;
                }
            }
        }

        $status = strtolower((string) ($order['status'] ?? ''));
        $paidAt = null;
        if (! empty($order['date_closed'])) {
            try {
                $paidAt = Carbon::parse((string) $order['date_closed']);
            } catch (\Throwable) {
            }
        }
        if ($paidAt === null && ! empty($payments[0]['date_approved'])) {
            try {
                $paidAt = Carbon::parse((string) $payments[0]['date_approved']);
            } catch (\Throwable) {
            }
        }

        $pedido->fill([
            'pack_id' => isset($order['pack_id']) ? (int) $order['pack_id'] : null,
            'status' => $status !== '' ? $status : null,
            'status_detail' => isset($order['status_detail']) ? (string) $order['status_detail'] : null,
            'es_full' => (bool) $defaults['es_full'],
            'total' => (float) ($order['total_amount'] ?? 0),
            'currency' => (string) ($order['currency_id'] ?? 'ARS'),
            'buyer_nickname' => isset($buyer['nickname']) ? (string) $buyer['nickname'] : null,
            'buyer_doc' => $doc !== '' ? $doc : null,
            'buyer_doc_type' => $docType !== '' ? $docType : null,
            'buyer_name' => $buyerName !== '' ? $buyerName : null,
            'payment_id' => $paymentId,
            'billing_info_id' => $billingInfoId !== '' ? $billingInfoId : null,
            'letra_sugerida' => $letra,
            'paid_at' => $paidAt,
            'date_created_ml' => ! empty($order['date_created']) ? Carbon::parse((string) $order['date_created']) : null,
            'buyer_json' => $buyer,
            'billing_json' => $billing,
            'payments_json' => $payments,
            'shipping_json' => $shipping,
            'payload_json' => $order,
            'puntoventa_id_sugerido' => $defaults['puntoventa_id'],
            'deposito_id_sugerido' => $defaults['deposito_id'],
            'synced_at' => now(),
            'error_mensaje' => null,
        ]);

        $canceladoMl = in_array($status, ['cancelled', 'canceled'], true);
        if ($canceladoMl && ! $pedido->estaFacturado()) {
            $pedido->estado_erp = MercadolibrePedidoEstadoSupport::CANCELADO;
        }

        $pedido->save();
        if (! $pedido->estaFacturado()) {
            $this->reemplazarLineas($pedido, is_array($order['order_items'] ?? null) ? $order['order_items'] : []);
        }

        if ($canceladoMl && $pedido->estaFacturado()) {
            // NC automática post-factura (Cati).
            try {
                app(MercadolibrePedidoNotaCreditoService::class)->emitirSiCorresponde($pedido->fresh());
            } catch (\Throwable $e) {
                Log::warning('mercadolibre.sync.nc', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
                $pedido->error_mensaje = mb_substr('NC pendiente: '.$e->getMessage(), 0, 1000);
                $pedido->save();
            }
        } elseif ($pedido->estado_erp !== MercadolibrePedidoEstadoSupport::CANCELADO
            && ! $pedido->estaFacturado()) {
            $pedido->estado_erp = $this->evaluarEstado($pedido);
            $pedido->save();
        }

        return $devolverModelo ? $pedido->fresh(['lineas']) : ($esNuevo ? 'creado' : 'actualizado');
    }

    /**
     * @param  list<array<string,mixed>>  $items
     */
    private function reemplazarLineas(MercadolibrePedido $pedido, array $items): void
    {
        EloquentAuditDeleteSupport::each(
            MercadolibrePedidoLinea::query()->where('mercadolibre_pedido_id', $pedido->id)
        );

        $orden = 0;
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $producto = is_array($item['item'] ?? null) ? $item['item'] : [];
            $sellerSku = (string) (
                $producto['seller_sku']
                ?? $producto['seller_custom_field']
                ?? $item['seller_sku']
                ?? ''
            );
            $skuRes = MercadolibrePedidoSkuResolverSupport::resolver($sellerSku);
            MercadolibrePedidoLinea::query()->create([
                'mercadolibre_pedido_id' => $pedido->id,
                'tipo' => 'producto',
                'sku' => $sellerSku !== '' ? $sellerSku : null,
                'seller_sku' => $sellerSku !== '' ? $sellerSku : null,
                'item_id' => isset($producto['id']) ? (string) $producto['id'] : null,
                'nombre' => (string) ($producto['title'] ?? ''),
                'quantity' => (float) ($item['quantity'] ?? 0),
                'unit_price' => (float) ($item['unit_price'] ?? $item['full_unit_price'] ?? 0),
                'full_unit_price' => isset($item['full_unit_price']) ? (float) $item['full_unit_price'] : null,
                'articulo_id' => $skuRes['articulo_id'],
                'combinacion_id' => $skuRes['combinacion_id'],
                'talle_id' => $skuRes['talle_id'],
                'color_id' => $skuRes['color_id'],
                'orden' => $orden++,
            ]);
        }
    }

    private function evaluarEstado(MercadolibrePedido $pedido): string
    {
        $pedido->loadMissing('lineas');
        if (! $pedido->estaPagado()) {
            return MercadolibrePedidoEstadoSupport::PENDIENTE;
        }
        foreach ($pedido->lineas as $linea) {
            if (! $linea->articulo_id) {
                $pedido->error_mensaje = 'SKU no encontrado: '.($linea->seller_sku ?: $linea->nombre);

                return MercadolibrePedidoEstadoSupport::BLOQUEADO_FISCAL;
            }
        }
        if (! $pedido->puntoventa_id_sugerido || ! $pedido->deposito_id_sugerido) {
            $pedido->error_mensaje = 'Falta PV/depósito sugerido (local MeLi / Full).';

            return MercadolibrePedidoEstadoSupport::BLOQUEADO_FISCAL;
        }

        return MercadolibrePedidoEstadoSupport::LISTO;
    }

    /**
     * @param  array<string,mixed>  $billing
     * @return array{0:string,1:string,2:string,3:string} letra, doc, docType, nombre
     */
    private function extraerFiscal(array $billing): array
    {
        $info = is_array($billing['buyer']['billing_info'] ?? null)
            ? $billing['buyer']['billing_info']
            : (is_array($billing['billing_info'] ?? null) ? $billing['billing_info'] : $billing);

        $docType = strtoupper((string) ($info['doc_type'] ?? $info['identification']['type'] ?? ''));
        $doc = preg_replace('/\D+/', '', (string) ($info['doc_number'] ?? $info['identification']['number'] ?? '')) ?: '';
        $nombre = trim((string) (
            $info['business_name']
            ?? trim(((string) ($info['name'] ?? '')).' '.((string) ($info['last_name'] ?? '')))
            ?? ''
        ));

        $letra = 'B';
        if ($docType === 'CUIT' || strlen($doc) === 11) {
            $letra = 'A';
            $docType = $docType !== '' ? $docType : 'CUIT';
        } elseif ($docType === '' && strlen($doc) === 8) {
            $docType = 'DNI';
        }

        return [$letra, $doc, $docType, $nombre];
    }
}
