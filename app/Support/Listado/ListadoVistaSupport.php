<?php

declare(strict_types=1);

namespace App\Support\Listado;

use App\Models\Listado\ListadoVista;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Vistas guardadas de listados (filtros + columnas). Etapa 2/3 workbench.
 */
final class ListadoVistaSupport
{
    public static function tablasDisponibles(): bool
    {
        return Schema::hasTable('listado_vista');
    }

    /**
     * @return Collection<int, ListadoVista>
     */
    public static function listarParaUsuario(string $recurso, ?int $usuarioId): Collection
    {
        if (! self::tablasDisponibles() || $usuarioId === null || $usuarioId <= 0) {
            return collect();
        }

        return ListadoVista::query()
            ->where('recurso', $recurso)
            ->where(function ($q) use ($usuarioId) {
                $q->where('usuario_id', $usuarioId)
                    ->orWhere('compartida', true);
            })
            ->orderByDesc('es_default')
            ->orderBy('nombre')
            ->get();
    }

    public static function findParaUsuario(int $id, string $recurso, ?int $usuarioId): ?ListadoVista
    {
        if (! self::tablasDisponibles() || $usuarioId === null || $usuarioId <= 0) {
            return null;
        }

        /** @var ListadoVista|null $vista */
        $vista = ListadoVista::query()
            ->whereKey($id)
            ->where('recurso', $recurso)
            ->where(function ($q) use ($usuarioId) {
                $q->where('usuario_id', $usuarioId)
                    ->orWhere('compartida', true);
            })
            ->first();

        return $vista;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $columnas
     */
    public static function guardar(
        string $recurso,
        int $usuarioId,
        string $nombre,
        array $filtros,
        array $columnas,
        bool $esDefault = false,
        bool $compartida = false,
        ?int $id = null
    ): ?ListadoVista {
        if (! self::tablasDisponibles() || $usuarioId <= 0) {
            return null;
        }

        $nombre = mb_substr(trim($nombre), 0, 120);
        if ($nombre === '') {
            return null;
        }

        if ($esDefault) {
            ListadoVista::query()
                ->where('recurso', $recurso)
                ->where('usuario_id', $usuarioId)
                ->update(['es_default' => false]);
        }

        $attrs = [
            'recurso' => $recurso,
            'usuario_id' => $usuarioId,
            'nombre' => $nombre,
            'filtros_json' => $filtros,
            'columnas_json' => array_values($columnas),
            'es_default' => $esDefault,
            'compartida' => $compartida,
        ];

        if ($id !== null && $id > 0) {
            $vista = ListadoVista::query()
                ->whereKey($id)
                ->where('recurso', $recurso)
                ->where('usuario_id', $usuarioId)
                ->first();
            if (! $vista) {
                return null;
            }
            $vista->fill($attrs);
            $vista->save();

            return $vista;
        }

        return ListadoVista::query()->create($attrs);
    }

    public static function eliminar(int $id, string $recurso, int $usuarioId): bool
    {
        if (! self::tablasDisponibles() || $usuarioId <= 0) {
            return false;
        }

        $vista = ListadoVista::query()
            ->whereKey($id)
            ->where('recurso', $recurso)
            ->where('usuario_id', $usuarioId)
            ->first();

        if (! $vista) {
            return false;
        }

        return (bool) $vista->delete();
    }

    public static function defaultDelUsuario(string $recurso, ?int $usuarioId): ?ListadoVista
    {
        if (! self::tablasDisponibles() || $usuarioId === null || $usuarioId <= 0) {
            return null;
        }

        return ListadoVista::query()
            ->where('recurso', $recurso)
            ->where('usuario_id', $usuarioId)
            ->where('es_default', true)
            ->first();
    }
}
