<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Liberar una factura retenida para pago es la contrapartida de haber dejado pasar una diferencia
 * contra la COM, así que va con permiso propio: no alcanza con poder contabilizar. Se otorga solo
 * a los roles que ya pueden contabilizar, para no ampliar el alcance por default.
 */
return new class extends Migration
{
    private const SLUG = 'liberar-bloqueo-pago-comprobante-proveedor';

    private const SLUG_REFERENCIA = 'contabilizar-comprobante-proveedor';

    public function up(): void
    {
        $referencia = DB::table('permiso')->where('slug', self::SLUG_REFERENCIA)->first();
        if (! $referencia) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Liberar bloqueo para pago de comprobante de proveedor',
                'slug' => self::SLUG,
                'menu_id' => $referencia->menu_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('permiso_rol')->where('permiso_id', (int) $referencia->id)->pluck('rol_id');
        foreach ($rolIds as $rolId) {
            $existe = DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->where('rol_id', (int) $rolId)
                ->exists();
            if (! $existe) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => (int) $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            return;
        }

        DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
        DB::table('permiso')->where('id', $permisoId)->delete();

        SuitecrmPermiso::flushCachePermisos();
    }
};
