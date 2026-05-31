<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/cajaasignacion';

    /** @var list<string> */
    private const PERMISO_SLUGS = [
        'lista-asignacion-caja',
        'crea-asignacion-caja',
        'edita-asignacion-caja',
        'actualiza-asignacion-caja',
        'borra-asignacion-caja',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISO_SLUGS)
            ->pluck('id')
            ->all();

        if ($permisoIds === []) {
            return;
        }

        DB::table('permiso')
            ->whereIn('id', $permisoIds)
            ->update(['menu_id' => $menuId, 'updated_at' => now()]);

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', $menuId)
            ->pluck('rol_id')
            ->unique()
            ->all();

        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            foreach ($permisoIds as $permisoId) {
                $permisoId = (int) $permisoId;
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::PERMISO_SLUGS)
            ->pluck('id')
            ->all();

        if ($permisoIds === []) {
            return;
        }

        DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
        DB::table('permiso')->whereIn('id', $permisoIds)->update(['menu_id' => null, 'updated_at' => now()]);
    }
};
