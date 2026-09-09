<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso para que el encargado asigne técnico desde la bandeja claim.
 */
return new class extends Migration
{
    private const SLUG = 'asignar-ticket-bandeja';

    private const MENU_URL = 'ticket/bandeja';

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-mantenimiento',
        'Enc-sistemas',
        'op-Gerencia de Tecnologia',
        'supervisor-ticket', // por si existe como rol; si no, se ignora
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Asignar técnico desde bandeja de tickets',
                'slug' => self::SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Asignar técnico desde bandeja de tickets',
                'updated_at' => now(),
            ]);
        }

        foreach (self::ROLES as $nombre) {
            $rolId = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($rolId <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        // También quien ya tenga encargado-ticket
        $encargadoPermisoId = (int) (DB::table('permiso')->where('slug', 'encargado-ticket')->value('id') ?? 0);
        if ($encargadoPermisoId > 0) {
            $rolIds = DB::table('permiso_rol')
                ->where('permiso_id', $encargadoPermisoId)
                ->pluck('rol_id');
            foreach ($rolIds as $rolId) {
                $rolId = (int) $rolId;
                if ($rolId <= 0) {
                    continue;
                }
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }
};
