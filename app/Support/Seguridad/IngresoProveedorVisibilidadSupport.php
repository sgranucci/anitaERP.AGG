<?php

namespace App\Support\Seguridad;

use App\Models\Admin\Permiso;
use App\Models\Seguridad\IngresoProveedor;
use App\Support\Cache\PermisoCacheSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Alcance del listado y acceso a tickets de ingreso.
 *
 * Sin listar-todos: solo los tickets que cargó el usuario.
 * Autorizar / rechazar y la bandeja de pendientes ven todos (Seguridad).
 */
final class IngresoProveedorVisibilidadSupport
{
    public const PERMISO_VER_TODOS = 'listar-todos-ingreso-proveedor';

    public static function puedeVerTodos(): bool
    {
        return can(self::PERMISO_VER_TODOS, false);
    }

    public static function etiquetaAlcanceActivo(): ?string
    {
        if (self::puedeVerTodos()) {
            return null;
        }

        return 'Solo tickets cargados por usted';
    }

    /**
     * Empresas asignadas al usuario (usuario_empresa).
     * Sin filas: el ERP trata al usuario como acceso a todas las empresas.
     *
     * @return list<int>
     */
    public static function empresaIdsAsignadasDe(int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            return [];
        }

        return DB::table('usuario_empresa')
            ->where('usuario_id', $usuarioId)
            ->pluck('empresa_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Recorta un listado al establecimiento del usuario.
     * Lista vacía = sin recorte (acceso total).
     *
     * @param  Builder<\App\Models\Seguridad\IngresoProveedor>|\Illuminate\Database\Query\Builder  $query
     * @param  list<int>  $empresaIds
     */
    public static function aplicarEmpresas($query, array $empresaIds, string $column = 'empresa_id'): void
    {
        $empresaIds = array_values(array_unique(array_filter(
            array_map('intval', $empresaIds),
            fn (int $id) => $id > 0
        )));
        if ($empresaIds === []) {
            return;
        }

        $query->whereIn($column, $empresaIds);
    }

    /**
     * @param  Builder<\App\Models\Seguridad\IngresoProveedor>|\Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarFiltroEmpresaDelUsuario($query, int $usuarioId, string $column = 'empresa_id'): void
    {
        self::aplicarEmpresas($query, self::empresaIdsAsignadasDe($usuarioId), $column);
    }

    public static function puedeOperarEmpresa(int $empresaId, ?int $usuarioId = null): bool
    {
        $usuarioId = $usuarioId ?? (int) (Auth::id() ?? 0);
        $ids = self::empresaIdsAsignadasDe($usuarioId);
        if ($ids === []) {
            return true;
        }

        return $empresaId > 0 && in_array($empresaId, $ids, true);
    }

    public static function aplicarFiltroAlcance(Builder $query, string $alias = 'ingreso_proveedor'): void
    {
        if (self::puedeVerTodos()) {
            return;
        }

        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId > 0) {
            $query->where("{$alias}.usuario_id", $usuarioId);
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    public static function ticketAccesiblePorId(int $ticketId): bool
    {
        if ($ticketId <= 0) {
            return false;
        }

        if (self::puedeVerTodos() || can('autorizar-ingreso-proveedor', false)) {
            return IngresoProveedor::query()->whereKey($ticketId)->exists();
        }

        $query = IngresoProveedor::query()->where('ingreso_proveedor.id', $ticketId);
        self::aplicarFiltroAlcance($query);

        return $query->exists();
    }

    public static function abortSiNoAccesible(int $ticketId): void
    {
        if (! self::ticketAccesiblePorId($ticketId)) {
            abort(404);
        }
    }

    public static function requestEsConsulta(Request $request): bool
    {
        return $request->query('origen') === 'modal_consulta'
            || $request->input('origen') === 'modal_consulta'
            || $request->query('vista') === 'consulta'
            || $request->input('vista') === 'consulta';
    }

    /**
     * Mail de rechazo / recordatorio y solapa consulta: el que cargó el ticket
     * tiene que poder abrirlo aunque el rol activo no tenga editar/listar.
     */
    public static function puedeAbrirEnConsulta(int $ticketId): bool
    {
        if ($ticketId <= 0 || ! IngresoProveedor::query()->whereKey($ticketId)->exists()) {
            return false;
        }
        if (self::esDuenio($ticketId)) {
            return true;
        }
        if (self::tieneSlugEnAlgunRol([self::PERMISO_VER_TODOS, 'autorizar-ingreso-proveedor'])) {
            return true;
        }

        return self::ticketAccesiblePorId($ticketId);
    }

    public static function abortSiNoPuedeAbrirEnConsulta(int $ticketId): void
    {
        if (! self::puedeAbrirEnConsulta($ticketId)) {
            can('listar-ingreso-proveedor');
        }
    }

    public static function esDuenio(int $ticketId): bool
    {
        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId <= 0 || $ticketId <= 0) {
            return false;
        }

        return IngresoProveedor::query()
            ->whereKey($ticketId)
            ->where('usuario_id', $usuarioId)
            ->exists();
    }

    /**
     * @param  list<string>  $slugs
     */
    private static function tieneSlugEnAlgunRol(array $slugs): bool
    {
        foreach ($slugs as $slug) {
            if (can($slug, false)) {
                return true;
            }
        }
        foreach (self::rolIdsDelUsuario() as $rolId) {
            if ($rolId === (int) (session('rol_id') ?? 0)) {
                continue;
            }
            $permisos = PermisoCacheSupport::rememberSlugsPorRol($rolId, static function () use ($rolId) {
                return Permiso::query()
                    ->whereHas('roles', static function ($query) use ($rolId) {
                        $query->where('rol.id', $rolId);
                    })
                    ->pluck('slug')
                    ->all();
            });
            foreach ($slugs as $slug) {
                if (in_array($slug, $permisos, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<int> */
    private static function rolIdsDelUsuario(): array
    {
        $ids = [];
        $sesion = (int) (session('rol_id') ?? 0);
        if ($sesion > 0) {
            $ids[] = $sesion;
        }
        foreach ((array) session('roles', []) as $rol) {
            $id = (int) ($rol['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $usuario = Auth::user();
        if ($usuario && method_exists($usuario, 'roles')) {
            foreach ($usuario->roles()->pluck('rol.id') as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
