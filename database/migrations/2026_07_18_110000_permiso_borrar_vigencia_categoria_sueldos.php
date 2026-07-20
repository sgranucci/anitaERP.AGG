<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'sueldos/categoria';

    /** @var list<string> */
    private const ROLES = ['administrador'];

    private const PERMISO_NOMBRE = 'Borrar vigencia de base sueldos';

    private const PERMISO_SLUG = 'borrar-vigencia-categoria-sueldos';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        $payload = ['nombre' => self::PERMISO_NOMBRE, 'slug' => self::PERMISO_SLUG, 'menu_id' => $menuId ?: null, 'updated_at' => now()];
        if ($permisoId > 0) {
            DB::table('permiso')->where('id', $permisoId)->update($payload);
        } else {
            $permisoId = (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
