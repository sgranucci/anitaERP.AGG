<?php

namespace App\Support\Compras;

use App\Models\Admin\Rol;
use App\Models\Compras\Requisicion;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Alcance de listado y acceso a requisiciones (cabecera centrocosto_id = CC de origen):
 *
 * - listar-requisicion: solo las que cargó (creousuario_id).
 * - usuario-requisicion-resto: las de su CC y el del rol (incluye las propias).
 * - usuario-requisicion-compras: todas las de sus empresas asignadas (todos los CC).
 * - listar-todas-requisicion: sin restricción de alcance (supervisión / contaduría).
 *
 * Tablero de seguimiento (`aplicarFiltroTableroSeguimiento`): Enc-compras / listar-todas
 * sin recorte por CC; resto sectores = origen o centrocostodestino_arbol_id
 * del usuario o del rol.
 */
final class RequisicionVisibilidadSupport
{
    public const PERMISO_VER_TODAS = 'listar-todas-requisicion';

    public const PERMISO_USUARIO_COMPRAS = 'usuario-requisicion-compras';

    public const PERMISO_USUARIO_RESTO = 'usuario-requisicion-resto';

    public static function puedeVerTodasSinRestriccion(): bool
    {
        return can(self::PERMISO_VER_TODAS, false);
    }

    public static function esUsuarioCompras(): bool
    {
        return can(self::PERMISO_USUARIO_COMPRAS, false);
    }

    public static function esUsuarioRestoSectores(): bool
    {
        return can(self::PERMISO_USUARIO_RESTO, false);
    }

    /** @deprecated Use puedeVerTodasSinRestriccion() */
    public static function puedeVerTodas(): bool
    {
        return self::puedeVerTodasSinRestriccion();
    }

