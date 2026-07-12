<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/rendicionbingo';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $slug = 'borrar-rendicion-bingo-caja';
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);

        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Borrar rendición bingo caja',
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Borrar rendición bingo caja',
                'updated_at' => now(),
            ]);
        }

        // Otorgar el permiso a los roles que ya pueden crear rendiciones bingo en caja.
        $crearId = (int) (DB::table('permiso')->where('slug', 'crear-rendicion-bingo-caja')->value('id') ?? 0);
        if ($crearId > 0) {
            foreach (DB::table('permiso_rol')->where('permiso_id', $crearId)->pluck('rol_id') as $rolId) {
                $rid = (int) $rolId;
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', 'borrar-rendicion-bingo-caja')->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
