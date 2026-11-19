<?php

namespace App\Services\Ventas\Mercadolibre;

use App\Support\Ventas\Mercadolibre\MercadolibreTokenSupport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * OAuth MeLi: Authorization Code + Refresh Token.
 */
final class MercadolibreOauthService
{
    public function urlAutorizacion(?string $state = null): string
    {
        $clientId = trim((string) config('mercadolibre.client_id', ''));
        $redirect = trim((string) config('mercadolibre.redirect_uri', ''));
        $base = rtrim((string) config('mercadolibre.auth_base', 'https://auth.mercadolibre.com.ar'), '/');
        $state = $state ?: Str::random(32);

        return $base.'/authorization?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirect,
            'state' => $state,
        ]);
    }

    /**
     * @return array{ok:bool,error?:string,seller_id?:string}
     */
    public function intercambiarCode(string $code): array
    {
        $resp = $this->postToken([
            'grant_type' => 'authorization_code',
            'client_id' => trim((string) config('mercadolibre.client_id', '')),
            'client_secret' => trim((string) config('mercadolibre.client_secret', '')),
            'code' => $code,
            'redirect_uri' => trim((string) config('mercadolibre.redirect_uri', '')),
        ]);

        if (! ($resp['ok'] ?? false)) {
            return $resp;
        }

        /** @var array<string,mixed> $data */
        $data = $resp['data'];
        $fila = MercadolibreTokenSupport::persistirDesdeOauth($data);
        $sellerId = (string) ($fila->seller_id ?? '');

        if ($sellerId === '') {
            $me = $this->consultarUsuario($fila->access_token);
            if (($me['ok'] ?? false) && isset($me['data']['id'])) {
                $sellerId = (string) $me['data']['id'];
                $fila->seller_id = $sellerId;
                $fila->save();
            }
        }

        return ['ok' => true, 'seller_id' => $sellerId];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public function refrescarSiPuede(): array
    {
        $refresh = MercadolibreTokenSupport::refreshToken();
        if ($refresh === '') {
            return ['ok' => false, 'error' => 'Sin refresh_token.'];
        }

        $resp = $this->postToken([
            'grant_type' => 'refresh_token',
            'client_id' => trim((string) config('mercadolibre.client_id', '')),
            'client_secret' => trim((string) config('mercadolibre.client_secret', '')),
            'refresh_token' => $refresh,
        ]);

        if (! ($resp['ok'] ?? false)) {
            return $resp;
        }

        /** @var array<string,mixed> $data */
        $data = $resp['data'];
        MercadolibreTokenSupport::persistirDesdeOauth($data);

        return ['ok' => true];
    }

    /**
     * @param  array<string,string>  $form
     * @return array{ok:bool,data?:array<string,mixed>,error?:string,status?:int}
     */
    private function postToken(array $form): array
    {
        $url = rtrim((string) config('mercadolibre.api_base', 'https://api.mercadolibre.com'), '/').'/oauth/token';
        try {
            $response = Http::asForm()
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => (string) config('mercadolibre.user_agent')])
                ->timeout(30)
                ->post($url, $form);
        } catch (\Throwable $e) {
            Log::warning('mercadolibre.oauth.exception', ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage(), 'status' => 0];
        }

        $data = $response->json();
        if ($response->successful() && is_array($data) && ! empty($data['access_token'])) {
            return ['ok' => true, 'data' => $data, 'status' => $response->status()];
        }

        $error = is_array($data)
            ? (string) ($data['message'] ?? $data['error'] ?? $response->body())
            : $response->body();

        return ['ok' => false, 'error' => $error, 'status' => $response->status()];
    }

    /**
     * @return array{ok:bool,data?:array<string,mixed>,error?:string}
     */
    private function consultarUsuario(string $accessToken): array
    {
        $url = rtrim((string) config('mercadolibre.api_base'), '/').'/users/me';
        try {
            $response = Http::withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->timeout(20)
                ->get($url);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        $data = $response->json();
        if ($response->successful() && is_array($data)) {
            return ['ok' => true, 'data' => $data];
        }

        return ['ok' => false, 'error' => $response->body()];
    }
}
