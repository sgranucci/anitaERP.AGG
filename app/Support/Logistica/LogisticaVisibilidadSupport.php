<?php

namespace App\Support\Logistica;

use App\Models\Admin\Rol;
use App\Models\Logistica\LogisticaHabilitacion;
use App\Models\Seguridad\Usuario;
use App\Support\Seguridad\UsuarioOperativoSupport;

final class LogisticaVisibilidadSupport
{
    public static function habilitado(string $nivel, int $usuarioId, int $referenciaId): bool
    {
        if ($usuarioId <= 0 || $referenciaId <= 0) {
            return false;
        }

        $columna = self::columna($nivel);
        $filaUsuario = LogisticaHabilitacion::query()
            ->where('nivel', $nivel)
            ->where('alcance', 'usuario')
            ->where('usuario_id', $usuarioId)
            ->where($columna, $referenciaId)
            ->first();
        if ($filaUsuario !== null) {
            return (bool) $filaUsuario->habilitado;
        }

        $rolIds = self::rolIds($usuarioId);
        if ($rolIds === []) {
            return false;
        }

        return LogisticaHabilitacion::query()
            ->where('nivel', $nivel)
            ->where('alcance', 'rol')
            ->whereIn('rol_id', $rolIds)
            ->where($columna, $referenciaId)
            ->where('habilitado', true)
            ->exists();
    }

    /**
     * @return list<int>
     */
    public static function rolIds(int $usuarioId): array
    {
        $usuario = Usuario::query()->with('roles:id')->find($usuarioId);
        if ($usuario === null) {
            return [];
        }

        return $usuario->roles->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public static function resolverRol(?int $id, ?string $nombre): ?Rol
    {
        if ($id !== null && $id > 0) {
            $rol = Rol::query()->find($id);
            if ($rol !== null) {
                return $rol;
            }
        }
        $nombre = trim((string) $nombre);
        if ($nombre === '') {
            return null;
        }

        return Rol::query()->where('nombre', $nombre)->first();
    }

    public static function resolverUsuario(?int $id, ?string $codigo): ?Usuario
    {
        if ($id !== null && $id > 0) {
            $usuario = Usuario::query()->find($id);
            if (UsuarioOperativoSupport::esOperativo($usuario)) {
                return $usuario;
            }
        }
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return null;
        }
        $usuario = Usuario::query()->where('usuario', $codigo)->soloActivos()->first();

        return UsuarioOperativoSupport::esOperativo($usuario) ? $usuario : null;
    }

    private static function columna(string $nivel): string
    {
        return match ($nivel) {
            'tipo' => 'tipo_solicitud_id',
            'categoria' => 'catalogo_categoria_id',
            'item' => 'articulo_id',
            default => throw new \InvalidArgumentException('Nivel de visibilidad desconocido.'),
        };
    }
}
