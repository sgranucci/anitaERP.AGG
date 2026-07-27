<?php

namespace App\Support\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Persistencia de bitácora de acceso — INSERT directo, sin cola Laravel.
 */
class BitacoraAccesoWriter
{
    public static function habilitado(): bool
    {
        return (bool) config('bitacora_acceso.habilitado', false)
            && Schema::hasTable('bitacora_acceso');
    }

    /**
     * @param  array{
     *   usuario_id:?int,
     *   usuario_nombre:?string,
     *   empresa_id:?int,
     *   rol_id:?int,
     *   session_id:?string,
     *   started:float
     * }  $contexto
     */
    public static function registrarDesdeRequest(Request $request, Response $response, array $contexto): void
    {
        if (! self::habilitado()) {
            return;
        }

        $started = (float) ($contexto['started'] ?? microtime(true));
        $duracionMs = (int) max(0, round((microtime(true) - $started) * 1000));

        $memoriaKb = null;
        if (config('bitacora_acceso.registrar_memoria', true)) {
            $memoriaKb = (int) max(0, round(memory_get_peak_usage(true) / 1024));
        }

        $rutaNombre = optional($request->route())->getName();
        $tipo = self::resolverTipo($request, $rutaNombre);

        $url = (string) $request->fullUrl();
        if (mb_strlen($url) > 500) {
            $url = mb_substr($url, 0, 497).'...';
        }

        $ua = (string) $request->userAgent();
        if (mb_strlen($ua) > 255) {
            $ua = mb_substr($ua, 0, 252).'...';
        }

        $path = '/'.ltrim($request->path(), '/');
        if (mb_strlen($path) > 255) {
            $path = mb_substr($path, 0, 252).'...';
        }

        self::insertar([
            'usuario_id' => $contexto['usuario_id'] ?? null,
            'usuario_nombre' => self::truncar($contexto['usuario_nombre'] ?? null, 120),
            'empresa_id' => $contexto['empresa_id'] ?? null,
            'rol_id' => $contexto['rol_id'] ?? null,
            'session_id' => self::truncar($contexto['session_id'] ?? null, 64),
            'tipo' => $tipo,
            'metodo' => strtoupper(substr((string) $request->method(), 0, 10)),
            'ruta' => $path,
            'nombre_ruta' => self::truncar($rutaNombre, 120),
            'url' => $url,
            'status' => (int) $response->getStatusCode(),
            'duracion_ms' => $duracionMs,
            'memoria_pico_kb' => $memoriaKb,
            'ip' => self::truncar($request->ip(), 45),
            'user_agent' => $ua,
            'created_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $fila */
    public static function insertar(array $fila): void
    {
        try {
            DB::table('bitacora_acceso')->insert($fila);
        } catch (Throwable $e) {
            // Nunca romper la respuesta del usuario por fallas de bitácora.
            try {
                Log::warning('bitacora_acceso: no se pudo grabar', [
                    'error' => $e->getMessage(),
                ]);
            } catch (Throwable) {
                // ignore
            }
        }
    }

    public static function debeExcluir(Request $request): bool
    {
        $path = trim($request->path(), '/');
        $pathLower = mb_strtolower($path);

        foreach ((array) config('bitacora_acceso.excluir_paths', []) as $exacto) {
            if ($pathLower === mb_strtolower(trim((string) $exacto, '/'))) {
                return true;
            }
        }

        foreach ((array) config('bitacora_acceso.excluir_path_contiene', []) as $fragmento) {
            $fragmento = mb_strtolower((string) $fragmento);
            if ($fragmento !== '' && str_contains($pathLower, $fragmento)) {
                return true;
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, (array) config('bitacora_acceso.excluir_extensiones', []), true)) {
            return true;
        }

        // Prefijo barra-tareas (cualquier subruta)
        if (str_starts_with($pathLower, 'seguridad/barra-tareas')) {
            return true;
        }

        return false;
    }

    private static function resolverTipo(Request $request, ?string $rutaNombre): string
    {
        $path = trim($request->path(), '/');
        $nombre = (string) $rutaNombre;

        if ($request->isMethod('POST') && (str_contains($path, 'seguridad/login') || $nombre === 'login')) {
            return 'login';
        }
        if (str_contains($path, 'seguridad/logout') || $nombre === 'logout') {
            return 'logout';
        }

        return 'navegacion';
    }

    private static function truncar(?string $valor, int $max): ?string
    {
        if ($valor === null || $valor === '') {
            return $valor;
        }
        if (mb_strlen($valor) <= $max) {
            return $valor;
        }

        return mb_substr($valor, 0, max(0, $max - 3)).'...';
    }
}
