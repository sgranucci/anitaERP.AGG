<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/facturas-dia';

    private const SLUG = 'cambiar-medio-pago-gastronomia-facturas-dia';

    private const SLUG_REF_NOTA_CREDITO = 'generar-nota-credito-gastronomia-facturas-dia';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Cambiar medio de pago (facturas del día gastronomía)',
                'slug' => self::SLUG,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Cambiar medio de pago (facturas del día gastronomía)',
                'updated_at' => now(),
            ]);
        }

        $refPermisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_REF_NOTA_CREDITO)->value('id') ?? 0);
        if ($refPermisoId > 0) {
            $rolIds = DB::table('permiso_rol')->where('permiso_id', $refPermisoId)->pluck('rol_id')->unique()->all();
        } else {
            $encId = (int) (DB::table('rol')->where('nombre', 'Enc-gastronomía')->value('id') ?? 0);
            if ($encId <= 0) {
                $encId = (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->value('id') ?? 0);
            }
            $supId = (int) (DB::table('rol')->where('nombre', 'Sup-Gastronomia')->value('id') ?? 0);
            $rolIds = array_values(array_filter([$encId, $supId]));
        }

        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if ($rid <= 0) {
                continue;
            }
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rid,
                ]);
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
    }

};
