<?php

namespace App\Support\Ventas\Tiendanube;

use App\Services\Ventas\Tiendanube\TiendanubeApiClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Estado de salud de la API Tiendanube (token / último sync).
 * El access_token no se renueva solo: hay que detectar 401 y avisar.
 */
final class TiendanubeApiHealthSupport
{
    public const CACHE_KEY = 'tiendanube.api_health';

    public const MENSAJE_TOKEN_INVALIDO =
        'Token de Tiendanube inválido o app desinstalada. '
        .'Reautorizá la app en el panel de Tiendanube y actualizá '
        .'TIENDANUBE_ACCESS_TOKEN en .env; luego php artisan config:clear.';

    /**
     * @return array{
     *   ok:bool,
     *   auth_ok:bool,
     *   status:int,
     *   error:?string,
     *   checked_at:?string,
     *   last_sync_ok_at:?string,
     *   last_sync_error:?string,
     *   stale:bool,
     *   mensaje_ui:?string
     * }
     */
    public static function estado(): array
    {
        $raw = Cache::get(self::CACHE_KEY);
        $data = is_array($raw) ? $raw : [];

        $authOk = array_key_exists('auth_ok', $data) ? (bool) $data['auth_ok'] : null;
        $ok = (bool) ($data['ok'] ?? false);
        $lastSync = isset($data['last_sync_ok_at']) ? (string) $data['last_sync_ok_at'] : null;
        $staleHoras = max(1, (int) config('tiendanube.sync_stale_horas', 36));
        $stale = false;
        if ($lastSync) {
            try {
                $stale = Carbon::parse($lastSync)->lt(now()->subHours($staleHoras));
            } catch (\Throwable) {
                $stale = true;
            }
        } elseif ($authOk === true) {
            // Nunca sincronizó con éxito registrado
            $stale = true;
        }

        $mensaje = null;
        if ($authOk === false) {
            $mensaje = self::MENSAJE_TOKEN_INVALIDO;
        } elseif ($stale && $lastSync) {
            $mensaje = 'La última sincronización exitosa fue el '
                .Carbon::parse($lastSync)->format('d/m/Y H:i')
                .'. Revisá el cron o sincronizá manualmente.';
        } elseif ($stale && $lastSync === null && $authOk === true) {
            $mensaje = 'Aún no hay sincronización exitosa registrada. Usá «Sincronizar» o esperá el cron.';
        }

        return [
            'ok' => $ok && $authOk !== false,
            'auth_ok' => $authOk ?? false,
            'status' => (int) ($data['status'] ?? 0),
            'error' => isset($data['error']) ? (string) $data['error'] : null,
            'checked_at' => isset($data['checked_at']) ? (string) $data['checked_at'] : null,
            'last_sync_ok_at' => $lastSync,
            'last_sync_error' => isset($data['last_sync_error']) ? (string) $data['last_sync_error'] : null,
            'stale' => $stale,
            'mensaje_ui' => $mensaje,
        ];
    }

    public static function esErrorAuth(int $status, ?string $error = null): bool
    {
        if (in_array($status, [401, 403], true)) {
            return true;
        }
        $e = mb_strtolower((string) $error);

        return $e !== '' && (
            str_contains($e, 'invalid access token')
            || str_contains($e, 'unauthorized')
            || str_contains($e, 'authentication failed')
        );
    }

    public static function mensajeErrorApi(int $status, string $error): string
    {
        if (self::esErrorAuth($status, $error)) {
            return self::MENSAJE_TOKEN_INVALIDO;
        }

        return $error !== '' ? $error : 'Error HTTP '.$status;
    }

    public static function marcarAuthInvalida(int $status, string $detalle = ''): void
    {
        $prev = self::leer();
        $prev['ok'] = false;
        $prev['auth_ok'] = false;
        $prev['status'] = $status;
        $prev['error'] = $detalle !== '' ? mb_substr($detalle, 0, 500) : self::MENSAJE_TOKEN_INVALIDO;
        $prev['checked_at'] = now()->toIso8601String();
        self::guardar($prev);

        try {
            Log::error('tiendanube.api.auth_invalida', [
                'status' => $status,
                'error' => mb_substr($detalle, 0, 300),
            ]);
        } catch (\Throwable) {
        }
    }

    public static function marcarAuthOk(int $status = 200): void
    {
        $prev = self::leer();
        $prev['ok'] = true;
        $prev['auth_ok'] = true;
        $prev['status'] = $status;
        $prev['error'] = null;
        $prev['checked_at'] = now()->toIso8601String();
        self::guardar($prev);
    }

    public static function marcarSyncOk(): void
    {
        $prev = self::leer();
        $prev['ok'] = true;
        $prev['auth_ok'] = true;
        $prev['status'] = 200;
        $prev['error'] = null;
        $prev['checked_at'] = now()->toIso8601String();
        $prev['last_sync_ok_at'] = now()->toIso8601String();
        $prev['last_sync_error'] = null;
        self::guardar($prev);
    }

