<?php

namespace App\Services\Ventas\Gastronomia\Waitry;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * OAuth password grant Waitry con cache de access_token (~14 días, renovación anticipada).
 */
final class WaitryAuthService
{
    private const CACHE_KEY_TOKEN = 'waitry.oauth.access_token';

    /**
     * @return array{ok:true,access_token:string}|array{ok:false,error:string}
     */
    public function contextoAutenticado(): array
    {
        if (! $this->credencialesCompletas()) {
            return [
                'ok' => false,
                'error' => 'Waitry: credenciales incompletas (client_id, client_secret, user, password).',
            ];
        }

        try {
            $token = $this->obtenerAccessToken();
        } catch (Throwable $e) {
            Log::error('waitry.auth.fallo', ['msg' => $e->getMessage()]);

            return [
                'ok' => false,
                'error' => 'No se pudo obtener el token de Waitry.',
            ];
        }

        if ($token === '') {
            return [
                'ok' => false,
                'error' => 'Token de Waitry vacío tras autenticación.',
            ];
        }

        return [
            'ok' => true,
            'access_token' => $token,
        ];
    }

    public function renovarTokenForzado(): void
    {
        $this->solicitarYGuardarToken();
    }

    public function credencialesCompletas(): bool
    {
        return $this->clientId() !== ''
            && $this->clientSecret() !== ''
            && $this->usuario() !== ''
            && $this->password() !== '';
    }

    private function obtenerAccessToken(): string
    {
        $cached = Cache::get(self::CACHE_KEY_TOKEN);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $lock = Cache::lock('waitry.oauth.refresh', 45);

        try {
            return $lock->block(30, function (): string {
                $cached = Cache::get(self::CACHE_KEY_TOKEN);
                if (is_string($cached) && $cached !== '') {
                    return $cached;
                }

                if ($this->cargarTokenDesdeDisco()) {
                    $cached = Cache::get(self::CACHE_KEY_TOKEN);
                    if (is_string($cached) && $cached !== '') {
                        return $cached;
                    }
                }

                return $this->solicitarYGuardarToken();
            });
        } finally {
            optional($lock)->release();
        }
    }

    private function cargarTokenDesdeDisco(): bool
    {
        $path = (string) config('waitry.token_storage_path', 'waitry/oauth_token.json');
        if (! Storage::exists($path)) {
            return false;
        }

        $raw = Storage::get($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($data) || empty($data['access_token'])) {
            return false;
        }

        $obtainedAt = (int) ($data['obtained_at'] ?? Storage::lastModified($path));
        $ttl = (int) config('waitry.token_ttl_segundos', 14 * 24 * 3600);
        $buffer = (int) config('waitry.token_renovar_antes_segundos', 24 * 3600);

        if (time() >= $obtainedAt + $ttl - $buffer) {
            return false;
        }

        $this->persistirTokenEnCache((string) $data['access_token'], $obtainedAt, $ttl);

        return true;
    }

    private function solicitarYGuardarToken(): string
    {
        $timeout = (int) config('waitry.http_timeout_segundos', 30);

        $response = Http::timeout($timeout)
            ->asForm()
            ->post((string) config('waitry.login_url'), [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'grant_type' => 'password',
                'user' => $this->usuario(),
                'password' => $this->password(),
            ]);

        if (! $response->successful()) {
            Log::warning('waitry.auth.http_error', [
                'status' => $response->status(),
                'body' => substr((string) $response->body(), 0, 500),
            ]);

            throw new RuntimeException('Waitry login HTTP '.$response->status());
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('Waitry login: respuesta no JSON');
        }

        if (empty($json['ok'])) {
            $msg = trim((string) ($json['msg'] ?? $json['message'] ?? 'Error de autenticación Waitry'));

            throw new RuntimeException($msg);
        }

        $token = trim((string) ($json['response']['access_token'] ?? ''));
        if ($token === '') {
            throw new RuntimeException('Waitry login sin access_token en response');
        }

        $obtainedAt = time();
        $ttl = (int) config('waitry.token_ttl_segundos', 14 * 24 * 3600);

        $path = (string) config('waitry.token_storage_path', 'waitry/oauth_token.json');
        Storage::put($path, json_encode([
            'access_token' => $token,
            'obtained_at' => $obtainedAt,
            'ttl_segundos' => $ttl,
        ], JSON_UNESCAPED_UNICODE));

        $this->persistirTokenEnCache($token, $obtainedAt, $ttl);

        Log::info('waitry.auth.token_renovado', ['obtained_at' => $obtainedAt]);

        return $token;
    }

    private function persistirTokenEnCache(string $token, int $obtainedAt, int $ttl): void
    {
        $buffer = (int) config('waitry.token_renovar_antes_segundos', 24 * 3600);
        $expiraEn = max(60, $obtainedAt + $ttl - $buffer - time());

        Cache::put(self::CACHE_KEY_TOKEN, $token, $expiraEn);
    }

    public function invalidarToken(): void
    {
        Cache::forget(self::CACHE_KEY_TOKEN);
        $path = (string) config('waitry.token_storage_path', 'waitry/oauth_token.json');
        if (Storage::exists($path)) {
            Storage::delete($path);
        }
    }

    private function clientId(): string
    {
        return trim((string) config('waitry.client_id', ''));
    }

    private function clientSecret(): string
    {
        return trim((string) config('waitry.client_secret', ''));
    }

    private function usuario(): string
    {
        return trim((string) config('waitry.user', ''));
    }

    private function password(): string
    {
        return (string) config('waitry.password', '');
    }
}