    public static function centrocostoOrigenUsuario(): ?int
    {
        $sesion = (int) (session('centrocosto_id') ?? 0);
        if ($sesion > 0) {
            return $sesion;
        }

        $id = (int) (Auth::user()->centrocosto_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * Centros que ve el permiso resto: el del usuario y el de su rol activo
     * (o de los roles de la sesión, si todavía no eligió uno).
     *
     * @return list<int>
     */
    public static function centrocostosAlcance(): array
    {
        $ids = [];

        $sesion = (int) (session('centrocosto_id') ?? 0);
        if ($sesion > 0) {
            $ids[] = $sesion;
        }

        $usuarioCc = (int) (Auth::user()->centrocosto_id ?? 0);
        if ($usuarioCc > 0) {
            $ids[] = $usuarioCc;
        }

        foreach (self::centrocostosDeRoles() as $id) {
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return list<int> */
    public static function empresaIdsAsignadas(): array
    {
        return collect(Session::get('usuario_empresas', []))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  EloquentBuilder<\App\Models\Compras\Requisicion>|QueryBuilder  $query
     */
    public static function aplicarFiltroListado(EloquentBuilder|QueryBuilder $query, string $tabla = 'requisicion'): void
    {
        $tabla = trim($tabla) !== '' ? $tabla : 'requisicion';

        if (self::puedeVerTodasSinRestriccion()) {
            return;
        }

        self::aplicarFiltroEmpresasAsignadas($query, $tabla);

        if (self::esUsuarioCompras()) {
            self::aplicarFiltroOficinaComprasSiActivo($query, $tabla);

            return;
        }

        if (self::esUsuarioRestoSectores()) {
            self::aplicarFiltroCentrocostoOrigen($query, $tabla);

            return;
        }

        self::aplicarFiltroSoloCreador($query, $tabla);
    }

    /**
     * Tablero de seguimiento: Enc-compras / listar-todas sin recorte por CC.
     * Resto sectores: origen o CC destino del árbol del usuario y del rol.
     *
     * @param  EloquentBuilder<\App\Models\Compras\Requisicion>|QueryBuilder  $query
     */
    public static function aplicarFiltroTableroSeguimiento(EloquentBuilder|QueryBuilder $query, string $tabla = 'requisicion'): void
    {
        $tabla = trim($tabla) !== '' ? $tabla : 'requisicion';

        if (self::puedeVerTodasSinRestriccion()) {
            return;
        }

        self::aplicarFiltroEmpresasAsignadas($query, $tabla);

        if (self::esUsuarioCompras()) {
            self::aplicarFiltroOficinaComprasSiActivo($query, $tabla);

            return;
        }

        if (self::esUsuarioRestoSectores()) {
            $centrocostos = self::centrocostosAlcance();
            if ($centrocostos !== []) {
                self::aplicarFiltroCentrocostosOrigenODestinoArbol($query, $centrocostos, $tabla);

                return;
            }
            self::aplicarFiltroSoloCreador($query, $tabla);

            return;
        }

        self::aplicarFiltroSoloCreador($query, $tabla);
    }

    /**
     * @param  EloquentBuilder<\App\Models\Compras\Requisicion>|QueryBuilder  $query
     */
    public static function aplicarFiltroCentrocostoOrigenODestinoArbol(
        EloquentBuilder|QueryBuilder $query,
        int $centrocostoId,
        string $tabla = 'requisicion'
    ): void {
        self::aplicarFiltroCentrocostosOrigenODestinoArbol($query, [$centrocostoId], $tabla);
    }

    /**
     * @param  list<int>  $centrocostoIds
     * @param  EloquentBuilder<\App\Models\Compras\Requisicion>|QueryBuilder  $query
     */
    public static function aplicarFiltroCentrocostosOrigenODestinoArbol(
        EloquentBuilder|QueryBuilder $query,
        array $centrocostoIds,
        string $tabla = 'requisicion'
    ): void {
        $centrocostoIds = self::normalizarIds($centrocostoIds);
        if ($centrocostoIds === []) {
            return;
        }

        $tabla = trim($tabla) !== '' ? $tabla : 'requisicion';
        $query->where(function ($q) use ($tabla, $centrocostoIds) {
            $q->whereIn($tabla.'.centrocosto_id', $centrocostoIds)
                ->orWhereIn($tabla.'.centrocostodestino_arbol_id', $centrocostoIds);
        });
    }

    /**
     * @param  EloquentBuilder<\App\Models\Compras\Requisicion>|QueryBuilder  $query
     */
    private static function aplicarFiltroEmpresasAsignadas(EloquentBuilder|QueryBuilder $query, string $tabla): void
    {
        $empresas = self::empresaIdsAsignadas();
        if (count($empresas) >= 1) {
            $query->whereIn($tabla.'.empresa_id', $empresas);
        }
    }

    /**
     * @param  EloquentBuilder<\App\Models\Compras\Requisicion>|QueryBuilder  $query
     */
    private static function aplicarFiltroSoloCreador(EloquentBuilder|QueryBuilder $query, string $tabla): void
    {
        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId > 0) {
            $query->where($tabla.'.creousuario_id', $usuarioId);
        }
    }

    /**
     * @param  EloquentBuilder<\App\Models\Compras\Requisicion>|QueryBuilder  $query
     */
    private static function aplicarFiltroCentrocostoOrigen(EloquentBuilder|QueryBuilder $query, string $tabla): void
    {
        $centrocostos = self::centrocostosAlcance();
        if ($centrocostos !== []) {
            $query->whereIn($tabla.'.centrocosto_id', $centrocostos);

            return;
        }

        self::aplicarFiltroSoloCreador($query, $tabla);
    }

    /**
     * Rol activo de la sesión. Si todavía no eligió uno, los roles cargados en la sesión
     * o los de la ficha.
     *
     * @return list<int>
     */
    private static function centrocostosDeRoles(): array
    {
        $rolId = (int) (session('rol_id') ?? 0);
        if ($rolId > 0) {
            return self::centrocostosDeRolIds([$rolId]);
        }

        $ids = [];
        $rolIds = [];
        foreach ((array) session('roles', []) as $rol) {
            if (! is_array($rol)) {
                continue;
            }
            $cc = (int) ($rol['centrocosto_id'] ?? 0);
            if ($cc > 0) {
                $ids[] = $cc;

                continue;
            }
            $id = (int) ($rol['id'] ?? 0);
            if ($id > 0) {
                $rolIds[] = $id;
            }
        }
        if ($rolIds !== []) {
            $ids = array_merge($ids, self::centrocostosDeRolIds($rolIds));
        }
        if ($ids !== []) {
            return array_values(array_unique($ids));
        }

        $usuario = Auth::user();
        if ($usuario && method_exists($usuario, 'roles')) {
            foreach ($usuario->roles()->pluck('rol.centrocosto_id') as $cc) {
                $cc = (int) $cc;
                if ($cc > 0) {
                    $ids[] = $cc;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $rolIds
     * @return list<int>
     */
    private static function centrocostosDeRolIds(array $rolIds): array
    {
        $rolIds = self::normalizarIds($rolIds);
        if ($rolIds === []) {
            return [];
        }

        return Rol::query()
            ->whereIn('id', $rolIds)
            ->pluck('centrocosto_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private static function normalizarIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $ids),
            static fn (int $id) => $id > 0
        )));
    }

    /**
     * @param  EloquentBuilder<\App\Models\Compras\Requisicion>|QueryBuilder  $query
     */
    private static function aplicarFiltroOficinaComprasSiActivo(EloquentBuilder|QueryBuilder $query, string $tabla): void
    {
        if (! config('requisicion.filtro_oficina_compras_activo', false)) {
            return;
        }

        $oficinaCompraId = Auth::user()->oficinacompra_id ?? null;
        if ($oficinaCompraId) {
            $query->where($tabla.'.oficinacompra_id', $oficinaCompraId);
        }
    }

    public static function requisicionAccesiblePorId(int $requisicionId): bool
    {
        if ($requisicionId <= 0) {
            return false;
        }

        if (self::puedeVerTodasSinRestriccion()) {
            return Requisicion::query()->whereKey($requisicionId)->exists();
        }

        $query = Requisicion::query()->where('requisicion.id', $requisicionId);
        self::aplicarFiltroListado($query);

        return $query->exists();
    }
}
