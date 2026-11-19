<?php

namespace App\Http\Controllers\Ventas\Mercadolibre;

use App\Http\Controllers\Controller;
use App\Models\Ventas\MercadolibreNotificacion;
use App\Models\Ventas\MercadolibrePedido;
use App\Services\Ventas\Mercadolibre\MercadolibreApiClient;
use App\Services\Ventas\Mercadolibre\MercadolibrePedidoNotaCreditoService;
use App\Services\Ventas\Mercadolibre\MercadolibrePedidoSyncService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\Mercadolibre\MercadolibreTokenSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Notificaciones MeLi (orders_v2 / claims). Responde 200 rápido.
 */
class MercadolibreWebhookController extends Controller
{
    public function __construct(
        private readonly MercadolibrePedidoSyncService $syncService,
        private readonly MercadolibrePedidoNotaCreditoService $notaCreditoService,
        private readonly MercadolibreApiClient $api,
    ) {
    }

    public function __invoke(Request $request)
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return response()->json(['ok' => false], 404);
        }

        $payload = $request->all();
        $topic = strtolower((string) ($payload['topic'] ?? ''));
        $resource = (string) ($payload['resource'] ?? '');
        $userId = isset($payload['user_id']) ? (string) $payload['user_id'] : null;

        $notif = null;
        if (Schema::hasTable('mercadolibre_notificacion')) {
            $notif = MercadolibreNotificacion::query()->create([
                'topic' => $topic !== '' ? $topic : null,
                'resource' => $resource !== '' ? $resource : null,
                'user_id' => $userId,
                'application_id' => isset($payload['application_id']) ? (string) $payload['application_id'] : null,
                'attempts' => isset($payload['attempts']) ? (string) $payload['attempts'] : null,
                'estado' => 'recibida',
                'payload_json' => $payload,
            ]);
        }

        try {
            if ($this->esTopicOrden($topic, $resource)) {
                $orderId = $this->extraerId($resource, $payload, 'orders');
                if ($orderId > 0 && MercadolibreTokenSupport::configurado()) {
                    $this->syncService->refrescarOrden($orderId);
                    $this->marcarNotif($notif, 'procesada');
                } else {
                    $this->marcarNotif($notif, 'omitida');
                }
            } elseif ($this->esTopicClaim($topic, $resource)) {
                $this->procesarClaim($resource, $payload, $notif);
            } else {
                $this->marcarNotif($notif, 'omitida');
            }
        } catch (Throwable $e) {
            Log::warning('mercadolibre.webhook', ['error' => $e->getMessage(), 'payload' => $payload]);
            if ($notif) {
                $notif->estado = 'error';
                $notif->error_mensaje = $e->getMessage();
                $notif->save();
            }
        }

        return response()->json(['ok' => true], 200);
    }

    private function esTopicOrden(string $topic, string $resource): bool
    {
        return str_contains($topic, 'order') || str_contains($resource, '/orders/');
    }

    private function esTopicClaim(string $topic, string $resource): bool
    {
        return str_contains($topic, 'claim')
            || str_contains($topic, 'post_purchase')
            || str_contains($resource, '/claims/')
            || str_contains($resource, 'post-purchase');
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function procesarClaim(string $resource, array $payload, ?MercadolibreNotificacion $notif): void
    {
        if (! MercadolibreTokenSupport::configurado()) {
            $this->marcarNotif($notif, 'omitida', 'Sin token');

            return;
        }

        $claimId = $this->extraerId($resource, $payload, 'claims');
        $claim = null;
        if ($claimId > 0) {
            $resp = $this->api->get('/v1/claims/'.$claimId);
            if (! ($resp['ok'] ?? false)) {
                $resp = $this->api->get('/claims/'.$claimId);
            }
            if (($resp['ok'] ?? false) && is_array($resp['data'] ?? null)) {
                $claim = $resp['data'];
            }
        }

        $orderId = 0;
        if (is_array($claim)) {
            $orderId = (int) ($claim['resource_id'] ?? $claim['order_id'] ?? 0);
            if ($orderId <= 0 && ! empty($claim['resource']) && preg_match('#/orders/(\d+)#', (string) $claim['resource'], $m)) {
                $orderId = (int) $m[1];
            }
        }
        if ($orderId <= 0) {
            $orderId = $this->extraerId($resource, $payload, 'orders');
        }

        if ($orderId > 0) {
            $this->syncService->refrescarOrden($orderId);
            $pedido = MercadolibrePedido::query()->where('order_id', $orderId)->orderByDesc('id')->first();
            $statusClaim = strtolower((string) ($claim['status'] ?? $claim['stage'] ?? ''));
            $cerrado = in_array($statusClaim, ['closed', 'resolved', 'cancelled', 'canceled'], true)
                || ($pedido && in_array(strtolower((string) $pedido->status), ['cancelled', 'canceled'], true));
            if ($pedido && $pedido->estaFacturado() && $cerrado) {
                $this->notaCreditoService->emitirSiCorresponde($pedido);
            }
            $this->marcarNotif($notif, 'procesada');

            return;
        }

        $this->marcarNotif($notif, 'omitida', 'Claim sin order_id');
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function extraerId(string $resource, array $payload, string $tipo): int
    {
        if (preg_match('#/'.$tipo.'/(\d+)#', $resource, $m)) {
            return (int) $m[1];
        }
        if (! empty($payload['id']) && ctype_digit((string) $payload['id']) && $tipo !== 'orders') {
            return (int) $payload['id'];
        }
        if ($tipo === 'orders' && ! empty($payload['id']) && ctype_digit((string) $payload['id'])) {
            return (int) $payload['id'];
        }

        return 0;
    }

    private function marcarNotif(?MercadolibreNotificacion $notif, string $estado, ?string $error = null): void
    {
        if (! $notif) {
            return;
        }
        $notif->estado = $estado;
        $notif->procesada_at = now();
        if ($error) {
            $notif->error_mensaje = $error;
        }
        $notif->save();
    }
}
