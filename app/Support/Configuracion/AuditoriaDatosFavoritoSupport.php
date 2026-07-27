<?php

namespace App\Support\Configuracion;

use App\Models\Seguridad\UsuarioAuditoriaFavorito;
use Illuminate\Support\Facades\Schema;

/**
 * Favoritos de auditoría de datos por usuario (chincheta, como barra de tareas).
 */
class AuditoriaDatosFavoritoSupport
{
    public const MAX_FAVORITOS = 30;

    /**
     * @return list<array{auditable_type: string, etiqueta: string, tabla: string, modulo: string, orden: int}>
     */
    public static function listar(?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        if ($usuarioId <= 0 || ! Schema::hasTable('usuario_auditoria_favorito')) {
            return [];
        }

        self::sembrarDesdeConfigSiVacio($usuarioId);

        $filas = UsuarioAuditoriaFavorito::query()
            ->where('usuario_id', $usuarioId)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($filas as $fila) {
            $type = (string) $fila->auditable_type;
            if ($type === '' || ! str_starts_with($type, 'App\\Models\\')) {
                continue;
            }
            $out[] = [
                'auditable_type' => $type,
                'etiqueta' => (string) ($fila->etiqueta ?: AuditoriaDatosCatalogoSupport::etiquetaTipo($type)),
                'tabla' => AuditoriaDatosCatalogoSupport::inferirTablaPublica($type),
                'modulo' => AuditoriaDatosCatalogoSupport::inferirModuloPublico($type),
                'orden' => (int) $fila->orden,
            ];
        }

        return $out;
    }

    /** @return list<string> */
    public static function typesAnclados(?int $usuarioId = null): array
    {
        return array_column(self::listar($usuarioId), 'auditable_type');
    }

    public static function estaAnclado(string $auditableType, ?int $usuarioId = null): bool
    {
        return in_array($auditableType, self::typesAnclados($usuarioId), true);
    }

    /**
     * @return list<array{auditable_type: string, etiqueta: string, tabla: string, modulo: string, orden: int}>
     */
    public static function anclar(string $auditableType, ?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        $auditableType = self::normalizarType($auditableType);

        if (! self::existeEnCatalogoOConfig($auditableType)) {
            throw new \InvalidArgumentException('Modelo no válido para marcar como favorito.');
        }

        $cantidad = UsuarioAuditoriaFavorito::query()->where('usuario_id', $usuarioId)->count();
        if ($cantidad >= self::MAX_FAVORITOS) {
            throw new \InvalidArgumentException('Alcanzó el máximo de '.self::MAX_FAVORITOS.' favoritos de auditoría.');
        }

        $existe = UsuarioAuditoriaFavorito::query()
            ->where('usuario_id', $usuarioId)
            ->where('auditable_type', $auditableType)
            ->exists();

        if (! $existe) {
            $orden = (int) UsuarioAuditoriaFavorito::query()
                ->where('usuario_id', $usuarioId)
                ->max('orden');

            $meta = config('auditoria_datos.favoritos.'.$auditableType);
            $etiqueta = is_array($meta) ? (string) ($meta['etiqueta'] ?? '') : '';
            if ($etiqueta === '') {
                $etiqueta = AuditoriaDatosCatalogoSupport::etiquetaTipo($auditableType);
            }

            UsuarioAuditoriaFavorito::query()->create([
                'usuario_id' => $usuarioId,
                'auditable_type' => $auditableType,
                'etiqueta' => $etiqueta,
                'orden' => $orden + 1,
            ]);
        }

        return self::listar($usuarioId);
    }

    /**
     * @return list<array{auditable_type: string, etiqueta: string, tabla: string, modulo: string, orden: int}>
     */
    public static function desanclar(string $auditableType, ?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        $auditableType = self::normalizarType($auditableType);

        UsuarioAuditoriaFavorito::query()
            ->where('usuario_id', $usuarioId)
            ->where('auditable_type', $auditableType)
            ->delete();

        return self::listar($usuarioId);
    }

    private static function sembrarDesdeConfigSiVacio(int $usuarioId): void
    {
        $tiene = UsuarioAuditoriaFavorito::query()->where('usuario_id', $usuarioId)->exists();
        if ($tiene) {
            return;
        }

        $orden = 0;
        foreach ((array) config('auditoria_datos.favoritos', []) as $type => $meta) {
            $orden++;
            UsuarioAuditoriaFavorito::query()->create([
                'usuario_id' => $usuarioId,
                'auditable_type' => $type,
                'etiqueta' => (string) ($meta['etiqueta'] ?? class_basename($type)),
                'orden' => $orden,
            ]);
        }
    }

    private static function normalizarType(string $type): string
    {
        $type = trim($type);
        if ($type === '' || ! str_starts_with($type, 'App\\Models\\')) {
            throw new \InvalidArgumentException('Tipo de modelo inválido.');
        }

        return $type;
    }

    private static function existeEnCatalogoOConfig(string $type): bool
    {
        if (isset(config('auditoria_datos.favoritos')[$type])) {
            return true;
        }
        foreach (AuditoriaDatosCatalogoSupport::catalogo() as $item) {
            if (($item['type'] ?? '') === $type) {
                return true;
            }
        }

        return false;
    }
}
