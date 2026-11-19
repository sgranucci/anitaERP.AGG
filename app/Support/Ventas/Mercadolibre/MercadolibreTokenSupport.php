<?php

namespace App\Support\Ventas\Mercadolibre;

use App\Models\Ventas\MercadolibreOauthToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens OAuth MeLi: tabla preferida; fallback .env.
 */
final class MercadolibreTokenSupport
{
    public static function filaActiva(): ?MercadolibreOauthToken
    {
        if (! Schema::hasTable('mercadolibre_oauth_token')) {
            return null;
        }

        return MercadolibreOauthToken::query()->orderByDesc('id')->first();
    }

    public static function accessToken(): string
    {
        $fila = self::filaActiva();
        if ($fila && trim((string) $fila->access_token) !== '') {
            return trim((string) $fila->access_token);
        }

        return trim((string) config('mercadolibre.access_token', ''));
    }

    public static function refreshToken(): string
    {
        $fila = self::filaActiva();
        if ($fila && trim((string) $fila->refresh_token) !== '') {
            return trim((string) $fila->refresh_token);
        }

        return trim((string) config('mercadolibre.refresh_token', ''));
    }

    public static function sellerId(): string
    {
        $fila = self::filaActiva();
        if ($fila && trim((string) ($fila->seller_id ?? '')) !== '') {
            return trim((string) $fila->seller_id);
        }

        return trim((string) config('mercadolibre.seller_id', ''));
    }

    public static function configurado(): bool
    {
        $clientId = trim((string) config('mercadolibre.client_id', ''));
        $secret = trim((string) config('mercadolibre.client_secret', ''));

        return $clientId !== '' && $secret !== '' && self::accessToken() !== '';
    }

    public static function appConfigurada(): bool
    {
        return trim((string) config('mercadolibre.client_id', '')) !== ''
            && trim((string) config('mercadolibre.client_secret', '')) !== '';
    }

    /**
     * @param  array<string,mixed>  $tokenResponse
     */
    public static function persistirDesdeOauth(array $tokenResponse, ?string $sellerId = null): MercadolibreOauthToken
    {
        $expiresIn = (int) ($tokenResponse['expires_in'] ?? 0);
        $expiresAt = $expiresIn > 0 ? Carbon::now()->addSeconds($expiresIn) : null;
        $seller = $sellerId
            ?: (isset($tokenResponse['user_id']) ? (string) $tokenResponse['user_id'] : null)
            ?: self::sellerId()
            ?: null;

        $fila = self::filaActiva() ?: new MercadolibreOauthToken();
        $fila->fill([
            'seller_id' => $seller !== '' ? $seller : null,
            'access_token' => (string) ($tokenResponse['access_token'] ?? ''),
            'refresh_token' => (string) ($tokenResponse['refresh_token'] ?? $fila->refresh_token ?? ''),
            'token_type' => (string) ($tokenResponse['token_type'] ?? 'bearer'),
            'expires_in' => $expiresIn > 0 ? $expiresIn : null,
            'expires_at' => $expiresAt,
            'scope' => isset($tokenResponse['scope']) ? (string) $tokenResponse['scope'] : null,
            'payload_json' => $tokenResponse,
        ]);
        $fila->save();

        return $fila;
    }

    /**
     * @return array{autorizado:bool,seller_id:?string,expires_at:?string,vigente:bool}
     */
    public static function estadoParaVista(): array
    {
        $fila = self::filaActiva();
        $token = self::accessToken();

        return [
            'autorizado' => $token !== '',
            'seller_id' => self::sellerId() ?: null,
            'expires_at' => $fila?->expires_at?->format('d/m/Y H:i'),
            'vigente' => $fila ? $fila->estaVigente() : ($token !== ''),
        ];
    }
}
