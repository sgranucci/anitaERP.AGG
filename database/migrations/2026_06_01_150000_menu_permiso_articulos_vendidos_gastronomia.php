<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/articulos-vendidos';

    private const ROL_ENC_GASTRONOMIA = 'Enc-gastronomía';

    public function up(): void
    {
        $parentMenuId = $this->resolverMenuGastronomiaId();
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')->where('url', 'ventas/gastronomia/facturas-dia')->value('menu_id') ?? 10);
        }

        $refMenuId = (int) (DB::table('menu')->where('url', 'ventas/gastronomia/facturas-dia')->value('id') ?? $parentMenuId);
        $orden = (int) (DB::table('menu')->where('url', 'ventas/gastronomia/facturas-dia')->value('orden') ?? 0);
        if ($orden <= 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0);
        }
        $orden++;

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Artículos vendidos',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-cubes',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Artículos vendidos',
                'orden' => $orden,
                'icono' => 'fa-cubes',
                'updated_at' => now(),
            ]);
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', 'listar-articulos-vendidos-gastronomia')->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Listar artículos vendidos gastronomía',
                'slug' => 'listar-articulos-vendidos-gastronomia',
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Listar artículos vendidos gastronomía',
                'updated_at' => now(),
            ]);
        }

        $rolId = $this->resolverRolEncGastronomiaId();
        if ($rolId > 0) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
            }
        }

        $refPermisoListarFd = (int) (DB::table('permiso')->where('slug', 'listar-facturas-gastronomia-dia')->value('id') ?? 0);
        if ($refPermisoListarFd > 0) {
            foreach (DB::table('permiso_rol')->where('permiso_id', $refPermisoListarFd)->pluck('rol_id')->unique() as $rid) {
                $rid = (int) $rid;
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
                }
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function resolverMenuGastronomiaId(): int
    {
        return (int) (DB::table('menu')
            ->where(function ($q) {
                $q->where('nombre', 'Gastronomía')->orWhere('nombre', 'like', '%Gastronom%');
            })
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function resolverRolEncGastronomiaId(): int
    {
        $id = (int) (DB::table('rol')->where('nombre', self::ROL_ENC_GASTRONOMIA)->value('id') ?? 0);

        return $id > 0 ? $id : (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->value('id') ?? 0);
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', 'listar-articulos-vendidos-gastronomia')->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
