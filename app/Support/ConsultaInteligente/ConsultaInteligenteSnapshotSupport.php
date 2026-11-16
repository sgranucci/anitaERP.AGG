<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use App\Models\Direccion\ConsultaInteligenteSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshots persistidos de consultas pesadas (más allá del cache TTL corto).
 */
final class ConsultaInteligenteSnapshotSupport
{
    public const TTL_HORAS = 48;

    public const MAX_FILAS = 500;

    public const MAX_POR_USUARIO = 20;

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $columnas
     * @param  list<array<string, string>>  $filas  celdas ya renderizadas a texto
     */
    public static function guardar(
        string $entidad,
        string $titulo,
        array $filtros,
        array $columnas,
        array $filas,
        ?int $usuarioId = null
    ): ?ConsultaInteligenteSnapshot {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        if ($usuarioId < 1 || ! self::tablaLista()) {
            return null;
        }

        self::purgarExpirados($usuarioId);
        $existentes = ConsultaInteligenteSnapshot::query()
            ->where('usuario_id', $usuarioId)
            ->count();
        if ($existentes >= self::MAX_POR_USUARIO) {
            $viejos = ConsultaInteligenteSnapshot::query()
                ->where('usuario_id', $usuarioId)
                ->orderBy('id')
                ->limit($existentes - self::MAX_POR_USUARIO + 1)
                ->get();
            foreach ($viejos as $viejo) {
                $viejo->delete();
            }
        }

        $filas = array_slice($filas, 0, self::MAX_FILAS);

        return ConsultaInteligenteSnapshot::query()->create([
            'usuario_id' => $usuarioId,
            'entidad' => $entidad,
            'titulo' => mb_substr(trim($titulo) !== '' ? trim($titulo) : $entidad, 0, 120),
            'filtros_json' => $filtros,
            'columnas_json' => array_values($columnas),
            'filas_json' => $filas,
            'filas_count' => count($filas),
            'expires_at' => Carbon::now()->addHours(self::TTL_HORAS),
        ]);
    }

    /**
     * @return list<ConsultaInteligenteSnapshot>
     */
    public static function listar(?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        if ($usuarioId < 1 || ! self::tablaLista()) {
            return [];
        }
        self::purgarExpirados($usuarioId);

        return ConsultaInteligenteSnapshot::query()
            ->where('usuario_id', $usuarioId)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->limit(self::MAX_POR_USUARIO)
            ->get()
            ->all();
    }

    public static function encontrar(int $id, ?int $usuarioId = null): ?ConsultaInteligenteSnapshot
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        if ($usuarioId < 1 || ! self::tablaLista()) {
            return null;
        }
        $snap = ConsultaInteligenteSnapshot::query()
            ->where('id', $id)
            ->where('usuario_id', $usuarioId)
            ->first();
        if (! $snap) {
            return null;
        }
        if ($snap->expires_at && $snap->expires_at->isPast()) {
            $snap->delete();

            return null;
        }

        return $snap;
    }

    public static function eliminar(int $id, ?int $usuarioId = null): bool
    {
        $snap = self::encontrar($id, $usuarioId);
        if (! $snap) {
            return false;
        }
        $snap->delete();

        return true;
    }

    private static function purgarExpirados(int $usuarioId): void
    {
        ConsultaInteligenteSnapshot::query()
            ->where('usuario_id', $usuarioId)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get()
            ->each(static fn (ConsultaInteligenteSnapshot $s) => $s->delete());
    }

    private static function tablaLista(): bool
    {
        try {
            return Schema::hasTable('consulta_inteligente_snapshot');
        } catch (\Throwable) {
            return false;
        }
    }
}
