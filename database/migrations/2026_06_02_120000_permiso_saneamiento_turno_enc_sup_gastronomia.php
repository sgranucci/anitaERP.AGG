<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/saneamiento-turno';

    private const SLUGS = [
        'gestionar-saneamiento-turno-gastronomia',
        'ejecutar-saneamiento-turno-gastronomia',
    ];

    private const ROLES = [
        'Enc-gastronomía',
        'Sup-Gastronomia',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $rolIds = $this->resolverRolIds();
        if ($rolIds === []) {
            return;
        }

        foreach (self::SLUGS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId === 0) {
                continue;
            }

            foreach ($rolIds as $rid) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rid,
                    ]);
                }
            }
        }

        foreach ($rolIds as $rid) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rid,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $rolIds = $this->resolverRolIds();

        foreach (self::SLUGS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId === 0) {
                continue;
            }
            foreach ($rolIds as $rid) {
                DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->delete();
            }
        }

        if ($menuId > 0) {
            foreach ($rolIds as $rid) {
                DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->delete();
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id === 0 && $nombre === 'Enc-gastronomía') {
                $id = (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->orderBy('id')->value('id') ?? 0);
            }
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
};
