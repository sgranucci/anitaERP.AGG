<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_CLIENTE_URL = 'ventas/cliente';

    private const PERMISO = 'ver-notas-supervisor-suitecrm-cliente';

    private const ROL_SUPERVISOR_ANITA = 'supervisor';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_CLIENTE_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Ver notas SuiteCRM de supervisores (restringidas)',
                'slug' => self::PERMISO,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'nombre' => 'Ver notas SuiteCRM de supervisores (restringidas)',
                'menu_id' => $menuId,
                'updated_at' => now(),
            ]);
        }

        $rolSupervisorId = (int) (DB::table('rol')->where('nombre', self::ROL_SUPERVISOR_ANITA)->value('id') ?? 0);
        if ($rolSupervisorId > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolSupervisorId)->exists()) {
            DB::table('permiso_rol')->insert([
                'permiso_id' => $permisoId,
                'rol_id' => $rolSupervisorId,
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = DB::table('permiso')->where('slug', self::PERMISO)->value('id');
        if ($permisoId) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
    }
};
