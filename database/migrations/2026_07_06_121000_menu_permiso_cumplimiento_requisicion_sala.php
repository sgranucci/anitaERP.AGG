<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU = [
        'url' => 'sala/cumplimiento-requisicion-sala',
        'nombre' => 'Cumplimientos requisición sala',
        'icono' => 'fa-clipboard-check',
        'permisos' => [
            ['slug' => 'listar-cumplimiento-requisicion-sala', 'nombre' => 'Listar cumplimientos requisición sala'],
            ['slug' => 'ver-cumplimiento-requisicion-sala', 'nombre' => 'Ver cumplimiento requisición sala'],
            ['slug' => 'imprimir-cumplimiento-requisicion-sala', 'nombre' => 'Imprimir cumplimiento requisición sala'],
            ['slug' => 'actualizar-cumplimiento-requisicion-sala', 'nombre' => 'Actualizar cumplimiento requisición sala'],
            ['slug' => 'revertir-cumplimiento-requisicion-sala', 'nombre' => 'Revertir cumplimiento requisición sala'],
        ],
    ];

    private const ROLES_LABORATORIO = [
        'Enc-Laboratorio',
        'Op-Laboratorio',
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

        $refMenuId = (int) (DB::table('menu')->where('url', 'sala/cumplir-requisicion-sala')->value('id') ?? 0);
        if ($refMenuId === 0) {
            $refMenuId = (int) (DB::table('menu')->where('url', 'sala/requisicion-sala')->value('id') ?? 0);
        }
        $refPermisoId = (int) (DB::table('permiso')->where('slug', 'cumplir-requisicion-sala')->value('id') ?? 0);
        if ($refPermisoId === 0) {
            $refPermisoId = (int) (DB::table('permiso')->where('slug', 'listar-requisicion-sala')->value('id') ?? 0);
        }
        $rolIds = $this->resolverRoles($refMenuId, $refPermisoId);

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;
        $menuId = $this->upsertMenu($parentMenuId, self::MENU, $orden);
        $permisoIds = $this->upsertPermisos($menuId, self::MENU['permisos']);
        $this->asignarRoles($menuId, $permisoIds, $rolIds);
    }

    public function down(): void
    {
        foreach (self::MENU['permisos'] as $permiso) {
            $permisoId = DB::table('permiso')->where('slug', $permiso['slug'])->value('id');
            if ($permisoId) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
                DB::table('permiso')->where('id', $permisoId)->delete();
            }
        }
        $menuId = DB::table('menu')->where('url', self::MENU['url'])->value('id');
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

    /** @return list<int> */
    private function resolverRoles(int $refMenuId, int $refPermisoId): array
    {
        $rolIds = [];
        if ($refPermisoId > 0) {
            $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
        }
        if ($refMenuId > 0) {
            $rolIdsMenu = DB::table('menu_rol')->where('menu_id', $refMenuId)->pluck('rol_id')->unique()->all();
            $rolIds = array_values(array_unique(array_merge($rolIds, $rolIdsMenu)));
        }
        $rolIdsLab = DB::table('rol')->whereIn('nombre', self::ROLES_LABORATORIO)->pluck('id')->all();

        return array_values(array_unique(array_merge($rolIds, $rolIdsLab)));
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

    /** @param list<array{slug: string, nombre: string}> $permisos */
    /** @return list<int> */
    private function upsertPermisos(int $menuId, array $permisos): array
    {
        $ids = [];
        foreach ($permisos as $permiso) {
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
            $ids[] = $permisoId;
        }

        return $ids;
    }

    /** @param list<int> $permisoIds @param list<int> $rolIds */
    private function asignarRoles(int $menuId, array $permisoIds, array $rolIds): void
    {
        if ($rolIds === []) {
            return;
        }
        $rolIds = DB::table('rol')->whereIn('id', $rolIds)->pluck('id')->all();
        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
                }
            }
        }
    }
};
