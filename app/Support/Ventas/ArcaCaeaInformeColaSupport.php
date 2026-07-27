<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Jobs\Ventas\InformarArcaCaeaPeriodoJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Estado de presentación CAEA en cola (un job por quincena).
 * Flag compartido (no por usuario) para deshabilitar el avión en la UI.
 */
final class ArcaCaeaInformeColaSupport
{
    public static function claveActivo(int $arcaCaeaId): string
    {
        return 'arca-caea-informe-activo-'.$arcaCaeaId;
    }

    public static function claveProgreso(int $arcaCaeaId): string
    {
        return 'arca-caea-informe-progreso-'.$arcaCaeaId;
    }

    public static function ttlSegundos(): int
    {
        return max(300, (int) config('arca.caea.informe_job_unique_for', 7200));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function marcarActivo(int $arcaCaeaId, int $usuarioId, array $extra = []): void
    {
        $payload = array_merge([
            'arca_caea_id' => $arcaCaeaId,
            'usuario_id' => $usuarioId,
            'fase' => 'encolado',
            'informados' => 0,
            'lotes' => 0,
            'updated_at' => now()->toIso8601String(),
        ], $extra);

        $ttl = now()->addSeconds(self::ttlSegundos());
        Cache::put(self::claveActivo($arcaCaeaId), $payload, $ttl);
        Cache::put(self::claveProgreso($arcaCaeaId), $payload, $ttl);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function guardarProgreso(int $arcaCaeaId, int $usuarioId, array $data): void
    {
        $prev = Cache::get(self::claveProgreso($arcaCaeaId), []);
        if (! is_array($prev)) {
            $prev = [];
        }

        $payload = array_merge($prev, $data, [
            'arca_caea_id' => $arcaCaeaId,
            'usuario_id' => $usuarioId,
            'updated_at' => now()->toIso8601String(),
        ]);

        $ttl = now()->addSeconds(self::ttlSegundos());
        Cache::put(self::claveProgreso($arcaCaeaId), $payload, $ttl);

        // Compat: progreso por usuario (mail failed / overlays viejos).
        Cache::put(
            'arca-caea-informe-progreso-'.$arcaCaeaId.'-'.$usuarioId,
            $payload,
            $ttl,
        );

        $fase = (string) ($payload['fase'] ?? '');
        if ($fase === 'fin') {
            Cache::forget(self::claveActivo($arcaCaeaId));

            return;
        }

        Cache::put(self::claveActivo($arcaCaeaId), [
            'arca_caea_id' => $arcaCaeaId,
            'usuario_id' => $usuarioId,
            'fase' => $fase !== '' ? $fase : 'lote',
            'informados' => (int) ($payload['informados'] ?? 0),
            'lotes' => (int) ($payload['lotes'] ?? 0),
            'updated_at' => $payload['updated_at'],
        ], $ttl);
    }

    public static function liberar(int $arcaCaeaId): void
    {
        Cache::forget(self::claveActivo($arcaCaeaId));

        $progreso = Cache::get(self::claveProgreso($arcaCaeaId));
        if (is_array($progreso)) {
            $progreso['fase'] = 'fin';
            $progreso['updated_at'] = now()->toIso8601String();
            Cache::put(self::claveProgreso($arcaCaeaId), $progreso, now()->addHours(6));
        }
    }

    public static function estaActivo(int $arcaCaeaId): bool
    {
        if ($arcaCaeaId < 1) {
            return false;
        }

        // 1) Job todavía en tabla jobs (pendiente o reserved por el worker).
        if (self::hayJobPendienteEnCola($arcaCaeaId)) {
            return true;
        }

        // 2) Flag / progreso en curso, pero no confiar en "encolado" eterno sin job
        //    (workers viejos no llamaban liberar y la UI quedaba colgada).
        $activo = Cache::get(self::claveActivo($arcaCaeaId));
        if (self::payloadIndicaEnCurso($activo) && ! self::esEncoladoHuerfano($activo)) {
            return true;
        }

        $progreso = Cache::get(self::claveProgreso($arcaCaeaId));
        if (self::payloadIndicaEnCurso($progreso) && ! self::esEncoladoHuerfano($progreso)) {
            return true;
        }

        // 3) Lock ShouldBeUnique en Redis (Laravel Lock no siempre responde a Cache::has).
        if (self::hayLockUniqueRedis($arcaCaeaId)) {
            return true;
        }

        // Flag fantasma: limpiar encolado sin job.
        if (self::esEncoladoHuerfano($activo) || self::esEncoladoHuerfano($progreso)) {
            self::liberar($arcaCaeaId);
        }

        return false;
    }

    /**
     * @param  mixed  $payload
     */
    private static function esEncoladoHuerfano(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }
        if ((string) ($payload['fase'] ?? '') !== 'encolado') {
            return false;
        }

        $updatedAt = (string) ($payload['updated_at'] ?? '');
        if ($updatedAt === '') {
            return true;
        }

        try {
            // Tras 2 minutos sin job en cola, el "encolado" es basura de UI.
            return Carbon::parse($updatedAt)->lessThan(now()->subMinutes(2));
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * @return array{activo: bool, puede_presentar: bool, leyenda: string, fase: string, informados: int, lotes: int}
     */
    public static function estadoUi(int $arcaCaeaId, bool $puedePresentarBase): array
    {
        $activo = self::estaActivo($arcaCaeaId);
        $progreso = self::progreso($arcaCaeaId);
        $fase = is_array($progreso) ? (string) ($progreso['fase'] ?? '') : '';

        return [
            'activo' => $activo,
            'puede_presentar' => $activo ? false : $puedePresentarBase,
            'leyenda' => $activo
                ? self::leyendaProcesoActivo($progreso)
                : '',
            'fase' => $fase,
            'informados' => (int) ($progreso['informados'] ?? 0),
            'lotes' => (int) ($progreso['lotes'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function progreso(int $arcaCaeaId): ?array
    {
        $data = Cache::get(self::claveProgreso($arcaCaeaId));
        if (is_array($data) && $data !== []) {
            return $data;
        }

        $activo = Cache::get(self::claveActivo($arcaCaeaId));

        return is_array($activo) ? $activo : null;
    }

    public static function leyendaProcesoActivo(?array $progreso): string
    {
        if ($progreso === null || $progreso === []) {
            return 'Presentación en segundo plano…';
        }

        $fase = (string) ($progreso['fase'] ?? '');
        $informados = (int) ($progreso['informados'] ?? 0);
        $lotes = (int) ($progreso['lotes'] ?? 0);

        if ($fase === 'encolado') {
            return 'Encolado: esperando worker…';
        }
        if ($fase === 'inicio') {
            return 'Procesando en segundo plano…';
        }
        if ($fase === 'lote') {
            $partes = ['Procesando en 2.º plano'];
            if ($lotes > 0) {
                $partes[] = 'lote '.$lotes;
            }
            if ($informados > 0) {
                $partes[] = $informados.' informado(s)';
            }

            return implode(' · ', $partes);
        }

        return 'Presentación en segundo plano…';
    }

    /**
     * @param  mixed  $payload
     */
    private static function payloadIndicaEnCurso(mixed $payload): bool
    {
        if (! is_array($payload) || $payload === []) {
            return false;
        }

        $fase = (string) ($payload['fase'] ?? '');
        if (! in_array($fase, ['encolado', 'inicio', 'lote'], true)) {
            return false;
        }

        $updatedAt = (string) ($payload['updated_at'] ?? '');
        if ($updatedAt === '') {
            return true;
        }

        try {
            $updated = Carbon::parse($updatedAt);
        } catch (\Throwable) {
            return true;
        }

        // Evitar basura vieja en cache: si no hubo update reciente y no hay job, no bloquear.
        return $updated->greaterThan(now()->subSeconds(self::ttlSegundos()));
    }

    private static function hayJobPendienteEnCola(int $arcaCaeaId): bool
    {
        if (config('queue.default') !== 'database') {
            return false;
        }

        // Serialización PHP del job (promoted property): arcaCaeaId";i:{id};
        $needles = [
            'arcaCaeaId";i:'.$arcaCaeaId.';',
            'arcaCaeaId";i:'.$arcaCaeaId.'"',
            '"arcaCaeaId";i:'.$arcaCaeaId.';',
        ];

        $query = DB::table('jobs')->where('payload', 'like', '%InformarArcaCaeaPeriodoJob%');
        $query->where(function ($q) use ($needles): void {
            foreach ($needles as $needle) {
                $q->orWhere('payload', 'like', '%'.$needle.'%');
            }
        });

        return $query->exists();
    }

    private static function hayLockUniqueRedis(int $arcaCaeaId): bool
    {
        $suffixes = [
            'arca-caea-informe-'.$arcaCaeaId,
            'arca-caea-informe-'.$arcaCaeaId.'-errores',
        ];

        try {
            foreach (['cache', 'default'] as $connection) {
                $redis = Redis::connection($connection);
                foreach ($suffixes as $suffix) {
                    $patterns = [
                        '*laravel_unique_job*InformarArcaCaeaPeriodoJob*'.$suffix.'*',
                        '*unique_job*'.$suffix.'*',
                    ];
                    foreach ($patterns as $pattern) {
                        $keys = $redis->keys($pattern);
                        if (is_array($keys) && $keys !== []) {
                            return true;
                        }
                    }
                    $exact = 'laravel_unique_job:'.InformarArcaCaeaPeriodoJob::class.':'.$suffix;
                    $prefixed = (string) config('cache.prefix').$exact;
                    if ($redis->exists($exact) || ($prefixed !== $exact && $redis->exists($prefixed))) {
                        return true;
                    }
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
