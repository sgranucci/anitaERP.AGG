<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gastronomía (Sup-Gastronomia) puede modificar precios directamente en la recepción,
 * sin pasar por el circuito de aprobación de compras.
 */
return new class extends Migration
{
    private const PERMISO_SLUG = 'modificar-precio-recepcion-proveedor';

    /** @var list<string> */
    private const ROLES = ['Sup-Gastronomia'];

    public function up(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
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

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_SLUG)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return;
        }

        foreach (self::ROLES as $nombre) {
            $rolId = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($rolId <= 0) {
                continue;
            }
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
