<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alejandro Blanco (ablanco) prueba el módulo Seguridad de punta a punta.
 * Completa menús/permisos en Enc-compras (único usuario del rol) y le suma enc-SEGURIDAD.
 */
return new class extends Migration
{
    private const USUARIO = 'ablanco';

    private const ROL_SEGURIDAD = 'enc-SEGURIDAD';

    /** @var list<string> */
    private const SLUGS = [
        'listar-ingreso-proveedor',
        'crear-ingreso-proveedor',
        'editar-ingreso-proveedor',
        'actualizar-ingreso-proveedor',
        'borrar-ingreso-proveedor',
        'autorizar-ingreso-proveedor',
        'listar-todos-ingreso-proveedor',
        'listar-ingreso-proveedor-catalogo',
        'crear-ingreso-proveedor-catalogo',
        'editar-ingreso-proveedor-catalogo',
        'actualizar-ingreso-proveedor-catalogo',
        'borrar-ingreso-proveedor-catalogo',
        'listar-reporte-tickets-ingreso',
        'listar-reporte-ingresos-planta',
        'listar-reporte-abono-sin-ingresos',
    ];

    public function up(): void
    {
        $usuarioId = (int) (DB::table('usuario')->where('usuario', self::USUARIO)->value('id') ?? 0);
        if ($usuarioId <= 0) {
            return;
        }

        $rolSeguridadId = (int) (DB::table('rol')->where('nombre', self::ROL_SEGURIDAD)->value('id') ?? 0);
        if ($rolSeguridadId > 0) {
            $this->asignarUsuarioRol($usuarioId, $rolSeguridadId);
        }

        foreach ($this->rolIdsDelUsuario($usuarioId) as $rolId) {
            if ($rolId === $rolSeguridadId) {
                continue;
            }
            $this->asignarModuloSeguridad($rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $usuarioId = (int) (DB::table('usuario')->where('usuario', self::USUARIO)->value('id') ?? 0);
        $rolSeguridadId = (int) (DB::table('rol')->where('nombre', self::ROL_SEGURIDAD)->value('id') ?? 0);
        if ($usuarioId > 0 && $rolSeguridadId > 0) {
            DB::table('usuario_rol')
                ->where('usuario_id', $usuarioId)
                ->where('rol_id', $rolSeguridadId)
                ->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @return list<int>
     */
    private function rolIdsDelUsuario(int $usuarioId): array
    {
        return DB::table('usuario_rol')
            ->where('usuario_id', $usuarioId)
            ->pluck('rol_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function asignarUsuarioRol(int $usuarioId, int $rolId): void
    {
        if (! DB::table('usuario_rol')->where('usuario_id', $usuarioId)->where('rol_id', $rolId)->exists()) {
            DB::table('usuario_rol')->insert([
                'usuario_id' => $usuarioId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function asignarModuloSeguridad(int $rolId): void
    {
        $moduloId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'Seguridad')
            ->value('id') ?? 0);
        if ($moduloId <= 0 || $rolId <= 0) {
            return;
        }

        foreach ($this->menuIdsDelModulo($moduloId) as $menuId) {
            $this->vincularMenuRol($menuId, $rolId);
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::SLUGS)->pluck('id');
        foreach ($permisoIds as $permisoId) {
            $this->vincularPermisoRol((int) $permisoId, $rolId);
        }
    }

    /**
     * @return list<int>
     */
    private function menuIdsDelModulo(int $moduloId): array
    {
        $ids = [$moduloId];
        $pendientes = [$moduloId];
        while ($pendientes !== []) {
            $hijos = DB::table('menu')->whereIn('menu_id', $pendientes)->pluck('id');
            $pendientes = [];
            foreach ($hijos as $hijoId) {
                $hijoId = (int) $hijoId;
                if ($hijoId <= 0 || in_array($hijoId, $ids, true)) {
                    continue;
                }
                $ids[] = $hijoId;
                $pendientes[] = $hijoId;
            }
        }

        return $ids;
    }

    private function vincularMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert([
                'menu_id' => $menuId,
                'rol_id' => $rolId,
            ]);
        }
    }

    private function vincularPermisoRol(int $permisoId, int $rolId): void
    {
        if ($permisoId <= 0 || $rolId <= 0) {
            return;
        }
        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }
};
