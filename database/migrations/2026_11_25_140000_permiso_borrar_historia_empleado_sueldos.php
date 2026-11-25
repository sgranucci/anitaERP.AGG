<?php

use App\Support\Cache\PermisoCacheSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permiso para borrar renglones de historia (emping) en el legajo.
 * Se asigna a todos los roles de Capital Humano.
 */
return new class extends Migration
{
    private const SLUG = 'borrar-historia-empleado-sueldos';

    private const NOMBRE = 'Borrar historia ingreso/egreso empleado sueldos';

    /** @var list<string> */
    private const ROLES_CAPITAL_HUMANO = [
        'enc-Capital Humano',
        'op-Capital Humano',
        'ger-capitalhumano',
        'opcont-capitalhumano',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', 'sueldos/empleado')->value('id') ?? 0);
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => self::NOMBRE,
                'slug' => self::SLUG,
                'menu_id' => $menuId > 0 ? $menuId : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $upd = ['nombre' => self::NOMBRE, 'updated_at' => now()];
            if ($menuId > 0) {
                $upd['menu_id'] = $menuId;
            }
            DB::table('permiso')->where('id', $permisoId)->update($upd);
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_CAPITAL_HUMANO)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($rolIds as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
            PermisoCacheSupport::forgetRol($rolId);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_CAPITAL_HUMANO)
            ->pluck('id');
        if ($rolIds->isNotEmpty()) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
            foreach ($rolIds as $rolId) {
                PermisoCacheSupport::forgetRol((int) $rolId);
            }
        }

        DB::table('permiso')->where('id', $permisoId)->delete();
        SuitecrmPermiso::flushCachePermisos();
    }
};
