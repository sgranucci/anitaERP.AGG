<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/arca-caea';

    private const PERMISO_LISTAR = 'listar-arca-caea';

    private const PERMISO_VER = 'ver-arca-caea';

    private const PERMISO_SOLICITAR = 'solicitar-arca-caea';

    private const PERMISO_REF = 'listar-puntos-de-venta';

    public function up(): void
    {
        $parentMenuId = (int) (DB::table('menu')->where('url', 'ventas/puntoventa')->value('menu_id') ?? 0);
        if ($parentMenuId === 0) {
            $parentMenuId = (int) (DB::table('menu')
                ->where(function ($q) {
                    $q->where('nombre', 'Módulo de Ventas')->orWhere('nombre', 'like', '%Módulo de Ventas%');
                })
                ->where('url', '#')
                ->value('id') ?? 51);
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'CAEA (ARCA)',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-calendar-check-o',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'CAEA (ARCA)',
                'orden' => $orden,
                'icono' => 'fa-calendar-check-o',
                'updated_at' => now(),
            ]);
        }

        $permisos = [
            ['nombre' => 'Listar CAEA ARCA', 'slug' => self::PERMISO_LISTAR],
            ['nombre' => 'Ver detalle CAEA ARCA', 'slug' => self::PERMISO_VER],
            ['nombre' => 'Solicitar CAEA ARCA manualmente', 'slug' => self::PERMISO_SOLICITAR],
        ];

        $permisoIds = [];
        foreach ($permisos as $row) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $row['slug'])->value('id') ?? 0);
            if ($permisoId === 0) {
                $permisoId = (int) DB::table('permiso')->insertGetId([
                    'nombre' => $row['nombre'],
                    'slug' => $row['slug'],
                    'menu_id' => $menuId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'nombre' => $row['nombre'],
                    'updated_at' => now(),
                ]);
            }
            $permisoIds[] = $permisoId;
        }

        $refPermisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_REF)->value('id') ?? 0);
        if ($refPermisoId > 0) {
            $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique();
            foreach ($rolIds as $rolId) {
                $rid = (int) $rolId;
                foreach ($permisoIds as $pid) {
                    if (! DB::table('permiso_rol')->where('permiso_id', $pid)->where('rol_id', $rid)->exists()) {
                        DB::table('permiso_rol')->insert(['permiso_id' => $pid, 'rol_id' => $rid]);
                    }
                }
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
                }
            }
        }
    }

    public function down(): void
    {
        $slugs = [self::PERMISO_LISTAR, self::PERMISO_VER, self::PERMISO_SOLICITAR];
        foreach (DB::table('permiso')->whereIn('slug', $slugs)->pluck('id') as $pid) {
            DB::table('permiso_rol')->where('permiso_id', $pid)->delete();
            DB::table('permiso')->where('id', $pid)->delete();
        }
        $menuId = DB::table('menu')->where('url', self::MENU_URL)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};
