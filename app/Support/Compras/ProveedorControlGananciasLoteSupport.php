<?php

declare(strict_types=1);

namespace App\Support\Compras;

use Illuminate\Support\Facades\Cache;

/**
 * Lote grande de control de ganancias fuera del request HTTP (Apache corta a los 20 min).
 */
final class ProveedorControlGananciasLoteSupport
{
    public const TTL_SEGUNDOS = 21600;

    public static function debeSegundoPlano(array $filtros): bool
    {
        if (empty($filtros['consultar_arca'])) {
            return false;
        }

        $limite = (int) ($filtros['limite'] ?? 0);

        return $limite === 0 || $limite >= 150;
    }

    public static function id(array $filtros, int $usuarioId): string
    {
        $clave = [
            'usuario_id' => $usuarioId,
            'empresa_id' => (int) ($filtros['empresa_id'] ?? 0),
            'estado' => (string) ($filtros['estado'] ?? ''),
            'segmento' => (string) ($filtros['segmento'] ?? ''),
            'proveedor_id' => (int) ($filtros['proveedor_id'] ?? 0),
            'solo_discrepancias' => ! empty($filtros['solo_discrepancias']),
            'consultar_arca' => ! empty($filtros['consultar_arca']),
            'limite' => (int) ($filtros['limite'] ?? 0),
        ];

        return substr(sha1(json_encode($clave)), 0, 16);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function asegurar(string $jobId, array $filtros): array
    {
        $actual = self::leer($jobId);
        if ($actual !== null && ($actual['estado'] ?? '') === 'listo' && isset($actual['resultado'])) {
            return $actual;
        }

        if ($actual !== null && ($actual['estado'] ?? '') === 'corriendo' && ! self::estancado($actual)) {
            return $actual;
        }

        $job = [
            'estado' => 'corriendo',
            'procesados' => 0,
            'total' => 0,
            'mensaje' => 'Iniciando consulta ARCA…',
            'error' => null,
            'filtros' => $filtros,
            'resultado' => null,
            'iniciado_at' => now()->toDateTimeString(),
            'actualizado_at' => now()->toDateTimeString(),
        ];
        Cache::put(self::clave($jobId), $job, self::TTL_SEGUNDOS);
        self::disparar($jobId);

        return $job;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function leer(string $jobId): ?array
    {
        $job = Cache::get(self::clave($jobId));

        return is_array($job) ? $job : null;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function actualizar(string $jobId, array $datos): void
    {
        $job = self::leer($jobId) ?? [];
        $job = array_merge($job, $datos, [
            'actualizado_at' => now()->toDateTimeString(),
        ]);
        Cache::put(self::clave($jobId), $job, self::TTL_SEGUNDOS);
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private static function estancado(array $job): bool
    {
        $marca = strtotime((string) ($job['actualizado_at'] ?? ''));
        if ($marca === false) {
            return true;
        }

        return (time() - $marca) > 180;
    }

    private static function disparar(string $jobId): void
    {
        $php = is_executable('/usr/bin/php') ? '/usr/bin/php' : 'php';
        $log = storage_path('logs/control-ganancias-lote.log');
        $cmd = $php.' '.escapeshellarg(base_path('artisan'))
            .' compras:control-ganancias-reporte '.escapeshellarg($jobId)
            .' >> '.escapeshellarg($log).' 2>&1 &';
        exec($cmd);
    }

    private static function clave(string $jobId): string
    {
        return 'compras_control_ganancias_lote_'.$jobId;
    }
}
