<?php

namespace App\Support\Arca;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Failover CAEA automático (runtime): activado por arca:monitorear-conectividad.
 *
 * Persiste en storage (sobrevive cache:clear) y replica en cache Laravel para lectura rápida.
 * No modifica .env; ARCA_WSFE_FORZAR_MODO_CAEA manual sigue teniendo prioridad en forzarModoCaea().
 */
final class ArcaFailoverStore
{
    public const WS_WSFE = 'wsfe';

    public const WS_MTXCA = 'mtxca';

    private const CACHE_PREFIX = 'arca.failover.';

    private static function statePath(): string
    {
        return storage_path('app/arca/failover/state.json');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function leerArchivo(): array
    {
        $path = self::statePath();
        if (! is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $data
     */
    private static function escribirArchivo(array $data): void
    {
        $path = self::statePath();
        $dir = dirname($path);
        File::ensureDirectoryExists($dir);
        if (is_file($path) && ! is_writable($path)) {
            if (! @unlink($path)) {
                Log::error('ARCA failover: state.json no escribible y no se pudo reemplazar', [
                    'path' => $path,
                ]);

                return;
            }
        }

        $ok = @file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n",
            LOCK_EX
        );
        if ($ok === false) {
            Log::error('ARCA failover: no se pudo grabar state.json', ['path' => $path]);

            return;
        }
        @chmod($path, 0664);
    }

    private static function cacheKey(string $webservice): string
    {
        return self::CACHE_PREFIX.$webservice;
    }

    /**
     * @return array<string, mixed>
     */
    public static function bloque(string $webservice): array
    {
        $cached = Cache::get(self::cacheKey($webservice));
        if (is_array($cached)) {
            return $cached;
        }

        $all = self::leerArchivo();
        $block = $all[$webservice] ?? self::bloqueVacio();

        Cache::forever(self::cacheKey($webservice), $block);

        return $block;
    }

    /**
     * @return array<string, mixed>
     */
    private static function bloqueVacio(): array
    {
        return [
            'active' => false,
            'consecutive_failures' => 0,
            'consecutive_ok' => 0,
            'activated_at' => null,
            'deactivated_at' => null,
            'last_check_at' => null,
            'last_ok_at' => null,
            'last_error' => null,
            'last_ultimo_nro' => null,
        ];
    }

    public static function estaActivo(string $webservice): bool
    {
        return (bool) (self::bloque($webservice)['active'] ?? false);
    }

    /**
     * @param  array{ultimo_nro?:int|null,probe?:string|null}  $meta
     */
    public static function registrarChequeo(
        string $webservice,
        bool $ok,
        ?string $error = null,
        array $meta = [],
    ): void {
        $fallosUmbral = max(1, (int) config('arca.monitor_conectividad.fallos_para_activar', 2));
        $okUmbral = max(1, (int) config('arca.monitor_conectividad.ok_para_desactivar', 2));

        $all = self::leerArchivo();
        $block = $all[$webservice] ?? self::bloqueVacio();
        $now = now()->toIso8601String();
        $wasActive = (bool) ($block['active'] ?? false);

        $block['last_check_at'] = $now;
        if (isset($meta['ultimo_nro'])) {
            $block['last_ultimo_nro'] = $meta['ultimo_nro'];
        }
        if (isset($meta['probe'])) {
            $block['last_probe'] = $meta['probe'];
        }

        if ($ok) {
            $block['last_ok_at'] = $now;
            $block['last_error'] = null;
            $block['consecutive_failures'] = 0;
            $block['consecutive_ok'] = (int) ($block['consecutive_ok'] ?? 0) + 1;

            if ($wasActive && (int) $block['consecutive_ok'] >= $okUmbral) {
                $block['active'] = false;
                $block['deactivated_at'] = $now;
                Log::info('ARCA failover CAEA desactivado (conectividad OK)', [
                    'webservice' => $webservice,
                    'consecutive_ok' => $block['consecutive_ok'],
                ]);
            }
        } else {
            $block['last_error'] = $error !== null && $error !== '' ? $error : 'Error de conectividad sin detalle';
            $block['consecutive_ok'] = 0;
            $block['consecutive_failures'] = (int) ($block['consecutive_failures'] ?? 0) + 1;

            if (! $wasActive && (int) $block['consecutive_failures'] >= $fallosUmbral) {
                $block['active'] = true;
                $block['activated_at'] = $now;
                Log::warning('ARCA failover CAEA activado (fallos de conectividad)', [
                    'webservice' => $webservice,
                    'consecutive_failures' => $block['consecutive_failures'],
                    'error' => $block['last_error'],
                ]);
            } elseif ($wasActive) {
                Log::debug('ARCA failover CAEA sigue activo tras chequeo fallido', [
                    'webservice' => $webservice,
                    'consecutive_failures' => $block['consecutive_failures'],
                ]);
            }
        }

        $all[$webservice] = $block;
        self::escribirArchivo($all);
        Cache::forever(self::cacheKey($webservice), $block);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function snapshot(): array
    {
        return self::leerArchivo();
    }

    /** Solo tests / soporte manual. */
    public static function reset(string $webservice): void
    {
        $all = self::leerArchivo();
        unset($all[$webservice]);
        self::escribirArchivo($all);
        Cache::forget(self::cacheKey($webservice));
    }
}
