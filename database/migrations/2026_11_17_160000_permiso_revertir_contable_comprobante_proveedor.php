<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reverso contable compensatorio (NC en mes abierto) desde el index de comprobantes.
 * Se otorga a los mismos roles que ya pueden contabilizar.
 */
return new class extends Migration
{
    private const SLUG = 'revertir-contable-comprobante-proveedor';

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
                'nombre' => 'Revertir contable comprobante de proveedor (NC compensatoria)',
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
