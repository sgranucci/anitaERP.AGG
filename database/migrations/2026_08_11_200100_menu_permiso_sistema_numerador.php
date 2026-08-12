<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'configuracion/sistema-numerador';

    private const MENU_REF_URL = 'configuracion/ubicacion-impresora';

    private const ROL_ADMINISTRADOR = 'administrador';

    /** @var array<int, array{nombre: string, slug: string}> */
    private const PERMISOS = [
        ['nombre' => 'Listar numeradores del sistema', 'slug' => 'listar-sistema-numerador'],
        ['nombre' => 'Crear numeradores del sistema', 'slug' => 'crear-sistema-numerador'],
        ['nombre' => 'Editar numeradores del sistema', 'slug' => 'editar-sistema-numerador'],
        ['nombre' => 'Actualizar numeradores del sistema', 'slug' => 'actualizar-sistema-numerador'],
        ['nombre' => 'Borrar numeradores del sistema', 'slug' => 'borrar-sistema-numerador'],
        ['nombre' => 'Sincronizar numeradores desde Anita', 'slug' => 'sincronizar-sistema-numerador'],
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
                'nombre' => 'Numeradores sistema',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'far fa-circle',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $menuId)->update([
            'menu_id' => $parentMenuId,
            'nombre' => 'Numeradores sistema',
            'updated_at' => now(),
        ]);

        return $menuId;
    }

    private function upsertPermisos(int $menuId, int $refMenuId, int $parentMenuId): void
    {
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
                    'nombre' => $permiso['nombre'],
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
            }

            if ($refMenuId > 0) {
                $rolesRef = DB::table('permiso_rol as pr')
                    ->join('permiso as p', 'p.id', '=', 'pr.permiso_id')
                    ->where('p.menu_id', $refMenuId)
                    ->distinct()
                    ->pluck('pr.rol_id');
                foreach ($rolesRef as $rolId) {
                    $exists = DB::table('permiso_rol')
                        ->where('permiso_id', $permisoId)
                        ->where('rol_id', $rolId)
                        ->exists();
                    if (! $exists) {
                        DB::table('permiso_rol')->insert([
                            'permiso_id' => $permisoId,
                            'rol_id' => $rolId,
                        ]);
                    }
                }
            }
        }
    }

    private function asignarRolAdministrador(int $menuId, int $parentMenuId): void
    {
        $rolId = (int) (DB::table('rol')->where('nombre', self::ROL_ADMINISTRADOR)->value('id') ?? 0);
        if ($rolId <= 0) {
            return;
        }

        foreach ([$menuId, $parentMenuId] as $mid) {
            if ($mid <= 0) {
                continue;
            }
            $exists = DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists();
            if (! $exists) {
                DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rolId]);
            }
        }

        foreach (self::PERMISOS as $permiso) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $permiso['slug'])->value('id') ?? 0);
            if ($permisoId <= 0) {
                continue;
            }
            $exists = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $exists) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
