<?php

namespace App\Support\Wigos;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Servidor SQL Wigos activo por empresa (runtime): publicado por wigos:monitorear-servidor-activo.
 *
 * Persiste en storage (sobrevive cache:clear) y replica en cache para lectura rápida.
 * No modifica .env; CURR_WIGOS / WIGOS_POR_EMPRESA siguen siendo el preferido de config.
 */
final class WigosActiveServerStore
{
    private const CACHE_PREFIX = 'wigos.active_server.';

    private static function statePath(): string
    {
        return storage_path('app/wigos/active_server/state.json');
    }

    /**
     * Clave de estado: empresas con override en por_empresa usan su id; el resto comparte 0 (hosts globales).
     */
    public static function claveEmpresa(int $empresaId): int
    {
        if ($empresaId > 0) {
            $map = (array) config('wigos.por_empresa', []);
            if (isset($map[$empresaId]) || isset($map[(string) $empresaId])) {
                return $empresaId;
            }
        }

        return 0;
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
                Log::error('Wigos active server: state.json no escribible y no se pudo reemplazar', [
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
            Log::error('Wigos active server: no se pudo grabar state.json', ['path' => $path]);

            return;
        }
        @chmod($path, 0664);
    }

    private static function cacheKey(int $claveEmpresa): string
    {
        return self::CACHE_PREFIX.$claveEmpresa;
    }

    /**
     * @return array<string, mixed>
     */
    public static function bloque(int $empresaId): array
    {
        $clave = self::claveEmpresa($empresaId);
        $cached = Cache::get(self::cacheKey($clave));
        if (is_array($cached)) {
            return $cached;
        }

        $all = self::leerArchivo();
        $block = $all[(string) $clave] ?? self::bloqueVacio();

        Cache::forever(self::cacheKey($clave), $block);

        return $block;
    }

    /**
     * @return array<string, mixed>
     */
    private static function bloqueVacio(): array
    {
        return [
            'active' => null,
            'last_check_at' => null,
            'aliases' => [
                'A' => self::aliasVacio(),
                'B' => self::aliasVacio(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function aliasVacio(): array
    {
        return [
            'ok' => null,
            'consecutive_ok' => 0,
            'consecutive_failures' => 0,
            'last_error' => null,
            'last_ok_at' => null,
            'last_check_at' => null,
            'host' => null,
        ];
    }

    /**
     * Alias ONLINE publicado por el monitor, o null si aún no hay estado (usar config).
     */
    public static function aliasActivo(int $empresaId): ?string
    {
        if (! filter_var(config('wigos.monitor_servidor_activo.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $alias = self::bloque($empresaId)['active'] ?? null;

        return in_array($alias, ['A', 'B'], true) ? $alias : null;
    }

    /**
     * Actualiza contadores A/B y publica el alias activo con histéresis.
     *
     * @param  array<string, array{ok:bool,error:?string,host?:?string}>  $resultados  clave A|B
     */
    public static function registrarChequeos(int $empresaId, array $resultados, string $preferidoConfig): void
    {
        $preferido = strtoupper(trim($preferidoConfig));
        if (! in_array($preferido, ['A', 'B'], true)) {
            $preferido = 'A';
        }
        $otro = $preferido === 'A' ? 'B' : 'A';
        $okParaPreferido = max(1, (int) config('wigos.monitor_servidor_activo.ok_para_preferido', 2));

        $clave = self::claveEmpresa($empresaId);
        $all = self::leerArchivo();
        $block = $all[(string) $clave] ?? self::bloqueVacio();
        $now = now()->toIso8601String();
        $prevActive = in_array($block['active'] ?? null, ['A', 'B'], true) ? $block['active'] : null;

        $block['last_check_at'] = $now;
        if (! isset($block['aliases']) || ! is_array($block['aliases'])) {
            $block['aliases'] = ['A' => self::aliasVacio(), 'B' => self::aliasVacio()];
        }

        foreach (['A', 'B'] as $alias) {
            if (! isset($resultados[$alias])) {
                continue;
            }
            $r = $resultados[$alias];
            $aliasBlock = is_array($block['aliases'][$alias] ?? null)
                ? $block['aliases'][$alias]
                : self::aliasVacio();
            $aliasBlock['last_check_at'] = $now;
            if (array_key_exists('host', $r)) {
                $aliasBlock['host'] = $r['host'];
            }

            if (! empty($r['ok'])) {
                $aliasBlock['ok'] = true;
                $aliasBlock['last_ok_at'] = $now;
                $aliasBlock['last_error'] = null;
                $aliasBlock['consecutive_failures'] = 0;
                $aliasBlock['consecutive_ok'] = (int) ($aliasBlock['consecutive_ok'] ?? 0) + 1;
            } else {
                $aliasBlock['ok'] = false;
                $aliasBlock['last_error'] = ! empty($r['error'])
                    ? (string) $r['error']
                    : 'Error de conectividad sin detalle';
                $aliasBlock['consecutive_ok'] = 0;
                $aliasBlock['consecutive_failures'] = (int) ($aliasBlock['consecutive_failures'] ?? 0) + 1;
            }

            $block['aliases'][$alias] = $aliasBlock;
        }

        $okPreferido = (bool) ($block['aliases'][$preferido]['ok'] ?? false);
        $okOtro = (bool) ($block['aliases'][$otro]['ok'] ?? false);
        $consecPreferido = (int) ($block['aliases'][$preferido]['consecutive_ok'] ?? 0);

        $nuevo = $prevActive;
        if ($okPreferido && $okOtro) {
            if ($prevActive === $preferido || $consecPreferido >= $okParaPreferido || $prevActive === null) {
                $nuevo = $preferido;
            } else {
                $nuevo = $otro;
            }
        } elseif ($okPreferido) {
            $nuevo = $preferido;
        } elseif ($okOtro) {
            $nuevo = $otro;
        }
        // si ninguno OK: conservar último activo

        $block['active'] = $nuevo;

        if ($nuevo !== $prevActive && $nuevo !== null) {
            Log::info('Wigos servidor activo actualizado', [
                'empresa_clave' => $clave,
                'anterior' => $prevActive,
                'activo' => $nuevo,
                'preferido_config' => $preferido,
            ]);
        }

        $all[(string) $clave] = $block;
        self::escribirArchivo($all);
        Cache::forever(self::cacheKey($clave), $block);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function snapshot(): array
    {
        return self::leerArchivo();
    }

    /** Solo tests / soporte manual. */
    public static function reset(int $empresaId): void
    {
        $clave = self::claveEmpresa($empresaId);
        $all = self::leerArchivo();
        unset($all[(string) $clave]);
        self::escribirArchivo($all);
        Cache::forget(self::cacheKey($clave));
    }
}
