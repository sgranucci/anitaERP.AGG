<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'configuracion/modulo-aviso';

    private const PERMISOS = [
        ['slug' => 'listar-modulo-aviso', 'nombre' => 'Listar tipos de aviso por módulo'],
        ['slug' => 'editar-modulo-aviso', 'nombre' => 'Editar configuración de avisos por módulo'],
        ['slug' => 'actualizar-modulo-aviso', 'nombre' => 'Actualizar configuración de avisos por módulo'],
    ];

    public function up(): void
    {
        $parentMenuId = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('menu_id') ?? 0);
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')
                ->where('nombre', 'like', '%Configuraci%')
                ->where('url', '#')
                ->value('id') ?? 0);
        }
        if ($parentMenuId === 0) {
            return;
        }

        $refMenuId = (int) (DB::table('menu')->where('url', 'configuracion/empresa')->value('id') ?? 0);
        $refPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-empresa')->value('id') ?? 0);

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Avisos por módulo',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-envelope-o',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Avisos por módulo',
                'orden' => $orden,
                'icono' => 'fa-envelope-o',
                'updated_at' => now(),
            ]);
        }

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

            if ($refPermisoId > 0) {
                $existeRol = DB::table('permiso_role')
                    ->where('permiso_id', $refPermisoId)
                    ->exists();
                if ($existeRol) {
                    $roles = DB::table('permiso_role')
                        ->where('permiso_id', $refPermisoId)
                        ->pluck('role_id');
                    foreach ($roles as $roleId) {
                        $ya = DB::table('permiso_role')
                            ->where('permiso_id', $permisoId)
                            ->where('role_id', $roleId)
                            ->exists();
                        if (! $ya) {
                            DB::table('permiso_role')->insert([
                                'permiso_id' => $permisoId,
                                'role_id' => $roleId,
                            ]);
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            $permisoIds = DB::table('permiso')->where('menu_id', $menuId)->pluck('id');
            if ($permisoIds->isNotEmpty()) {
                DB::table('permiso_role')->whereIn('permiso_id', $permisoIds)->delete();
                DB::table('permiso')->whereIn('id', $permisoIds)->delete();
            }
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};
