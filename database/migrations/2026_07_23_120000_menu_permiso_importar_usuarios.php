<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permiso de carga masiva de usuarios (Administrador).
 * Asigna a administrador + todos los roles del centro de costo Sistemas (código 92).
 */
return new class extends Migration
{
    private const MENU_PADRE_NOMBRE = 'Administrador';

    private const MENU = [
        'url' => 'admin/usuario/importar',
        'nombre' => 'Carga masiva usuarios',
        'icono' => 'fa-file-excel',
    ];

    private const PERMISO = [
        'slug' => 'importar-usuarios',
        'nombre' => 'Importar usuarios masivo',
    ];

    private const CENTRO_COSTO_SISTEMAS = '92';

    private const ROLES_EXTRA = [
        'administrador',
        'Enc-sistemas',
    ];

    public function up(): void
    {
        $parentMenuId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'admin/usuario')->value('menu_id') ?? 0);
        }

        if ($parentMenuId === 0) {
            return;
        }

        $ordenUsuarios = (int) (DB::table('menu')->where('url', 'admin/usuario')->value('orden') ?? 1);
        $orden = $ordenUsuarios + 1;

        // Desplazar ítems siguientes para dejar la carga masiva justo debajo de Usuarios.
        DB::table('menu')
            ->where('menu_id', $parentMenuId)
            ->where('orden', '>=', $orden)
            ->where('url', '<>', self::MENU['url'])
            ->increment('orden');

        $menuId = $this->upsertMenu($parentMenuId, self::MENU, $orden);
        $permisoId = $this->upsertPermiso($menuId, self::PERMISO);
        $rolIds = $this->resolverRolesSistemas();

        $this->asignarRoles($parentMenuId, $menuId, $permisoId, $rolIds);
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO['slug'])->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU['url'])->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolesSistemas(): array
    {
        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES_EXTRA)->pluck('id')->all();

        $centrocostoId = (int) (DB::table('centrocosto')
            ->where('codigo', self::CENTRO_COSTO_SISTEMAS)
            ->value('id') ?? 0);

        if ($centrocostoId > 0) {
            $rolIdsCc = DB::table('rol')->where('centrocosto_id', $centrocostoId)->pluck('id')->all();
            $rolIds = array_merge($rolIds, $rolIdsCc);
        }

        return array_values(array_unique(array_map('intval', $rolIds)));
    }

    /** @param array{url: string, nombre: string, icono: string} $menuDef */
    private function upsertMenu(int $parentMenuId, array $menuDef, int $orden): int
    {
        $menuId = (int) (DB::table('menu')->where('url', $menuDef['url'])->value('id') ?? 0);
        if ($menuId === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => $menuDef['nombre'],
                'url' => $menuDef['url'],
                'orden' => $orden,
                'icono' => $menuDef['icono'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $parentMenuId,
            'nombre' => $menuDef['nombre'],
            'orden' => $orden,
            'icono' => $menuDef['icono'],
            'updated_at' => now(),
        ]);

        return $menuId;
    }

    /** @param array{slug: string, nombre: string} $permiso */
    private function upsertPermiso(int $menuId, array $permiso): int
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
        if ($permisoId === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $permiso['nombre'],
                'slug' => $permiso['slug'],
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $permisoId)->update([
            'menu_id' => $menuId,
            'nombre' => $permiso['nombre'],
            'updated_at' => now(),
        ]);

        return $permisoId;
    }

    /** @param list<int> $rolIds */
    private function asignarRoles(int $parentMenuId, int $menuId, int $permisoId, array $rolIds): void
    {
        if ($rolIds === []) {
            return;
        }

        $rolIds = DB::table('rol')->whereIn('id', $rolIds)->pluck('id')->all();
        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            foreach ([$parentMenuId, $menuId] as $mid) {
                if ($mid > 0 && ! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rid]);
                }
            }
            if ($permisoId > 0
                && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
            }
        }
    }
};
