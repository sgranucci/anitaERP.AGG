<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tilde manual para dejar una factura fuera de propuestas, órdenes de pago y proyección.
 * Lo usan los roles de pagos (y administrador), no quien solo carga o contabiliza.
 */
return new class extends Migration
{
    private const SLUG = 'excluir-circuito-pago-comprobante-proveedor';

    private const SLUG_MENU = 'listar-comprobante-proveedor';

    public function up(): void
    {
        $referencia = DB::table('permiso')->where('slug', self::SLUG_MENU)->first();
        if (! $referencia) {
            return;
        }

        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG)->value('id') ?? 0);
        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Excluir comprobante de proveedor del circuito de pago',
                'slug' => self::SLUG,
                'menu_id' => $referencia->menu_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('rol')
            ->where('nombre', 'like', '%pagos%')
            ->orWhere('nombre', 'administrador')
            ->pluck('id');

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
