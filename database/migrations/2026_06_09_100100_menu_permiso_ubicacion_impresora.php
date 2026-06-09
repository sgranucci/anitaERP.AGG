<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'configuracion/ubicacion-impresora';

    private const MENU_REF_URL = 'configuracion/salida';

    private const ROL_ADMINISTRADOR = 'administrador';

    /** @var array<int, array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar ubicaciones de impresora', 'slug' => 'listar-ubicacion-impresora'],
        ['nombre' => 'Crear ubicaciones de impresora', 'slug' => 'crear-ubicacion-impresora'],
        ['nombre' => 'Editar ubicaciones de impresora', 'slug' => 'editar-ubicacion-impresora'],
        ['nombre' => 'Actualizar ubicaciones de impresora', 'slug' => 'actualizar-ubicacion-impresora'],
        ['nombre' => 'Borrar ubicaciones de impresora', 'slug' => 'borrar-ubicacion-impresora'],
    ];

    public function up(): void
    {
        $parentMenuId = $this->resolverModuloConfiguracionId();
        if ($parentMenuId === 0) {
            return;
        }

        $refMenuId = (int) (DB::table('menu')->where('url', self::MENU_REF_URL)->value('id') ?? 0);
        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        $menuId = $this->upsertMenu($parentMenuId, $orden);
        $this->upsertPermisos($menuId, $refMenuId, $parentMenuId);
        $this->asignarRolAdministrador($menuId, $parentMenuId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach (self::PERMISOS as $permiso) {
            $permisoId = DB::table('permiso')->where('slug', $permiso['slug'])->value('id');
            if ($permisoId) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }

        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }

    private function resolverModuloConfiguracionId(): int
    {
        $id = (int) (DB::table('menu')->where('url', self::MENU_REF_URL)->value('menu_id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')
            ->where('nombre', 'like', '%Configuraci%')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function upsertMenu(int $parentMenuId, int $orden): int
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            return (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Ubic. impresoras',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-map-marker',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $parentMenuId,
            'nombre' => 'Ubic. impresoras',
            'orden' => $orden,
            'icono' => 'fa-map-marker',
            'updated_at' => now(),
        ]);

        return $menuId;
    }

    private function upsertPermisos(int $menuId, int $refMenuId, int $parentMenuId): void
    {
        $rolIdsMenuRef = $refMenuId > 0
            ? DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all()
            : [];

        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);

            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $permiso['nombre'],
                    'slug' => $permiso['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $permiso['nombre'],
                    'updated_at' => now(),
                ]);
            }

            foreach ($rolIdsMenuRef as $rolId) {
                $this->vincularMenuPermisoRol($menuId, $permisoId, (int) $rolId);
            }
        }

        foreach ($rolIdsMenuRef as $rolId) {
            $rid = (int) $rolId;
            if ($parentMenuId > 0 && ! DB::table('menu_rol')->where('menu_id', $parentMenuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $parentMenuId, 'rol_id' => $rid]);
            }
        }
    }

    private function asignarRolAdministrador(int $menuId, int $parentMenuId): void
    {
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL_ADMINISTRADOR)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        if ($parentMenuId > 0) {
            if (! DB::table('menu_rol')->where('menu_id', $parentMenuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $parentMenuId, 'rol_id' => $rolId]);
            }
        }

        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId > 0) {
                $this->vincularMenuPermisoRol($menuId, $permisoId, $rolId);
            }
        }
    }

    private function vincularMenuPermisoRol(int $menuId, int $permisoId, int $rolId): void
    {
        if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
            DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
        }

        if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolId,
            ]);
        }
    }
};
