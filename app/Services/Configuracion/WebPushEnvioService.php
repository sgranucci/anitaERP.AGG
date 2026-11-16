<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\WebPushSubscription;
use App\Support\Configuracion\UsuarioAvisoPreferenciaSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Push del navegador / PWA (Android Chrome, desktop, iOS PWA).
 */
class WebPushEnvioService
{
    public function habilitado(): bool
    {
        if (! (bool) config('web_push.habilitado', true)) {
            return false;
        }
        $pub = trim((string) config('web_push.vapid.public_key', ''));
        $priv = trim((string) config('web_push.vapid.private_key', ''));

        return $pub !== '' && $priv !== '' && Schema::hasTable('web_push_subscription');
    }

    public function publicKey(): string
    {
        return trim((string) config('web_push.vapid.public_key', ''));
    }

    /**
     * @param  array{endpoint: string, keys: array{p256dh: string, auth: string}, contentEncoding?: string}  $payload
     */
    public function suscribir(int $usuarioId, array $payload, ?string $userAgent = null): ?WebPushSubscription
    {
        if ($usuarioId <= 0 || ! Schema::hasTable('web_push_subscription')) {
            return null;
        }
        $endpoint = trim((string) ($payload['endpoint'] ?? ''));
        $p256dh = trim((string) ($payload['keys']['p256dh'] ?? ''));
        $auth = trim((string) ($payload['keys']['auth'] ?? ''));
        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            return null;
        }

        $hash = hash('sha256', $endpoint);

        return WebPushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => $hash],
            [
                'usuario_id' => $usuarioId,
                'endpoint' => $endpoint,
                'p256dh' => $p256dh,
                'auth' => $auth,
                'content_encoding' => (string) ($payload['contentEncoding'] ?? 'aesgcm'),
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
            ]
        );
    }

    public function desuscribirPorEndpoint(int $usuarioId, string $endpoint): int
    {
        if ($usuarioId <= 0 || $endpoint === '' || ! Schema::hasTable('web_push_subscription')) {
            return 0;
        }

        return (int) WebPushSubscription::query()
            ->where('usuario_id', $usuarioId)
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->delete();
    }

    /**
     * @param  iterable<int|string>  $usuarioIds
     * @return array{enviados: int, fallidos: int}
     */
    public function enviarAUsuarios(iterable $usuarioIds, string $titulo, ?string $cuerpo = null, ?string $url = null): array
    {
        $stats = ['enviados' => 0, 'fallidos' => 0];
        if (! $this->habilitado()) {
            return $stats;
        }

        $ids = UsuarioAvisoPreferenciaSupport::filtrarIds(
            $usuarioIds,
            UsuarioAvisoPreferenciaSupport::CANAL_IN_APP
        );
        if ($ids === []) {
            return $stats;
        }

        $subs = WebPushSubscription::query()->whereIn('usuario_id', $ids)->get();
        if ($subs->isEmpty()) {
            return $stats;
        }

        $urlAbs = $url ?: (function_exists('urlAppAbsoluta') ? urlAppAbsoluta('mis-aprobaciones') : url('mis-aprobaciones'));
        if ($urlAbs && ! preg_match('#^https?://#i', $urlAbs) && function_exists('urlAppAbsoluta')) {
            $urlAbs = urlAppAbsoluta(ltrim($urlAbs, '/'));
        }

        $webPush = $this->cliente();
        $payload = json_encode([
            'title' => mb_substr($titulo, 0, 120),
            'body' => mb_substr((string) ($cuerpo ?? ''), 0, 240),
            'url' => $urlAbs,
            'icon' => asset('assets/pwa/icon-192.png'),
        ], JSON_UNESCAPED_UNICODE);

        foreach ($subs as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->p256dh,
                    'authToken' => $sub->auth,
                    'contentEncoding' => $sub->content_encoding ?: 'aesgcm',
                ]);
                $report = $webPush->sendOneNotification($subscription, $payload ?: '{}');
                if ($report->isSuccess()) {
                    $sub->ultima_ok_en = now();
                    $sub->ultimo_error = null;
                    $sub->ultimo_error_en = null;
                    $sub->save();
                    $stats['enviados']++;
                } else {
                    $stats['fallidos']++;
                    $reason = mb_substr((string) $report->getReason(), 0, 255);
                    $sub->ultimo_error = $reason;
                    $sub->ultimo_error_en = now();
                    $sub->save();
                    if ($report->isSubscriptionExpired()) {
                        $sub->delete();
                    }
                }
            } catch (\Throwable $e) {
                $stats['fallidos']++;
                Log::warning('WebPush: fallo envío', [
                    'usuario_id' => $sub->usuario_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function cliente(): WebPush
    {
        return new WebPush([
            'VAPID' => [
                'subject' => (string) config('web_push.vapid.subject'),
                'publicKey' => (string) config('web_push.vapid.public_key'),
                'privateKey' => (string) config('web_push.vapid.private_key'),
            ],
        ], [
            'TTL' => (int) config('web_push.ttl', 86400),
        ]);
    }
}