    public static function marcarSyncError(string $error, int $status = 0): void
    {
        $prev = self::leer();
        $prev['ok'] = false;
        if (self::esErrorAuth($status, $error)) {
            $prev['auth_ok'] = false;
            $prev['error'] = self::MENSAJE_TOKEN_INVALIDO;
        } else {
            $prev['error'] = mb_substr($error, 0, 500);
        }
        $prev['status'] = $status;
        $prev['checked_at'] = now()->toIso8601String();
        $prev['last_sync_error'] = mb_substr($error, 0, 500);
        self::guardar($prev);
    }

    /**
     * Ping liviano a la API. Usa cache breve salvo $forzar.
     *
     * @return array{ok:bool,auth_ok:bool,status:int,error:?string,from_cache:bool}
     */
    public function verificar(bool $forzar = false): array
    {
        $ttl = max(60, (int) config('tiendanube.health_cache_segundos', 300));
        if (! $forzar) {
            $estado = self::estado();
            if ($estado['checked_at']) {
                try {
                    if (Carbon::parse($estado['checked_at'])->gt(now()->subSeconds($ttl))) {
                        return [
                            'ok' => $estado['ok'],
                            'auth_ok' => $estado['auth_ok'],
                            'status' => $estado['status'],
                            'error' => $estado['error'],
                            'from_cache' => true,
                        ];
                    }
                } catch (\Throwable) {
                }
            }
        }

        /** @var TiendanubeApiClient $api */
        $api = app(TiendanubeApiClient::class);
        if (! $api->configurado()) {
            self::marcarAuthInvalida(0, 'Faltan TIENDANUBE_STORE_ID / TIENDANUBE_ACCESS_TOKEN en .env');

            return [
                'ok' => false,
                'auth_ok' => false,
                'status' => 0,
                'error' => 'Faltan credenciales en .env',
                'from_cache' => false,
            ];
        }

        // per_page=1: mínimo tráfico; no registra sync.
        $resp = $api->listarPedidos(['per_page' => 1], 1, 1);
        $status = (int) ($resp['status'] ?? 0);
        $error = (string) ($resp['error'] ?? '');

        if ($resp['ok'] ?? false) {
            self::marcarAuthOk($status);

            return [
                'ok' => true,
                'auth_ok' => true,
                'status' => $status,
                'error' => null,
                'from_cache' => false,
            ];
        }

        if (self::esErrorAuth($status, $error)) {
            self::marcarAuthInvalida($status, $error);
        } else {
            self::marcarSyncError($error !== '' ? $error : 'Error HTTP '.$status, $status);
        }

        return [
            'ok' => false,
            'auth_ok' => ! self::esErrorAuth($status, $error),
            'status' => $status,
            'error' => self::mensajeErrorApi($status, $error),
            'from_cache' => false,
        ];
    }

    /**
     * Mail de alerta (throttle). Solo si auth_ok=false.
     */
    public function notificarAuthInvalidaSiCorresponde(): bool
    {
        $estado = self::estado();
        if ($estado['auth_ok']) {
            return false;
        }

        $email = trim((string) config('tiendanube.alerta_email', ''));
        if ($email === '' || ! (bool) config('tiendanube.alerta_email_habilitado', true)) {
            return false;
        }

        $throttleHoras = max(1, (int) config('tiendanube.alerta_email_throttle_horas', 6));
        $cacheKey = 'tiendanube.alerta_auth_mail';
        if (Cache::has($cacheKey)) {
            return false;
        }

        $asunto = '[anitaERP] Tiendanube: token inválido — facturación detenida';
        $cuerpo = "La API de Tiendanube rechazó el access_token.\n\n"
            .self::MENSAJE_TOKEN_INVALIDO."\n\n"
            .'Status: '.$estado['status']."\n"
            .'Detalle: '.($estado['error'] ?? '')."\n"
            .'Verificado: '.($estado['checked_at'] ?? 'n/d')."\n"
            .'Último sync OK: '.($estado['last_sync_ok_at'] ?? 'nunca')."\n\n"
            ."Store ID: ".config('tiendanube.store_id')."\n";

        try {
            Mail::raw($cuerpo, function ($message) use ($email, $asunto) {
                $message->to($email)->subject($asunto);
            });
            Cache::put($cacheKey, true, now()->addHours($throttleHoras));
            Log::warning('tiendanube.alerta_auth_mail_enviado', ['to' => $email]);

            return true;
        } catch (\Throwable $e) {
            try {
                Log::error('tiendanube.alerta_auth_mail_fail', ['error' => $e->getMessage()]);
            } catch (\Throwable) {
            }

            return false;
        }
    }

    /** @return array<string,mixed> */
    private static function leer(): array
    {
        $raw = Cache::get(self::CACHE_KEY);

        return is_array($raw) ? $raw : [];
    }

    /** @param  array<string,mixed>  $data */
    private static function guardar(array $data): void
    {
        Cache::put(self::CACHE_KEY, $data, now()->addDays(30));
    }
}
