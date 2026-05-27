<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'sala/prioridad-sala';

    private const PERMISOS = [
        ['slug' => 'listar-prioridad-sala', 'nombre' => 'Listar prioridades de sala'],
        ['slug' => 'crear-prioridad-sala', 'nombre' => 'Crear prioridades de sala'],
        ['slug' => 'editar-prioridad-sala', 'nombre' => 'Editar prioridades de sala'],
        ['slug' => 'actualizar-prioridad-sala', 'nombre' => 'Actualizar prioridades de sala'],
        ['slug' => 'borrar-prioridad-sala', 'nombre' => 'Borrar prioridades de sala'],
    ];

    public function up(): void
    {
        $parentMenuId = $this->resolverModuloSalaId();
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'sala/zona-sala')->value('menu_id') ?? 0);
        }
        if ($parentMenuId === 0) {
            return;
        }

        $refMenuId = (int) (DB::table('menu')->where('url', 'sala/zona-sala')->value('id') ?? 0);
        $refPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-zona-sala')->value('id') ?? 0);

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Prioridades de sala',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-sort-amount-up',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Prioridades de sala',
                'orden' => $orden,
                'icono' => 'fa-sort-amount-up',
                'updated_at' => now(),
            ]);
        }

        $permisoIds = [];
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
            $permisoIds[] = $permisoId;
        }

        $rolIds = [];
        if ($refPermisoId > 0) {
            $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
        }
        if ($refMenuId > 0) {
            $rolIdsMenu = DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all();
            $rolIds = array_values(array_unique(array_merge($rolIds, $rolIdsMenu)));
        }

        if ($rolIds !== []) {
            $rolIds = DB::table('rol')->whereIn('id', $rolIds)->pluck('id')->all();
        }

        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rid,
                ]);
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rid,
                    ]);
                }
            }
        }
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

    private function resolverModuloSalaId(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where(function ($q) {
                $q->where('nombre', 'Módulo de Sala')
                    ->orWhere('nombre', 'like', '%Módulo de Sala%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }
};
