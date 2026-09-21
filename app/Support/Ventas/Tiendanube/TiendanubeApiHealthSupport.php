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
        'Token de Tiendanube inválido o app desinstalada en esa tienda. '
        .'Reautorizá la app y actualizá el access_token correspondiente en .env '
        .'(Ferli: TIENDANUBE_ACCESS_TOKEN; Boaonda: TIENDANUBE_BOAONDA_ACCESS_TOKEN); '
        .'luego php artisan config:clear.';

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
        $tiendasFallidas = self::nombresAuthFallida($data);
        if ($authOk === false) {
            $mensaje = $tiendasFallidas !== []
                ? 'Token inválido en: '.implode(', ', $tiendasFallidas).'. '.self::MENSAJE_TOKEN_INVALIDO
                : self::MENSAJE_TOKEN_INVALIDO;
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

    public static function marcarAuthInvalida(int $status, string $detalle = '', ?string $storeId = null): void
    {
        $prev = self::leer();
        $error = $detalle !== '' ? mb_substr($detalle, 0, 500) : self::MENSAJE_TOKEN_INVALIDO;
        $storeId = trim((string) $storeId);
        if ($storeId !== '') {
            $prev = self::aplicarTienda($prev, $storeId, [
                'ok' => false,
                'auth_ok' => false,
                'status' => $status,
                'error' => $error,
                'checked_at' => now()->toIso8601String(),
            ]);
            $prev = self::recalcularAgregado($prev);
        } else {
            $prev['ok'] = false;
            $prev['auth_ok'] = false;
            $prev['status'] = $status;
            $prev['error'] = $error;
            $prev['checked_at'] = now()->toIso8601String();
        }
        self::guardar($prev);

        try {
            Log::error('tiendanube.api.auth_invalida', [
                'store_id' => $storeId !== '' ? $storeId : null,
                'status' => $status,
                'error' => mb_substr($detalle, 0, 300),
            ]);
        } catch (\Throwable) {
        }
    }

    public static function marcarAuthOk(int $status = 200, ?string $storeId = null): void
    {
        $prev = self::leer();
        $storeId = trim((string) $storeId);
        $patch = [
            'ok' => true,
            'auth_ok' => true,
            'status' => $status,
            'error' => null,
            'checked_at' => now()->toIso8601String(),
        ];
        if ($storeId !== '') {
            $prev = self::aplicarTienda($prev, $storeId, $patch);
            $prev = self::recalcularAgregado($prev);
        } else {
            $prev = array_merge($prev, $patch);
        }
        self::guardar($prev);
    }

    public static function marcarSyncOk(?string $storeId = null): void
    {
        $prev = self::leer();
        $storeId = trim((string) $storeId);
        $patch = [
            'ok' => true,
            'auth_ok' => true,
            'status' => 200,
            'error' => null,
            'checked_at' => now()->toIso8601String(),
            'last_sync_ok_at' => now()->toIso8601String(),
            'last_sync_error' => null,
        ];
        if ($storeId !== '') {
            $prev = self::aplicarTienda($prev, $storeId, $patch);
            $prev = self::recalcularAgregado($prev);
        } else {
            $prev = array_merge($prev, $patch);
        }
        self::guardar($prev);
    }

    public static function marcarSyncError(string $error, int $status = 0, ?string $storeId = null): void
    {
        $prev = self::leer();
        $storeId = trim((string) $storeId);
        $authFail = self::esErrorAuth($status, $error);
        $patch = [
            'ok' => false,
            'status' => $status,
            'checked_at' => now()->toIso8601String(),
            'last_sync_error' => mb_substr($error, 0, 500),
            'error' => $authFail ? self::MENSAJE_TOKEN_INVALIDO : mb_substr($error, 0, 500),
        ];
        if ($authFail) {
            $patch['auth_ok'] = false;
        }
        if ($storeId !== '') {
            $prev = self::aplicarTienda($prev, $storeId, $patch);
            $prev = self::recalcularAgregado($prev);
        } else {
            if ($authFail) {
                $prev['auth_ok'] = false;
            }
            $prev['ok'] = false;
            $prev['status'] = $status;
            $prev['error'] = $patch['error'];
            $prev['checked_at'] = $patch['checked_at'];
            $prev['last_sync_error'] = $patch['last_sync_error'];
        }
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

        /** @var list<array{clave:string,nombre:string,store_id:string,access_token:string}> $tiendas */
        $tiendas = TiendanubeTiendasSupport::configuradas();
        if ($tiendas === []) {
            self::marcarAuthInvalida(0, 'Faltan store_id / access_token de Tiendanube en .env');

            return [
                'ok' => false,
                'auth_ok' => false,
                'status' => 0,
                'error' => 'Faltan credenciales en .env',
                'from_cache' => false,
            ];
        }

        $ultimaFalla = null;
        foreach ($tiendas as $tienda) {
            $api = TiendanubeApiClient::paraStoreId($tienda['store_id'])->sinRegistrarSalud();
            $resp = $api->listarPedidos(['per_page' => 1], 1, 1);
            $status = (int) ($resp['status'] ?? 0);
            $error = (string) ($resp['error'] ?? '');
            $storeId = $tienda['store_id'];

            if ($resp['ok'] ?? false) {
                self::marcarAuthOk($status, $storeId);
                continue;
            }

            if (self::esErrorAuth($status, $error)) {
                self::marcarAuthInvalida($status, $tienda['nombre'].': '.$error, $storeId);
            } else {
                self::marcarSyncError(
                    $tienda['nombre'].': '.($error !== '' ? $error : 'Error HTTP '.$status),
                    $status,
                    $storeId
                );
            }
            $ultimaFalla = [
                'status' => $status,
                'error' => self::mensajeErrorApi($status, $error),
                'auth' => self::esErrorAuth($status, $error),
            ];
        }

        $estado = self::estado();
        if ($ultimaFalla === null) {
            return [
                'ok' => true,
                'auth_ok' => true,
                'status' => 200,
                'error' => null,
                'from_cache' => false,
            ];
        }

        return [
            'ok' => false,
            'auth_ok' => $estado['auth_ok'],
            'status' => (int) $ultimaFalla['status'],
            'error' => $estado['mensaje_ui'] ?: (string) $ultimaFalla['error'],
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
            ."Store ID: ".config('tiendanube.store_id')."\n"
            .'Tiendas: '.implode(', ', array_map(
                static fn (array $t): string => $t['nombre'].' ('.$t['store_id'].')',
                TiendanubeTiendasSupport::paraVista()
            ))."\n";

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

    /**
     * @param  array<string,mixed>  $prev
     * @param  array<string,mixed>  $patch
     * @return array<string,mixed>
     */
    private static function aplicarTienda(array $prev, string $storeId, array $patch): array
    {
        $stores = is_array($prev['stores'] ?? null) ? $prev['stores'] : [];
        $actual = is_array($stores[$storeId] ?? null) ? $stores[$storeId] : [];
        $actual['nombre'] = TiendanubeTiendasSupport::nombre($storeId);
        $stores[$storeId] = array_merge($actual, $patch);
        $prev['stores'] = $stores;

        return $prev;
    }

    /**
     * El agregado no borra el fallo de una tienda cuando la otra responde bien.
     *
     * @param  array<string,mixed>  $prev
     * @return array<string,mixed>
     */
    private static function recalcularAgregado(array $prev): array
    {
        $stores = is_array($prev['stores'] ?? null) ? $prev['stores'] : [];
        if ($stores === []) {
            return $prev;
        }

        $authOk = true;
        $ok = true;
        $lastSync = null;
        $status = 200;
        foreach ($stores as $store) {
            if (! is_array($store)) {
                continue;
            }
            if (array_key_exists('auth_ok', $store) && $store['auth_ok'] === false) {
                $authOk = false;
                $ok = false;
                $status = (int) ($store['status'] ?? $status);
            } elseif (($store['ok'] ?? true) === false) {
                $ok = false;
                $status = (int) ($store['status'] ?? $status);
            }
            $syncAt = isset($store['last_sync_ok_at']) ? (string) $store['last_sync_ok_at'] : '';
            if ($syncAt !== '' && ($lastSync === null || $syncAt > $lastSync)) {
                $lastSync = $syncAt;
            }
        }

        $prev['auth_ok'] = $authOk;
        $prev['ok'] = $ok && $authOk;
        $prev['status'] = $authOk ? 200 : $status;
        $prev['checked_at'] = now()->toIso8601String();
        $nombres = self::nombresAuthFallida($prev);
        $prev['error'] = $nombres === [] ? null : 'Token inválido: '.implode(', ', $nombres);
        if ($lastSync !== null) {
            $prev['last_sync_ok_at'] = $lastSync;
        }

        return $prev;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return list<string>
     */
    private static function nombresAuthFallida(array $data): array
    {
        $stores = is_array($data['stores'] ?? null) ? $data['stores'] : [];
        $nombres = [];
        foreach ($stores as $storeId => $store) {
            if (! is_array($store) || ($store['auth_ok'] ?? true) !== false) {
                continue;
            }
            $nombre = trim((string) ($store['nombre'] ?? ''));
            $nombres[] = $nombre !== '' ? $nombre : (string) $storeId;
        }

        return $nombres;
    }
}
