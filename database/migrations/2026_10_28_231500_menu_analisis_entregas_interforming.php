<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * INTERFORMING: menú Análisis de entregas y pendientes.
 */
return new class extends Migration
{
    private const MENU_URL = 'ventas/analisis-entregas';

    private const MENU_NOMBRE = 'Análisis de entregas';

    private const PERMISO_NOMBRE = 'Listar análisis de entregas';

    private const PERMISO_SLUG = 'listar-analisis-entregas';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        $parentId = $this->resolverMenuVentasId();
        if ($parentId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu($parentId, $orden);
        $permisoId = $this->upsertPermiso($menuId);

        $rolIds = DB::table('permiso_rol')
            ->join('permiso', 'permiso.id', '=', 'permiso_rol.permiso_id')
            ->where('permiso.slug', 'listar-pedidos')
            ->pluck('permiso_rol.rol_id')
            ->map(static fn ($id) => (int) $id)
            ->all();
        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($adminId > 0) {
            $rolIds[] = $adminId;
        }
        $rolIds = array_values(array_unique(array_filter($rolIds)));

        foreach ($rolIds as $rolId) {
            $this->asegurarMenuRol($parentId, $rolId);
            $this->asegurarMenuRol($menuId, $rolId);
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esInterforming()) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuVentasId(): int
    {
        $pedidoMenu = DB::table('menu')->where('url', 'ventas/pedido')->first();
        if ($pedidoMenu && (int) ($pedidoMenu->menu_id ?? 0) > 0) {
            return (int) $pedidoMenu->menu_id;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'like', '%Ventas%')
            ->value('id') ?? 0);
    }

    private function upsertMenu(int $parentId, int $orden): int
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $now = now();
        if ($menuId > 0) {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::MENU_NOMBRE,
                'menu_id' => $parentId,
                'icono' => 'fa-truck',
                'updated_at' => $now,
            ]);

            return $menuId;
        }

        return (int) DB::table('menu')->insertGetId([
            'nombre' => self::MENU_NOMBRE,
            'url' => self::MENU_URL,
            'menu_id' => $parentId,
            'orden' => $orden,
            'icono' => 'fa-truck',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function upsertPermiso(int $menuId): int
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $now = now();
        if ($permisoId === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => self::PERMISO_NOMBRE,
                'slug' => self::PERMISO_SLUG,
                'menu_id' => $menuId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('permiso')->where('id', $permisoId)->update([
            'nombre' => self::PERMISO_NOMBRE,
            'menu_id' => $menuId,
            'updated_at' => $now,
        ]);

        return $permisoId;
    }

    private function asegurarMenuRol(int $menuId, int $rolId): void
    {
        if ($menuId <= 0 || $rolId <= 0) {
            return;
        }
        if (DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            return;
        }
        DB::table('menu_rol')->insert([
            'menu_id' => $menuId,
            'rol_id' => $rolId,
        ]);
    }
};
